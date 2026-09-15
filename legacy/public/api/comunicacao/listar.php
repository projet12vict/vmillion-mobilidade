<?php
/**
 * V-MILLION — API: histórico de comunicação de um veículo (secção 6).
 * O condutor vê tudo (broadcast + todas as conversas dos passageiros); cada
 * passageiro só vê o autofalante (broadcast) e a sua própria conversa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = kg_exigir_utilizador();

$veiculoId = filter_input(INPUT_GET, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o veículo.']);
    exit;
}

$pdo = kg_db();
$veiculo = $pdo->prepare("SELECT id, condutor_id FROM veiculos WHERE id = ?");
$veiculo->execute([$veiculoId]);
$veiculo = $veiculo->fetch();
if (!$veiculo) {
    http_response_code(404);
    echo json_encode(['erro' => 'Veículo não encontrado.']);
    exit;
}

$ehCondutorDoVeiculo = $utilizador['tipo'] === 'condutor' && (int) $veiculo['condutor_id'] === (int) $utilizador['id'];

if ($ehCondutorDoVeiculo) {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.remetente_id, c.destinatario_id, c.mensagem, c.tipo, c.lida, c.criado_em, u.nome AS remetente_nome
         FROM comunicacoes_veiculo c JOIN utilizadores u ON u.id = c.remetente_id
         WHERE c.veiculo_id = ? ORDER BY c.criado_em ASC LIMIT 200"
    );
    $stmt->execute([$veiculoId]);
} else {
    $temReserva = $pdo->prepare(
        "SELECT 1 FROM reservas WHERE veiculo_id = ? AND passageiro_id = ? AND estado IN ('pendente', 'confirmado', 'a_bordo') LIMIT 1"
    );
    $temReserva->execute([$veiculoId, $utilizador['id']]);
    if (!$temReserva->fetch()) {
        http_response_code(403);
        echo json_encode(['erro' => 'Não tem uma reserva ativa neste veículo.']);
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT c.id, c.remetente_id, c.destinatario_id, c.mensagem, c.tipo, c.lida, c.criado_em, u.nome AS remetente_nome
         FROM comunicacoes_veiculo c JOIN utilizadores u ON u.id = c.remetente_id
         WHERE c.veiculo_id = ? AND (c.destinatario_id IS NULL OR c.destinatario_id = ? OR c.remetente_id = ?)
         ORDER BY c.criado_em ASC LIMIT 200"
    );
    $stmt->execute([$veiculoId, $utilizador['id'], $utilizador['id']]);
}

$mensagens = $stmt->fetchAll();

echo json_encode([
    'mensagens' => $mensagens,
    'nao_lidas' => count(array_filter($mensagens, fn($m) => !$m['lida'] && (int) $m['destinatario_id'] === (int) $utilizador['id'])),
]);
