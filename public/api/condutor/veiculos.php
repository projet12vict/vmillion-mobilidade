<?php
/**
 * V-MILLION — API: listar/criar veículos do condutor autenticado (secção 9.1, 7.1).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/veiculos.php';

header('Content-Type: application/json; charset=utf-8');
$condutor = kg_exigir_utilizador('condutor');
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Contagem de passageiros pendentes por veículo (relatório "pedidos
    // intermunicipais invisíveis"): um condutor com mais do que um veículo
    // aprovado só via passageiros do veículo que tem selecionado como ativo
    // — sem esta contagem, um pedido preso noutro veículo seu ficava
    // invisível sem qualquer pista de que existia.
    $stmt = $pdo->prepare(
        "SELECT v.id, v.matricula, v.tipo, v.tipo_servico, v.cor, v.modelo, v.lugares_total, v.lugares_livres, v.estado, v.aprovado,
                v.ponto_partida_id, v.destino_id, v.rota_fixa_id, v.posicao_fila, v.lat, v.lng,
                (SELECT COUNT(*) FROM reservas r WHERE r.veiculo_id = v.id AND r.estado = 'pendente') AS passageiros_pendentes
         FROM veiculos v WHERE v.condutor_id = ? ORDER BY v.criado_em DESC"
    );
    $stmt->execute([$condutor['id']]);
    echo json_encode(['veiculos' => $stmt->fetchAll()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();

$matricula = kg_normalizar_matricula((string) ($_POST['matricula'] ?? ''));
$tipo = (string) ($_POST['tipo'] ?? '');
$tipoServicoBruto = (string) ($_POST['tipo_servico'] ?? 'ambos');
$tipoServico = in_array($tipoServicoBruto, ['urbano', 'intermunicipal', 'ambos'], true) ? $tipoServicoBruto : 'ambos';
$rotaFixaId = filter_input(INPUT_POST, 'rota_fixa_id', FILTER_VALIDATE_INT) ?: null;
$cor = trim((string) ($_POST['cor'] ?? ''));
$modelo = trim((string) ($_POST['modelo'] ?? ''));

$erros = [];
if ($matricula === '') $erros['matricula'] = 'Indique a matrícula.';
if (!in_array($tipo, ['hiace', 'taxi', 'autocarro'], true)) $erros['tipo'] = 'Tipo inválido.';
if ($cor === '') $erros['cor'] = 'Indique a cor.';
if ($modelo === '') $erros['modelo'] = 'Indique o modelo.';

if ($rotaFixaId !== null) {
    $rotaExiste = $pdo->prepare("SELECT id FROM precos_rotas WHERE id = ?");
    $rotaExiste->execute([$rotaFixaId]);
    if (!$rotaExiste->fetch()) {
        $erros['rota_fixa_id'] = 'Rota fixa não encontrada.';
    }
}

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados inválidos.', 'campos' => $erros]);
    exit;
}

$dup = $pdo->prepare("SELECT id FROM veiculos WHERE matricula = ?");
$dup->execute([$matricula]);
if ($dup->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Já existe um veículo com esta matrícula.']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO veiculos (condutor_id, matricula, tipo, tipo_servico, cor, modelo, lugares_total, lugares_livres, estado, aprovado, rota_fixa_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'no_ponto', 0, ?)"
    );
    $stmt->execute([$condutor['id'], $matricula, $tipo, $tipoServico, $cor, $modelo, KG_LUGARES_TOTAL, KG_LUGARES_TOTAL, $rotaFixaId]);
    $veiculoId = (int) $pdo->lastInsertId();
    kg_criar_assentos_veiculo($pdo, $veiculoId);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível registar o veículo.']);
    exit;
}

echo json_encode([
    'sucesso' => true,
    'veiculo_id' => $veiculoId,
    'mensagem' => 'Veículo registado. Fica ativo após aprovação do administrador (mediante comprovativo de pagamento).',
]);
