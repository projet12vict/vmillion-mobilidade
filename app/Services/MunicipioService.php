<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Delimitação administrativa: em que concelho cai uma coordenada.
 *
 * Lê o mesmo GeoJSON que o mapa dos painéis desenha
 * (public/assets/geo/cv-municipios.geojson, gerado por docs/gerar-municipios.py),
 * para o que o condutor vê no ecrã e o que o servidor deixa ver serem a mesma
 * fronteira — se fossem duas fontes, o mapa acabava por mostrar uma linha que
 * a regra de visibilidade não respeita.
 *
 * É este serviço que sustenta a regra do arquipélago: condutor e passageiro só
 * se veem um ao outro dentro do mesmo concelho (ver
 * Condutor\PassageiroController::urbanos e Passageiro\VeiculoController::disponiveis).
 */
class MunicipioService
{
    private const FICHEIRO = 'assets/geo/cv-municipios.geojson';

    /**
     * Concelhos já preparados para consulta: nome, ilha, caixa envolvente e
     * anéis do polígono. Estático porque o traçado não muda dentro do
     * processo e cada pedido faz várias consultas — ler e descodificar 200 KB
     * uma vez por lista de passageiros seria o custo dominante do endpoint.
     *
     * @var array<string, array{nome: string, ilha: string, caixa: array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}, poligonos: array<int, array<int, array<int, array{0: float, 1: float}>>>}>|null
     */
    private static ?array $municipios = null;

    /**
     * O concelho que contém a coordenada, ou null quando ela cai fora do
     * território (mar, ou traçado indisponível).
     *
     * @return array{nome: string, ilha: string}|null
     */
    public function em(float $lat, float $lng): ?array
    {
        foreach ($this->carregar() as $municipio) {
            if ($this->dentro($municipio, $lat, $lng)) {
                return ['nome' => $municipio['nome'], 'ilha' => $municipio['ilha']];
            }
        }

        return null;
    }

    /** Só o nome do concelho — o atalho para quem não precisa da ilha. */
    public function nomeEm(float $lat, float $lng): ?string
    {
        return $this->em($lat, $lng)['nome'] ?? null;
    }

    /**
     * A coordenada está dentro deste concelho? Resolver o concelho uma vez e
     * chamar isto por cada candidato é muito mais barato do que perguntar
     * nomeEm() a cada um: poupa a volta aos outros 21 polígonos.
     */
    public function contem(string $nome, float $lat, float $lng): bool
    {
        $municipio = $this->carregar()[$nome] ?? null;

        return $municipio !== null && $this->dentro($municipio, $lat, $lng);
    }

    /**
     * Caixa envolvente do concelho, para uma pré-filtragem em SQL antes do
     * teste exato em PHP: sem ela, um LIMIT aplicado a todo o país podia
     * encher-se de linhas de outros concelhos e devolver uma lista vazia
     * depois de filtrada.
     *
     * @return array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}|null
     */
    public function caixaDe(string $nome): ?array
    {
        return $this->carregar()[$nome]['caixa'] ?? null;
    }

    /**
     * Há traçado carregado? Quando o ficheiro falta ou está ilegível, a regra
     * de visibilidade não pode ser aplicada — e quem chama deixa passar tudo
     * em vez de esconder toda a gente (ver os controllers).
     */
    public function disponivel(): bool
    {
        return $this->carregar() !== [];
    }

    /**
     * @param  array{caixa: array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}, poligonos: array<int, array<int, array<int, array{0: float, 1: float}>>>}  $municipio
     */
    private function dentro(array $municipio, float $lat, float $lng): bool
    {
        $caixa = $municipio['caixa'];

        if ($lat < $caixa['lat_min'] || $lat > $caixa['lat_max']
            || $lng < $caixa['lng_min'] || $lng > $caixa['lng_max']) {
            return false;
        }

        foreach ($municipio['poligonos'] as $poligono) {
            if ($this->dentroDoPoligono($poligono, $lat, $lng)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Um polígono é [exterior, buraco, buraco, ...]: estar dentro do exterior
     * não chega, tem de estar fora de todos os buracos — Santa Catarina do
     * Fogo, por exemplo, é um recorte dentro de São Filipe.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $poligono
     */
    private function dentroDoPoligono(array $poligono, float $lat, float $lng): bool
    {
        if (! $this->dentroDoAnel($poligono[0], $lat, $lng)) {
            return false;
        }

        for ($i = 1; $i < count($poligono); $i++) {
            if ($this->dentroDoAnel($poligono[$i], $lat, $lng)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ray casting sobre um anel fechado [[lng, lat], ...] — o mesmo algoritmo
     * que o mapa corre no browser (KGMap.municipioEm), para os dois lados
     * decidirem sempre igual em cima da fronteira.
     *
     * @param  array<int, array{0: float, 1: float}>  $anel
     */
    private function dentroDoAnel(array $anel, float $lat, float $lng): bool
    {
        $dentro = false;
        $total = count($anel);

        for ($i = 0; $i < $total - 1; $i++) {
            [$x1, $y1] = $anel[$i];
            [$x2, $y2] = $anel[$i + 1];

            if (($y1 > $lat) !== ($y2 > $lat)
                && $lng < $x1 + (($lat - $y1) / ($y2 - $y1)) * ($x2 - $x1)) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * @return array<string, array{nome: string, ilha: string, caixa: array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}, poligonos: array<int, array<int, array<int, array{0: float, 1: float}>>>}>
     */
    private function carregar(): array
    {
        if (self::$municipios !== null) {
            return self::$municipios;
        }

        self::$municipios = [];

        $caminho = public_path(self::FICHEIRO);
        $bruto = is_readable($caminho) ? file_get_contents($caminho) : false;
        $colecao = $bruto === false ? null : json_decode($bruto, true);

        if (! is_array($colecao) || ! is_array($colecao['features'] ?? null)) {
            // Sem traçado a regra do concelho não se aplica — quem chama
            // repara nisso por disponivel() e deixa passar a lista inteira.
            // Fica no log porque é um ficheiro que devia estar sempre lá.
            Log::warning('Delimitação por município indisponível: '.$caminho);

            return self::$municipios;
        }

        foreach ($colecao['features'] as $feature) {
            $nome = $feature['properties']['nome'] ?? null;
            $geometria = $feature['geometry'] ?? null;

            if (! is_string($nome) || ! is_array($geometria)) {
                continue;
            }

            $poligonos = $geometria['type'] === 'Polygon'
                ? [$geometria['coordinates']]
                : $geometria['coordinates'];

            self::$municipios[$nome] = [
                'nome' => $nome,
                'ilha' => (string) ($feature['properties']['ilha'] ?? ''),
                'caixa' => $this->caixaEnvolvente($poligonos),
                'poligonos' => $poligonos,
            ];
        }

        return self::$municipios;
    }

    /**
     * @param  array<int, array<int, array<int, array{0: float, 1: float}>>>  $poligonos
     * @return array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}
     */
    private function caixaEnvolvente(array $poligonos): array
    {
        $latMin = $lngMin = INF;
        $latMax = $lngMax = -INF;

        foreach ($poligonos as $poligono) {
            foreach ($poligono[0] as [$lng, $lat]) {
                $latMin = min($latMin, $lat);
                $latMax = max($latMax, $lat);
                $lngMin = min($lngMin, $lng);
                $lngMax = max($lngMax, $lng);
            }
        }

        return ['lat_min' => $latMin, 'lat_max' => $latMax, 'lng_min' => $lngMin, 'lng_max' => $lngMax];
    }
}
