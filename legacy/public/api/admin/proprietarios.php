<?php
/**
 * V-MILLION — API: gestão de proprietários de frota (secção 8). Um proprietário
 * pode também conduzir o seu próprio veículo — nesse caso é associado a uma
 * conta de condutor existente via utilizador_condutor_id.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proprietarios = $pdo->query(
        "SELECT p.id, p.nome, p.telefone, p.nif, p.utilizador_condutor_id, u.nome AS condutor_nome,
                (SELECT COUNT(*) FROM utilizadores c WHERE c.proprietario_id = p.id) AS total_condutores
         FROM proprietarios p
         LEFT JOIN utilizadores u ON u.id = p.utilizador_condutor_id
         ORDER BY p.nome"
    )->fetchAll();
    echo json_encode(['proprietarios' => $proprietarios]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if (in_array($acao, ['criar', 'editar'], true)) {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $nif = trim((string) ($_POST['nif'] ?? ''));
    $utilizadorCondutorId = filter_input(INPUT_POST, 'utilizador_condutor_id', FILTER_VALIDATE_INT) ?: null;

    $erros = [];
    if (mb_strlen($nome) < 3) $erros['nome'] = 'Indique o nome completo.';
    if (!kg_validar_telefone($telefone)) $erros['telefone'] = 'Telefone inválido.';
    if (!kg_validar_nif($nif)) $erros['nif'] = 'NIF inválido.';

    if ($utilizadorCondutorId) {
        $existe = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ? AND tipo = 'condutor'");
        $existe->execute([$utilizadorCondutorId]);
        if (!$existe->fetch()) $erros['utilizador_condutor_id'] = 'Condutor não encontrado.';
    }

    if (!empty($erros)) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados inválidos.', 'campos' => $erros]);
        exit;
    }

    $telefoneNormalizado = kg_normalizar_telefone($telefone);

    if ($acao === 'criar') {
        $dup = $pdo->prepare("SELECT id FROM proprietarios WHERE telefone = ? OR nif = ?");
        $dup->execute([$telefoneNormalizado, $nif]);
        if ($dup->fetch()) {
            http_response_code(409);
            echo json_encode(['erro' => 'Já existe um proprietário com este telefone ou NIF.']);
            exit;
        }
        $pdo->prepare(
            "INSERT INTO proprietarios (nome, telefone, nif, utilizador_condutor_id) VALUES (?, ?, ?, ?)"
        )->execute([$nome, $telefoneNormalizado, $nif, $utilizadorCondutorId]);
        $novoId = (int) $pdo->lastInsertId();
        kg_log_auditoria($pdo, $admin['id'], 'criou_proprietario', 'proprietarios', $novoId, $nome);
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
        $pdo->prepare(
            "UPDATE proprietarios SET nome = ?, telefone = ?, nif = ?, utilizador_condutor_id = ? WHERE id = ?"
        )->execute([$nome, $telefoneNormalizado, $nif, $utilizadorCondutorId, $id]);
        kg_log_auditoria($pdo, $admin['id'], 'editou_proprietario', 'proprietarios', $id, $nome);
    }

    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'associar_condutor') {
    // Liga um condutor existente a um proprietário (frota de terceiros — secção 8).
    $condutorId = filter_input(INPUT_POST, 'condutor_id', FILTER_VALIDATE_INT);
    $proprietarioId = filter_input(INPUT_POST, 'proprietario_id', FILTER_VALIDATE_INT) ?: null;
    if (!$condutorId) { http_response_code(422); echo json_encode(['erro' => 'Indique o condutor.']); exit; }

    $pdo->prepare("UPDATE utilizadores SET proprietario_id = ? WHERE id = ? AND tipo = 'condutor'")
        ->execute([$proprietarioId, $condutorId]);
    kg_log_auditoria($pdo, $admin['id'], 'associou_condutor_proprietario', 'utilizadores', $condutorId, (string) $proprietarioId);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'eliminar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("DELETE FROM proprietarios WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'eliminou_proprietario', 'proprietarios', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
