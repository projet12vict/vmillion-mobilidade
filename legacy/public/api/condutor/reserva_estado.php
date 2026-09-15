<?php
/**
 * V-MILLION — API: condutor confirma ou recusa uma reserva (secção 9.4).
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
$condutor = kg_exigir_utilizador('condutor');

$reservaId = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$acao = (string) ($_POST['acao'] ?? '');

if (!$reservaId || !in_array($acao, ['confirmar', 'recusar', 'embarcar', 'chegou'], true)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "SELECT r.*, v.condutor_id, v.lugares_livres, v.lugares_total FROM reservas r
         JOIN veiculos v ON v.id = r.veiculo_id
         WHERE r.id = ? FOR UPDATE"
    );
    $stmt->execute([$reservaId]);
    $reserva = $stmt->fetch();

    if (!$reserva || (int) $reserva['condutor_id'] !== (int) $condutor['id']) {
        throw new RuntimeException('Reserva não encontrada.', 404);
    }

    $novoLugaresLivres = null;

    if (in_array($acao, ['confirmar', 'recusar'], true)) {
        if ($reserva['estado'] !== 'pendente') {
            throw new RuntimeException('Esta reserva já foi processada.', 409);
        }
        if ($acao === 'confirmar') {
            $pdo->prepare("UPDATE reservas SET estado = 'confirmado' WHERE id = ?")->execute([$reservaId]);
        } else {
            $pdo->prepare("UPDATE reservas SET estado = 'recusado' WHERE id = ?")->execute([$reservaId]);
            $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE reserva_id = ?")->execute([$reservaId]);
            $pdo->prepare("UPDATE veiculos SET lugares_livres = lugares_livres + 1 WHERE id = ?")->execute([$reserva['veiculo_id']]);
            $novoLugaresLivres = (int) $reserva['lugares_livres'] + 1;
        }
    } elseif ($acao === 'embarcar') {
        if ($reserva['estado'] !== 'confirmado') {
            throw new RuntimeException('O passageiro só pode embarcar depois de confirmado.', 409);
        }
        $pdo->prepare("UPDATE reservas SET estado = 'a_bordo' WHERE id = ?")->execute([$reservaId]);
    } else { // chegou — o passageiro desce (no destino final ou a meio da rota)
        if ($reserva['estado'] !== 'a_bordo') {
            throw new RuntimeException('O passageiro precisa de estar a bordo para marcar a chegada.', 409);
        }
        $pdo->prepare("UPDATE reservas SET estado = 'concluido' WHERE id = ?")->execute([$reservaId]);
        $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE reserva_id = ?")->execute([$reservaId]);
        $pdo->prepare("UPDATE veiculos SET lugares_livres = LEAST(lugares_total, lugares_livres + 1) WHERE id = ?")->execute([$reserva['veiculo_id']]);
        $novoLugaresLivres = min((int) $reserva['lugares_total'], (int) $reserva['lugares_livres'] + 1);
    }

    $pdo->commit();
    echo json_encode([
        'sucesso' => true,
        'passageiro_id' => (int) $reserva['passageiro_id'],
        'veiculo_id' => (int) $reserva['veiculo_id'],
        'lugares_livres' => $novoLugaresLivres,
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
}
