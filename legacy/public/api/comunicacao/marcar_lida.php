<?php
/**
 * V-MILLION — API: marca uma mensagem de comunicação como lida (secção 6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$utilizador = kg_exigir_utilizador();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    echo json_encode(['erro' => 'ID inválido.']);
    exit;
}

$pdo = kg_db();
$pdo->prepare("UPDATE comunicacoes_veiculo SET lida = 1 WHERE id = ? AND destinatario_id = ?")
    ->execute([$id, $utilizador['id']]);

echo json_encode(['sucesso' => true]);
