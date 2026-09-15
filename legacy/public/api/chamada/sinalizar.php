<?php
/**
 * V-MILLION — API: guarda uma mensagem de sinalização WebRTC (offer/answer/ice)
 * de uma chamada. O servidor nunca vê áudio, só este texto de negociação —
 * ver database/migration_20260810_sinalizacao_chamada.sql.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/chamadas.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$utilizador = kg_exigir_utilizador();

$chamadaId = filter_input(INPUT_POST, 'chamada_id', FILTER_VALIDATE_INT);
$tipo = (string) ($_POST['tipo'] ?? '');
$payload = (string) ($_POST['payload'] ?? '');

if (!$chamadaId || !in_array($tipo, ['offer', 'answer', 'ice'], true) || $payload === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}
if (mb_strlen($payload) > 4000) {
    http_response_code(422);
    echo json_encode(['erro' => 'Sinal demasiado grande.']);
    exit;
}
json_decode($payload);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(422);
    echo json_encode(['erro' => 'Sinal mal formado.']);
    exit;
}

$pdo = kg_db();
$chamada = kg_chamada_participante($pdo, $chamadaId, $utilizador['id']);
if (!$chamada) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamada não encontrada.']);
    exit;
}
if (!in_array($chamada['estado'], ['iniciada', 'atendida'], true)) {
    http_response_code(409);
    echo json_encode(['erro' => 'Esta chamada já terminou.']);
    exit;
}

$pdo->prepare(
    "INSERT INTO sinalizacao_chamada (chamada_id, remetente_id, tipo, payload) VALUES (?, ?, ?, ?)"
)->execute([$chamadaId, $utilizador['id'], $tipo, $payload]);

echo json_encode(['sucesso' => true]);
