<?php
/**
 * V-MILLION — API: envio de sugestões (qualquer utilizador, visível só ao
 * Super Admin) e reclamações sobre condutores (só passageiros, visível aos
 * administradores), secção 10.
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

$tipo = in_array($_POST['tipo'] ?? '', ['sugestao', 'reclamacao'], true) ? $_POST['tipo'] : null;
$titulo = trim((string) ($_POST['titulo'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? ''));
$condutorId = filter_input(INPUT_POST, 'condutor_id', FILTER_VALIDATE_INT) ?: null;

if (!$tipo || $titulo === '' || $descricao === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Preencha o título e a descrição.']);
    exit;
}

if ($tipo === 'reclamacao') {
    if ($utilizador['tipo'] !== 'passageiro') {
        http_response_code(403);
        echo json_encode(['erro' => 'Só passageiros podem submeter reclamações sobre condutores.']);
        exit;
    }
    if (!$condutorId) {
        http_response_code(422);
        echo json_encode(['erro' => 'Indique o condutor a que se refere a reclamação.']);
        exit;
    }
    $pdo = kg_db();
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ? AND tipo = 'condutor'");
    $stmt->execute([$condutorId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['erro' => 'Condutor não encontrado.']);
        exit;
    }
} else {
    $condutorId = null;
}

$pdo = kg_db();
$pdo->prepare(
    "INSERT INTO sugestoes (utilizador_id, tipo, titulo, descricao, condutor_id) VALUES (?, ?, ?, ?, ?)"
)->execute([$utilizador['id'], $tipo, $titulo, $descricao, $condutorId]);

echo json_encode(['sucesso' => true]);
