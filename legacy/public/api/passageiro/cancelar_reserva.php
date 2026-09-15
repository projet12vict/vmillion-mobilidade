<?php
/**
 * V-MILLION — API: o passageiro sai da sua reserva/fila atual (secção 8/13).
 * Nenhum passageiro fica "refém" de um condutor: enquanto a reserva ainda
 * não embarcou, pode cancelar e escolher outro veículo.
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
$passageiro = kg_exigir_utilizador('passageiro');

$reservaId = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
if (!$reservaId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Reserva inválida.']);
    exit;
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    // LEFT JOIN: um pedido de viagem urbana em aberto (veiculo_id NULL,
    // nenhum condutor o reclamou ainda) também tem de poder ser cancelado.
    $stmt = $pdo->prepare(
        "SELECT r.*, v.lugares_livres, v.lugares_total FROM reservas r
         LEFT JOIN veiculos v ON v.id = r.veiculo_id
         WHERE r.id = ? AND r.passageiro_id = ? FOR UPDATE"
    );
    $stmt->execute([$reservaId, $passageiro['id']]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        throw new RuntimeException('Reserva não encontrada.', 404);
    }
    // Regra explícita: só pode sair enquanto pendente/confirmado — depois de
    // embarcar (a_bordo) já está no veículo, sair não faz sentido no mapa.
    if (!in_array($reserva['estado'], ['pendente', 'confirmado'], true)) {
        throw new RuntimeException('Já embarcou nesta viagem — não é possível sair agora.', 409);
    }

    $pdo->prepare("UPDATE reservas SET estado = 'recusado' WHERE id = ?")->execute([$reservaId]);

    $novoLugaresLivres = null;
    if ($reserva['veiculo_id']) {
        $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE reserva_id = ?")->execute([$reservaId]);
        $pdo->prepare("UPDATE veiculos SET lugares_livres = LEAST(lugares_total, lugares_livres + 1) WHERE id = ?")->execute([$reserva['veiculo_id']]);
        $novoLugaresLivres = min((int) $reserva['lugares_total'], (int) $reserva['lugares_livres'] + 1);
    }

    $pdo->commit();
    echo json_encode([
        'sucesso' => true,
        'veiculo_id' => $reserva['veiculo_id'] ? (int) $reserva['veiculo_id'] : null,
        'lugares_livres' => $novoLugaresLivres,
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
}
