<?php

declare(strict_types=1);

namespace App\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Criptografia do Web Push, feita com o openssl do PHP e sem nenhuma
 * dependência externa.
 *
 * São duas coisas independentes, ambas obrigatórias para um push ser aceite:
 *
 * 1. Cifra do conteúdo (RFC 8291, content-encoding 'aes128gcm'): a mensagem
 *    é cifrada para as chaves que o browser gerou na subscrição. O serviço
 *    de push do fabricante (FCM, Mozilla, WNS) encaminha o pacote sem o
 *    conseguir ler — quem chama quem nunca sai daqui em claro.
 * 2. Assinatura VAPID (RFC 8292): um JWT ES256 assinado com a chave privada
 *    do servidor, que prova ao serviço de push que o pedido vem de quem é
 *    dono da chave pública com que o browser subscreveu.
 *
 * Nota sobre não usar biblioteca: minishlink/web-push resolveria isto, mas
 * arrastava web-token/jwt-* e guzzle-http/psr7 para um projeto que hoje só
 * depende do framework. O que é preciso são três primitivas que o PHP já
 * traz nativas — ECDH (openssl_pkey_derive), HKDF (hash_hkdf) e AES-GCM
 * (openssl_encrypt) — por isso está tudo aqui, com os RFCs referidos passo
 * a passo.
 */
final class CifraWebPush
{
    /** OID prime256v1 (1.2.840.10045.3.1.7) — a única curva do Web Push. */
    private const OID_P256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

    public static function base64urlCodificar(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    public static function base64urlDescodificar(string $texto): string
    {
        $normalizado = strtr($texto, '-_', '+/');
        $descodificado = base64_decode(str_pad($normalizado, (int) (ceil(strlen($normalizado) / 4) * 4), '='), true);

        if ($descodificado === false) {
            throw new RuntimeException('Valor base64url inválido.');
        }

        return $descodificado;
    }

    /**
     * Par de chaves VAPID do servidor, para guardar no .env (ver o comando
     * `php artisan push:chaves`). Gera-se uma vez: mudar a chave pública
     * invalida todas as subscrições já feitas pelos browsers.
     *
     * @return array{publica: string, privada: string}
     */
    public static function gerarChavesVapid(): array
    {
        $chave = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($chave === false) {
            throw new RuntimeException('Não foi possível gerar o par de chaves VAPID.');
        }

        $ec = openssl_pkey_get_details($chave)['ec'];

        return [
            'publica' => self::base64urlCodificar(self::ponto($ec['x'], $ec['y'])),
            'privada' => self::base64urlCodificar(self::alinhar32($ec['d'])),
        ];
    }

    /**
     * JWT do cabeçalho Authorization: vapid t=<jwt>, k=<chave pública>.
     *
     * 'aud' é a origem do endpoint (só o esquema e o host, sem caminho) — um
     * JWT emitido para o FCM não é aceite pelo serviço da Mozilla, e é isso
     * que impede que um endpoint capturado seja reutilizado noutro serviço.
     */
    public static function jwtVapid(string $endpoint, string $assunto, string $chavePrivadaB64, string $chavePublicaB64): string
    {
        $partes = parse_url($endpoint);

        if (! isset($partes['scheme'], $partes['host'])) {
            throw new RuntimeException('Endpoint de push inválido.');
        }

        $cabecalho = self::base64urlCodificar('{"typ":"JWT","alg":"ES256"}');
        $corpo = self::base64urlCodificar((string) json_encode([
            'aud' => $partes['scheme'].'://'.$partes['host'],
            // 12h: o teto do RFC 8292 é 24h. Como o JWT é gerado a cada envio,
            // a validade longa só serve de folga para relógios desacertados.
            'exp' => time() + 43200,
            'sub' => $assunto,
        ], JSON_UNESCAPED_SLASHES));

        $pem = self::pemPrivada(
            self::base64urlDescodificar($chavePrivadaB64),
            self::base64urlDescodificar($chavePublicaB64)
        );

        if (! openssl_sign($cabecalho.'.'.$corpo, $assinaturaDer, $pem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar o token VAPID.');
        }

        return $cabecalho.'.'.$corpo.'.'.self::base64urlCodificar(self::assinaturaCrua($assinaturaDer));
    }

    /**
     * Corpo do POST ao serviço de push, no formato aes128gcm (RFC 8188 §2.1):
     *
     *   salt (16) | rs (4) | idlen (1) | chave efémera do servidor (65) | cifra
     *
     * $p256dhB64 e $authB64 são as duas chaves que o browser entregou na
     * subscrição (PushSubscription.getKey('p256dh'|'auth')).
     */
    public static function cifrar(string $conteudo, string $p256dhB64, string $authB64): string
    {
        $efemera = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($efemera === false) {
            throw new RuntimeException('Não foi possível gerar a chave efémera do push.');
        }

        // Sal e chave efémera novos em cada envio: repeti-los repetiria o
        // nonce do AES-GCM, que é a forma clássica de deitar fora a segurança
        // da cifra.
        return self::cifrarCom($conteudo, $p256dhB64, $authB64, $efemera, random_bytes(16));
    }

    /**
     * O mesmo, com a chave efémera e o sal vindos de fora.
     *
     * Existe para o teste poder correr o vetor de exemplo do RFC 8291 §5 e
     * comparar o resultado byte a byte com o do RFC. Com valores aleatórios
     * essa comparação era impossível, e decifrar com o nosso próprio código
     * não prova nada quanto às constantes de derivação: um erro de uma letra
     * em "Content-Encoding: aes128gcm" ficava consistente dos dois lados e só
     * aparecia num telemóvel real, como uma notificação que nunca chega.
     */
    public static function cifrarCom(
        string $conteudo,
        string $p256dhB64,
        string $authB64,
        OpenSSLAsymmetricKey $efemera,
        string $salt,
    ): string {
        $chaveCliente = self::base64urlDescodificar($p256dhB64);
        $segredoAuth = self::base64urlDescodificar($authB64);

        if (strlen($chaveCliente) !== 65 || $chaveCliente[0] !== "\x04") {
            throw new RuntimeException('Chave p256dh da subscrição inválida.');
        }

        // O registo tem de caber num bloco de 'rs' bytes: o conteúdo, o byte
        // 0x02 que marca o fim do registo e os 16 do selo do AES-GCM.
        if (strlen($conteudo) > 4096 - 17) {
            throw new RuntimeException('Conteúdo do push demasiado grande.');
        }

        $ec = openssl_pkey_get_details($efemera)['ec'];
        $chaveServidor = self::ponto($ec['x'], $ec['y']);

        // Sem o terceiro argumento: o ECDH em P-256 dá sempre 32 bytes e pedir
        // um comprimento é deprecado desde o PHP 8.5.
        $segredoEcdh = openssl_pkey_derive(self::pemPublica($chaveCliente), $efemera);

        if ($segredoEcdh === false) {
            throw new RuntimeException('Falhou o ECDH com a chave da subscrição.');
        }

        // RFC 8291 §3.4 — o segredo 'auth' entra como sal e as duas chaves
        // públicas entram no info, o que prende a mensagem a este par de
        // interlocutores: a mesma cifra não se repete noutra subscrição.
        $ikm = hash_hkdf(
            'sha256',
            $segredoEcdh,
            32,
            "WebPush: info\x00".$chaveCliente.$chaveServidor,
            $segredoAuth
        );

        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // 0x02: delimitador de último registo (RFC 8188 §2). Sem ele o
        // browser rejeita a mensagem inteira.
        $cifra = openssl_encrypt($conteudo."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $selo, '', 16);

        if ($cifra === false) {
            throw new RuntimeException('Falhou a cifra do conteúdo do push.');
        }

        return $salt.pack('N', 4096).chr(65).$chaveServidor.$cifra.$selo;
    }

    /** Ponto não comprimido: 0x04 | X (32) | Y (32). */
    private static function ponto(string $x, string $y): string
    {
        return "\x04".self::alinhar32($x).self::alinhar32($y);
    }

    /**
     * O openssl devolve os inteiros sem zeros à esquerda; num ponto da curva
     * cada coordenada tem sempre 32 bytes, por isso é aqui que voltam.
     */
    private static function alinhar32(string $inteiro): string
    {
        return str_pad(ltrim($inteiro, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    /** SubjectPublicKeyInfo (DER) a partir do ponto cru, para o openssl o ler. */
    private static function pemPublica(string $ponto65): string
    {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01".self::OID_P256
            ."\x03\x42\x00".$ponto65;

        return self::pem('PUBLIC KEY', $der);
    }

    /** ECPrivateKey (SEC1, DER) a partir da chave privada e do ponto crus. */
    private static function pemPrivada(string $privada32, string $ponto65): string
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20".self::alinhar32($privada32)
            ."\xa0\x0a".self::OID_P256
            ."\xa1\x44\x03\x42\x00".$ponto65;

        return self::pem('EC PRIVATE KEY', $der);
    }

    private static function pem(string $rotulo, string $der): string
    {
        return "-----BEGIN {$rotulo}-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END {$rotulo}-----\n";
    }

    /**
     * O openssl assina em DER (SEQUENCE de dois INTEGER); o JWS ES256 quer os
     * dois inteiros crus, com 32 bytes cada.
     */
    private static function assinaturaCrua(string $der): string
    {
        $posicao = 2;
        $inteiros = [];

        for ($i = 0; $i < 2; $i++) {
            if (($der[$posicao] ?? '') !== "\x02") {
                throw new RuntimeException('Assinatura ECDSA em formato inesperado.');
            }

            $tamanho = ord($der[$posicao + 1]);
            $inteiros[] = self::alinhar32(substr($der, $posicao + 2, $tamanho));
            $posicao += 2 + $tamanho;
        }

        return $inteiros[0].$inteiros[1];
    }
}
