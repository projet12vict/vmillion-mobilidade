<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$admin = kg_exigir_admin();

$novaSenha = (string) ($_POST['nova_senha'] ?? '');
$confirmar = (string) ($_POST['confirmar_senha'] ?? '');

if (!kg_validar_senha($novaSenha) || $novaSenha !== $confirmar) {
    http_response_code(422);
    echo json_encode(['erro' => 'Senha inválida ou as senhas não coincidem.']);
    exit;
}

$pdo = kg_db();
$pdo->prepare("UPDATE administradores SET senha_hash = ?, senha_temporaria = 0 WHERE id = ?")
    ->execute([kg_password_hash($novaSenha), $admin['id']]);

kg_log_auditoria($pdo, $admin['id'], 'trocou_senha', 'administradores', $admin['id']);

echo json_encode(['sucesso' => true]);
