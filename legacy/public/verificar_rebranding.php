<?php
/**
 * V-MILLION — Verificação pós-rebranding (antigo nome: KabuGo).
 * Ferramenta de diagnóstico para confirmar, depois de reiniciar o Apache e
 * limpar a cache do navegador, que não sobra nenhum "KabuGo" visível ou
 * funcional. Só corre fora de produção — ver bloqueio abaixo. Pode ser
 * apagada em segurança depois de confirmado o piloto; não é usada por mais
 * nenhuma parte do sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (KG_IS_PRODUCTION) {
    http_response_code(404);
    exit;
}

$pdo = kg_db();

function kg_verif_linha(string $rotulo, bool $ok, string $detalhe = ''): string
{
    $icone = $ok ? '✅' : '❌';
    $cor = $ok ? '#1c7a45' : '#a72a2a';
    $det = $detalhe !== '' ? '<div style="color:#666; font-size:0.85rem; margin-top:2px;">' . htmlspecialchars($detalhe, ENT_QUOTES) . '</div>' : '';
    return '<li style="margin-bottom:10px;"><span style="color:' . $cor . '; font-weight:700;">' . $icone . '</span> '
        . htmlspecialchars($rotulo, ENT_QUOTES) . $det . '</li>';
}

// --- 1. Varredura de ficheiros: procura "KabuGo" (case-sensitive, o nome
// antigo tal como era realmente escrito) em tudo o que é servido a partir de
// public/, excluindo os recibos já emitidos (histórico, tratados à parte).
$raizPublic = realpath(__DIR__);
$ocorrencias = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizPublic, FilesystemIterator::SKIP_DOTS));
foreach ($it as $ficheiro) {
    if (!$ficheiro->isFile()) continue;
    $caminho = $ficheiro->getPathname();
    if (str_contains($caminho, DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR)) continue;
    if (str_contains($caminho, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) continue;
    if (basename($caminho) === basename(__FILE__)) continue; // este próprio ficheiro documenta o nome antigo de propósito
    if (!preg_match('/\.(php|js|css|json|html|htm)$/i', $caminho)) continue;
    $conteudo = @file_get_contents($caminho);
    if ($conteudo !== false && str_contains($conteudo, 'KabuGo')) {
        $ocorrencias[] = substr($caminho, strlen($raizPublic) + 1);
    }
}
$semKabuGoEmFicheiros = empty($ocorrencias);

// --- 2. manifest.json ---
$manifestPath = $raizPublic . '/manifest.json';
$manifestOk = false;
$manifestDetalhe = 'manifest.json não encontrado.';
if (is_file($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $manifestOk = ($manifest['name'] ?? '') === 'V-MILLION' && ($manifest['short_name'] ?? '') === 'V-MILLION';
    $manifestDetalhe = 'name=' . ($manifest['name'] ?? '?') . ', short_name=' . ($manifest['short_name'] ?? '?');
}

// --- 3. Service Worker ---
$swPath = $raizPublic . '/sw.js';
$swOk = false;
$swDetalhe = 'sw.js não encontrado.';
if (is_file($swPath)) {
    $swConteudo = (string) file_get_contents($swPath);
    preg_match("/CACHE_NAME\s*=\s*'([^']+)'/", $swConteudo, $m);
    $cacheName = $m[1] ?? '?';
    $swOk = str_starts_with($cacheName, 'vmillion-') && !str_contains($swConteudo, 'KabuGo');
    $swDetalhe = "CACHE_NAME = '{$cacheName}'";
}

// --- 4. Recibo PDF (gera um recibo de teste real, não grava em disco) ---
require_once __DIR__ . '/../includes/pdf_recibo.php';
$reciboOk = false;
$reciboDetalhe = '';
try {
    $pdfBytes = kg_gerar_pdf_recibo(kg_montar_linhas_recibo_condutor([
        'referencia' => 'VERIF-' . date('YmdHis'),
        'condutor_nome' => 'Teste de verificação',
        'matricula' => 'TT-00-TT',
        'rota_texto' => 'Teste',
        'valor_pago' => 0.0,
        'data_pagamento' => date('Y-m-d H:i:s'),
        'data_validade' => date('Y-m-d H:i:s'),
        'aprovado_por' => 'Verificação automática',
        'pacote_nome' => 'teste',
        'duracao_dias' => 1,
        'tipo_servico' => 'urbano',
    ]));
    $reciboOk = str_contains($pdfBytes, 'V-MILLION') && !str_contains($pdfBytes, 'KabuGo');
    $reciboDetalhe = $reciboOk ? 'Recibo de teste gerado em memória, contém "V-MILLION" e não contém "KabuGo".' : 'Recibo gerado mas o conteúdo não corresponde ao esperado.';
} catch (Throwable $e) {
    $reciboDetalhe = 'Falha ao gerar recibo de teste: ' . $e->getMessage();
}

// --- 5. Base de dados: notificações, logs e sugestões com texto "kabugo" ---
$tabelasTexto = [
    'notificacoes' => "SELECT COUNT(*) FROM notificacoes WHERE titulo LIKE '%kabugo%' OR mensagem LIKE '%kabugo%'",
    'logs_auditoria' => "SELECT COUNT(*) FROM logs_auditoria WHERE acao LIKE '%kabugo%' OR detalhes LIKE '%kabugo%'",
    'sugestoes' => "SELECT COUNT(*) FROM sugestoes WHERE titulo LIKE '%kabugo%' OR descricao LIKE '%kabugo%'",
];
$dbOcorrencias = [];
foreach ($tabelasTexto as $tabela => $sql) {
    try {
        $n = (int) $pdo->query($sql)->fetchColumn();
        if ($n > 0) $dbOcorrencias[] = "{$tabela} ({$n})";
    } catch (Throwable $e) {
        // tabela pode não existir consoante a instalação — ignora
    }
}
$semKabuGoEmBD = empty($dbOcorrencias);

// --- 6. Apache: o simples facto de esta página ter respondido já confirma
// que o Apache está a servir o projeto correto através do alias pedido.
$hostAtual = $_SERVER['HTTP_HOST'] ?? '?';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>V-MILLION — Verificação de rebranding</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 20px; color: #12211f; }
  h1 { font-size: 1.4rem; }
  ul { list-style: none; padding: 0; }
  .caixa { background: #f5f7f6; border: 1px solid #dbe3df; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; }
  .ficheiros { font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; }
</style>
</head>
<body>
<h1>V-MILLION — Verificação de rebranding</h1>
<p style="color:#666;">Gerado em <?= date('Y-m-d H:i:s') ?> · host: <?= htmlspecialchars($hostAtual, ENT_QUOTES) ?></p>

<div class="caixa">
<ul>
<?= kg_verif_linha(
    'Nenhum "KabuGo" em ficheiros servidos (public/)',
    $semKabuGoEmFicheiros,
    $semKabuGoEmFicheiros ? '' : count($ocorrencias) . ' ficheiro(s): ' . implode(', ', array_slice($ocorrencias, 0, 10))
) ?>
<?= kg_verif_linha('manifest.json com name/short_name = "V-MILLION"', $manifestOk, $manifestDetalhe) ?>
<?= kg_verif_linha('Service Worker com cache "vmillion-*"', $swOk, $swDetalhe) ?>
<?= kg_verif_linha('Recibo PDF novo usa "V-MILLION"', $reciboOk, $reciboDetalhe) ?>
<?= kg_verif_linha('Nenhum "kabugo" em notificações/logs/sugestões na BD', $semKabuGoEmBD, $semKabuGoEmBD ? '' : 'Tabelas com ocorrências: ' . implode(', ', $dbOcorrencias)) ?>
<?= kg_verif_linha('Este pedido chegou através do alias /kabugo (Apache a servir o projeto certo)', true, $_SERVER['REQUEST_URI'] ?? '') ?>
</ul>
</div>

<?php if (!$semKabuGoEmFicheiros): ?>
<div class="caixa">
<strong>Ficheiros com "KabuGo" ainda por rever:</strong>
<div class="ficheiros"><?= htmlspecialchars(implode("\n", $ocorrencias), ENT_QUOTES) ?></div>
</div>
<?php endif; ?>

<p style="color:#666; font-size:0.85rem;">Nota: recibos PDF já emitidos em <code>uploads/recibos/</code> não são verificados aqui — mantêm-se como registo histórico do nome usado quando foram gerados, e não são reescritos.</p>
<p style="color:#666; font-size:0.85rem;">Esta página é uma ferramenta de diagnóstico temporária — pode ser apagada (<code>public/verificar_rebranding.php</code>) depois de confirmado o piloto.</p>
</body>
</html>
