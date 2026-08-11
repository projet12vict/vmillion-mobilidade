<?php
/**
 * V-MILLION — API: gestão de utilizadores (passageiros e condutores, secção 11.3).
 * Inclui aprovação de condutores (mediante pagamento aprovado e válido em
 * pagamentos_condutores — normalmente já acontece automaticamente ao aprovar
 * o pagamento em api/admin/pagamentos.php; esta ação serve para casos manuais).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tipo = $_GET['tipo'] ?? null;
    $sql = "SELECT id, tipo, nome, telefone, nif, email, status, criado_em FROM utilizadores";
    $params = [];
    if (in_array($tipo, ['passageiro', 'condutor'], true)) {
        $sql .= " WHERE tipo = ?";
        $params[] = $tipo;
    }
    $sql .= " ORDER BY criado_em DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['utilizadores' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(422);
    echo json_encode(['erro' => 'Utilizador inválido.']);
    exit;
}

switch ($acao) {
    case 'aprovar_condutor':
        // Fonte única de verdade para "o condutor pagou": pagamentos_condutores
        // (mesma tabela que o condutor alimenta em /api/condutor/pagamentos.php
        // e que o admin já aprova em /api/admin/pagamentos.php). A tabela
        // comprovativos deixou de ser usada aqui — ver includes/veiculos.php,
        // kg_condutor_pode_ver_mapa(), que já seguia esta mesma fonte.
        $pagamentoOk = $pdo->prepare("SELECT id FROM pagamentos_condutores WHERE condutor_id = ? AND status = 'aprovado' AND data_validade >= NOW() LIMIT 1");
        $pagamentoOk->execute([$id]);
        if (!$pagamentoOk->fetch()) {
            http_response_code(422);
            echo json_encode(['erro' => 'É necessário um pagamento aprovado e válido antes de aprovar o condutor.']);
            exit;
        }
        $pdo->prepare("UPDATE utilizadores SET status = 'ativo' WHERE id = ? AND tipo = 'condutor'")->execute([$id]);
        kg_log_auditoria($pdo, $admin['id'], 'aprovou_condutor', 'utilizadores', $id);
        echo json_encode(['sucesso' => true]);
        break;

    case 'suspender':
        // Banir um condutor é a "sentença final" (secção J do relatório) —
        // só o Super Admin decide. Passageiros ficam com a regra anterior
        // (qualquer admin), por serem uma ação menos grave/irreversível.
        $tipoAlvo = $pdo->prepare("SELECT tipo FROM utilizadores WHERE id = ?");
        $tipoAlvo->execute([$id]);
        if ($tipoAlvo->fetchColumn() === 'condutor') {
            kg_exigir_admin('super');
        }
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $pdo->prepare("UPDATE utilizadores SET status = 'suspenso' WHERE id = ?")->execute([$id]);
        kg_log_auditoria($pdo, $admin['id'], 'suspendeu_utilizador', 'utilizadores', $id, $motivo ?: null);
        echo json_encode(['sucesso' => true]);
        break;

    case 'reativar':
        $tipoAlvoReativar = $pdo->prepare("SELECT tipo FROM utilizadores WHERE id = ?");
        $tipoAlvoReativar->execute([$id]);
        if ($tipoAlvoReativar->fetchColumn() === 'condutor') {
            kg_exigir_admin('super');
        }
        $pdo->prepare("UPDATE utilizadores SET status = 'ativo' WHERE id = ?")->execute([$id]);
        kg_log_auditoria($pdo, $admin['id'], 'reativou_utilizador', 'utilizadores', $id);
        echo json_encode(['sucesso' => true]);
        break;

    case 'eliminar':
        $pdo->prepare("DELETE FROM utilizadores WHERE id = ?")->execute([$id]);
        kg_log_auditoria($pdo, $admin['id'], 'eliminou_utilizador', 'utilizadores', $id);
        echo json_encode(['sucesso' => true]);
        break;

    default:
        http_response_code(422);
        echo json_encode(['erro' => 'Ação inválida.']);
}
