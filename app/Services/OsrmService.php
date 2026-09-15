<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Motor de rotas (OSRM). Porta as funções kg_osrm_* de includes/pricing.php.
 *
 * A instância própria (vmillion.osrm.url, tipicamente localhost) é a via
 * principal; a instância pública do projeto OSRM só entra quando a própria
 * falha ou está lenta. Ambas são chamadas do lado do servidor — o browser
 * nunca fala com o OSRM, por isso nenhuma delas entra na CSP.
 *
 * O cache é sempre um atalho de performance, nunca uma dependência: se o
 * Redis estiver em baixo ou nem existir, o serviço continua a responder.
 */
class OsrmService
{
    /**
     * @return array{distancia_m: float, duracao_s: float, geometria: array}
     */
    public function calcularRota(float $origLat, float $origLng, float $destLat, float $destLng, ?string $baseUrl = null): array
    {
        $baseUrl ??= (string) config('vmillion.osrm.url');

        $url = sprintf(
            '%s/route/v1/driving/%F,%F;%F,%F',
            rtrim($baseUrl, '/'),
            $origLng,
            $origLat,
            $destLng,
            $destLat
        );

        $resposta = Http::timeout((float) config('vmillion.osrm.timeout'))
            ->get($url, ['overview' => 'full', 'geometries' => 'geojson']);

        if ($resposta->failed()) {
            throw new RuntimeException("OSRM indisponível ({$baseUrl})");
        }

        $rota = $resposta->json('routes.0');
        if (! is_array($rota)) {
            throw new RuntimeException("OSRM não devolveu rota válida ({$baseUrl})");
        }

        return [
            'distancia_m' => (float) $rota['distance'],
            'duracao_s' => (float) $rota['duration'],
            'geometria' => $rota['geometry']['coordinates'] ?? [],
        ];
    }

    /**
     * Igual a calcularRota, mas com rede de segurança: se a instância própria
     * falhar, tenta a pública antes de desistir. Só lança se ambas falharem.
     *
     * @return array{distancia_m: float, duracao_s: float, geometria: array}
     */
    public function calcularRotaComFallback(float $origLat, float $origLng, float $destLat, float $destLng): array
    {
        try {
            return $this->calcularRota($origLat, $origLng, $destLat, $destLng, (string) config('vmillion.osrm.url'));
        } catch (Throwable $e) {
            Log::warning('OSRM local falhou, a tentar instância pública: '.$e->getMessage());

            return $this->calcularRota($origLat, $origLng, $destLat, $destLng, (string) config('vmillion.osrm.url_publica'));
        }
    }

    /**
     * Rota com cache (24h por omissão). A maioria das viagens usa os mesmos
     * pontos fixos, por isso o acerto de cache é alto; 5 casas decimais
     * (~1 m) maximizam esse acerto sem misturar pontos distintos.
     *
     * @return array{distancia_m: float, duracao_s: float, geometria: array}
     */
    public function calcularRotaEmCache(float $origLat, float $origLng, float $destLat, float $destLng): array
    {
        $chave = sprintf('rota_%.5F_%.5F_%.5F_%.5F', $origLat, $origLng, $destLat, $destLng);

        try {
            $emCache = Cache::get($chave);
            if (is_array($emCache) && isset($emCache['distancia_m'], $emCache['duracao_s'], $emCache['geometria'])) {
                return $emCache;
            }
        } catch (Throwable $e) {
            Log::warning('Falha ao ler cache de rota: '.$e->getMessage());
        }

        $rota = $this->calcularRotaComFallback($origLat, $origLng, $destLat, $destLng);

        try {
            Cache::put($chave, $rota, (int) config('vmillion.osrm.cache_ttl'));
        } catch (Throwable $e) {
            Log::warning('Falha ao gravar cache de rota: '.$e->getMessage());
        }

        return $rota;
    }

    /**
     * Distância em km por estrada, caindo para linha reta (Haversine) se o
     * OSRM não responder. Nunca lança — é isto que garante que uma reserva
     * nunca é bloqueada por causa do motor de rotas.
     */
    public function distanciaKmOuLinhaReta(GeoService $geo, float $origLat, float $origLng, float $destLat, float $destLng): float
    {
        $linhaReta = $geo->distanciaKm($origLat, $origLng, $destLat, $destLng);

        try {
            $rota = $this->calcularRotaEmCache($origLat, $origLng, $destLat, $destLng);

            return $rota['distancia_m'] > 0 ? $rota['distancia_m'] / 1000 : $linhaReta;
        } catch (Throwable) {
            return $linhaReta;
        }
    }
}
