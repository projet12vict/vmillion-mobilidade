<?php
/**
 * V-MILLION — API: gestão de sugestões e reclamações (secção 10). Sugestões só
 * são visíveis ao Super Admin; reclamações são visíveis a qualquer admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tiposPermitidos = $admin['nivel'] === 'super' ? ['sugestao', 'reclamacao'] : ['reclamacao'];
    $placeholders = implode(',', array_fill(0, count($tiposPermitidos), '?'));

    $stmt = $pdo->prepare(
        "SELECT s.id, s.tipo, s.titulo, s.descricao, s.status, s.criado_em,
                u.nome AS utilizador_nome, u.tipo AS utilizador_tipo, u.telefone AS utilizador_telefone,
                c.nome AS condutor_nome, c.telefone AS condutor_telefone
         FROM sugestoes s
         JOIN utilizadores u ON u.id = s.utilizador_id
         LEFT JOIN utilizadores c ON c.id = s.condutor_id
         WHERE s.tipo IN ({$placeholders})
         ORDER BY (s.status = 'pendente') DESC, s.criado_em DESC LIMIT 200"
    );
    $stmt->execute($tiposPermitidos);
    echo json_encode(['sugestoes' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'atualizar_status') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = in_array($_POST['status'] ?? '', ['pendente', 'visto', 'implementado', 'resolvido'], true) ? $_POST['status'] : null;
    if (!$id || !$status) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados inválidos.']);
        exit;
    }

    // Um admin normal só pode atualizar reclamações (não vê/gere sugestões).
    $stmt = $pdo->prepare("SELECT tipo FROM sugestoes WHERE id = ?");
    $stmt->execute([$id]);
    $registo = $stmt->fetch();
    if (!$registo) {
        http_response_code(404);
        echo json_encode(['erro' => 'Registo não encontrado.']);
        exit;
    }
    if ($registo['tipo'] === 'sugestao' && $admin['nivel'] !== 'super') {
        http_response_code(403);
        echo json_encode(['erro' => 'Apenas o Super Admin gere sugestões.']);
        exit;
    }

    $pdo->prepare("UPDATE sugestoes SET status = ? WHERE id = ?")->execute([$status, $id]);
    kg_log_auditoria($pdo, $admin['id'], 'atualizou_sugestao', 'sugestoes', $id, $status);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
