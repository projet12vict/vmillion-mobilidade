<?php
/**
 * V-MILLION — API: passageiro atualiza a sua posição GPS ao vivo, para que o
 * condutor o veja no mapa (secção 5.3/9.4) e não apenas o ponto de partida.
 * Só é guardada se o passageiro tiver uma reserva ativa (pendente,
 * confirmado ou a_bordo) — sem reserva ativa não há veículo a mostrar isto.
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
$passageiro = kg_exigir_utilizador('passageiro');

$lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
$accuracy = filter_input(INPUT_POST, 'accuracy', FILTER_VALIDATE_FLOAT);

if ($lat === null || $lng === null || $lat === false || $lng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Coordenadas inválidas.']);
    exit;
}
// accuracy é opcional (nem todos os clientes a enviam), mas quando vem e é
// fraca (>100m), a leitura já não é fiável — o mesmo limite usado no
// front-end (kg-geolocation.js) para não deixar o passageiro "aparecer no
// mar" com uma leitura por WiFi/IP em vez de GPS real.
if ($accuracy !== null && $accuracy !== false && $accuracy > 100) {
    http_response_code(422);
    echo json_encode(['erro' => 'Precisão do GPS insuficiente.']);
    exit;
}
kg_exigir_coordenadas_validas($lat, $lng);

$pdo = kg_db();

$stmt = $pdo->prepare(
    "UPDATE reservas SET passageiro_lat = ?, passageiro_lng = ?, passageiro_localizacao_em = NOW()
     WHERE passageiro_id = ? AND estado IN ('pendente', 'confirmado', 'a_bordo')"
);
$stmt->execute([$lat, $lng, $passageiro['id']]);

echo json_encode(['sucesso' => true, 'atualizado' => $stmt->rowCount() > 0]);
