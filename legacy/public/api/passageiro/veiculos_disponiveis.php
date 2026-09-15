<?php
/**
 * V-MILLION — API: veículos disponíveis num ponto de partida com destino escolhido
 * (secção 8, passo 4). Só devolve veículos aprovados, no ponto ou na fila, com lugares livres.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador('passageiro');

$pontoId = filter_input(INPUT_GET, 'ponto_id', FILTER_VALIDATE_INT);
$destinoId = filter_input(INPUT_GET, 'destino_id', FILTER_VALIDATE_INT);
$viagemTipo = in_array($_GET['viagem_tipo'] ?? '', ['urbana', 'intermunicipal'], true) ? $_GET['viagem_tipo'] : null;

if (!$pontoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Indique o ponto de partida.']);
    exit;
}

$pdo = kg_db();

// Viagem urbana (relatório "viagem urbana vs intermunicipal", tarefa B): o
// passageiro vê não só os veículos parados neste ponto, mas também os que
// já andam a circular na mesma cidade (em_movimento/partiu_da_fila) — fora
// disso continua limitado ao ponto escolhido, tal como antes.
$cidadePonto = null;
if ($viagemTipo === 'urbana') {
    $cidadeStmt = $pdo->prepare("SELECT cidade FROM pontos_partida WHERE id = ?");
    $cidadeStmt->execute([$pontoId]);
    $cidadePonto = $cidadeStmt->fetchColumn() ?: null;
}

// Visibilidade condicionada ao pagamento (relatório "condutores urbanos",
// tarefa E): sem taxa de operação aprovada e em dia, o condutor não pode
// operar — o veículo não deve aparecer a passageiros.
$sql = "SELECT v.id, v.matricula, v.tipo, v.tipo_servico, v.cor, v.modelo, v.lugares_livres, v.estado, v.posicao_fila, v.lat, v.lng,
               v.destino_id, pd.nome AS destino_nome,
               u.nome AS condutor_nome,
               (SELECT ROUND(AVG(a.avaliacao), 1) FROM avaliacoes_condutores a WHERE a.condutor_id = v.condutor_id) AS condutor_avaliacao_media,
               (SELECT COUNT(*) FROM avaliacoes_condutores a WHERE a.condutor_id = v.condutor_id) AS condutor_avaliacao_total
        FROM veiculos v
        JOIN utilizadores u ON u.id = v.condutor_id
        JOIN pontos_partida pp ON pp.id = v.ponto_partida_id
        LEFT JOIN pontos_partida pd ON pd.id = v.destino_id
        WHERE v.aprovado = 1
          AND u.status = 'ativo'
          AND v.lugares_livres > 0
          AND EXISTS (
            SELECT 1 FROM pagamentos_condutores pc
            WHERE pc.condutor_id = v.condutor_id AND pc.status = 'aprovado' AND pc.data_validade >= NOW()
          )
          AND (
            (v.ponto_partida_id = ? AND v.estado IN ('no_ponto', 'na_fila'))";
$params = [$pontoId];

if ($cidadePonto !== null) {
    $sql .= " OR (pp.cidade = ? AND v.estado IN ('em_movimento', 'partiu_da_fila'))";
    $params[] = $cidadePonto;
}
$sql .= " )";

if ($destinoId) {
    $sql .= " AND v.destino_id = ?";
    $params[] = $destinoId;
}

if ($viagemTipo === 'urbana') {
    $sql .= " AND v.tipo_servico IN ('urbano', 'ambos')";
} elseif ($viagemTipo === 'intermunicipal') {
    $sql .= " AND v.tipo_servico IN ('intermunicipal', 'ambos')";
}

$sql .= " ORDER BY (v.estado = 'na_fila') DESC, v.posicao_fila ASC, v.lugares_livres DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['veiculos' => $stmt->fetchAll()]);
