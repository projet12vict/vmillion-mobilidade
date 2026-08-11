<?php
/**
 * V-MILLION — API: CRUD de parques de estacionamento (secção 6.2, 11.4).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['parques' => $pdo->query("SELECT * FROM parques ORDER BY nome")->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'criar' || $acao === 'editar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $morada = trim((string) ($_POST['morada'] ?? ''));
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
    $capacidade = filter_input(INPUT_POST, 'capacidade_total', FILTER_VALIDATE_INT);
    $ocupadas = filter_input(INPUT_POST, 'vagas_ocupadas', FILTER_VALIDATE_INT) ?: 0;

    if ($nome === '' || $morada === '' || $cidade === '' || $lat === null || $lng === null || !$capacidade || $lat === false || $lng === false) {
        http_response_code(422);
        echo json_encode(['erro' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }
    kg_exigir_coordenadas_validas($lat, $lng);
    if ($ocupadas > $capacidade) {
        http_response_code(422);
        echo json_encode(['erro' => 'Vagas ocupadas não pode exceder a capacidade total.']);
        exit;
    }

    if ($acao === 'criar') {
        $pdo->prepare(
            "INSERT INTO parques (nome, morada, cidade, lat, lng, capacidade_total, vagas_ocupadas, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$nome, $morada, $cidade, $lat, $lng, $capacidade, $ocupadas, $admin['id']]);
        $novoId = (int) $pdo->lastInsertId();
        kg_log_auditoria($pdo, $admin['id'], 'criou_parque', 'parques', $novoId, $nome);
        echo json_encode(['sucesso' => true, 'id' => $novoId]);
    } else {
        if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
        $pdo->prepare(
            "UPDATE parques SET nome=?, morada=?, cidade=?, lat=?, lng=?, capacidade_total=?, vagas_ocupadas=? WHERE id=?"
        )->execute([$nome, $morada, $cidade, $lat, $lng, $capacidade, $ocupadas, $id]);
        kg_log_auditoria($pdo, $admin['id'], 'editou_parque', 'parques', $id, $nome);
        echo json_encode(['sucesso' => true]);
    }
    exit;
}

if ($acao === 'eliminar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("DELETE FROM parques WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'eliminou_parque', 'parques', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
