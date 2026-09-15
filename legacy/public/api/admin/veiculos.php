<?php
/**
 * V-MILLION — API: gestão/aprovação de veículos (secção 7.2, 11.3).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT v.id, v.matricula, v.tipo, v.cor, v.modelo, v.aprovado, v.estado, u.nome AS condutor_nome, u.telefone AS condutor_telefone
         FROM veiculos v JOIN utilizadores u ON u.id = v.condutor_id
         ORDER BY v.criado_em DESC LIMIT 200"
    );
    echo json_encode(['veiculos' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(422);
    echo json_encode(['erro' => 'Veículo inválido.']);
    exit;
}

if ($acao === 'aprovar') {
    $pdo->prepare("UPDATE veiculos SET aprovado = 1 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'aprovou_veiculo', 'veiculos', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'rejeitar') {
    $pdo->prepare("UPDATE veiculos SET aprovado = 0 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'rejeitou_veiculo', 'veiculos', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
