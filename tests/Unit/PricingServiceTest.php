<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeoService;
use App\Services\PricingService;
use Tests\TestCase;

/**
 * Motor de preços — só os cálculos puros, que não tocam na base de dados.
 * Porta dos blocos "Motor de preços", "Limites de preço", "Segmentação
 * urbano/intermunicipal" e "Interseção reta/círculo" de
 * legacy/tests/run_tests.php (exemplos das secções 12.1 e 12.3).
 */
class PricingServiceTest extends TestCase
{
    private PricingService $precos;

    private GeoService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoService;
        $this->precos = new PricingService($this->geo);
    }

    public function test_passageiro_que_desce_a_cinco_km_urbanos_paga_vinte_e_cinco(): void
    {
        $preco = $this->precos->precoProporcional(5, 0, $this->tabela());

        $this->assertEqualsWithDelta(25.0, $preco, 0.001);
    }

    public function test_dez_km_urbanos_mais_quinze_intermunicipais_dao_duzentos(): void
    {
        $preco = $this->precos->precoProporcional(10, 15, $this->tabela());

        $this->assertEqualsWithDelta(200.0, $preco, 0.001);
    }

    public function test_aplica_o_valor_minimo_e_o_valor_maximo_configurados(): void
    {
        $config = ['valor_minimo' => 100.0, 'valor_maximo' => 5000.0, 'taxa_operacao_rota' => 50.0];

        $this->assertSame(100.0, $this->precos->aplicarLimites(30.0, $config));
        $this->assertSame(5000.0, $this->precos->aplicarLimites(9000.0, $config));
        $this->assertEqualsWithDelta(300.0, $this->precos->aplicarLimites(300.0, $config), 0.001);
    }

    /**
     * Rota do exemplo da secção 12.1: sai da cidade de origem aos 5 km, entra
     * na cidade de destino aos 25 km, com 30 km no total.
     */
    public function test_quem_desce_dentro_do_limite_da_cidade_de_origem_so_paga_urbano(): void
    {
        [$urbano, $intermunicipal] = $this->precos->zonasAteDistancia($this->segmentos(), 3.0);

        $this->assertSame(3.0, $urbano);
        $this->assertSame(0.0, $intermunicipal);
    }

    public function test_descida_a_meio_do_troco_intermunicipal_divide_as_duas_zonas(): void
    {
        [$urbano, $intermunicipal] = $this->precos->zonasAteDistancia($this->segmentos(), 15.0);

        $this->assertSame(5.0, $urbano);
        $this->assertSame(10.0, $intermunicipal);
    }

    public function test_rota_completa_soma_os_dois_trocos_urbanos_das_pontas(): void
    {
        [$urbano, $intermunicipal] = $this->precos->zonasAteDistancia($this->segmentos(), 30.0);

        $this->assertEqualsWithDelta(10.0, $urbano, 0.001);
        $this->assertEqualsWithDelta(20.0, $intermunicipal, 0.001);
    }

    public function test_circulo_centrado_na_origem_intersecta_a_reta_em_t_zero(): void
    {
        $o = $this->geo->paraXyKm(14.9177, -23.5092, 15.0);
        $d = $this->geo->paraXyKm(15.2785, -23.7519, 15.0);

        $raizes = $this->precos->raizesIntersecaoCirculo($o, $d, $o, 5.0);

        $this->assertNotNull($raizes);
        $this->assertLessThan(0, $raizes[0]);
        $this->assertGreaterThan(0, $raizes[1]);
    }

    public function test_reta_que_nao_toca_no_circulo_nao_tem_intersecao(): void
    {
        $o = ['x' => 0.0, 'y' => 0.0];
        $d = ['x' => 10.0, 'y' => 0.0];
        $centro = ['x' => 5.0, 'y' => 50.0];

        $this->assertNull($this->precos->raizesIntersecaoCirculo($o, $d, $centro, 1.0));
    }

    /**
     * @return array{urbana: float, intermunicipal: float}
     */
    private function tabela(): array
    {
        return ['urbana' => 5.0, 'intermunicipal' => 10.0];
    }

    /**
     * @return array{saida_km: float, entrada_km: float}
     */
    private function segmentos(): array
    {
        return ['saida_km' => 5.0, 'entrada_km' => 25.0];
    }
}
