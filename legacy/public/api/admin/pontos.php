<?php
/**
 * V-MILLION — API: CRUD e fluxo de aprovação de pontos de partida
 * (secção 6.1, 6.3, 11.4). Um ponto novo entra sempre 'pendente' e só fica
 * visível ao público (passageiros/condutores) depois de um admin o aprovar
 * — ver api/passageiro/pontos.php, que filtra status = 'aprovado'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $status = in_array($_GET['status'] ?? '', ['pendente', 'aprovado', 'recusado'], true) ? $_GET['status'] : null;
    $sql = "SELECT p.*, c.nome AS criado_por_nome, a.nome AS aprovado_por_nome
            FROM pontos_partida p
            LEFT JOIN administradores c ON c.id = p.criado_por
            LEFT JOIN administradores a ON a.id = p.aprovado_por";
    $params = [];
    if ($status) {
        $sql .= " WHERE p.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY (p.status = 'pendente') DESC, p.nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['pontos' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'criar' || $acao === 'editar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
    $zona = in_array($_POST['zona'] ?? '', ['urbana', 'intermunicipal'], true) ? $_POST['zona'] : 'urbana';

    if ($nome === '' || $cidade === '' || $lat === null || $lng === null || $lat === false || $lng === false) {
        http_response_code(422);
        echo json_encode(['erro' => 'Preencha nome, cidade e coordenadas válidas.']);
        exit;
    }
    kg_exigir_coordenadas_validas($lat, $lng);

    if ($acao === 'criar') {
        $pdo->prepare("INSERT INTO pontos_partida (nome, cidade, lat, lng, zona, status, criado_por) VALUES (?, ?, ?, ?, ?, 'pendente', ?)")
            ->execute([$nome, $cidade, $lat, $lng, $zona, $admin['id']]);
        $novoId = (int) $pdo->lastInsertId();
        kg_log_auditoria($pdo, $admin['id'], 'criou_ponto', 'pontos_partida', $novoId, $nome);
        kg_notificar_admins_ponto_pendente($pdo, $admin, $novoId, $nome, $cidade);
        echo json_encode(['sucesso' => true, 'id' => $novoId]);
    } else {
        if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
        $pdo->prepare("UPDATE pontos_partida SET nome=?, cidade=?, lat=?, lng=?, zona=? WHERE id=?")
            ->execute([$nome, $cidade, $lat, $lng, $zona, $id]);
        kg_log_auditoria($pdo, $admin['id'], 'editou_ponto', 'pontos_partida', $id, $nome);
        echo json_encode(['sucesso' => true]);
    }
    exit;
}

// Arrastar o marcador no editor de mapa: guarda só a nova posição, sem
// exigir os restantes campos (nome/cidade/zona ficam como estavam).
if ($acao === 'mover') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
    if (!$id || $lat === null || $lng === null || $lat === false || $lng === false) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados de posição inválidos.']);
        exit;
    }
    kg_exigir_coordenadas_validas($lat, $lng);

    $pdo->prepare("UPDATE pontos_partida SET lat = ?, lng = ? WHERE id = ?")->execute([$lat, $lng, $id]);
    kg_log_auditoria($pdo, $admin['id'], 'moveu_ponto', 'pontos_partida', $id, "{$lat}, {$lng}");
    echo json_encode(['sucesso' => true, 'lat' => $lat, 'lng' => $lng]);
    exit;
}

if (in_array($acao, ['aprovar', 'recusar'], true)) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }

    if ($acao === 'aprovar') {
        // Aprovar também reativa o ponto (caso tivesse sido desativado antes
        // deste fluxo existir) — status passa a ser a única fonte de verdade
        // sobre visibilidade pública.
        $pdo->prepare("UPDATE pontos_partida SET status = 'aprovado', ativo = 1, aprovado_por = ?, aprovado_em = NOW() WHERE id = ?")
            ->execute([$admin['id'], $id]);
        kg_log_auditoria($pdo, $admin['id'], 'aprovou_ponto', 'pontos_partida', $id);
    } else {
        $pdo->prepare("UPDATE pontos_partida SET status = 'recusado', aprovado_por = ?, aprovado_em = NOW() WHERE id = ?")
            ->execute([$admin['id'], $id]);
        kg_log_auditoria($pdo, $admin['id'], 'recusou_ponto', 'pontos_partida', $id);
    }
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'eliminar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("UPDATE pontos_partida SET ativo = 0 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'desativou_ponto', 'pontos_partida', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

// Eliminação definitiva e irreversível (editor de mapa, tarefa 3). Restrita
// ao Super Admin — é a única ação desta API que apaga uma linha em vez de
// mudar um estado. Só é permitida se o ponto não tiver reservas associadas
// (a FK é RESTRICT nessa tabela e rebentaria com uma exceção não tratada);
// veículos e preços de rota que ainda apontem para o ponto são também
// bloqueados, para o admin usar "Recusar" nesses casos em vez de perder
// referências silenciosamente.
if ($acao === 'eliminar_definitivo') {
    kg_exigir_admin('super');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }

    $ponto = $pdo->prepare("SELECT nome FROM pontos_partida WHERE id = ?");
    $ponto->execute([$id]);
    $ponto = $ponto->fetch();
    if (!$ponto) { http_response_code(404); echo json_encode(['erro' => 'Ponto não encontrado.']); exit; }

    $reservas = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE ponto_partida_id = ? OR destino_id = ?");
    $reservas->execute([$id, $id]);
    if ((int) $reservas->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['erro' => 'Este ponto tem reservas associadas e não pode ser eliminado — use "Recusar" para o esconder do público.']);
        exit;
    }

    $veiculos = $pdo->prepare("SELECT COUNT(*) FROM veiculos WHERE ponto_partida_id = ? OR destino_id = ?");
    $veiculos->execute([$id, $id]);
    if ((int) $veiculos->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['erro' => 'Há veículos atualmente associados a este ponto — mude-os de ponto ou use "Recusar" em vez de eliminar.']);
        exit;
    }

    $pdo->prepare("DELETE FROM pontos_partida WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'eliminou_ponto', 'pontos_partida', $id, $ponto['nome']);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);

// Avisa os restantes admins ativos de que há um novo ponto por rever
// (secção 6, tarefa 6). Nunca deixa a criação do ponto falhar por causa
// disto — uma notificação gorada não pode bloquear a operação principal.
function kg_notificar_admins_ponto_pendente(PDO $pdo, array $criador, int $pontoId, string $nome, string $cidade): void
{
    try {
        $outros = $pdo->prepare("SELECT id FROM administradores WHERE ativo = 1 AND id != ?");
        $outros->execute([$criador['id']]);
        $alvos = $outros->fetchAll(PDO::FETCH_COLUMN);
        if (!$alvos) {
            return;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO notificacoes (destinatario_id, destinatario_tipo, remetente_id, titulo, mensagem, tipo) VALUES (?, 'admins', ?, ?, ?, 'informativo')"
        );
        foreach ($alvos as $alvoId) {
            $stmt->execute([$alvoId, $criador['id'], 'Novo ponto pendente', "{$nome} ({$cidade}) aguarda aprovação — ponto #{$pontoId}."]);
        }
    } catch (Throwable $e) {
        error_log('[V-MILLION] Falha ao notificar admins sobre ponto pendente: ' . $e->getMessage());
    }
}
