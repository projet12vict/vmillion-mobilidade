<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Iniciar rota" e o GPS do veículo (secções 9.3/9.5): parado no ponto o
 * veículo fica ancorado nas coordenadas do ponto; depois de iniciar a rota
 * passa a seguir o GPS do condutor e nunca mais volta ao ponto.
 */
class PosicaoAposIniciarRotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_posicao_recusada_enquanto_a_rota_nao_e_iniciada(): void
    {
        [$condutor, $veiculo, $partida] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/posicao', [
                'veiculo_id' => $veiculo->id, 'lat' => 14.93, 'lng' => -23.52, 'accuracy' => 10,
            ])
            ->assertStatus(403);

        $veiculo->refresh();
        $this->assertSame('no_ponto', $veiculo->estado);
        $this->assertEqualsWithDelta((float) $partida->lat, (float) $veiculo->lat, 0.000001);
        $this->assertEqualsWithDelta((float) $partida->lng, (float) $veiculo->lng, 0.000001);
    }

    public function test_apos_iniciar_rota_o_veiculo_sai_do_ponto_e_segue_o_gps(): void
    {
        [$condutor, $veiculo, $partida] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', ['veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota'])
            ->assertOk()
            ->assertJsonPath('estado', 'partiu_da_fila');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/posicao', [
                'veiculo_id' => $veiculo->id, 'lat' => 14.93, 'lng' => -23.52, 'accuracy' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'em_movimento');

        $veiculo->refresh();
        $this->assertSame('em_movimento', $veiculo->estado);
        $this->assertEqualsWithDelta(14.93, (float) $veiculo->lat, 0.000001);
        $this->assertEqualsWithDelta(-23.52, (float) $veiculo->lng, 0.000001);
        $this->assertNotEqualsWithDelta((float) $partida->lat, (float) $veiculo->lat, 0.0001);
    }

    public function test_lista_de_veiculos_devolve_o_estado_que_liberta_o_gps_no_painel(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', ['veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota'])
            ->assertOk();

        // O painel (sincronizarEstadoMeuVeiculo) depende deste campo para
        // deixar o GPS mexer o marcador; sem ele o carro ficava no ponto.
        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/veiculos')
            ->assertOk()
            ->assertJsonPath('veiculos.0.estado', 'partiu_da_fila')
            ->assertJsonPath('veiculos.0.id', $veiculo->id);
    }

    /**
     * @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida}
     */
    private function cenario(): array
    {
        $partida = PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $destino = PontoPartida::query()->create([
            'nome' => 'Assomada', 'cidade' => 'Santa Catarina',
            'lat' => 15.0983, 'lng' => -23.6703, 'zona' => 'intermunicipal',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor',
            'nome' => 'Condutor Teste',
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id,
            'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
            'lat' => $partida->lat, 'lng' => $partida->lng,
        ]);

        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 1000,
            'referencia' => 'REF'.random_int(100000, 999999),
            'data_pagamento' => now(),
            'data_validade' => now()->addMonth(),
            'status' => 'aprovado',
        ]);

        return [$condutor, $veiculo, $partida];
    }
}
