<?php
/**
 * V-MILLION — API: registo de passageiro ou condutor (formulário único, secção 4.1).
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

$nome     = trim((string) ($_POST['nome'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$nif      = trim((string) ($_POST['nif'] ?? ''));
$senha    = (string) ($_POST['senha'] ?? '');
$confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
$tipo     = (string) ($_POST['tipo'] ?? '');

$erros = [];

if (mb_strlen($nome) < 3) {
    $erros['nome'] = 'Indique o nome completo.';
}
if (!kg_validar_telefone($telefone)) {
    $erros['telefone'] = 'Telefone inválido. Formato esperado: +238 9912345.';
}
if (!kg_validar_nif($nif)) {
    $erros['nif'] = 'NIF inválido. Deve ter exatamente 9 dígitos.';
}
if (!kg_validar_senha($senha)) {
    $erros['senha'] = 'A senha deve ter no mínimo 8 caracteres.';
}
if ($senha !== $confirmarSenha) {
    $erros['confirmar_senha'] = 'As senhas não coincidem.';
}
if (!in_array($tipo, ['passageiro', 'condutor'], true)) {
    $erros['tipo'] = 'Tipo de utilizador inválido.';
}

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.', 'campos' => $erros]);
    exit;
}

$telefoneNormalizado = kg_normalizar_telefone($telefone);
$pdo = kg_db();

$verificar = $pdo->prepare("SELECT id FROM utilizadores WHERE telefone = ? OR nif = ? LIMIT 1");
$verificar->execute([$telefoneNormalizado, $nif]);
if ($verificar->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Já existe uma conta com este telefone ou NIF.']);
    exit;
}

$status = $tipo === 'condutor' ? 'pendente' : 'ativo';
$hash = kg_password_hash($senha);

$stmt = $pdo->prepare(
    "INSERT INTO utilizadores (tipo, nome, telefone, nif, senha_hash, status) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$tipo, $nome, $telefoneNormalizado, $nif, $hash, $status]);

$resposta = ['sucesso' => true];
if ($tipo === 'condutor') {
    $resposta['mensagem'] = 'Registo efetuado. A sua conta fica ativa após aprovação do administrador (mediante comprovativo de pagamento).';
} else {
    $resposta['mensagem'] = 'Registo efetuado com sucesso. Pode iniciar sessão.';
}

echo json_encode($resposta);
