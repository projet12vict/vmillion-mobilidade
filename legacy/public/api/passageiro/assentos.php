<?php
/**
 * V-MILLION — API: layout de assentos de um veículo (5 filas x 3, secção 8).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador('passageiro');

$veiculoId = filter_input(INPUT_GET, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Veículo inválido.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT id, numero, fila, coluna, ocupado FROM assentos_veiculo WHERE veiculo_id = ? ORDER BY numero"
);
$stmt->execute([$veiculoId]);
$assentos = $stmt->fetchAll();

if (!$assentos) {
    http_response_code(404);
    echo json_encode(['erro' => 'Veículo sem assentos configurados.']);
    exit;
}

echo json_encode(['assentos' => $assentos]);
