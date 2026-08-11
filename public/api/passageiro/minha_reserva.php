<?php
/**
 * V-MILLION — API: estado da reserva ativa do passageiro + contacto do condutor
 * (revelado apenas após confirmação, secção 13).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$passageiro = kg_exigir_utilizador('passageiro');

$pdo = kg_db();
// LEFT JOIN em veiculos/utilizadores: um pedido de viagem urbana em aberto
// (ainda sem condutor a reclamá-lo) tem veiculo_id NULL — continua a ser
// "a reserva ativa" do passageiro, só sem esses dados ainda disponíveis.
$stmt = $pdo->prepare(
    "SELECT r.id, r.estado, r.preco_final, r.tipo_viagem, r.ponto_descida_nome, r.ponto_descida_lat, r.ponto_descida_lng, r.criado_em,
            v.id AS veiculo_id, v.matricula, v.tipo, v.cor, v.lat AS veiculo_lat, v.lng AS veiculo_lng, v.estado AS veiculo_estado,
            pp.nome AS ponto_partida_nome, pp.lat AS ponto_partida_lat, pp.lng AS ponto_partida_lng,
            pd.nome AS destino_nome,
            u.id AS condutor_id, u.nome AS condutor_nome,
            CASE WHEN r.estado = 'confirmado' OR r.estado = 'a_bordo' THEN u.telefone ELSE NULL END AS condutor_telefone
     FROM reservas r
     LEFT JOIN veiculos v ON v.id = r.veiculo_id
     LEFT JOIN utilizadores u ON u.id = v.condutor_id
     JOIN pontos_partida pp ON pp.id = r.ponto_partida_id
     JOIN pontos_partida pd ON pd.id = r.destino_id
     WHERE r.passageiro_id = ? AND r.estado NOT IN ('concluido', 'recusado')
     ORDER BY r.criado_em DESC LIMIT 1"
);
$stmt->execute([$passageiro['id']]);
$reserva = $stmt->fetch();

// Viagem concluída e ainda por avaliar (secção 9): pede avaliação do condutor.
$avaliarPendente = null;
if (!$reserva) {
    $stmtAv = $pdo->prepare(
        "SELECT r.id AS reserva_id, u.id AS condutor_id, u.nome AS condutor_nome, v.matricula
         FROM reservas r
         JOIN veiculos v ON v.id = r.veiculo_id
         JOIN utilizadores u ON u.id = v.condutor_id
         WHERE r.passageiro_id = ? AND r.estado = 'concluido'
           AND NOT EXISTS (SELECT 1 FROM avaliacoes_condutores a WHERE a.reserva_id = r.id)
         ORDER BY r.atualizado_em DESC LIMIT 1"
    );
    $stmtAv->execute([$passageiro['id']]);
    $avaliarPendente = $stmtAv->fetch() ?: null;
}

echo json_encode(['reserva' => $reserva ?: null, 'avaliar_pendente' => $avaliarPendente]);
