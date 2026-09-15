<?php
/**
 * V-MILLION — API: polling rápido (1s) da sinalização WebRTC nova do OUTRO
 * participante de uma chamada (nunca devolve os meus próprios sinais).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/chamadas.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = kg_exigir_utilizador();

$chamadaId = filter_input(INPUT_GET, 'chamada_id', FILTER_VALIDATE_INT);
$desdeId = filter_input(INPUT_GET, 'desde_id', FILTER_VALIDATE_INT) ?: 0;
if (!$chamadaId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique a chamada.']);
    exit;
}

$pdo = kg_db();
$chamada = kg_chamada_participante($pdo, $chamadaId, $utilizador['id']);
if (!$chamada) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamada não encontrada.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, tipo, payload FROM sinalizacao_chamada
     WHERE chamada_id = ? AND remetente_id != ? AND id > ?
     ORDER BY id ASC LIMIT 50"
);
$stmt->execute([$chamadaId, $utilizador['id'], $desdeId]);

echo json_encode(['sinais' => $stmt->fetchAll()]);
