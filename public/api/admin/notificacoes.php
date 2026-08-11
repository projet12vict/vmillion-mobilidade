<?php
/**
 * V-MILLION — API: notificações do Super Admin para utilizadores/administradores
 * (secção 12). Usa "fan-out": uma linha por destinatário, para que o estado
 * lida/não-lida seja individual mesmo em broadcasts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Notificações recebidas pelo próprio admin (destinatario_tipo = 'admins').
    $stmt = $pdo->prepare(
        "SELECT id, titulo, mensagem, tipo, lida, criado_em FROM notificacoes
         WHERE destinatario_id = ? AND destinatario_tipo = 'admins'
         ORDER BY criado_em DESC LIMIT 30"
    );
    $stmt->execute([$admin['id']]);
    $recebidas = $stmt->fetchAll();

    echo json_encode(['recebidas' => $recebidas, 'nao_lidas' => count(array_filter($recebidas, fn($n) => !$n['lida']))]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'marcar_lida') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND destinatario_id = ? AND destinatario_tipo = 'admins'")
        ->execute([$id, $admin['id']]);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao !== 'enviar') {
    http_response_code(422);
    echo json_encode(['erro' => 'Ação inválida.']);
    exit;
}

if ($admin['nivel'] !== 'super') {
    http_response_code(403);
    echo json_encode(['erro' => 'Apenas o Super Admin pode enviar notificações.']);
    exit;
}

$destinatarioTipo = in_array($_POST['destinatario_tipo'] ?? '', ['todos', 'admins', 'condutores', 'passageiros', 'individual'], true)
    ? $_POST['destinatario_tipo'] : null;
$destinatarioId = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT) ?: null;
$titulo = trim((string) ($_POST['titulo'] ?? ''));
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));
$tipoBruto = (string) ($_POST['tipo'] ?? 'informativo');
$tipo = in_array($tipoBruto, ['alerta', 'informativo', 'urgente'], true) ? $tipoBruto : 'informativo';

if (!$destinatarioTipo || $titulo === '' || $mensagem === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Preencha o destinatário, o título e a mensagem.']);
    exit;
}
if ($destinatarioTipo === 'individual' && !$destinatarioId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o ID do utilizador (passageiro/condutor).']);
    exit;
}

// Resolve a lista de IDs de destino (fan-out) consoante o tipo escolhido.
$alvos = [];
if ($destinatarioTipo === 'individual') {
    $existe = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ?");
    $existe->execute([$destinatarioId]);
    if (!$existe->fetch()) {
        http_response_code(404);
        echo json_encode(['erro' => 'Utilizador não encontrado.']);
        exit;
    }
    $alvos = [$destinatarioId];
} elseif ($destinatarioTipo === 'admins') {
    $alvos = $pdo->query("SELECT id FROM administradores WHERE ativo = 1")->fetchAll(PDO::FETCH_COLUMN);
} elseif ($destinatarioTipo === 'condutores') {
    $alvos = $pdo->query("SELECT id FROM utilizadores WHERE tipo = 'condutor'")->fetchAll(PDO::FETCH_COLUMN);
} elseif ($destinatarioTipo === 'passageiros') {
    $alvos = $pdo->query("SELECT id FROM utilizadores WHERE tipo = 'passageiro'")->fetchAll(PDO::FETCH_COLUMN);
} else { // todos
    $alvos = $pdo->query("SELECT id FROM utilizadores")->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($alvos)) {
    http_response_code(404);
    echo json_encode(['erro' => 'Não há destinatários para esta seleção.']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO notificacoes (destinatario_id, destinatario_tipo, remetente_id, titulo, mensagem, tipo) VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($alvos as $alvoId) {
        $stmt->execute([$alvoId, $destinatarioTipo, $admin['id'], $titulo, $mensagem, $tipo]);
    }
    kg_log_auditoria($pdo, $admin['id'], 'enviou_notificacao', 'notificacoes', null, "{$destinatarioTipo}: {$titulo} ({$mensagem}) para " . count($alvos) . " destinatário(s)");
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível enviar a notificação.']);
    exit;
}

echo json_encode(['sucesso' => true, 'total_destinatarios' => count($alvos)]);
