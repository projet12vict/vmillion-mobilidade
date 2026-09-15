<?php
/**
 * V-MILLION — API: estatísticas do dashboard admin (secção 11.2).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_admin();
$pdo = kg_db();

$contar = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

$stats = [
    'passageiros'            => $contar("SELECT COUNT(*) FROM utilizadores WHERE tipo = 'passageiro'"),
    'condutores'              => $contar("SELECT COUNT(*) FROM utilizadores WHERE tipo = 'condutor' AND status = 'ativo'"),
    'condutores_pendentes'    => $contar("SELECT COUNT(*) FROM utilizadores WHERE tipo = 'condutor' AND status = 'pendente'"),
    'veiculos'                => $contar("SELECT COUNT(*) FROM veiculos WHERE aprovado = 1"),
    'veiculos_pendentes'      => $contar("SELECT COUNT(*) FROM veiculos WHERE aprovado = 0"),
    'pontos'                  => $contar("SELECT COUNT(*) FROM pontos_partida WHERE status = 'aprovado'"),
    'pontos_pendentes'        => $contar("SELECT COUNT(*) FROM pontos_partida WHERE status = 'pendente'"),
    'parques'                 => $contar("SELECT COUNT(*) FROM parques"),
    'pagamentos_pendentes'    => $contar("SELECT COUNT(*) FROM pagamentos_condutores WHERE status = 'pendente'"),
    'faturas_pendentes'       => $contar("SELECT COUNT(*) FROM faturas WHERE estado = 'pendente'"),
    'faturas_vencidas'        => $contar("SELECT COUNT(*) FROM faturas WHERE estado = 'vencida'"),
    'administradores_ativos'  => $contar("SELECT COUNT(*) FROM administradores WHERE ativo = 1"),
    'sos_pendentes'           => $contar("SELECT COUNT(*) FROM alarmes_sos WHERE estado = 'pendente'"),
];

$ultimasAcoes = $pdo->query(
    "SELECT l.acao, l.entidade, l.entidade_id, l.criado_em, a.nome AS admin_nome
     FROM logs_auditoria l LEFT JOIN administradores a ON a.id = l.admin_id
     ORDER BY l.criado_em DESC LIMIT 15"
)->fetchAll();

echo json_encode(['stats' => $stats, 'ultimas_acoes' => $ultimasAcoes]);
