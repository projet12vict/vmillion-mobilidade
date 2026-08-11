<?php
/**
 * V-MILLION — API: login de passageiro/condutor (telefone ou email + senha).
 * Rate limiting: 5 tentativas -> bloqueio 15 min. Sessão regenerada após login (secção 4.2).
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

$identificador = trim((string) ($_POST['identificador'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if ($identificador === '' || $senha === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o telefone/email e a senha.']);
    exit;
}

$pdo = kg_db();

$campo = str_contains($identificador, '@') ? 'email' : 'telefone';
$valor = $campo === 'telefone' ? kg_normalizar_telefone($identificador) : $identificador;

$stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE {$campo} = ? LIMIT 1");
$stmt->execute([$valor]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['erro' => 'Credenciais inválidas.']);
    exit;
}

kg_rate_limit_check($pdo, 'utilizadores', (int) $user['id']);

if (!kg_password_verify($senha, $user['senha_hash'])) {
    kg_rate_limit_registar_falha($pdo, 'utilizadores', (int) $user['id']);
    http_response_code(401);
    echo json_encode(['erro' => 'Credenciais inválidas.']);
    exit;
}

if ($user['status'] === 'pendente') {
    http_response_code(403);
    echo json_encode(['erro' => 'A sua conta de condutor está pendente de aprovação pelo administrador.']);
    exit;
}

if ($user['status'] === 'suspenso') {
    http_response_code(403);
    echo json_encode(['erro' => 'A sua conta está suspensa. Contacte o suporte.']);
    exit;
}

kg_rate_limit_reset($pdo, 'utilizadores', (int) $user['id']);
kg_session_regenerate();

$_SESSION['kg_utilizador'] = [
    'id'       => (int) $user['id'],
    'tipo'     => $user['tipo'],
    'nome'     => $user['nome'],
    'telefone' => $user['telefone'],
];

echo json_encode([
    'sucesso' => true,
    'tipo'    => $user['tipo'],
    'redirect' => $user['tipo'] === 'condutor' ? '/condutor/painel.php' : '/passageiro/painel.php',
]);
