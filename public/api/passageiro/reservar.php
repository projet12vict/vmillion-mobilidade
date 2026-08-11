<?php
/**
 * V-MILLION — API: criar reserva (secção 8, passos 6-8). Escolha de veículo,
 * assento e ponto de descida; calcula preço e notifica o condutor (o
 * evento de tempo real é emitido pelo cliente após resposta de sucesso).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/pricing.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

kg_csrf_require();
$passageiro = kg_exigir_utilizador('passageiro');

$veiculoId = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
$assentoId = filter_input(INPUT_POST, 'assento_id', FILTER_VALIDATE_INT);
$pontoPartidaId = filter_input(INPUT_POST, 'ponto_partida_id', FILTER_VALIDATE_INT);
$destinoId = filter_input(INPUT_POST, 'destino_id', FILTER_VALIDATE_INT);
$lugares = filter_input(INPUT_POST, 'lugares', FILTER_VALIDATE_INT) ?: 1;
$motivoBruto = (string) ($_POST['motivo'] ?? 'normal');
$motivo = in_array($motivoBruto, ['normal', 'grupo', 'passeio'], true) ? $motivoBruto : 'normal';
$tipoViagemBruto = (string) ($_POST['tipo_viagem'] ?? 'intermunicipal');
$tipoViagem = in_array($tipoViagemBruto, ['urbano', 'intermunicipal'], true) ? $tipoViagemBruto : 'intermunicipal';
$descidaNome = trim((string) ($_POST['ponto_descida_nome'] ?? ''));
$descidaLat = filter_input(INPUT_POST, 'ponto_descida_lat', FILTER_VALIDATE_FLOAT);
$descidaLng = filter_input(INPUT_POST, 'ponto_descida_lng', FILTER_VALIDATE_FLOAT);

// Viagem urbana (relatório "viagem urbana vs intermunicipal", tarefa A): o
// destino não vem de uma lista — é sempre o ponto de descida escrito/
// geocodificado pelo passageiro. destino_id fica = ponto_partida_id só para
// satisfazer a FK (não influencia o preço nesse caso — ver kg_calcular_preco_urbano).
if ($tipoViagem === 'urbano') {
    if (!$veiculoId || !$assentoId || !$pontoPartidaId) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados de reserva incompletos.']);
        exit;
    }
    if ($descidaNome === '' || $descidaLat === null || $descidaLng === null || $descidaLat === false || $descidaLng === false) {
        http_response_code(422);
        echo json_encode(['erro' => 'Escreva o destino (viagem urbana).']);
        exit;
    }
    $destinoId = $pontoPartidaId;
} elseif (!$veiculoId || !$assentoId || !$pontoPartidaId || !$destinoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Dados de reserva incompletos.']);
    exit;
}

if ($lugares < 1 || $lugares > 8) {
    http_response_code(422);
    echo json_encode(['erro' => 'Número de lugares inválido (1-8).']);
    exit;
}

if ($descidaLat !== null && $descidaLng !== null) {
    kg_exigir_coordenadas_validas($descidaLat, $descidaLng);
}

$pdo = kg_db();
$pdo->beginTransaction();

try {
    // Bloqueia a linha do veículo para evitar condições de corrida em reservas concorrentes.
    $veiculoStmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? FOR UPDATE");
    $veiculoStmt->execute([$veiculoId]);
    $veiculo = $veiculoStmt->fetch();

    if (!$veiculo || !$veiculo['aprovado'] || $veiculo['lugares_livres'] < 1) {
        throw new RuntimeException('Este veículo já não tem lugares disponíveis.');
    }

    $assentoStmt = $pdo->prepare("SELECT * FROM assentos_veiculo WHERE id = ? AND veiculo_id = ? FOR UPDATE");
    $assentoStmt->execute([$assentoId, $veiculoId]);
    $assento = $assentoStmt->fetch();

    if (!$assento || $assento['ocupado']) {
        throw new RuntimeException('Este lugar já foi reservado por outro passageiro.');
    }

    $pontoPartida = $pdo->prepare("SELECT * FROM pontos_partida WHERE id = ?");
    $pontoPartida->execute([$pontoPartidaId]);
    $pontoPartida = $pontoPartida->fetch();

    $destino = $pdo->prepare("SELECT * FROM pontos_partida WHERE id = ?");
    $destino->execute([$destinoId]);
    $destino = $destino->fetch();

    if (!$pontoPartida || !$destino) {
        throw new RuntimeException('Ponto de partida ou destino inválido.');
    }

    if ($tipoViagem === 'urbano') {
        // Sem segmentação de zonas nem rota fixa a considerar — é sempre
        // tarifa urbana, distância direta até ao ponto de descida escrito.
        $distanciaUrbanaKm = kg_distancia_metros(
            (float) $pontoPartida['lat'], (float) $pontoPartida['lng'], $descidaLat, $descidaLng
        ) / 1000;
        try {
            $rota = kg_osrm_calcular_rota_cache((float) $pontoPartida['lat'], (float) $pontoPartida['lng'], $descidaLat, $descidaLng);
            if ($rota['distancia_m'] > 0) {
                $distanciaUrbanaKm = $rota['distancia_m'] / 1000;
            }
        } catch (Throwable $e) {
            // OSRM indisponível: mantém a aproximação em linha reta (haversine).
        }
        $resultadoPreco = kg_calcular_preco_urbano($pdo, $distanciaUrbanaKm);
    } else {
        // Distância total da rota e distância até ao ponto de descida (secção 12.2).
        $distanciaTotalKm = kg_distancia_metros(
            (float) $pontoPartida['lat'], (float) $pontoPartida['lng'],
            (float) $destino['lat'], (float) $destino['lng']
        ) / 1000;

        $temDescida = $descidaLat !== null && $descidaLng !== null;
        $distanciaAteDescidaKm = $temDescida
            ? kg_distancia_metros((float) $pontoPartida['lat'], (float) $pontoPartida['lng'], $descidaLat, $descidaLng) / 1000
            : $distanciaTotalKm;

        try {
            $rota = kg_osrm_calcular_rota_cache(
                (float) $pontoPartida['lat'], (float) $pontoPartida['lng'],
                (float) $destino['lat'], (float) $destino['lng']
            );
            if ($rota['distancia_m'] > 0) {
                $fator = $distanciaAteDescidaKm / max($distanciaTotalKm, 0.001);
                $distanciaTotalKm = $rota['distancia_m'] / 1000;
                $distanciaAteDescidaKm = $temDescida ? $distanciaTotalKm * $fator : $distanciaTotalKm;
            }
        } catch (Throwable $e) {
            // OSRM indisponível: mantém a aproximação em linha reta (haversine).
        }

        // Motor de preços (secção 12): rota fixa fracionada, ou por km com
        // segmentação real urbano/intermunicipal via limites de cidade.
        $descidaCoord = $temDescida ? ['lat' => $descidaLat, 'lng' => $descidaLng] : null;
        $resultadoPreco = kg_calcular_preco_viagem(
            $pdo, $veiculo, $pontoPartida, $destino, $distanciaTotalKm, $distanciaAteDescidaKm, $descidaCoord
        );
    }
    $preco = $resultadoPreco['preco'];

    $inserirReserva = $pdo->prepare(
        "INSERT INTO reservas (passageiro_id, veiculo_id, assento_id, ponto_partida_id, destino_id, tipo_viagem,
                                ponto_descida_nome, ponto_descida_lat, ponto_descida_lng, lugares, motivo, preco_final, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')"
    );
    $inserirReserva->execute([
        $passageiro['id'], $veiculoId, $assentoId, $pontoPartidaId, $destinoId, $tipoViagem,
        $descidaNome ?: null, $descidaLat, $descidaLng, $lugares, $motivo, $preco,
    ]);
    $reservaId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE assentos_veiculo SET ocupado = 1, reserva_id = ? WHERE id = ?")
        ->execute([$reservaId, $assentoId]);

    $pdo->prepare("UPDATE veiculos SET lugares_livres = lugares_livres - 1 WHERE id = ?")
        ->execute([$veiculoId]);

    $pdo->commit();

    if ($tipoViagem === 'urbano' && $descidaNome !== '' && $descidaLat !== null && $descidaLng !== null) {
        kg_guardar_destino_urbano($pdo, $descidaNome, $descidaLat, $descidaLng, $passageiro['id']);
    }

    echo json_encode([
        'sucesso' => true,
        'reserva_id' => $reservaId,
        'preco_final' => $preco,
        'veiculo_id' => $veiculoId,
        'condutor_id' => (int) $veiculo['condutor_id'],
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code($e instanceof RuntimeException ? 409 : 500);
    echo json_encode(['erro' => $e->getMessage() ?: 'Não foi possível concluir a reserva.']);
}
