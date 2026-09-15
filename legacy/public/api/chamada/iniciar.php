<?php
/**
 * V-MILLION — API: iniciar chamada simulada (passageiro <-> condutor).
 * Fica persistida em BD para o destinatário a apanhar por polling
 * (api/chamada/verificar.php) mesmo que o socket.io não esteja a correr —
 * ver database/migration_20260810_chamadas.sql.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/chamadas.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$utilizador = kg_exigir_utilizador();

$destinatarioId = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
if (!$destinatarioId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o destinatário.']);
    exit;
}

$pdo = kg_db();
kg_expirar_chamadas_paradas($pdo);

if (!kg_pode_chamar($pdo, $utilizador, $destinatarioId)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem relação ativa com este utilizador.']);
    exit;
}

// Ocupado: já há uma chamada em curso (minha ou do destinatário) que ainda
// não terminou — a app comporta-se como uma central telefónica, não deixa
// duas chamadas simultâneas para a mesma pessoa.
$ocupado = $pdo->prepare(
    "SELECT 1 FROM chamadas
     WHERE estado IN ('iniciada', 'atendida')
       AND (remetente_id IN (?, ?) OR destinatario_id IN (?, ?))
     LIMIT 1"
);
$ocupado->execute([$utilizador['id'], $destinatarioId, $utilizador['id'], $destinatarioId]);
if ($ocupado->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['erro' => 'ocupado']);
    exit;
}

$pdo->prepare(
    "INSERT INTO chamadas (remetente_id, destinatario_id, estado) VALUES (?, ?, 'iniciada')"
)->execute([$utilizador['id'], $destinatarioId]);

echo json_encode(['sucesso' => true, 'chamada_id' => (int) $pdo->lastInsertId()]);
