<?php
/**
 * V-MILLION — API: pagamentos do condutor, por pacote diário/semanal/mensal/anual
 * (preço e duração definidos pelo Super Admin em pacotes_pagamento). O
 * condutor escolhe o pacote e anexa o comprovativo ao solicitar; o admin
 * aprova/recusa e, ao aprovar, emite o recibo (ver /api/admin/pagamentos.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$condutor = kg_exigir_utilizador('condutor');
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT p.id, p.veiculo_id, p.rota_id, p.pacote_id, p.valor_pago, p.referencia, p.data_pagamento, p.data_validade,
                p.status, p.recibo_path, p.comprovativo_path, p.observacao_admin, p.criado_em, v.matricula,
                pac.nome AS pacote_nome
         FROM pagamentos_condutores p
         JOIN veiculos v ON v.id = p.veiculo_id
         LEFT JOIN pacotes_pagamento pac ON pac.id = p.pacote_id
         WHERE p.condutor_id = ?
         ORDER BY p.criado_em DESC LIMIT 50"
    );
    $stmt->execute([$condutor['id']]);
    $pagamentos = $stmt->fetchAll();

    $valido = $pdo->prepare(
        "SELECT 1 FROM pagamentos_condutores WHERE condutor_id = ? AND status = 'aprovado' AND data_validade >= NOW() LIMIT 1"
    );
    $valido->execute([$condutor['id']]);

    $pacotes = $pdo->query("SELECT id, nome, tipo_servico, descricao, preco, duracao_dias FROM pacotes_pagamento WHERE ativo = 1 ORDER BY tipo_servico ASC, duracao_dias ASC")->fetchAll();

    echo json_encode(['pagamentos' => $pagamentos, 'pagamento_valido' => (bool) $valido->fetchColumn(), 'pacotes' => $pacotes]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao !== 'solicitar') {
    http_response_code(422);
    echo json_encode(['erro' => 'Ação inválida.']);
    exit;
}

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$rotaId = filter_input(INPUT_POST, 'rota_id', FILTER_VALIDATE_INT) ?: null;
$pacoteId = filter_input(INPUT_POST, 'pacote_id', FILTER_VALIDATE_INT);

if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o veículo.']);
    exit;
}
if (!$pacoteId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Escolha um pacote (diário, semanal, mensal ou anual).']);
    exit;
}

// O condutor só pode operar (e portanto só pode pagar por) um veículo que
// lhe pertence e que já foi aprovado pelo Super Admin — pedir pagamento para
// um veículo ainda em análise não faz sentido (secção "condutor só pode usar
// veículos aprovados").
$veiculo = $pdo->prepare("SELECT id, aprovado, tipo_servico FROM veiculos WHERE id = ? AND condutor_id = ?");
$veiculo->execute([$veiculoId, $condutor['id']]);
$veiculo = $veiculo->fetch();
if (!$veiculo) {
    http_response_code(403);
    echo json_encode(['erro' => 'Veículo não pertence ao condutor autenticado.']);
    exit;
}
if (!$veiculo['aprovado']) {
    http_response_code(403);
    echo json_encode(['erro' => 'Este veículo ainda não foi aprovado pelo administrador.']);
    exit;
}

$pacote = $pdo->prepare("SELECT id, preco, tipo_servico FROM pacotes_pagamento WHERE id = ? AND ativo = 1");
$pacote->execute([$pacoteId]);
$pacote = $pacote->fetch();
if (!$pacote) {
    http_response_code(404);
    echo json_encode(['erro' => 'Pacote inválido ou já não está disponível.']);
    exit;
}
// O pacote tem de ser do mesmo tipo de serviço do veículo (secção "pacotes
// específicos por tipo de serviço") — evita um condutor Urbano pagar (e
// ficar com acesso associado a) um pacote pensado para Intermunicipal.
if ($pacote['tipo_servico'] !== $veiculo['tipo_servico']) {
    http_response_code(400);
    echo json_encode(['erro' => 'O pacote escolhido não corresponde ao tipo de serviço deste veículo.']);
    exit;
}

$pendente = $pdo->prepare("SELECT id FROM pagamentos_condutores WHERE condutor_id = ? AND veiculo_id = ? AND status = 'pendente'");
$pendente->execute([$condutor['id'], $veiculoId]);
if ($pendente->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Já existe um pedido de pagamento pendente para este veículo.']);
    exit;
}

// O comprovativo é sempre obrigatório aqui — é o condutor a provar que pagou
// antes de o pedido poder ser avaliado. Esta é agora a única via para pedir
// aprovação de pagamento, quer seja a mensalidade recorrente de um condutor
// já ativo, quer seja o primeiro pagamento de um condutor novo (que ativa a
// conta automaticamente ao ser aprovado — ver api/admin/pagamentos.php).
if (empty($_FILES['comprovativo']['tmp_name']) || !is_uploaded_file($_FILES['comprovativo']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['erro' => 'Anexe o comprovativo de pagamento (imagem ou PDF).']);
    exit;
}
$extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
$extensao = strtolower(pathinfo((string) $_FILES['comprovativo']['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, $extensoesPermitidas, true) || $_FILES['comprovativo']['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['erro' => 'Ficheiro inválido. Use PDF/JPG/PNG até 5MB.']);
    exit;
}
$dir = __DIR__ . '/../../../uploads/comprovativos';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}
$nomeFicheiro = 'pag_' . $condutor['id'] . '_' . time() . '.' . $extensao;
if (!move_uploaded_file($_FILES['comprovativo']['tmp_name'], $dir . '/' . $nomeFicheiro)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível guardar o comprovativo.']);
    exit;
}
$comprovativoPath = 'uploads/comprovativos/' . $nomeFicheiro;
$comprovativoTipo = $extensao === 'pdf' ? 'pdf' : 'imagem';

$valor = (float) $pacote['preco'];
$referencia = 'KG' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$pdo->prepare(
    "INSERT INTO pagamentos_condutores (condutor_id, veiculo_id, rota_id, pacote_id, valor_pago, referencia, comprovativo_path, comprovativo_tipo, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')"
)->execute([$condutor['id'], $veiculoId, $rotaId, $pacoteId, $valor, $referencia, $comprovativoPath, $comprovativoTipo]);

echo json_encode(['sucesso' => true, 'referencia' => $referencia, 'valor' => $valor]);
