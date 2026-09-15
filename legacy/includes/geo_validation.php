<?php
/**
 * V-MILLION — Validação de coordenadas geográficas (limites de Cabo Verde).
 * Ver secção 5.3: nada flutua, tudo é ancorado a coordenadas reais e válidas.
 */

declare(strict_types=1);

function kg_coordenadas_validas(float $lat, float $lng): bool
{
    return $lat >= KG_LAT_MIN && $lat <= KG_LAT_MAX
        && $lng >= KG_LNG_MIN && $lng <= KG_LNG_MAX;
}

function kg_exigir_coordenadas_validas(float $lat, float $lng): void
{
    if (!kg_coordenadas_validas($lat, $lng)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Coordenadas fora dos limites de Cabo Verde.']);
        exit;
    }
}

/**
 * Distância aproximada em metros entre duas coordenadas (fórmula de Haversine).
 */
function kg_distancia_metros(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $raioTerra = 6371000; // metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raioTerra * $c;
}
