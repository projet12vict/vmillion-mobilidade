<?php
/**
 * V-MILLION — API: gestão de preços (apenas Super Admin, secção 11.5, 12.1).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/pricing.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin('super');
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $km = $pdo->query("SELECT * FROM precos_km")->fetchAll();
    $rotas = $pdo->query(
        "SELECT pr.id, pr.preco_fixo_cve, pr.distancia_km, po.nome AS origem_nome, pd.nome AS destino_nome, pr.ponto_origem_id, pr.ponto_destino_id
         FROM precos_rotas pr
         JOIN pontos_partida po ON po.id = pr.ponto_origem_id
         JOIN pontos_partida pd ON pd.id = pr.ponto_destino_id
         ORDER BY po.nome"
    )->fetchAll();
    $pacotes = $pdo->query("SELECT * FROM pacotes_pagamento ORDER BY tipo_servico ASC, duracao_dias ASC")->fetchAll();
    echo json_encode(['precos_km' => $km, 'precos_rotas' => $rotas, 'config' => kg_obter_config_precos($pdo), 'pacotes' => $pacotes]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'atualizar_km') {
    $zona = in_array($_POST['zona'] ?? '', ['urbana', 'intermunicipal'], true) ? $_POST['zona'] : null;
    $preco = filter_input(INPUT_POST, 'preco_por_km_cve', FILTER_VALIDATE_FLOAT);
    if (!$zona || $preco === null || $preco === false || $preco < 0) {
        http_response_code(422);
        echo json_encode(['erro' => 'Zona ou preço inválido.']);
        exit;
    }
    $pdo->prepare(
        "INSERT INTO precos_km (zona, preco_por_km_cve, atualizado_por) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE preco_por_km_cve = VALUES(preco_por_km_cve), atualizado_por = VALUES(atualizado_por)"
    )->execute([$zona, $preco, $admin['id']]);
    kg_log_auditoria($pdo, $admin['id'], 'atualizou_preco_km', 'precos_km', null, "{$zona}: {$preco} CVE/km");
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'definir_rota') {
    $origemId = filter_input(INPUT_POST, 'ponto_origem_id', FILTER_VALIDATE_INT);
    $destinoId = filter_input(INPUT_POST, 'ponto_destino_id', FILTER_VALIDATE_INT);
    $precoFixo = filter_input(INPUT_POST, 'preco_fixo_cve', FILTER_VALIDATE_FLOAT);

    if (!$origemId || !$destinoId || $precoFixo === null || $precoFixo === false || $precoFixo < 0) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados de rota inválidos.']);
        exit;
    }
    if ($origemId === $destinoId) {
        http_response_code(422);
        echo json_encode(['erro' => 'Origem e destino não podem ser o mesmo ponto.']);
        exit;
    }

    $pontos = $pdo->prepare("SELECT id, lat, lng FROM pontos_partida WHERE id IN (?, ?)");
    $pontos->execute([$origemId, $destinoId]);
    $porId = [];
    foreach ($pontos->fetchAll() as $p) { $porId[(int) $p['id']] = $p; }
    if (!isset($porId[$origemId]) || !isset($porId[$destinoId])) {
        http_response_code(404);
        echo json_encode(['erro' => 'Ponto de origem ou destino não encontrado.']);
        exit;
    }
    $distanciaKm = round(kg_distancia_metros(
        (float) $porId[$origemId]['lat'], (float) $porId[$origemId]['lng'],
        (float) $porId[$destinoId]['lat'], (float) $porId[$destinoId]['lng']
    ) / 1000, 2);

    $pdo->prepare(
        "INSERT INTO precos_rotas (ponto_origem_id, ponto_destino_id, preco_fixo_cve, distancia_km, definido_por) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE preco_fixo_cve = VALUES(preco_fixo_cve), distancia_km = VALUES(distancia_km), definido_por = VALUES(definido_por)"
    )->execute([$origemId, $destinoId, $precoFixo, $distanciaKm, $admin['id']]);
    kg_log_auditoria($pdo, $admin['id'], 'definiu_preco_rota', 'precos_rotas', null, "{$origemId}->{$destinoId}: {$precoFixo} CVE ({$distanciaKm} km)");
    echo json_encode(['sucesso' => true, 'distancia_km' => $distanciaKm]);
    exit;
}

if ($acao === 'eliminar_rota') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("DELETE FROM precos_rotas WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'eliminou_preco_rota', 'precos_rotas', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'atualizar_config') {
    $chave = in_array($_POST['chave'] ?? '', ['valor_minimo', 'valor_maximo', 'taxa_operacao_rota'], true) ? $_POST['chave'] : null;
    $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
    if (!$chave || $valor === null || $valor === false || $valor < 0) {
        http_response_code(422);
        echo json_encode(['erro' => 'Configuração ou valor inválido.']);
        exit;
    }
    $pdo->prepare(
        "INSERT INTO config_precos (chave, valor, atualizado_por) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)"
    )->execute([$chave, $valor, $admin['id']]);
    kg_log_auditoria($pdo, $admin['id'], 'atualizou_config_precos', 'config_precos', null, "{$chave}: {$valor}");
    echo json_encode(['sucesso' => true]);
    exit;
}

// Pacotes de pagamento dos condutores (secção "pacotes diário/semanal/mensal/
// anual", preço definido pelo Super Admin). id presente = atualiza um pacote
// existente; ausente = cria um novo (além dos 4 pré-definidos na migração).
// Cada pacote pertence a um tipo_servico (urbano/intermunicipal/ambos) — o
// condutor só vê e só pode pagar pacotes do mesmo tipo do seu veículo (ver
// api/condutor/pagamentos.php), por isso é obrigatório em ambos os casos.
if ($acao === 'guardar_pacote') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
    $duracaoDias = filter_input(INPUT_POST, 'duracao_dias', FILTER_VALIDATE_INT);
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $ativo = filter_input(INPUT_POST, 'ativo', FILTER_VALIDATE_INT) !== 0 ? 1 : 0;
    $tipoServico = in_array($_POST['tipo_servico'] ?? '', ['urbano', 'intermunicipal', 'ambos'], true) ? $_POST['tipo_servico'] : null;

    if ($preco === null || $preco === false || $preco < 0 || !$duracaoDias || $duracaoDias < 1 || !$tipoServico) {
        http_response_code(422);
        echo json_encode(['erro' => 'Preço, duração ou tipo de serviço inválidos.']);
        exit;
    }

    if ($id) {
        $nomeAtual = $pdo->prepare("SELECT nome FROM pacotes_pagamento WHERE id = ?");
        $nomeAtual->execute([$id]);
        $nome = $nomeAtual->fetchColumn();
        if ($nome === false) {
            http_response_code(404);
            echo json_encode(['erro' => 'Pacote não encontrado.']);
            exit;
        }
        $duplicado = $pdo->prepare("SELECT id FROM pacotes_pagamento WHERE tipo_servico = ? AND nome = ? AND id != ?");
        $duplicado->execute([$tipoServico, $nome, $id]);
        if ($duplicado->fetch()) {
            http_response_code(409);
            echo json_encode(['erro' => 'Já existe um pacote com este nome para este tipo de serviço.']);
            exit;
        }
        $pdo->prepare(
            "UPDATE pacotes_pagamento SET tipo_servico = ?, preco = ?, duracao_dias = ?, descricao = ?, ativo = ? WHERE id = ?"
        )->execute([$tipoServico, $preco, $duracaoDias, $descricao ?: null, $ativo, $id]);
        kg_log_auditoria($pdo, $admin['id'], 'atualizou_pacote_pagamento', 'pacotes_pagamento', $id, "{$tipoServico}: {$preco} CVE / {$duracaoDias}d");
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 20) {
            http_response_code(422);
            echo json_encode(['erro' => 'Indique um nome para o pacote (até 20 caracteres).']);
            exit;
        }
        $duplicado = $pdo->prepare("SELECT id FROM pacotes_pagamento WHERE tipo_servico = ? AND nome = ?");
        $duplicado->execute([$tipoServico, $nome]);
        if ($duplicado->fetch()) {
            http_response_code(409);
            echo json_encode(['erro' => 'Já existe um pacote com este nome para este tipo de serviço.']);
            exit;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO pacotes_pagamento (nome, tipo_servico, descricao, preco, duracao_dias, criado_por) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nome, $tipoServico, $descricao ?: null, $preco, $duracaoDias, $admin['id']]);
        $id = (int) $pdo->lastInsertId();
        kg_log_auditoria($pdo, $admin['id'], 'criou_pacote_pagamento', 'pacotes_pagamento', $id, "{$nome} ({$tipoServico}): {$preco} CVE / {$duracaoDias}d");
    }
    echo json_encode(['sucesso' => true, 'id' => $id]);
    exit;
}

if ($acao === 'remover_pacote') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    // Nunca apaga a linha (pagamentos já feitos com este pacote referenciam
    // pacote_id) — só deixa de aparecer como opção para novos pedidos.
    $pdo->prepare("UPDATE pacotes_pagamento SET ativo = 0 WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'desativou_pacote_pagamento', 'pacotes_pagamento', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
