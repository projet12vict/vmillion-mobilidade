<?php
/**
 * V-MILLION — API: download do recibo PDF de um pagamento aprovado (secção 11.6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

$condutor = kg_exigir_utilizador('condutor');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'ID inválido.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT recibo_path, referencia FROM pagamentos_condutores WHERE id = ? AND condutor_id = ? AND status = 'aprovado'"
);
$stmt->execute([$id, $condutor['id']]);
$pagamento = $stmt->fetch();

if (!$pagamento || !$pagamento['recibo_path']) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Recibo não encontrado.']);
    exit;
}

$caminho = __DIR__ . '/../../../' . $pagamento['recibo_path'];
if (!is_file($caminho)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Ficheiro do recibo não encontrado.']);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="recibo_' . preg_replace('/[^A-Za-z0-9_-]/', '', $pagamento['referencia']) . '.pdf"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
