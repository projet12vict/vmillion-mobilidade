<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeoService;
use Tests\TestCase;

/**
 * Limites geográficos e distâncias. Porta dos blocos "Coordenadas dentro de
 * Cabo Verde" e "Distância Haversine" de legacy/tests/run_tests.php.
 */
class GeoServiceTest extends TestCase
{
    private GeoService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoService;
    }

    public function test_aceita_coordenadas_dentro_de_cabo_verde(): void
    {
        $this->assertTrue($this->geo->coordenadasValidas(14.9177, -23.5092)); // Praia
        $this->assertTrue($this->geo->coordenadasValidas(17.2, -23.5)); // limite superior
    }

    public function test_rejeita_coordenadas_fora_dos_limites(): void
    {
        $this->assertFalse($this->geo->coordenadasValidas(0, 0));
        $this->assertFalse($this->geo->coordenadasValidas(17.3, -23.5));
    }

    public function test_distancia_de_um_ponto_a_si_mesmo_e_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->geo->distanciaMetros(14.9177, -23.5092, 14.9177, -23.5092), 0.01);
    }

    public function test_um_grau_de_latitude_sao_cerca_de_cento_e_onze_quilometros(): void
    {
        $this->assertEqualsWithDelta(111195, $this->geo->distanciaMetros(0, 0, 1, 0), 500);
    }
}
