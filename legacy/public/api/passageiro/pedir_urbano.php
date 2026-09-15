<?php
/**
 * V-MILLION — API: pedido de viagem urbana em aberto (secção "condutores fora
 * do ponto veem passageiros urbanos"). Ao contrário de reservar.php, não
 * exige veiculo_id/assento_id — fica visível a qualquer condutor aprovado e
 * em dia (api/condutor/passageiros_urbanos.php) até ser reclamado
 * (api/condutor/recolher_urbano.php), altura em que passa a seguir
 * exatamente o mesmo fluxo de sempre (confirmar/embarcar/chat/WhatsApp).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/pricing.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$passageiro = kg_exigir_utilizador('passageiro');

$pontoPartidaId = filter_input(INPUT_POST, 'ponto_partida_id', FILTER_VALIDATE_INT);
$origemLat = filter_input(INPUT_POST, 'origem_lat', FILTER_VALIDATE_FLOAT);
$origemLng = filter_input(INPUT_POST, 'origem_lng', FILTER_VALIDATE_FLOAT);
$descidaNome = trim((string) ($_POST['ponto_descida_nome'] ?? ''));
$descidaLat = filter_input(INPUT_POST, 'ponto_descida_lat', FILTER_VALIDATE_FLOAT);
$descidaLng = filter_input(INPUT_POST, 'ponto_descida_lng', FILTER_VALIDATE_FLOAT);
$lugares = filter_input(INPUT_POST, 'lugares', FILTER_VALIDATE_INT) ?: 1;
$motivoBruto = (string) ($_POST['motivo'] ?? 'normal');
$motivo = in_array($motivoBruto, ['normal', 'grupo', 'passeio'], true) ? $motivoBruto : 'normal';

if (!$pontoPartidaId || $origemLat === null || $origemLng === null || $origemLat === false || $origemLng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique a sua localização atual.']);
    exit;
}
if ($descidaNome === '' || $descidaLat === null || $descidaLng === null || $descidaLat === false || $descidaLng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Escreva o destino.']);
    exit;
}
if ($lugares < 1 || $lugares > 8) {
    http_response_code(422);
    echo json_encode(['erro' => 'Número de lugares inválido (1-8).']);
    exit;
}
kg_exigir_coordenadas_validas($origemLat, $origemLng);
kg_exigir_coordenadas_validas($descidaLat, $descidaLng);

$pdo = kg_db();

// Não pode haver mais do que um pedido/reserva ativo em simultâneo (mesma
// regra do fluxo normal — o passageiro sai do que tem antes de pedir outro).
$existente = $pdo->prepare(
    "SELECT id FROM reservas WHERE passageiro_id = ? AND estado NOT IN ('concluido', 'recusado') LIMIT 1"
);
$existente->execute([$passageiro['id']]);
if ($existente->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Já tem uma reserva ativa. Saia dela antes de pedir uma nova viagem.']);
    exit;
}

$pontoPartida = $pdo->prepare("SELECT id, lat, lng FROM pontos_partida WHERE id = ? AND status = 'aprovado'");
$pontoPartida->execute([$pontoPartidaId]);
$pontoPartida = $pontoPartida->fetch();
if (!$pontoPartida) {
    http_response_code(422);
    echo json_encode(['erro' => 'Ponto de referência inválido.']);
    exit;
}

$distanciaKm = kg_distancia_metros($origemLat, $origemLng, $descidaLat, $descidaLng) / 1000;
try {
    $rota = kg_osrm_calcular_rota_cache($origemLat, $origemLng, $descidaLat, $descidaLng);
    if ($rota['distancia_m'] > 0) {
        $distanciaKm = $rota['distancia_m'] / 1000;
    }
} catch (Throwable $e) {
    // OSRM indisponível: mantém a aproximação em linha reta (haversine).
}
$resultadoPreco = kg_calcular_preco_urbano($pdo, $distanciaKm);

$pdo->beginTransaction();
try {
    $inserirReserva = $pdo->prepare(
        "INSERT INTO reservas (passageiro_id, veiculo_id, assento_id, ponto_partida_id, destino_id, tipo_viagem,
                                ponto_descida_nome, ponto_descida_lat, ponto_descida_lng,
                                passageiro_lat, passageiro_lng, passageiro_localizacao_em,
                                lugares, motivo, preco_final, estado)
         VALUES (?, NULL, NULL, ?, ?, 'urbano', ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'pendente')"
    );
    $inserirReserva->execute([
        $passageiro['id'], $pontoPartidaId, $pontoPartidaId,
        $descidaNome, $descidaLat, $descidaLng,
        $origemLat, $origemLng,
        $lugares, $motivo, $resultadoPreco['preco'],
    ]);
    $reservaId = (int) $pdo->lastInsertId();
    $pdo->commit();

    kg_guardar_destino_urbano($pdo, $descidaNome, $descidaLat, $descidaLng, $passageiro['id']);

    echo json_encode(['sucesso' => true, 'reserva_id' => $reservaId, 'preco_estimado' => $resultadoPreco['preco']]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível criar o pedido de viagem.']);
}
