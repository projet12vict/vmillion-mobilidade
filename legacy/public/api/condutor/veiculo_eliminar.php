<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$condutor = kg_exigir_utilizador('condutor');

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Veículo inválido.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare("DELETE FROM veiculos WHERE id = ? AND condutor_id = ?");
$stmt->execute([$veiculoId, $condutor['id']]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['erro' => 'Veículo não encontrado.']);
    exit;
}

echo json_encode(['sucesso' => true]);
