<?php
/**
 * V-MILLION — API: rota real por estrada entre dois pontos (mapa do passageiro e
 * do condutor). Proxy para a instância própria do OSRM (KG_OSRM_URL, só
 * acessível a partir do servidor) com cache em Redis (kg_osrm_calcular_rota_cache).
 * Se o OSRM falhar, devolve sucesso=false — o front-end (KGMap.tracarRota, em
 * assets/js/kg-map.js) cai então para a linha reta entre origem e destino,
 * exatamente como antes desta integração.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/pricing.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador();

$origemLat = filter_input(INPUT_GET, 'origem_lat', FILTER_VALIDATE_FLOAT);
$origemLng = filter_input(INPUT_GET, 'origem_lng', FILTER_VALIDATE_FLOAT);
$destinoLat = filter_input(INPUT_GET, 'destino_lat', FILTER_VALIDATE_FLOAT);
$destinoLng = filter_input(INPUT_GET, 'destino_lng', FILTER_VALIDATE_FLOAT);

if ($origemLat === null || $origemLng === null || $destinoLat === null || $destinoLng === null
    || $origemLat === false || $origemLng === false || $destinoLat === false || $destinoLng === false) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => 'Coordenadas de origem/destino em falta.']);
    exit;
}

if (!kg_coordenadas_validas($origemLat, $origemLng) || !kg_coordenadas_validas($destinoLat, $destinoLng)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => 'Coordenadas fora dos limites de Cabo Verde.']);
    exit;
}

try {
    $rota = kg_osrm_calcular_rota_cache($origemLat, $origemLng, $destinoLat, $destinoLng);
    echo json_encode([
        'sucesso' => true,
        'distancia_m' => $rota['distancia_m'],
        'duracao_s' => $rota['duracao_s'],
        'geometria' => $rota['geometria'],
    ]);
} catch (Throwable $e) {
    // OSRM indisponível: o front-end trata isto como pedido para usar o
    // fallback de linha reta — nunca é um erro fatal para o utilizador.
    echo json_encode(['sucesso' => false, 'erro' => 'Motor de rotas indisponível.']);
}
