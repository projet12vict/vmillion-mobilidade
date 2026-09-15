<?php
/**
 * V-MILLION — API: média de avaliações de um condutor (pública para utilizadores
 * autenticados, secção 9 — visível no mapa/perfil do condutor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador();

$condutorId = filter_input(INPUT_GET, 'condutor_id', FILTER_VALIDATE_INT);
if (!$condutorId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o condutor.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, COALESCE(AVG(avaliacao), 0) AS media FROM avaliacoes_condutores WHERE condutor_id = ?"
);
$stmt->execute([$condutorId]);
$row = $stmt->fetch();

echo json_encode([
    'condutor_id' => $condutorId,
    'total' => (int) $row['total'],
    'media' => round((float) $row['media'], 2),
]);
