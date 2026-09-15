<?php
/**
 * V-MILLION — API: aprovação de pagamentos de condutores (taxa de operação por
 * rota, secção 11.6). Ao aprovar, emite recibo em PDF válido por 30 dias.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/pdf_recibo.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin();
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // aprovado_por_nome permite ao Super Admin fiscalizar pagamentos
    // aprovados por outros administradores, sem filtro nenhum por quem os
    // processou (secção G do relatório "taxa de operação e pagamentos").
    $stmt = $pdo->query(
        "SELECT p.id, p.condutor_id, p.veiculo_id, p.rota_id, p.pacote_id, p.valor_pago, p.referencia, p.data_pagamento,
                p.data_validade, p.status, p.recibo_path, p.comprovativo_path, p.comprovativo_tipo,
                p.observacao_admin, p.criado_em, p.aprovado_por, p.aprovado_em,
                u.nome AS condutor_nome, u.telefone AS condutor_telefone, u.status AS condutor_status, v.matricula,
                po.nome AS origem_nome, pd.nome AS destino_nome, a.nome AS aprovado_por_nome,
                pac.nome AS pacote_nome, pac.duracao_dias AS pacote_duracao_dias, pac.tipo_servico AS pacote_tipo_servico
         FROM pagamentos_condutores p
         JOIN utilizadores u ON u.id = p.condutor_id
         JOIN veiculos v ON v.id = p.veiculo_id
         LEFT JOIN precos_rotas pr ON pr.id = p.rota_id
         LEFT JOIN pontos_partida po ON po.id = pr.ponto_origem_id
         LEFT JOIN pontos_partida pd ON pd.id = pr.ponto_destino_id
         LEFT JOIN administradores a ON a.id = p.aprovado_por
         LEFT JOIN pacotes_pagamento pac ON pac.id = p.pacote_id
         ORDER BY (p.status = 'pendente') DESC, p.criado_em DESC LIMIT 200"
    );
    echo json_encode(['pagamentos' => $stmt->fetchAll()]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if (!in_array($acao, ['aprovar', 'recusar', 'reverter'], true)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Ação inválida.']);
    exit;
}

// Reverter uma aprovação (irregularidade encontrada depois) é uma ação mais
// sensível do que aprovar/recusar um pedido pendente — restrita ao Super
// Admin, tal como suspender um condutor (secção G do relatório).
if ($acao === 'reverter') {
    kg_exigir_admin('super');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    echo json_encode(['erro' => 'ID inválido.']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nome AS condutor_nome, u.status AS condutor_status, v.matricula, po.nome AS origem_nome, pd.nome AS destino_nome,
                pac.nome AS pacote_nome, pac.duracao_dias AS pacote_duracao_dias, pac.tipo_servico AS pacote_tipo_servico
         FROM pagamentos_condutores p
         JOIN utilizadores u ON u.id = p.condutor_id
         JOIN veiculos v ON v.id = p.veiculo_id
         LEFT JOIN precos_rotas pr ON pr.id = p.rota_id
         LEFT JOIN pontos_partida po ON po.id = pr.ponto_origem_id
         LEFT JOIN pontos_partida pd ON pd.id = pr.ponto_destino_id
         LEFT JOIN pacotes_pagamento pac ON pac.id = p.pacote_id
         WHERE p.id = ? FOR UPDATE"
    );
    $stmt->execute([$id]);
    $pagamento = $stmt->fetch();

    if (!$pagamento) {
        throw new RuntimeException('Pagamento não encontrado.', 404);
    }

    if ($acao === 'reverter') {
        if ($pagamento['status'] !== 'aprovado') {
            throw new RuntimeException('Só é possível reverter um pagamento aprovado.', 409);
        }
        // Invalida já (data_validade no passado) em vez de só mudar o
        // status — o gate do mapa do condutor ($mostrarMapa) verifica
        // data_validade >= NOW(), por isso o efeito é imediato no próximo
        // pedido dele, sem precisar de mais nenhuma lógica.
        $pdo->prepare("UPDATE pagamentos_condutores SET status = 'pendente', data_validade = NOW() - INTERVAL 1 SECOND WHERE id = ?")
            ->execute([$id]);
        kg_log_auditoria($pdo, $admin['id'], 'reverteu_pagamento_condutor', 'pagamentos_condutores', $id, $pagamento['referencia']);
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
        exit;
    }

    if ($pagamento['status'] !== 'pendente') {
        throw new RuntimeException('Este pagamento já foi processado.', 409);
    }

    $observacao = trim((string) ($_POST['observacao'] ?? '')) ?: null;

    if ($acao === 'recusar') {
        $pdo->prepare("UPDATE pagamentos_condutores SET status = 'recusado', aprovado_por = ?, aprovado_em = NOW(), observacao_admin = ? WHERE id = ?")
            ->execute([$admin['id'], $observacao, $id]);
        kg_log_auditoria($pdo, $admin['id'], 'recusou_pagamento_condutor', 'pagamentos_condutores', $id);
        $pdo->prepare(
            "INSERT INTO notificacoes (destinatario_id, destinatario_tipo, remetente_id, titulo, mensagem, tipo) VALUES (?, 'individual', ?, ?, ?, 'alerta')"
        )->execute([
            $pagamento['condutor_id'], $admin['id'], 'Pagamento recusado',
            'O seu comprovativo (ref. ' . $pagamento['referencia'] . ') foi recusado.' . ($observacao ? " Motivo: {$observacao}" : ' Verifique o documento e volte a enviar.'),
        ]);
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // duracao_dias vem do pacote escolhido pelo condutor; pagamentos antigos
    // sem pacote (taxa fixa, antes desta funcionalidade) mantêm os 30 dias
    // de sempre, por compatibilidade com o que já estava aprovado/pendente.
    $duracaoDias = $pagamento['pacote_duracao_dias'] !== null ? (int) $pagamento['pacote_duracao_dias'] : 30;
    $dataPagamento = date('Y-m-d H:i:s');
    $dataValidade = date('Y-m-d H:i:s', strtotime("+{$duracaoDias} days"));

    $rotaTexto = $pagamento['origem_nome'] ? "{$pagamento['origem_nome']} -> {$pagamento['destino_nome']}" : 'Sem rota fixa associada';

    $pdfBytes = kg_gerar_pdf_recibo(kg_montar_linhas_recibo_condutor([
        'referencia' => $pagamento['referencia'],
        'condutor_nome' => $pagamento['condutor_nome'],
        'matricula' => $pagamento['matricula'],
        'rota_texto' => $rotaTexto,
        'valor_pago' => (float) $pagamento['valor_pago'],
        'data_pagamento' => $dataPagamento,
        'data_validade' => $dataValidade,
        'aprovado_por' => $admin['nome'],
        'pacote_nome' => $pagamento['pacote_nome'],
        'duracao_dias' => $duracaoDias,
        'tipo_servico' => $pagamento['pacote_tipo_servico'],
    ]));

    $dir = __DIR__ . '/../../../uploads/recibos';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $nomeFicheiro = 'recibo_' . $pagamento['referencia'] . '.pdf';
    file_put_contents($dir . '/' . $nomeFicheiro, $pdfBytes);
    $reciboPath = 'uploads/recibos/' . $nomeFicheiro;

    $pdo->prepare(
        "UPDATE pagamentos_condutores
         SET status = 'aprovado', aprovado_por = ?, aprovado_em = NOW(), data_pagamento = ?, data_validade = ?, recibo_path = ?, observacao_admin = ?
         WHERE id = ?"
    )->execute([$admin['id'], $dataPagamento, $dataValidade, $reciboPath, $observacao, $id]);

    kg_log_auditoria($pdo, $admin['id'], 'aprovou_pagamento_condutor', 'pagamentos_condutores', $id, $pagamento['referencia']);

    // Um condutor novo fica em status 'pendente' até ter um pagamento
    // aprovado e válido (ver aprovar_condutor em api/admin/utilizadores.php,
    // e kg_condutor_pode_ver_mapa em includes/veiculos.php, que já seguiam
    // esta mesma fonte). Aprovar aqui o primeiro pagamento é o que desbloqueia
    // a conta — sem isto, um condutor novo ficava para sempre à espera de um
    // admin ir manualmente aprová-lo noutro ecrã.
    $ativouConta = $pagamento['condutor_status'] === 'pendente';
    if ($ativouConta) {
        $pdo->prepare("UPDATE utilizadores SET status = 'ativo' WHERE id = ? AND tipo = 'condutor'")->execute([$pagamento['condutor_id']]);
        kg_log_auditoria($pdo, $admin['id'], 'aprovou_condutor', 'utilizadores', $pagamento['condutor_id'], 'ativado automaticamente ao aprovar pagamento ' . $pagamento['referencia']);
    }

    $pdo->prepare(
        "INSERT INTO notificacoes (destinatario_id, destinatario_tipo, remetente_id, titulo, mensagem, tipo) VALUES (?, 'individual', ?, ?, ?, 'informativo')"
    )->execute([
        $pagamento['condutor_id'], $admin['id'],
        $ativouConta ? 'Conta ativada' : 'Pagamento aprovado',
        'O seu pagamento (ref. ' . $pagamento['referencia'] . ') foi aprovado. Acesso válido até ' . $dataValidade . '.'
            . ($ativouConta ? ' A sua conta foi ativada com sucesso — já pode aceder ao mapa.' : ''),
    ]);
    $pdo->commit();
    echo json_encode(['sucesso' => true, 'recibo_path' => $reciboPath]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['erro' => $e->getMessage()]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível aprovar o pagamento.']);
}
