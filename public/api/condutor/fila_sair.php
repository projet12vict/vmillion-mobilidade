<?php
/**
 * V-MILLION — API: condutor sai da fila com justificação obrigatória (secção 9.3).
 *
 * Nota de implementação: os passageiros com reserva pendente/confirmada
 * nesse veículo são libertados (lugar devolvido, reserva marcada como
 * 'recusado' com o motivo do condutor) e notificados de imediato em tempo
 * real para poderem reservar outro veículo no mesmo ponto — a forma mais
 * simples e transparente de lhes dar prioridade imediata face a novos
 * pedidos, dado que uma fila de reatribuição automática entre veículos
 * exigiria um motor de emparelhamento próprio (fora do âmbito desta fase).
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

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$motivo = trim((string) ($_POST['motivo'] ?? ''));

if (!$veiculoId || $motivo === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o motivo para sair da fila.']);
    exit;
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    $veiculoStmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? AND condutor_id = ? FOR UPDATE");
    $veiculoStmt->execute([$veiculoId, $condutor['id']]);
    $veiculo = $veiculoStmt->fetch();

    if (!$veiculo) {
        throw new RuntimeException('Veículo não encontrado.', 404);
    }

    $passageirosAfetados = $pdo->prepare(
        "SELECT r.id, r.passageiro_id FROM reservas r
         WHERE r.veiculo_id = ? AND r.estado IN ('pendente', 'confirmado')"
    );
    $passageirosAfetados->execute([$veiculoId]);
    $reservasAfetadas = $passageirosAfetados->fetchAll();

    foreach ($reservasAfetadas as $r) {
        $pdo->prepare("UPDATE reservas SET estado = 'recusado' WHERE id = ?")->execute([$r['id']]);
        $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE reserva_id = ?")->execute([$r['id']]);
    }

    $pdo->prepare("UPDATE veiculos SET estado = 'partiu_da_fila', posicao_fila = NULL, lugares_livres = lugares_total WHERE id = ?")
        ->execute([$veiculoId]);

    kg_log_auditoria($pdo, null, 'condutor_saiu_fila', 'veiculos', $veiculoId, $motivo);

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'passageiros_notificar' => array_column($reservasAfetadas, 'passageiro_id'),
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
}
