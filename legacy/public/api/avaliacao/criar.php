<?php
/**
 * V-MILLION — API: passageiro avalia o condutor após a viagem (secção 9).
 * Uma avaliação por reserva (UNIQUE em avaliacoes_condutores.reserva_id).
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
$avaliacao = filter_input(INPUT_POST, 'avaliacao', FILTER_VALIDATE_INT);
$comentario = trim((string) ($_POST['comentario'] ?? ''));

if (!$reservaId || !$avaliacao || $avaliacao < 1 || $avaliacao > 5) {
    http_response_code(422);
    echo json_encode(['erro' => 'Avaliação inválida (1 a 5).']);
    exit;
}

$pdo = kg_db();

$stmt = $pdo->prepare(
    "SELECT r.id, v.condutor_id FROM reservas r
     JOIN veiculos v ON v.id = r.veiculo_id
     WHERE r.id = ? AND r.passageiro_id = ? AND r.estado = 'concluido'"
);
$stmt->execute([$reservaId, $passageiro['id']]);
$reserva = $stmt->fetch();

if (!$reserva) {
    http_response_code(404);
    echo json_encode(['erro' => 'Reserva não encontrada ou ainda não concluída.']);
    exit;
}

try {
    $pdo->prepare(
        "INSERT INTO avaliacoes_condutores (condutor_id, passageiro_id, reserva_id, avaliacao, comentario) VALUES (?, ?, ?, ?, ?)"
    )->execute([$reserva['condutor_id'], $passageiro['id'], $reservaId, $avaliacao, $comentario ?: null]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'uq_avaliacao_reserva')) {
        http_response_code(409);
        echo json_encode(['erro' => 'Esta viagem já foi avaliada.']);
        exit;
    }
    throw $e;
}

// Avaliação baixa com comentário = reclamação (secção "avaliações e
// reclamações visíveis para administradores") — reaproveita a secção
// "Sugestões e reclamações" já existente no admin, em vez de duplicar uma
// segunda caixa de entrada só para isto.
if ($avaliacao <= 2 && $comentario !== '') {
    try {
        $pdo->prepare(
            "INSERT INTO sugestoes (utilizador_id, tipo, titulo, descricao, condutor_id) VALUES (?, 'reclamacao', ?, ?, ?)"
        )->execute([
            $passageiro['id'],
            "Avaliação {$avaliacao}/5 numa viagem",
            $comentario,
            $reserva['condutor_id'],
        ]);
    } catch (Throwable $e) {
        error_log('[V-MILLION] Falha ao gerar reclamação a partir de avaliação: ' . $e->getMessage());
    }
}

echo json_encode(['sucesso' => true]);
