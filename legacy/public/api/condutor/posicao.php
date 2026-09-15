<?php
/**
 * V-MILLION — API: condutor atualiza a sua posição GPS (secção 9.5, 9.7).
 * Deteta proximidade do ponto de descida (<500m) e chegada ao destino (<100m),
 * neste caso inverte automaticamente ponto de partida <-> destino.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$condutor = kg_exigir_utilizador('condutor');

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
$accuracy = filter_input(INPUT_POST, 'accuracy', FILTER_VALIDATE_FLOAT);

if (!$veiculoId || $lat === null || $lng === null || $lat === false || $lng === false) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados de posição inválidos.']);
    exit;
}
// Uma leitura de baixa precisão aqui não é só um marcador feio no mapa: a
// deteção de chegada ao destino (<100m) e os avisos de proximidade (<500m)
// mais abaixo confiam nesta coordenada — uma leitura ruim podia disparar
// uma chegada falsa e concluir a viagem de todos os passageiros por engano.
if ($accuracy !== null && $accuracy !== false && $accuracy > 100) {
    http_response_code(422);
    echo json_encode(['erro' => 'Precisão do GPS insuficiente.']);
    exit;
}
kg_exigir_coordenadas_validas($lat, $lng);

$pdo = kg_db();

$veiculoStmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? AND condutor_id = ?");
$veiculoStmt->execute([$veiculoId, $condutor['id']]);
$veiculo = $veiculoStmt->fetch();

if (!$veiculo) {
    http_response_code(404);
    echo json_encode(['erro' => 'Veículo não encontrado.']);
    exit;
}

// Enquanto o veículo tem um ponto de partida definido e ainda não iniciou
// rota (no_ponto/na_fila/chegou_destino), fica ancorado nas coordenadas do
// ponto — o GPS do telemóvel do condutor não pode arrastar o marcador para
// fora do ponto antes de "Iniciar rota" (relatório "carro fora do ponto").
// Veículos sem ponto definido (modo urbano a circular livremente) não são
// afetados por esta regra — continuam a seguir o GPS como sempre.
if ($veiculo['ponto_partida_id'] !== null && !in_array($veiculo['estado'], ['partiu_da_fila', 'em_movimento'], true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Veículo ainda não iniciou a rota — clique em "Iniciar rota" para partir do ponto.']);
    exit;
}

// Ao sair da fila o condutor fica 'partiu_da_fila'; a primeira posição GPS
// recebida depois disso transita para 'em_movimento' — é assim que o mapa
// reflete a posição/estado real do veículo (relatório do mapa, tarefa 5).
$pdo->prepare(
    "UPDATE veiculos SET lat = ?, lng = ?, ultima_posicao_em = NOW(),
            estado = IF(estado = 'partiu_da_fila', 'em_movimento', estado)
     WHERE id = ?"
)->execute([$lat, $lng, $veiculoId]);

$novoEstado = $veiculo['estado'] === 'partiu_da_fila' ? 'em_movimento' : $veiculo['estado'];
$resposta = ['sucesso' => true, 'estado' => $novoEstado, 'chegou_destino' => false, 'passageiros_proximos' => []];

// Avisos de proximidade (<500m) aos pontos de descida das reservas ativas.
$reservas = $pdo->prepare(
    "SELECT id, passageiro_id, ponto_descida_nome, ponto_descida_lat, ponto_descida_lng
     FROM reservas WHERE veiculo_id = ? AND estado IN ('confirmado', 'a_bordo')
       AND ponto_descida_lat IS NOT NULL AND ponto_descida_lng IS NOT NULL"
);
$reservas->execute([$veiculoId]);
foreach ($reservas->fetchAll() as $r) {
    $dist = kg_distancia_metros($lat, $lng, (float) $r['ponto_descida_lat'], (float) $r['ponto_descida_lng']);
    if ($dist < 500) {
        $resposta['passageiros_proximos'][] = [
            'reserva_id' => (int) $r['id'],
            'ponto_descida_nome' => $r['ponto_descida_nome'],
            'distancia_m' => round($dist),
        ];
    }
}

// Deteção automática de chegada ao destino (<100m) — secção 9.7.
if ($veiculo['destino_id']) {
    $destino = $pdo->prepare("SELECT lat, lng FROM pontos_partida WHERE id = ?");
    $destino->execute([$veiculo['destino_id']]);
    $destino = $destino->fetch();

    if ($destino && kg_distancia_metros($lat, $lng, (float) $destino['lat'], (float) $destino['lng']) < 100) {
        $pdo->beginTransaction();
        try {
            // Marca todos os passageiros como descidos e liberta os lugares.
            $pdo->prepare("UPDATE reservas SET estado = 'concluido' WHERE veiculo_id = ? AND estado IN ('confirmado', 'a_bordo')")
                ->execute([$veiculoId]);
            $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 0, reserva_id = NULL WHERE veiculo_id = ?")
                ->execute([$veiculoId]);

            // Inverte ponto de partida <-> destino; aguarda decisão do condutor (fila ou livre).
            $novoPontoPartida = $veiculo['destino_id'];
            $novoDestino = $veiculo['ponto_partida_id'];
            $pdo->prepare(
                "UPDATE veiculos SET estado = 'chegou_destino', ponto_partida_id = ?, destino_id = ?,
                        lugares_livres = lugares_total, posicao_fila = NULL WHERE id = ?"
            )->execute([$novoPontoPartida, $novoDestino, $veiculoId]);

            $pdo->commit();
            $resposta['chegou_destino'] = true;
            $resposta['estado'] = 'chegou_destino';
            $resposta['novo_ponto_partida_id'] = $novoPontoPartida;
            $resposta['novo_destino_id'] = $novoDestino;
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }
}

echo json_encode($resposta);
