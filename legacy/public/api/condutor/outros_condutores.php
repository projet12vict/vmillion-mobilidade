<?php
/**
 * V-MILLION — API: contactos de outros condutores no mesmo ponto (secção 9.6).
 * Só devolve veículos intermunicipais/ambos (um condutor urbano não tem
 * "outros condutores no ponto" — não opera por ponto/fila) e, quando se sabe
 * o destino do próprio condutor (veiculo_id), só os que vão para o mesmo
 * destino — ver mesmo com destino/pessoas no caminho.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$condutor = kg_exigir_utilizador('condutor');

$pontoId = filter_input(INPUT_GET, 'ponto_id', FILTER_VALIDATE_INT);
if (!$pontoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Ponto inválido.']);
    exit;
}

$pdo = kg_db();

$destinoId = null;
$veiculoId = filter_input(INPUT_GET, 'veiculo_id', FILTER_VALIDATE_INT);
if ($veiculoId) {
    $meuVeiculo = $pdo->prepare("SELECT destino_id FROM veiculos WHERE id = ? AND condutor_id = ?");
    $meuVeiculo->execute([$veiculoId, $condutor['id']]);
    $destinoId = $meuVeiculo->fetchColumn() ?: null;
}

$sql = "SELECT v.id, v.matricula, v.tipo, v.cor, v.estado, v.posicao_fila, v.lat, v.lng, v.destino_id, pd.nome AS destino_nome,
               u.nome AS condutor_nome, u.telefone AS condutor_telefone
        FROM veiculos v
        JOIN utilizadores u ON u.id = v.condutor_id
        LEFT JOIN pontos_partida pd ON pd.id = v.destino_id
        WHERE v.ponto_partida_id = ? AND v.aprovado = 1 AND u.status = 'ativo' AND v.condutor_id != ?
          AND v.tipo_servico IN ('intermunicipal', 'ambos')
          AND v.estado IN ('no_ponto', 'na_fila')
          AND EXISTS (
            SELECT 1 FROM pagamentos_condutores pc
            WHERE pc.condutor_id = v.condutor_id AND pc.status = 'aprovado' AND pc.data_validade >= NOW()
          )";
$params = [$pontoId, $condutor['id']];
if ($destinoId) {
    $sql .= " AND v.destino_id = ?";
    $params[] = $destinoId;
}
$sql .= " ORDER BY v.posicao_fila ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['condutores' => $stmt->fetchAll()]);
