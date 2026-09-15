<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gerador de recibo em PDF sem dependências externas (secção 11.6).
 *
 * Produz um PDF válido de página única usando apenas a fonte base Helvetica.
 * Porta de includes/pdf_recibo.php: o Laravel traz Composer, mas manter o
 * gerador próprio evita adicionar uma dependência pesada (dompdf) só para
 * emitir um recibo de texto de meia dúzia de linhas.
 */
class ReciboPdfService
{
    private const LARGURA_A4 = 595;

    private const ALTURA_A4 = 842;

    private const MARGEM_ESQUERDA = 56;

    /**
     * @param  array<int, array{texto: string, tamanho?: int, negrito?: bool}>  $linhas
     */
    public function gerar(array $linhas): string
    {
        $y = self::ALTURA_A4 - 80;

        $conteudo = "BT\n";
        foreach ($linhas as $linha) {
            $tamanho = $linha['tamanho'] ?? 11;
            $fonte = ! empty($linha['negrito']) ? '/F2' : '/F1';
            $texto = $this->escaparTexto($linha['texto']);

            $conteudo .= sprintf(
                "%s %d Tf\n1 0 0 1 %d %d Tm\n(%s) Tj\n",
                $fonte,
                $tamanho,
                self::MARGEM_ESQUERDA,
                $y,
                $texto
            );

            $y -= (int) round($tamanho * 1.6);
        }
        $conteudo .= "ET\n";

        return $this->montarDocumento($conteudo);
    }

    /**
     * Linhas do recibo de pagamento de um condutor (secção 11.6).
     *
     * @param  array{referencia: string, condutor_nome: string, matricula: string, rota_texto: string,
     *               valor_pago: float, data_pagamento: string, data_validade: string, aprovado_por: string,
     *               pacote_nome?: ?string, duracao_dias?: int, tipo_servico?: ?string}  $dados
     * @return array<int, array{texto: string, tamanho?: int, negrito?: bool}>
     */
    public function linhasReciboCondutor(array $dados): array
    {
        $duracaoDias = $dados['duracao_dias'] ?? 30;
        $pacoteLabel = ! empty($dados['pacote_nome']) ? ucfirst($dados['pacote_nome']) : 'Taxa de operação';

        $tipoServicoLabel = [
            'urbano' => 'Urbano',
            'intermunicipal' => 'Intermunicipal',
            'ambos' => 'Urbano + Intermunicipal',
        ][$dados['tipo_servico'] ?? ''] ?? null;

        $linhas = [
            ['texto' => 'V-MILLION', 'tamanho' => 22, 'negrito' => true],
            ['texto' => 'Recibo de pagamento — '.$pacoteLabel, 'tamanho' => 12],
            ['texto' => 'Santiago, Cabo Verde', 'tamanho' => 10],
            ['texto' => ' ', 'tamanho' => 10],
            ['texto' => 'Referência: '.$dados['referencia'], 'tamanho' => 11, 'negrito' => true],
            ['texto' => 'Condutor: '.$dados['condutor_nome'], 'tamanho' => 11],
            ['texto' => 'Veículo: '.$dados['matricula'], 'tamanho' => 11],
            ['texto' => 'Rota: '.$dados['rota_texto'], 'tamanho' => 11],
        ];

        if ($tipoServicoLabel !== null) {
            $linhas[] = ['texto' => 'Tipo de serviço: '.$tipoServicoLabel, 'tamanho' => 11];
        }

        return array_merge($linhas, [
            ['texto' => ' ', 'tamanho' => 10],
            ['texto' => 'Valor pago: '.number_format($dados['valor_pago'], 2, ',', '.').' CVE', 'tamanho' => 13, 'negrito' => true],
            ['texto' => 'Data de pagamento: '.$dados['data_pagamento'], 'tamanho' => 11],
            ['texto' => 'Válido até: '.$dados['data_validade']." ({$duracaoDias} dias)", 'tamanho' => 11],
            ['texto' => 'Aprovado por: '.$dados['aprovado_por'], 'tamanho' => 11],
            ['texto' => ' ', 'tamanho' => 10],
            ['texto' => 'Este recibo é gerado automaticamente pelo sistema V-MILLION e não requer assinatura manual.', 'tamanho' => 9],
        ]);
    }

    /**
     * WinAnsiEncoding cobre os acentos usados em português (á, ã, ç, é, í...).
     */
    private function escaparTexto(string $texto): string
    {
        $latin1 = @iconv('UTF-8', 'CP1252//TRANSLIT', $texto);
        if ($latin1 === false) {
            $latin1 = $texto;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
    }

    private function montarDocumento(string $conteudo): string
    {
        $largura = self::LARGURA_A4;
        $altura = self::ALTURA_A4;

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> '
                ."/MediaBox [0 0 {$largura} {$altura}] /Contents 6 0 R >>",
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => '<< /Length '.strlen($conteudo)." >>\nstream\n{$conteudo}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $num => $corpo) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$corpo}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $total = count($objetos) + 1;

        $pdf .= "xref\n0 {$total}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
