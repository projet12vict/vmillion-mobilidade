<?php
/**
 * V-MILLION — API: login de administradores (página/modal dedicada, secção 4.3).
 * Super Admin nunca é bloqueado por rate limiting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();

$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o email e a senha.']);
    exit;
}

$pdo = kg_db();

$stmt = $pdo->prepare("SELECT * FROM administradores WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin || !$admin['ativo']) {
    http_response_code(401);
    echo json_encode(['erro' => 'Credenciais inválidas.']);
    exit;
}

$isSuperAdmin = $admin['nivel'] === 'super';

if (!$isSuperAdmin) {
    kg_rate_limit_check($pdo, 'administradores', (int) $admin['id']);
}

if (!kg_password_verify($senha, $admin['senha_hash'])) {
    kg_rate_limit_registar_falha($pdo, 'administradores', (int) $admin['id'], $isSuperAdmin);
    http_response_code(401);
    echo json_encode(['erro' => 'Credenciais inválidas.']);
    exit;
}

kg_rate_limit_reset($pdo, 'administradores', (int) $admin['id']);
kg_session_regenerate();

$_SESSION['kg_admin'] = [
    'id'    => (int) $admin['id'],
    'nome'  => $admin['nome'],
    'email' => $admin['email'],
    'nivel' => $admin['nivel'],
];

kg_log_auditoria($pdo, (int) $admin['id'], 'login', 'administradores', (int) $admin['id']);

echo json_encode([
    'sucesso' => true,
    'senha_temporaria' => (bool) $admin['senha_temporaria'],
    'redirect' => $admin['senha_temporaria'] ? '/admin/pages/trocar_senha.php' : '/admin/painel.php',
]);
