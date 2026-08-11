<?php
/**
 * V-MILLION — API: destinos urbanos já gravados por outros passageiros, para
 * sugerir por autocomplete antes de ir à pesquisa externa (Nominatim) —
 * secção "destinos criados por passageiros são gravados e reutilizáveis".
 * Não são pontos públicos (ao contrário de pontos_partida): só aparecem
 * como sugestão de texto, nunca como marcador visível a todos no mapa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador('passageiro');

$termo = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($termo) < 2) {
    echo json_encode(['destinos' => []]);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT id, nome, lat, lng FROM destinos_urbanos WHERE nome LIKE ? ORDER BY usos DESC, nome ASC LIMIT 8"
);
$stmt->execute(['%' . $termo . '%']);

echo json_encode(['destinos' => $stmt->fetchAll()]);
