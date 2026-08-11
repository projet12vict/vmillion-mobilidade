<?php
/**
 * V-MILLION — API: condutor entra na fila do ponto de partida (secção 9.3).
 * Ganha prioridade na recolha de passageiros (posição = próxima na fila).
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
$condutor = kg_exigir_utilizador('condutor');

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Veículo inválido.']);
    exit;
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    $veiculoStmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? AND condutor_id = ? FOR UPDATE");
    $veiculoStmt->execute([$veiculoId, $condutor['id']]);
    $veiculo = $veiculoStmt->fetch();

    if (!$veiculo) {
        throw new RuntimeException('Veículo não encontrado.', 404);
    }
    if (!$veiculo['aprovado']) {
        throw new RuntimeException('O veículo ainda não foi aprovado.', 403);
    }
    if (!$veiculo['ponto_partida_id']) {
        throw new RuntimeException('Escolha primeiro o ponto de partida.', 422);
    }

    $maxPos = $pdo->prepare(
        "SELECT COALESCE(MAX(posicao_fila), 0) FROM veiculos WHERE ponto_partida_id = ? AND estado = 'na_fila'"
    );
    $maxPos->execute([$veiculo['ponto_partida_id']]);
    $novaPosicao = (int) $maxPos->fetchColumn() + 1;

    $pdo->prepare("UPDATE veiculos SET estado = 'na_fila', posicao_fila = ? WHERE id = ?")
        ->execute([$novaPosicao, $veiculoId]);

    $pdo->commit();
    echo json_encode(['sucesso' => true, 'posicao_fila' => $novaPosicao]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
}
