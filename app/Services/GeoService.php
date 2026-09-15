<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validação de coordenadas e distâncias.
 *
 * Nada flutua: toda a coordenada que entra no sistema é ancorada aos limites
 * reais de Cabo Verde (secção 5.3). Porta de includes/geo_validation.php.
 */
class GeoService
{
    private const RAIO_TERRA_M = 6371000;

    public function coordenadasValidas(float $lat, float $lng): bool
    {
        $limites = config('vmillion.geo');

        return $lat >= $limites['lat_min'] && $lat <= $limites['lat_max']
            && $lng >= $limites['lng_min'] && $lng <= $limites['lng_max'];
    }

    /**
     * Distância aproximada em metros entre duas coordenadas (Haversine).
     */
    public function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::RAIO_TERRA_M * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return $this->distanciaMetros($lat1, $lng1, $lat2, $lng2) / 1000;
    }

    /**
     * Projeção plana local (equirretangular) em km, válida para as pequenas
     * distâncias de uma única rota dentro do arquipélago — suficiente para
     * resolver interseções reta/círculo sem geometria esférica exata.
     *
     * @return array{x: float, y: float}
     */
    public function paraXyKm(float $lat, float $lng, float $latReferencia): array
    {
        return [
            'x' => $lng * 111.320 * cos(deg2rad($latReferencia)),
            'y' => $lat * 110.574,
        ];
    }
}
