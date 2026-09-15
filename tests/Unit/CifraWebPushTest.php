<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CifraWebPush;
use PHPUnit\Framework\TestCase;

/**
 * A cifra do Web Push só se prova de uma maneira: refazendo do outro lado o
 * que o browser faz. Estes testes fazem o papel do browser — geram um par de
 * chaves de subscrição, mandam cifrar e decifram — porque um erro na
 * derivação das chaves não dá erro nenhum no servidor: o pacote sai, o
 * serviço de push aceita-o e é o telemóvel que o deita fora em silêncio.
 */
class CifraWebPushTest extends TestCase
{
    public function test_o_conteudo_cifrado_e_decifravel_pelas_chaves_da_subscricao(): void
    {
        [$privadaCliente, $pontoCliente] = $this->parDeChaves();
        $auth = random_bytes(16);

        $mensagem = (string) json_encode(['tipo' => 'chamada', 'de' => 'João Tavares']);

        $corpo = CifraWebPush::cifrar(
            $mensagem,
            CifraWebPush::base64urlCodificar($pontoCliente),
            CifraWebPush::base64urlCodificar($auth),
        );

        // Cabeçalho do formato aes128gcm (RFC 8188 §2.1).
        $salt = substr($corpo, 0, 16);
        $rs = unpack('N', substr($corpo, 16, 4))[1];
        $tamanhoChave = ord($corpo[20]);
        $pontoServidor = substr($corpo, 21, $tamanhoChave);
        $cifra = substr($corpo, 21 + $tamanhoChave);

        $this->assertSame(4096, $rs);
        $this->assertSame(65, $tamanhoChave);
        $this->assertSame("\x04", $pontoServidor[0]);

        // Derivação do lado do browser (RFC 8291 §3.4).
        $segredo = openssl_pkey_derive($this->pemPublica($pontoServidor), $privadaCliente);
        $ikm = hash_hkdf('sha256', $segredo, 32, "WebPush: info\x00".$pontoCliente.$pontoServidor, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $claro = openssl_decrypt(
            substr($cifra, 0, -16),
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            substr($cifra, -16),
        );

        // 0x02 é o delimitador de último registo, que o browser retira.
        $this->assertSame($mensagem."\x02", $claro);
    }

    public function test_cada_envio_usa_uma_chave_efemera_e_um_sal_novos(): void
    {
        [, $pontoCliente] = $this->parDeChaves();
        $p256dh = CifraWebPush::base64urlCodificar($pontoCliente);
        $auth = CifraWebPush::base64urlCodificar(random_bytes(16));

        $primeiro = CifraWebPush::cifrar('igual', $p256dh, $auth);
        $segundo = CifraWebPush::cifrar('igual', $p256dh, $auth);

        // Reutilizar sal e chave efémera reutilizaria o nonce do AES-GCM, que
        // é a forma clássica de deitar fora a segurança da cifra.
        $this->assertNotSame(substr($primeiro, 0, 16), substr($segundo, 0, 16));
        $this->assertNotSame(substr($primeiro, 21, 65), substr($segundo, 21, 65));
    }

    public function test_o_jwt_vapid_e_verificavel_com_a_chave_publica_anunciada(): void
    {
        $chaves = CifraWebPush::gerarChavesVapid();

        $jwt = CifraWebPush::jwtVapid(
            'https://fcm.googleapis.com/fcm/send/abc123',
            'mailto:suporte@vmillion.cv',
            $chaves['privada'],
            $chaves['publica'],
        );

        [$cabecalho, $corpo, $assinatura] = explode('.', $jwt);

        $this->assertSame(
            ['typ' => 'JWT', 'alg' => 'ES256'],
            json_decode(CifraWebPush::base64urlDescodificar($cabecalho), true),
        );

        $reclamacoes = json_decode(CifraWebPush::base64urlDescodificar($corpo), true);

        // 'aud' é só a origem: um token emitido para o FCM não serve noutro
        // serviço de push.
        $this->assertSame('https://fcm.googleapis.com', $reclamacoes['aud']);
        $this->assertSame('mailto:suporte@vmillion.cv', $reclamacoes['sub']);
        $this->assertGreaterThan(time(), $reclamacoes['exp']);
        $this->assertLessThanOrEqual(time() + 86400, $reclamacoes['exp']);

        $crua = CifraWebPush::base64urlDescodificar($assinatura);
        $this->assertSame(64, strlen($crua), 'ES256 assina com r|s crus de 32 bytes cada, não em DER.');

        $this->assertSame(1, openssl_verify(
            $cabecalho.'.'.$corpo,
            $this->assinaturaEmDer($crua),
            $this->pemPublica(CifraWebPush::base64urlDescodificar($chaves['publica'])),
            OPENSSL_ALGO_SHA256,
        ));
    }

    /**
     * Vetor de exemplo do RFC 8291 §5. É o único teste que prova
     * interoperabilidade a sério: com o sal e a chave efémera do RFC, o corpo
     * cifrado tem de dar exatamente o mesmo que lá está impresso.
     *
     * Sem ele, um erro numa das cadeias de derivação ("Content-Encoding:
     * aes128gcm", "WebPush: info") passava despercebido — ficava consistente
     * com o teste da ida e volta acima e só aparecia num telemóvel real, sob
     * a forma de uma chamada que nunca notifica.
     */
    public function test_reproduz_o_vetor_de_exemplo_do_rfc_8291(): void
    {
        $texto = 'When I grow up, I want to be a watermelon';
        $uaPublica = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $auth = 'BTBZMqHH6r4Tts7J_aSIgg';
        $asPublica = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
        $asPrivada = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
        $salt = 'DGv6ra1nlYgDCS1FRnbzlw';
        $esperado = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vC'
            .'YLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXL'
            .'WyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

        $corpo = CifraWebPush::cifrarCom(
            $texto,
            $uaPublica,
            $auth,
            $this->chaveEfemeraDoRfc($asPrivada, $asPublica),
            CifraWebPush::base64urlDescodificar($salt),
        );

        $this->assertSame($esperado, CifraWebPush::base64urlCodificar($corpo));
    }

    /**
     * Par de chaves como o que o browser gera ao subscrever.
     *
     * @return array{0: \OpenSSLAsymmetricKey, 1: string}
     */
    private function parDeChaves(): array
    {
        $chave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($chave)['ec'];

        $alinhar = fn (string $n): string => str_pad(ltrim($n, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return [$chave, "\x04".$alinhar($ec['x']).$alinhar($ec['y'])];
    }

    /** A chave efémera do exemplo do RFC, montada a partir dos dois valores impressos lá. */
    private function chaveEfemeraDoRfc(string $privadaB64, string $publicaB64): \OpenSSLAsymmetricKey
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20".CifraWebPush::base64urlDescodificar($privadaB64)
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".CifraWebPush::base64urlDescodificar($publicaB64);

        return openssl_pkey_get_private(
            "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n"
        );
    }

    private function pemPublica(string $ponto): string
    {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
            ."\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".$ponto;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    /** O openssl_verify quer a assinatura em DER; o JWS traz r|s crus. */
    private function assinaturaEmDer(string $crua): string
    {
        $inteiros = '';

        foreach ([substr($crua, 0, 32), substr($crua, 32)] as $valor) {
            $valor = ltrim($valor, "\x00");
            if (ord($valor[0]) > 0x7F) {
                $valor = "\x00".$valor;
            }
            $inteiros .= "\x02".chr(strlen($valor)).$valor;
        }

        return "\x30".chr(strlen($inteiros)).$inteiros;
    }
}
