<?php
/**
 * V-MILLION — API: condutor escolhe ponto de partida e destino (secção 9.2).
 * A escolha persiste na base de dados (não apenas em sessão).
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
$pontoPartidaId = filter_input(INPUT_POST, 'ponto_partida_id', FILTER_VALIDATE_INT);
$destinoId = filter_input(INPUT_POST, 'destino_id', FILTER_VALIDATE_INT);

if (!$veiculoId || !$pontoPartidaId || !$destinoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Selecione o ponto de partida e o destino.']);
    exit;
}

if ($pontoPartidaId === $destinoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'O destino tem de ser diferente do ponto de partida.']);
    exit;
}

$pdo = kg_db();

// Verificação de posse feita à parte (SELECT) em vez de confiar no rowCount()
// do UPDATE: com PDO::ATTR_EMULATE_PREPARES=false, o MySQL devolve 0 linhas
// afetadas também quando o veículo existe mas os valores não mudam (ex:
// condutor clica "Guardar" sem alterar nada) — isso estava a ser lido como
// "Veículo não encontrado" mesmo com o veículo bem selecionado.
$existe = $pdo->prepare("SELECT id FROM veiculos WHERE id = ? AND condutor_id = ?");
$existe->execute([$veiculoId, $condutor['id']]);
if (!$existe->fetch()) {
    http_response_code(404);
    echo json_encode(['erro' => 'Veículo não encontrado.']);
    exit;
}

$ponto = $pdo->prepare("SELECT lat, lng FROM pontos_partida WHERE id = ? AND status = 'aprovado'");
$ponto->execute([$pontoPartidaId]);
$ponto = $ponto->fetch();
if (!$ponto) {
    http_response_code(422);
    echo json_encode(['erro' => 'Ponto de partida inválido ou ainda não aprovado.']);
    exit;
}

// Ancora o veículo nas coordenadas reais do ponto de partida escolhido
// (relatório do mapa, tarefa A) — nunca fica "a flutuar" sem lat/lng.
$pdo->prepare(
    "UPDATE veiculos SET ponto_partida_id = ?, destino_id = ?, estado = 'no_ponto', posicao_fila = NULL,
            lat = ?, lng = ?, ultima_posicao_em = NOW()
     WHERE id = ?"
)->execute([$pontoPartidaId, $destinoId, $ponto['lat'], $ponto['lng'], $veiculoId]);

kg_log_auditoria($pdo, null, 'condutor_definiu_ponto', 'veiculos', $veiculoId, "ponto={$pontoPartidaId} destino={$destinoId}");

echo json_encode(['sucesso' => true]);
