<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConfigPreco;
use App\Models\DestinoUrbano;
use App\Models\LimiteCidade;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
use App\Models\PrecoRota;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Motor de preços dinâmicos (secção 12). Porta de includes/pricing.php.
 *
 * Regras:
 *  - Preço fixo por rota (ponto A -> ponto B), definido pelo Super Admin.
 *  - Preço por km dentro de cidade (zona 'urbana').
 *  - Preço por km fora de cidade (zona 'intermunicipal').
 *  - Se a rota atravessar as duas zonas, o preço é proporcional (km urbanos
 *    x preço urbano + km intermunicipais x preço intermunicipal).
 *  - Existindo preço fixo para a rota completa, este funciona como teto: o
 *    passageiro paga o menor valor entre o proporcional e o fixo.
 *
 * Os métodos de cálculo puro (proporcional, limites, segmentação por
 * distância, interseção reta/círculo) não tocam na base de dados — é por
 * isso que são testáveis isoladamente.
 */
class PricingService
{
    public function __construct(private readonly GeoService $geo) {}

    /**
     * Preços por km atualmente configurados.
     *
     * @return array{urbana: float, intermunicipal: float}
     */
    public function precosKm(): array
    {
        $precos = ['urbana' => 0.0, 'intermunicipal' => 0.0];

        foreach (PrecoKm::query()->get(['zona', 'preco_por_km_cve']) as $linha) {
            $precos[$linha->zona] = (float) $linha->preco_por_km_cve;
        }

        return $precos;
    }

    /**
     * Configurações gerais de preço (valor mínimo/máximo, taxa de operação).
     *
     * @return array{valor_minimo: float, valor_maximo: float, taxa_operacao_rota: float}
     */
    public function configPrecos(): array
    {
        $config = ['valor_minimo' => 0.0, 'valor_maximo' => 0.0, 'taxa_operacao_rota' => 0.0];

        foreach (ConfigPreco::query()->get(['chave', 'valor']) as $linha) {
            $config[$linha->chave] = (float) $linha->valor;
        }

        return $config;
    }

    /**
     * Preço fixo definido para a rota entre dois pontos, em qualquer direção.
     */
    public function precoFixoRota(int $pontoOrigemId, int $pontoDestinoId): ?float
    {
        $preco = PrecoRota::query()
            ->where(function ($q) use ($pontoOrigemId, $pontoDestinoId) {
                $q->where('ponto_origem_id', $pontoOrigemId)->where('ponto_destino_id', $pontoDestinoId);
            })
            ->orWhere(function ($q) use ($pontoOrigemId, $pontoDestinoId) {
                $q->where('ponto_origem_id', $pontoDestinoId)->where('ponto_destino_id', $pontoOrigemId);
            })
            ->value('preco_fixo_cve');

        return $preco === null ? null : (float) $preco;
    }

    /**
     * Preço proporcional aos km percorridos em cada zona até ao ponto de
     * descida do passageiro (não à rota completa).
     *
     * @param  array{urbana: float, intermunicipal: float}  $precosKm
     */
    public function precoProporcional(float $kmUrbano, float $kmIntermunicipal, array $precosKm): float
    {
        return round(
            max(0.0, $kmUrbano) * $precosKm['urbana']
            + max(0.0, $kmIntermunicipal) * $precosKm['intermunicipal'],
            2
        );
    }

    /**
     * Aplica o piso e o teto configurados (secção 12.1). Um valor_maximo de 0
     * é tratado como "sem teto".
     *
     * @param  array{valor_minimo?: float, valor_maximo?: float}  $config
     */
    public function aplicarLimites(float $preco, array $config): float
    {
        $min = $config['valor_minimo'] ?? 0.0;
        $max = $config['valor_maximo'] ?? 0.0;

        if ($min > 0 && $preco < $min) {
            $preco = $min;
        }

        if ($max > 0 && $preco > $max) {
            $preco = $max;
        }

        return round($preco, 2);
    }

    /**
     * Resolve a interseção entre o segmento O->D (coordenadas já projetadas
     * em km) e a circunferência de centro C e raio r. Devolve os dois
     * parâmetros t (ordenados) tais que O + t*(D-O) está sobre a
     * circunferência, ou null se a reta não intersectar o círculo.
     *
     * @param  array{x: float, y: float}  $o
     * @param  array{x: float, y: float}  $d
     * @param  array{x: float, y: float}  $c
     * @return array{0: float, 1: float}|null
     */
    public function raizesIntersecaoCirculo(array $o, array $d, array $c, float $raio): ?array
    {
        $ax = $d['x'] - $o['x'];
        $ay = $d['y'] - $o['y'];
        $relx = $o['x'] - $c['x'];
        $rely = $o['y'] - $c['y'];

        $a = $ax * $ax + $ay * $ay;
        if ($a < 1e-9) {
            return null;
        }

        $b = 2 * ($relx * $ax + $rely * $ay);
        $cc = $relx * $relx + $rely * $rely - $raio * $raio;

        $disc = $b * $b - 4 * $a * $cc;
        if ($disc < 0) {
            return null;
        }

        $sqrtDisc = sqrt($disc);
        $t1 = (-$b - $sqrtDisc) / (2 * $a);
        $t2 = (-$b + $sqrtDisc) / (2 * $a);

        return $t1 <= $t2 ? [$t1, $t2] : [$t2, $t1];
    }

    /**
     * Segmenta a rota origem->destino em três troços (secção 12.1):
     *  - do ponto de partida até sair do limite da cidade de origem (urbano);
     *  - daí até entrar no limite da cidade de destino (intermunicipal);
     *  - da entrada na cidade de destino até ao destino (urbano).
     *
     * Devolve as distâncias, medidas a partir da origem, onde a rota sai da
     * cidade de origem e onde entra na cidade de destino. Sem limites de
     * cidade configurados, aproxima por metade da rota — comportamento de
     * recurso, nunca bloqueia o cálculo do preço.
     *
     * @return array{saida_km: float, entrada_km: float}
     */
    public function segmentarZonaUrbana(PontoPartida $pontoPartida, PontoPartida $destino, float $distanciaTotalKm): array
    {
        if ($distanciaTotalKm <= 0.0001) {
            return ['saida_km' => 0.0, 'entrada_km' => 0.0];
        }

        if ($pontoPartida->cidade === $destino->cidade) {
            // Toda a rota é urbana (ver zonasAteDistancia).
            return ['saida_km' => $distanciaTotalKm, 'entrada_km' => $distanciaTotalKm];
        }

        $limiteOrigem = LimiteCidade::query()->where('cidade', $pontoPartida->cidade)->first();
        $limiteDestino = LimiteCidade::query()->where('cidade', $destino->cidade)->first();

        if (! $limiteOrigem || ! $limiteDestino) {
            $metade = $distanciaTotalKm / 2;

            return ['saida_km' => $metade, 'entrada_km' => $metade];
        }

        $latRef = ($pontoPartida->lat + $destino->lat) / 2;
        $o = $this->geo->paraXyKm($pontoPartida->lat, $pontoPartida->lng, $latRef);
        $d = $this->geo->paraXyKm($destino->lat, $destino->lng, $latRef);
        $co = $this->geo->paraXyKm($limiteOrigem->lat, $limiteOrigem->lng, $latRef);
        $cd = $this->geo->paraXyKm($limiteDestino->lat, $limiteDestino->lng, $latRef);

        // Ponto onde a rota sai do círculo da cidade de origem (assumindo que
        // o ponto de partida está dentro do seu próprio limite de cidade).
        $saidaT = 0.0;
        $distOrigemCentro = sqrt(($o['x'] - $co['x']) ** 2 + ($o['y'] - $co['y']) ** 2);
        if ($distOrigemCentro <= $limiteOrigem->raio_km) {
            $raizes = $this->raizesIntersecaoCirculo($o, $d, $co, $limiteOrigem->raio_km);
            $saidaT = 1.0;
            if ($raizes !== null) {
                foreach ($raizes as $t) {
                    if ($t > 1e-6) {
                        $saidaT = min(1.0, $t);
                        break;
                    }
                }
            }
        }

        // Ponto onde a rota entra no círculo da cidade de destino.
        $entradaT = 1.0;
        $distDestinoCentro = sqrt(($d['x'] - $cd['x']) ** 2 + ($d['y'] - $cd['y']) ** 2);
        if ($distDestinoCentro <= $limiteDestino->raio_km) {
            $raizes = $this->raizesIntersecaoCirculo($o, $d, $cd, $limiteDestino->raio_km);
            $entradaT = 0.0;
            if ($raizes !== null) {
                $entradaT = max(0.0, min(1.0, $raizes[0]));
            }
        }

        $saidaKm = max(0.0, min($distanciaTotalKm, $saidaT * $distanciaTotalKm));
        $entradaKm = max(0.0, min($distanciaTotalKm, $entradaT * $distanciaTotalKm));

        if ($entradaKm < $saidaKm) {
            // Geometria degenerada (círculos sobrepostos): sem troço intermunicipal.
            $entradaKm = $saidaKm;
        }

        return ['saida_km' => $saidaKm, 'entrada_km' => $entradaKm];
    }

    /**
     * Dada a segmentação da rota completa e a distância até ao ponto de
     * descida de um passageiro, devolve [kmUrbano, kmIntermunicipal] desse
     * troço. Quem desce ainda dentro do limite da cidade de origem paga só
     * tarifa urbana (secção 12.1, "destino na periferia").
     *
     * @param  array{saida_km: float, entrada_km: float}  $segmentos
     * @return array{0: float, 1: float}
     */
    public function zonasAteDistancia(array $segmentos, float $distanciaAteDescidaKm): array
    {
        $d = max(0.0, $distanciaAteDescidaKm);
        $saida = $segmentos['saida_km'];
        $entrada = $segmentos['entrada_km'];

        $kmUrbano = min($d, $saida) + max(0.0, $d - $entrada);
        $kmIntermunicipal = max(0.0, min($d, $entrada) - $saida);

        return [$kmUrbano, $kmIntermunicipal];
    }

    /**
     * Fraciona o preço fixo de uma rota (secção 12.1, exemplo Praia ->
     * Calheta) proporcionalmente à distância que o passageiro percorre dentro
     * dessa rota. Devolve null se a rota não tiver distância registada — o
     * chamador deve então cair no cálculo por km.
     *
     * @param  array{lat: float, lng: float}  $pontoEmbarque
     * @param  array{lat: float, lng: float}  $pontoDesembarque
     */
    public function precoRotaFixaFracionado(int $rotaId, array $pontoEmbarque, array $pontoDesembarque): ?float
    {
        $rota = PrecoRota::query()->with('origem:id,lat,lng')->find($rotaId);

        if (! $rota || ! $rota->origem || $rota->distancia_km === null || $rota->distancia_km <= 0) {
            return null;
        }

        $distanciaAteEmbarque = $this->geo->distanciaKm(
            $rota->origem->lat, $rota->origem->lng,
            (float) $pontoEmbarque['lat'], (float) $pontoEmbarque['lng']
        );
        $distanciaAteDesembarque = $this->geo->distanciaKm(
            $rota->origem->lat, $rota->origem->lng,
            (float) $pontoDesembarque['lat'], (float) $pontoDesembarque['lng']
        );

        $fracao = ($distanciaAteDesembarque - $distanciaAteEmbarque) / $rota->distancia_km;
        $fracao = max(0.0, min(1.0, $fracao));

        return round($fracao * $rota->preco_fixo_cve, 2);
    }

    /**
     * Preço da rota fixa do veículo para o troço deste passageiro, já com os
     * limites aplicados — null quando o veículo não tem rota fixa (ou ela não
     * tem distância registada, e por isso não dá para fracionar).
     *
     * Vive à parte de calcularPrecoViagem porque não é só um passo dela: um
     * pedido feito a partir do ponto nasce sem veículo, com o preço por km, e
     * a tarifa da rota fixa de quem o reclamar tem de prevalecer sobre essa
     * estimativa (ver Condutor\PassageiroController::recolher).
     *
     * @param  array{lat: float, lng: float}|null  $pontoDescidaCoord
     */
    public function precoRotaFixaDoVeiculo(
        ?Veiculo $veiculo,
        PontoPartida $pontoPartida,
        PontoPartida $destino,
        ?array $pontoDescidaCoord = null
    ): ?float {
        if (! $veiculo || ! $veiculo->rota_fixa_id) {
            return null;
        }

        $preco = $this->precoRotaFixaFracionado(
            (int) $veiculo->rota_fixa_id,
            ['lat' => $pontoPartida->lat, 'lng' => $pontoPartida->lng],
            $pontoDescidaCoord ?? ['lat' => $destino->lat, 'lng' => $destino->lng]
        );

        return $preco === null ? null : $this->aplicarLimites($preco, $this->configPrecos());
    }

    /**
     * Ponto de entrada do motor de preços para viagens intermunicipais.
     * Ordem de decisão:
     *  1) veículo com rota fixa que tenha distância registada -> fraciona o
     *     preço fixo proporcionalmente ao troço do passageiro;
     *  2) caso contrário, por km com segmentação real urbano/intermunicipal,
     *     usando o preço fixo da rota (se existir) como teto.
     *
     * @param  array{lat: float, lng: float}|null  $pontoDescidaCoord
     * @return array{preco: float, metodo: string}
     */
    public function calcularPrecoViagem(
        ?Veiculo $veiculo,
        PontoPartida $pontoPartida,
        PontoPartida $destino,
        float $distanciaTotalKm,
        float $distanciaAteDescidaKm,
        ?array $pontoDescidaCoord = null
    ): array {
        $config = $this->configPrecos();

        $precoRotaFixa = $this->precoRotaFixaDoVeiculo($veiculo, $pontoPartida, $destino, $pontoDescidaCoord);

        if ($precoRotaFixa !== null) {
            return ['preco' => $precoRotaFixa, 'metodo' => 'rota_fixa_fracionada'];
        }

        $segmentos = $this->segmentarZonaUrbana($pontoPartida, $destino, $distanciaTotalKm);
        [$kmUrbano, $kmIntermunicipal] = $this->zonasAteDistancia($segmentos, $distanciaAteDescidaKm);

        $proporcional = $this->precoProporcional($kmUrbano, $kmIntermunicipal, $this->precosKm());

        $fixo = $this->precoFixoRota($pontoPartida->id, $destino->id);
        $preco = $fixo !== null ? min($proporcional, $fixo) : $proporcional;

        return [
            'preco' => $this->aplicarLimites($preco, $config),
            'metodo' => $fixo !== null ? 'rota_fixa_teto' : 'proporcional',
        ];
    }

    /**
     * Preço de uma viagem urbana: o destino não é um ponto da lista mas um
     * local escrito/geocodificado pelo passageiro, por isso não há
     * segmentação de zonas — é sempre tarifa urbana sobre a distância direta
     * entre a partida e o ponto de descida.
     *
     * @return array{preco: float, metodo: string}
     */
    public function calcularPrecoUrbano(float $distanciaKm): array
    {
        $preco = round(max(0.0, $distanciaKm) * $this->precosKm()['urbana'], 2);

        return [
            'preco' => $this->aplicarLimites($preco, $this->configPrecos()),
            'metodo' => 'urbano_direto',
        ];
    }

    /**
     * Grava (ou reforça, se o mesmo nome já existir) um destino escrito por
     * um passageiro numa viagem urbana, para poder ser sugerido a outros.
     * Nunca bloqueia a criação da reserva por causa disto.
     */
    public function guardarDestinoUrbano(string $nome, float $lat, float $lng, int $criadoPor): void
    {
        try {
            DB::table((new DestinoUrbano)->getTable())->upsert(
                [[
                    'nome' => $nome,
                    'lat' => $lat,
                    'lng' => $lng,
                    'criado_por' => $criadoPor,
                    'usos' => 1,
                ]],
                ['nome'],
                ['usos' => DB::raw('usos + 1'), 'atualizado_em' => now()]
            );
        } catch (Throwable $e) {
            Log::warning('Falha ao gravar destino urbano: '.$e->getMessage());
        }
    }
}
