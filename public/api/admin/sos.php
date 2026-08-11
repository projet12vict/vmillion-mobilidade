<?php
/**
 * V-MILLION — API: central de alarmes SOS (secção 11.6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT a.id, a.tipo_utilizador, a.lat, a.lng, a.estado, a.criado_em, u.nome AS utilizador_nome, u.telefone
         FROM alarmes_sos a JOIN utilizadores u ON u.id = a.utilizador_id
         ORDER BY (a.estado = 'pendente') DESC, a.criado_em DESC LIMIT 100"
    );
    echo json_encode(['alarmes' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$estado = (string) ($_POST['estado'] ?? '');

if (!$id || !in_array($estado, ['em_curso', 'resolvido'], true)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}

$pdo->prepare("UPDATE alarmes_sos SET estado = ?, resolvido_por = ? WHERE id = ?")
    ->execute([$estado, $admin['id'], $id]);
kg_log_auditoria($pdo, $admin['id'], 'atualizou_sos', 'alarmes_sos', $id, $estado);

echo json_encode(['sucesso' => true]);
