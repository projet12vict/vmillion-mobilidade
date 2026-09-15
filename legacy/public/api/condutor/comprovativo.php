<?php
/**
 * V-MILLION — API: download do próprio comprovativo de pagamento (vista do condutor).
 * uploads/ fica fora do document root (public/), por isso o ficheiro tem de
 * ser servido através de um endpoint autenticado, tal como o recibo.
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
$stmt = $pdo->prepare("SELECT comprovativo_path FROM pagamentos_condutores WHERE id = ? AND condutor_id = ?");
$stmt->execute([$id, $condutor['id']]);
$pagamento = $stmt->fetch();

if (!$pagamento || !$pagamento['comprovativo_path']) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Comprovativo não encontrado.']);
    exit;
}

$caminho = __DIR__ . '/../../../' . $pagamento['comprovativo_path'];
if (!is_file($caminho)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Ficheiro do comprovativo não encontrado.']);
    exit;
}

$extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
$tiposMime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
header('Content-Type: ' . ($tiposMime[$extensao] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="comprovativo_' . $id . '.' . $extensao . '"');
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
