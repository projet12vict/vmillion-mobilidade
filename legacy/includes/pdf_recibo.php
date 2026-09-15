<?php
/**
 * V-MILLION — Gerador de recibo em PDF, sem dependências externas (não há
 * Composer/dompdf disponível no ambiente do piloto). Produz um PDF válido
 * de página única usando apenas a fonte base Helvetica (secção 11.6).
 */

declare(strict_types=1);

function kg_pdf_escapar_texto(string $texto): string
{
    // WinAnsiEncoding cobre os acentos usados em português (á, ã, ç, é, í, ó, ú, ê...).
    $latin1 = @iconv('UTF-8', 'CP1252//TRANSLIT', $texto);
    if ($latin1 === false) {
        $latin1 = $texto;
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
}

/**
 * @param array<int, array{texto: string, tamanho?: int, negrito?: bool}> $linhas
 */
function kg_gerar_pdf_recibo(array $linhas): string
{
    $largura = 595; // A4 em pontos
    $altura = 842;
    $margemEsquerda = 56;
    $y = $altura - 80;

    $conteudo = "BT\n";
    foreach ($linhas as $linha) {
        $tamanho = $linha['tamanho'] ?? 11;
        $fonte = !empty($linha['negrito']) ? '/F2' : '/F1';
        $texto = kg_pdf_escapar_texto($linha['texto']);
        $conteudo .= sprintf("%s %d Tf\n1 0 0 1 %d %d Tm\n(%s) Tj\n", $fonte, $tamanho, $margemEsquerda, $y, $texto);
        $y -= (int) round($tamanho * 1.6);
    }
    $conteudo .= "ET\n";

    $objetos = [];
    $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objetos[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objetos[3] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> "
        . "/MediaBox [0 0 {$largura} {$altura}] /Contents 6 0 R >>";
    $objetos[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objetos[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $objetos[6] = "<< /Length " . strlen($conteudo) . " >>\nstream\n{$conteudo}endstream";

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

/**
 * Monta as linhas do recibo de pagamento de um condutor (secção 11.6).
 * @param array{referencia:string, condutor_nome:string, matricula:string, rota_texto:string,
 *              valor_pago:float, data_pagamento:string, data_validade:string, aprovado_por:string,
 *              pacote_nome?:?string, duracao_dias?:int, tipo_servico?:?string} $dados
 */
function kg_montar_linhas_recibo_condutor(array $dados): array
{
    $duracaoDias = $dados['duracao_dias'] ?? 30;
    $pacoteLabel = !empty($dados['pacote_nome']) ? ucfirst($dados['pacote_nome']) : 'Taxa de operação';
    $tipoServicoLabel = ['urbano' => 'Urbano', 'intermunicipal' => 'Intermunicipal', 'ambos' => 'Urbano + Intermunicipal'][$dados['tipo_servico'] ?? ''] ?? null;

    $linhas = [
        ['texto' => 'V-MILLION', 'tamanho' => 22, 'negrito' => true],
        ['texto' => 'Recibo de pagamento — ' . $pacoteLabel, 'tamanho' => 12],
        ['texto' => 'Santiago, Cabo Verde', 'tamanho' => 10],
        ['texto' => ' ', 'tamanho' => 10],
        ['texto' => 'Referência: ' . $dados['referencia'], 'tamanho' => 11, 'negrito' => true],
        ['texto' => 'Condutor: ' . $dados['condutor_nome'], 'tamanho' => 11],
        ['texto' => 'Veículo: ' . $dados['matricula'], 'tamanho' => 11],
        ['texto' => 'Rota: ' . $dados['rota_texto'], 'tamanho' => 11],
    ];
    if ($tipoServicoLabel !== null) {
        $linhas[] = ['texto' => 'Tipo de serviço: ' . $tipoServicoLabel, 'tamanho' => 11];
    }
    $linhas = array_merge($linhas, [
        ['texto' => ' ', 'tamanho' => 10],
        ['texto' => 'Valor pago: ' . number_format($dados['valor_pago'], 2, ',', '.') . ' CVE', 'tamanho' => 13, 'negrito' => true],
        ['texto' => 'Data de pagamento: ' . $dados['data_pagamento'], 'tamanho' => 11],
        ['texto' => 'Válido até: ' . $dados['data_validade'] . " ({$duracaoDias} dias)", 'tamanho' => 11],
        ['texto' => 'Aprovado por: ' . $dados['aprovado_por'], 'tamanho' => 11],
        ['texto' => ' ', 'tamanho' => 10],
        ['texto' => 'Este recibo é gerado automaticamente pelo sistema V-MILLION e não requer assinatura manual.', 'tamanho' => 9],
    ]);

    return $linhas;
}
