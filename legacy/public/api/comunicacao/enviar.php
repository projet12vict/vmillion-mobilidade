<?php
/**
 * V-MILLION — API: comunicação (autofalante) entre passageiros e o condutor de
 * um veículo, secção 6. O condutor é o administrador do canal (pode falar
 * com todos ou responder a um passageiro específico); os passageiros só
 * podem falar com o condutor.
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

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));
$destinatarioId = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT) ?: null;

if (!$veiculoId || $mensagem === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o veículo e a mensagem.']);
    exit;
}
if (mb_strlen($mensagem) > 500) {
    http_response_code(422);
    echo json_encode(['erro' => 'Mensagem demasiado longa (máx. 500 caracteres).']);
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
    // O condutor pode fazer broadcast (destinatario_id nulo) ou responder a um passageiro específico.
    if ($destinatarioId) {
        $pertence = $pdo->prepare(
            "SELECT 1 FROM reservas WHERE veiculo_id = ? AND passageiro_id = ? AND estado IN ('pendente', 'confirmado', 'a_bordo') LIMIT 1"
        );
        $pertence->execute([$veiculoId, $destinatarioId]);
        if (!$pertence->fetch()) {
            http_response_code(403);
            echo json_encode(['erro' => 'Este passageiro não está associado a este veículo.']);
            exit;
        }
    }
} elseif ($utilizador['tipo'] === 'passageiro') {
    // O passageiro só pode falar com o condutor da sua própria reserva ativa neste veículo.
    $temReserva = $pdo->prepare(
        "SELECT 1 FROM reservas WHERE veiculo_id = ? AND passageiro_id = ? AND estado IN ('pendente', 'confirmado', 'a_bordo') LIMIT 1"
    );
    $temReserva->execute([$veiculoId, $utilizador['id']]);
    if (!$temReserva->fetch()) {
        http_response_code(403);
        echo json_encode(['erro' => 'Não tem uma reserva ativa neste veículo.']);
        exit;
    }
    $destinatarioId = (int) $veiculo['condutor_id'];
} else {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão para comunicar neste canal.']);
    exit;
}

$pdo->prepare(
    "INSERT INTO comunicacoes_veiculo (veiculo_id, remetente_id, destinatario_id, mensagem, tipo) VALUES (?, ?, ?, ?, 'texto')"
)->execute([$veiculoId, $utilizador['id'], $destinatarioId, $mensagem]);

echo json_encode(['sucesso' => true, 'mensagem_id' => (int) $pdo->lastInsertId()]);
