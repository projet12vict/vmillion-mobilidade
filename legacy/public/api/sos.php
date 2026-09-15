<?php
/**
 * V-MILLION — API: ativar alarme SOS (secção 10.5). Disponível para passageiros e condutores.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$user = kg_exigir_utilizador();

$lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);

if ($lat === null || $lng === null || $lat === false || $lng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Localização em falta.']);
    exit;
}

kg_exigir_coordenadas_validas($lat, $lng);

$pdo = kg_db();
$stmt = $pdo->prepare(
    "INSERT INTO alarmes_sos (utilizador_id, tipo_utilizador, lat, lng, estado) VALUES (?, ?, ?, ?, 'pendente')"
);
$stmt->execute([$user['id'], $user['tipo'], $lat, $lng]);

echo json_encode(['sucesso' => true, 'alarme_id' => (int) $pdo->lastInsertId()]);
