<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VeiculoService;
use Tests\TestCase;

/**
 * Layout de assentos por tipo: 14 lugares no Hiace (fila 0 com 2 e filas 1 a
 * 4 com 3 cada) e 4 no táxi (1 junto ao condutor e 3 no banco de trás).
 * Porta do bloco "Layout de assentos" de legacy/tests/run_tests.php.
 */
class VeiculoServiceTest extends TestCase
{
    public function test_o_layout_tem_catorze_lugares_com_a_distribuicao_esperada(): void
    {
        $layout = (new VeiculoService)->layoutAssentos();

        $this->assertCount(14, $layout);
        $this->assertCount(2, array_filter($layout, fn ($l) => $l['fila'] === 0));

        foreach (range(1, 4) as $fila) {
            $this->assertCount(3, array_filter($layout, fn ($l) => $l['fila'] === $fila), "fila {$fila}");
        }
    }

    public function test_os_assentos_estao_numerados_de_um_a_catorze_sem_repeticoes(): void
    {
        $numeros = array_column((new VeiculoService)->layoutAssentos(), 'numero');

        $this->assertSame(range(1, 14), $numeros);
    }

    public function test_o_taxi_tem_quatro_lugares_um_deles_junto_ao_condutor(): void
    {
        $servico = new VeiculoService;

        $this->assertSame(4, $servico->lugaresTotal('taxi'));

        $layout = $servico->layoutAssentos('taxi');

        $this->assertCount(4, $layout);
        $this->assertSame(range(1, 4), array_column($layout, 'numero'));
        // O lugar 1 é o da fila 0, ao lado do condutor; os outros três vão no
        // banco de trás.
        $this->assertSame([0, 1, 1, 1], array_column($layout, 'fila'));
        $this->assertSame([1, 1, 2, 3], array_column($layout, 'coluna'));
    }

    public function test_o_autocarro_tem_trinta_lugares_em_filas_de_tres(): void
    {
        $servico = new VeiculoService;
        $layout = $servico->layoutAssentos('autocarro');

        $this->assertSame(30, $servico->lugaresTotal('autocarro'));
        $this->assertSame(range(1, 30), array_column($layout, 'numero'));
        // 30 é múltiplo de 3: ninguém vai ao lado do condutor.
        $this->assertNotContains(0, array_column($layout, 'fila'));
        $this->assertSame(10, max(array_column($layout, 'fila')));
    }

    public function test_lugares_acima_da_capacidade_do_tipo_vao_para_filas_novas_atras(): void
    {
        $servico = new VeiculoService;

        $layout = $servico->layoutAssentos('hiace', 16);

        // Os 14 de origem ficam exatamente onde estavam.
        $this->assertSame($servico->layoutAssentos('hiace'), array_slice($layout, 0, 14));
        $this->assertSame([
            ['numero' => 15, 'fila' => 5, 'coluna' => 1],
            ['numero' => 16, 'fila' => 5, 'coluna' => 2],
        ], array_slice($layout, 14));

        // No táxi a fila 0 continua com um só lugar.
        $this->assertSame([0, 1, 1, 1, 2], array_column($servico->layoutAssentos('taxi', 5), 'fila'));
    }

    public function test_o_teto_de_lugares_nunca_fica_abaixo_da_capacidade_de_origem(): void
    {
        $servico = new VeiculoService;

        $this->assertSame(16, $servico->lugaresMax('hiace'));

        config(['vmillion.veiculos.lugares_max_por_tipo' => ['hiace' => 2]]);

        $this->assertSame(14, $servico->lugaresMax('hiace'));
        $this->assertSame(4, $servico->lugaresMax('taxi'));
    }
}
