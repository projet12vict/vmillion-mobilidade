<?php
/**
 * V-MILLION — API: logs de auditoria com filtros (secção 11.8).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_admin();
$pdo = kg_db();

$sql = "SELECT l.id, l.acao, l.entidade, l.entidade_id, l.detalhes, l.ip, l.criado_em, a.nome AS admin_nome
        FROM logs_auditoria l LEFT JOIN administradores a ON a.id = l.admin_id WHERE 1=1";
$params = [];

if (!empty($_GET['admin_id'])) {
    $sql .= " AND l.admin_id = ?";
    $params[] = (int) $_GET['admin_id'];
}
if (!empty($_GET['data_inicio'])) {
    $sql .= " AND l.criado_em >= ?";
    $params[] = $_GET['data_inicio'] . ' 00:00:00';
}
if (!empty($_GET['data_fim'])) {
    $sql .= " AND l.criado_em <= ?";
    $params[] = $_GET['data_fim'] . ' 23:59:59';
}

$sql .= " ORDER BY l.criado_em DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['logs' => $stmt->fetchAll()]);
