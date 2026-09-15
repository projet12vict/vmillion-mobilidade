<?php
/**
 * V-MILLION — API: notificações do utilizador autenticado (passageiro ou
 * condutor), incluindo alertas do Super Admin (secção 12).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = kg_exigir_utilizador();

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT id, titulo, mensagem, tipo, lida, criado_em FROM notificacoes
     WHERE destinatario_id = ? AND destinatario_tipo != 'admins'
     ORDER BY criado_em DESC LIMIT 30"
);
$stmt->execute([$utilizador['id']]);
$notificacoes = $stmt->fetchAll();

echo json_encode([
    'notificacoes' => $notificacoes,
    'nao_lidas' => count(array_filter($notificacoes, fn($n) => !$n['lida'])),
]);
