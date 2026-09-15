<?php
/**
 * V-MILLION — API: gestão de administradores (apenas Super Admin, secção 11.7, 4.3).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$superAdmin = kg_exigir_admin('super');
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, nome, email, nivel, ativo, senha_temporaria, criado_em FROM administradores ORDER BY criado_em DESC");
    echo json_encode(['administradores' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'criar') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $nivel = in_array($_POST['nivel'] ?? '', ['gestor', 'admin', 'super'], true) ? $_POST['nivel'] : 'admin';

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['erro' => 'Nome ou email inválido.']);
        exit;
    }

    // Regra de negócio: o Super Admin é único e exclusivo (secção 3) — nunca
    // se cria um segundo, mesmo que outro Super Admin peça explicitamente.
    if ($nivel === 'super') {
        $existeSuper = $pdo->query("SELECT COUNT(*) FROM administradores WHERE nivel = 'super'")->fetchColumn();
        if ((int) $existeSuper > 0) {
            http_response_code(409);
            echo json_encode(['erro' => 'Já existe um Super Admin. Apenas um pode existir no sistema.']);
            exit;
        }
    }

    $dup = $pdo->prepare("SELECT id FROM administradores WHERE email = ?");
    $dup->execute([$email]);
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode(['erro' => 'Já existe um administrador com este email.']);
        exit;
    }

    $senhaTemp = bin2hex(random_bytes(6));
    $pdo->prepare(
        "INSERT INTO administradores (nome, email, senha_hash, nivel, senha_temporaria, ativo, criado_por)
         VALUES (?, ?, ?, ?, 1, 1, ?)"
    )->execute([$nome, $email, kg_password_hash($senhaTemp), $nivel, $superAdmin['id']]);

    $novoId = (int) $pdo->lastInsertId();
    kg_log_auditoria($pdo, $superAdmin['id'], 'criou_admin', 'administradores', $novoId, $email);

    echo json_encode(['sucesso' => true, 'id' => $novoId, 'senha_temporaria' => $senhaTemp]);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }

if ($id === (int) $superAdmin['id'] && in_array($acao, ['desativar', 'eliminar'], true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Não é possível desativar ou eliminar o próprio Super Admin.']);
    exit;
}

if ($acao === 'desativar') {
    $pdo->prepare("UPDATE administradores SET ativo = 0 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $superAdmin['id'], 'desativou_admin', 'administradores', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'reativar') {
    $pdo->prepare("UPDATE administradores SET ativo = 1 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $superAdmin['id'], 'reativou_admin', 'administradores', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'eliminar') {
    $pdo->prepare("DELETE FROM administradores WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $superAdmin['id'], 'eliminou_admin', 'administradores', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
