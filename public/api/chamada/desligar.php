<?php
/**
 * V-MILLION — API: terminar uma chamada simulada (qualquer um dos dois lados
 * pode desligar, em qualquer estado ainda não terminado). Nunca automático.
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
if (!$chamadaId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique a chamada.']);
    exit;
}

$pdo = kg_db();
$stmt = $pdo->prepare("SELECT * FROM chamadas WHERE id = ? AND (remetente_id = ? OR destinatario_id = ?) LIMIT 1");
$stmt->execute([$chamadaId, $utilizador['id'], $utilizador['id']]);
$chamada = $stmt->fetch();

if (!$chamada) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamada não encontrada.']);
    exit;
}
if (!in_array($chamada['estado'], ['iniciada', 'atendida'], true)) {
    echo json_encode(['sucesso' => true]); // já tinha terminado — nada a fazer, não é erro
    exit;
}

$pdo->prepare("UPDATE chamadas SET estado = 'terminada', terminada_em = NOW() WHERE id = ?")->execute([$chamadaId]);

$outroId = (int) $chamada['remetente_id'] === (int) $utilizador['id']
    ? (int) $chamada['destinatario_id']
    : (int) $chamada['remetente_id'];

echo json_encode(['sucesso' => true, 'outro_id' => $outroId]);
