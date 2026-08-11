<?php
/**
 * V-MILLION — API: um condutor reclama um pedido de viagem urbana em aberto
 * (secção "condutor pode ir buscar o passageiro"). A partir daqui a reserva
 * passa a seguir exactamente o mesmo fluxo de sempre — confirmar/embarcar/
 * chat/WhatsApp (api/condutor/passageiros.php, reserva_estado.php) — sem
 * mais nenhuma lógica nova. Protegido contra dois condutores reclamarem o
 * mesmo pedido ao mesmo tempo (FOR UPDATE + verificação do estado dentro da
 * transação).
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
$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$reservaId || !$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.']);
    exit;
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    $veiculoStmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? AND condutor_id = ? FOR UPDATE");
    $veiculoStmt->execute([$veiculoId, $condutor['id']]);
    $veiculo = $veiculoStmt->fetch();

    if (!$veiculo || !$veiculo['aprovado']) {
        throw new RuntimeException('Veículo não encontrado ou não aprovado.', 404);
    }
    if ($veiculo['lugares_livres'] < 1) {
        throw new RuntimeException('Este veículo já não tem lugares disponíveis.', 409);
    }

    $reservaStmt = $pdo->prepare(
        "SELECT r.*, u.nome AS passageiro_nome FROM reservas r
         JOIN utilizadores u ON u.id = r.passageiro_id
         WHERE r.id = ? FOR UPDATE"
    );
    $reservaStmt->execute([$reservaId]);
    $reserva = $reservaStmt->fetch();

    if (!$reserva || $reserva['tipo_viagem'] !== 'urbano') {
        throw new RuntimeException('Pedido não encontrado.', 404);
    }
    if ($reserva['veiculo_id'] !== null || $reserva['estado'] !== 'pendente') {
        throw new RuntimeException('Este pedido já foi reclamado por outro condutor.', 409);
    }

    $assentoStmt = $pdo->prepare("SELECT id FROM assentos_veiculo WHERE veiculo_id = ? AND ocupado = 0 LIMIT 1 FOR UPDATE");
    $assentoStmt->execute([$veiculoId]);
    $assento = $assentoStmt->fetch();
    if (!$assento) {
        throw new RuntimeException('Este veículo já não tem lugares disponíveis.', 409);
    }

    $pdo->prepare("UPDATE reservas SET veiculo_id = ?, assento_id = ?, estado = 'confirmado' WHERE id = ?")
        ->execute([$veiculoId, $assento['id'], $reservaId]);
    $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 1, reserva_id = ? WHERE id = ?")
        ->execute([$reservaId, $assento['id']]);
    $pdo->prepare("UPDATE veiculos SET lugares_livres = lugares_livres - 1 WHERE id = ?")
        ->execute([$veiculoId]);

    // Avisa o passageiro por uma mensagem de sistema no autofalante — já
    // existe polling/realtime para isto, não precisa de canal novo.
    $pdo->prepare(
        "INSERT INTO comunicacoes_veiculo (veiculo_id, remetente_id, destinatario_id, mensagem, tipo)
         VALUES (?, ?, ?, ?, 'sistema')"
    )->execute([
        $veiculoId, $condutor['id'], $reserva['passageiro_id'],
        "🚗 {$condutor['nome']} está a caminho para o(a) buscar.",
    ]);

    kg_log_auditoria($pdo, null, 'condutor_recolheu_urbano', 'reservas', $reservaId, "veiculo #{$veiculoId}");

    $pdo->commit();
    echo json_encode([
        'sucesso' => true,
        'passageiro_id' => (int) $reserva['passageiro_id'],
        'passageiro_nome' => $reserva['passageiro_nome'],
        'passageiro_lat' => $reserva['passageiro_lat'] !== null ? (float) $reserva['passageiro_lat'] : null,
        'passageiro_lng' => $reserva['passageiro_lng'] !== null ? (float) $reserva['passageiro_lng'] : null,
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível reclamar este pedido.']);
}
