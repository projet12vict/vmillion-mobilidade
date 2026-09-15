<?php
/**
 * V-MILLION — API: o destinatário atende ou recusa uma chamada simulada.
 * Nunca automático — só chamado a partir de um clique real em "Atender"/"Recusar".
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
$utilizador = kg_exigir_utilizador();

$chamadaId = filter_input(INPUT_POST, 'chamada_id', FILTER_VALIDATE_INT);
$acao = (string) ($_POST['acao'] ?? '');
if (!$chamadaId || !in_array($acao, ['atender', 'recusar'], true)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare("SELECT * FROM chamadas WHERE id = ? AND destinatario_id = ? LIMIT 1");
$stmt->execute([$chamadaId, $utilizador['id']]);
$chamada = $stmt->fetch();

if (!$chamada) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamada não encontrada.']);
    exit;
}
if ($chamada['estado'] !== 'iniciada') {
    http_response_code(409);
    echo json_encode(['erro' => 'Esta chamada já não está a tocar.']);
    exit;
}

if ($acao === 'atender') {
    $pdo->prepare("UPDATE chamadas SET estado = 'atendida', atendida_em = NOW() WHERE id = ?")->execute([$chamadaId]);
} else {
    $pdo->prepare("UPDATE chamadas SET estado = 'recusada', terminada_em = NOW() WHERE id = ?")->execute([$chamadaId]);
}

echo json_encode(['sucesso' => true, 'remetente_id' => (int) $chamada['remetente_id']]);
