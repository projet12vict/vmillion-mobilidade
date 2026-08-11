<?php
/**
 * V-MILLION — API: marca uma notificação do utilizador como lida (secção 12).
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
$pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND destinatario_id = ? AND destinatario_tipo != 'admins'")
    ->execute([$id, $utilizador['id']]);

echo json_encode(['sucesso' => true]);
