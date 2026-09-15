<?php
/**
 * V-MILLION — API: o passageiro ajusta a localização exata do destino da sua
 * reserva ativa, arrastando o marcador no mapa (secção "passageiro pode
 * ajustar o destino no mapa"). Não recalcula o preço — fica fixo ao valor
 * combinado na reserva; o ajuste é só para a navegação/exibição ficarem
 * corretas. Não toca em destinos_urbanos (a entrada partilhada mantém-se
 * como estava — um ajuste pessoal não deve mudar a sugestão para outros).
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

$reservaId = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);

if (!$reservaId || $lat === null || $lng === null || $lat === false || $lng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}
kg_exigir_coordenadas_validas($lat, $lng);

$pdo = kg_db();

$existe = $pdo->prepare(
    "SELECT id FROM reservas WHERE id = ? AND passageiro_id = ? AND estado NOT IN ('concluido', 'recusado')"
);
$existe->execute([$reservaId, $passageiro['id']]);
if (!$existe->fetch()) {
    http_response_code(404);
    echo json_encode(['erro' => 'Reserva não encontrada.']);
    exit;
}

$pdo->prepare("UPDATE reservas SET ponto_descida_lat = ?, ponto_descida_lng = ? WHERE id = ?")
    ->execute([$lat, $lng, $reservaId]);

echo json_encode(['sucesso' => true, 'lat' => $lat, 'lng' => $lng]);
