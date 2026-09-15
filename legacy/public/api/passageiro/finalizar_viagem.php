<?php
/**
 * V-MILLION — API: o passageiro finaliza a própria viagem quando o condutor
 * não clicou em "Entregue" (secção "passageiro pode finalizar se o condutor
 * não o fizer"). Mesma lógica de conclusão que api/condutor/reserva_estado.php
 * (acao=chegou) — o primeiro dos dois lados a agir é que vale; depois de
 * concluída, a mesma reserva não pode ser processada outra vez por nenhum
 * dos dois (o estado já não é 'a_bordo').
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
    $stmt = $pdo->prepare(
        "SELECT r.*, v.lugares_livres, v.lugares_total FROM reservas r
         JOIN veiculos v ON v.id = r.veiculo_id
         WHERE r.id = ? AND r.passageiro_id = ? FOR UPDATE"
    );
    $stmt->execute([$reservaId, $passageiro['id']]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        throw new RuntimeException('Reserva não encontrada.', 404);
    }
    if ($reserva['estado'] !== 'a_bordo') {
        throw new RuntimeException('Só pode finalizar depois de embarcar.', 409);
    }

    $pdo->prepare("UPDATE reservas SET estado = 'concluido' WHERE id = ?")->execute([$reservaId]);
    $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE reserva_id = ?")->execute([$reservaId]);
    $pdo->prepare("UPDATE veiculos SET lugares_livres = LEAST(lugares_total, lugares_livres + 1) WHERE id = ?")->execute([$reserva['veiculo_id']]);
    $novoLugaresLivres = min((int) $reserva['lugares_total'], (int) $reserva['lugares_livres'] + 1);

    $pdo->commit();
    echo json_encode([
        'sucesso' => true,
        'veiculo_id' => (int) $reserva['veiculo_id'],
        'lugares_livres' => $novoLugaresLivres,
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
}
