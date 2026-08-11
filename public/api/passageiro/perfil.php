<?php
/**
 * V-MILLION — API: editar perfil do passageiro (secção 10.4). A senha só muda
 * com confirmação da senha atual.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$passageiro = kg_exigir_utilizador();

$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT nome, telefone, nif, email FROM utilizadores WHERE id = ?");
    $stmt->execute([$passageiro['id']]);
    echo json_encode(['perfil' => $stmt->fetch()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();

$nome = trim((string) ($_POST['nome'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$nif = trim((string) ($_POST['nif'] ?? ''));
$senhaAtual = (string) ($_POST['senha_atual'] ?? '');
$novaSenha = (string) ($_POST['nova_senha'] ?? '');

$erros = [];
if (mb_strlen($nome) < 3) $erros['nome'] = 'Indique o nome completo.';
if (!kg_validar_telefone($telefone)) $erros['telefone'] = 'Telefone inválido.';
if (!kg_validar_nif($nif)) $erros['nif'] = 'NIF inválido (9 dígitos).';

if ($novaSenha !== '' && !kg_validar_senha($novaSenha)) {
    $erros['nova_senha'] = 'A nova senha deve ter no mínimo 8 caracteres.';
}

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.', 'campos' => $erros]);
    exit;
}

$telefoneNormalizado = kg_normalizar_telefone($telefone);

$dup = $pdo->prepare("SELECT id FROM utilizadores WHERE (telefone = ? OR nif = ?) AND id != ?");
$dup->execute([$telefoneNormalizado, $nif, $passageiro['id']]);
if ($dup->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Telefone ou NIF já em uso por outra conta.']);
    exit;
}

if ($novaSenha !== '') {
    $stmt = $pdo->prepare("SELECT senha_hash FROM utilizadores WHERE id = ?");
    $stmt->execute([$passageiro['id']]);
    $hashAtual = $stmt->fetchColumn();
    if (!kg_password_verify($senhaAtual, (string) $hashAtual)) {
        http_response_code(403);
        echo json_encode(['erro' => 'Senha atual incorreta.']);
        exit;
    }
    $novoHash = kg_password_hash($novaSenha);
    $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ?, nif = ?, senha_hash = ? WHERE id = ?")
        ->execute([$nome, $telefoneNormalizado, $nif, $novoHash, $passageiro['id']]);
} else {
    $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ?, nif = ? WHERE id = ?")
        ->execute([$nome, $telefoneNormalizado, $nif, $passageiro['id']]);
}

$_SESSION['kg_utilizador']['nome'] = $nome;
$_SESSION['kg_utilizador']['telefone'] = $telefoneNormalizado;

echo json_encode(['sucesso' => true, 'mensagem' => 'Perfil atualizado com sucesso.']);
