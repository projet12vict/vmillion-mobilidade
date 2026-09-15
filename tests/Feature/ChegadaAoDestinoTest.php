<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssentoVeiculo;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chegada ao destino de uma viagem intermunicipal (secção 9.7): a posição do
 * GPS dentro dos 100 m do destino fecha as viagens a bordo, devolve todos os
 * lugares e inverte ponto de partida e destino para o regresso.
 *
 * O painel do condutor depende disto de duas formas: da resposta ao POST da
 * posição (novo_ponto_partida_id/novo_destino_id, que ele aplica logo, antes
 * do alerta) e de /api/condutor/veiculos, de onde o polling relê o sentido
 * para as listas e para a linha do mapa.
 */
class ChegadaAoDestinoTest extends TestCase
{
    use RefreshDatabase;

    public function test_chegar_ao_destino_inverte_partida_e_destino(): void
    {
        [$condutor, $veiculo, $reserva, $partida, $destino] = $this->cenario();

        $this->iniciarRota($condutor, $veiculo);

        $this->posicao($condutor, $veiculo, (float) $destino->lat, (float) $destino->lng)
            ->assertOk()
            ->assertJsonPath('chegou_destino', true)
            ->assertJsonPath('estado', 'chegou_destino')
            ->assertJsonPath('novo_ponto_partida_id', $destino->id)
            ->assertJsonPath('novo_destino_id', $partida->id);

        $veiculo->refresh();
        $this->assertSame('chegou_destino', $veiculo->estado);
        $this->assertSame($destino->id, $veiculo->ponto_partida_id);
        $this->assertSame($partida->id, $veiculo->destino_id);
        $this->assertNull($veiculo->posicao_fila);

        // A viagem de quem ia a bordo fecha-se sozinha, sem o condutor ter de
        // carregar em "Entregue" passageiro a passageiro.
        $this->assertSame('concluido', $reserva->fresh()->estado);
    }

    public function test_chegar_ao_destino_liberta_todos_os_lugares(): void
    {
        [$condutor, $veiculo, , , $destino] = $this->cenario();

        $this->iniciarRota($condutor, $veiculo);
        $this->assertSame(13, $veiculo->fresh()->lugares_livres);

        $this->posicao($condutor, $veiculo, (float) $destino->lat, (float) $destino->lng)->assertOk();

        $veiculo->refresh();
        $this->assertSame($veiculo->lugares_total, $veiculo->lugares_livres);
        $this->assertSame(0, AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->where('ocupado', true)
            ->count());
        $this->assertSame(0, AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->whereNotNull('reserva_id')
            ->count());
    }

    /**
     * O raio de chegada é de 100 m: a caminho, mesmo já no concelho do
     * destino, nada se fecha — senão as viagens de todos os passageiros eram
     * concluídas a meio da estrada.
     */
    public function test_a_caminho_do_destino_nada_e_invertido(): void
    {
        [$condutor, $veiculo, $reserva, $partida, $destino] = $this->cenario();

        $this->iniciarRota($condutor, $veiculo);

        $this->posicao($condutor, $veiculo, 15.05, -23.64)
            ->assertOk()
            ->assertJsonPath('chegou_destino', false)
            ->assertJsonPath('estado', 'em_movimento');

        $veiculo->refresh();
        $this->assertSame($partida->id, $veiculo->ponto_partida_id);
        $this->assertSame($destino->id, $veiculo->destino_id);
        $this->assertSame('a_bordo', $reserva->fresh()->estado);
    }

    /**
     * A fonte de onde o painel relê o sentido depois da inversão
     * (carregarMeusVeiculos -> sincronizarListasPontoVeiculo): sem estes dois
     * campos já trocados, as listas e a linha do mapa ficavam a mostrar o
     * sentido da ida até um reload.
     */
    public function test_a_lista_de_veiculos_ja_devolve_o_sentido_invertido(): void
    {
        [$condutor, $veiculo, , $partida, $destino] = $this->cenario();

        $this->iniciarRota($condutor, $veiculo);
        $this->posicao($condutor, $veiculo, (float) $destino->lat, (float) $destino->lng)->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/veiculos')
            ->assertOk()
            ->assertJsonPath('veiculos.0.id', $veiculo->id)
            ->assertJsonPath('veiculos.0.estado', 'chegou_destino')
            ->assertJsonPath('veiculos.0.ponto_partida_id', $destino->id)
            ->assertJsonPath('veiculos.0.destino_id', $partida->id);
    }

    /**
     * Chegar ao destino não é entrar na fila: o veículo fica parado no ponto
     * novo à espera da decisão do condutor (entrar na fila ou partir logo).
     */
    public function test_depois_de_chegar_o_veiculo_pode_entrar_na_fila_do_ponto_novo(): void
    {
        [$condutor, $veiculo, , , $destino] = $this->cenario();

        $this->iniciarRota($condutor, $veiculo);
        $this->posicao($condutor, $veiculo, (float) $destino->lat, (float) $destino->lng)->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_entrar', ['veiculo_id' => $veiculo->id])
            ->assertOk();

        $veiculo->refresh();
        $this->assertSame('na_fila', $veiculo->estado);
        $this->assertSame($destino->id, $veiculo->ponto_partida_id);
    }

    /** "Iniciar rota": sem isto o POST da posição é recusado com 403. */
    private function iniciarRota(Utilizador $condutor, Veiculo $veiculo): void
    {
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', ['veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota'])
            ->assertOk();
    }

    private function posicao(Utilizador $condutor, Veiculo $veiculo, float $lat, float $lng)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/posicao', [
                'veiculo_id' => $veiculo->id, 'lat' => $lat, 'lng' => $lng, 'accuracy' => 10,
            ]);
    }

    /**
     * Condutor em dia, veículo intermunicipal no ponto com destino guardado e
     * um passageiro já a bordo.
     *
     * @return array{0: Utilizador, 1: Veiculo, 2: Reserva, 3: PontoPartida, 4: PontoPartida}
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
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+23898'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
            'lat' => $partida->lat, 'lng' => $partida->lng,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 3000,
            'referencia' => 'REF-'.$veiculo->id,
            'data_pagamento' => now()->subDay(),
            'data_validade' => now()->addMonth(),
            'status' => 'aprovado',
        ]);

        return [$condutor, $veiculo, $this->reservaABordo($veiculo), $partida, $destino];
    }

    private function reservaABordo(Veiculo $veiculo): Reserva
    {
        $passageiro = Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => 'Ana Lopes',
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $assento = AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->where('ocupado', false)
            ->firstOrFail();

        $reserva = Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $veiculo->ponto_partida_id,
            'destino_id' => $veiculo->destino_id,
            'preco_final' => 250,
            'estado' => 'a_bordo',
        ]);

        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        return $reserva;
    }
}
