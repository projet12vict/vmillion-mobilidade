<?php
/**
 * V-MILLION — API: lista de pontos de partida aprovados (para dropdowns).
 * Só devolve status = 'aprovado' — pontos pendentes/recusados só são
 * visíveis no admin (ver api/admin/pontos.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador();

$pdo = kg_db();
$pontos = $pdo->query(
    "SELECT id, nome, cidade, lat, lng, zona FROM pontos_partida WHERE status = 'aprovado' ORDER BY nome"
)->fetchAll();

echo json_encode(['pontos' => $pontos]);
