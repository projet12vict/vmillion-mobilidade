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
 * "Iniciar rota (sair do ponto)" (secção 9.3): quem ainda não foi recolhido é
 * libertado para poder reservar outro veículo no ponto; quem já vai dentro do
 * carro continua a ocupar o lugar.
 */
class SairDaFilaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservas_por_recolher_sao_recusadas_e_devolvem_o_lugar(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $pendente = $this->reserva($veiculo, 'Ana Lopes', 'pendente');
        $confirmada = $this->reserva($veiculo, 'Bruno Silva', 'confirmado');

        $this->sairDaFila($condutor, $veiculo)
            ->assertOk()
            ->assertJsonPath('estado', 'partiu_da_fila');

        $this->assertSame('recusado', $pendente->fresh()->estado);
        $this->assertSame('recusado', $confirmada->fresh()->estado);
        $this->assertSame(14, $veiculo->fresh()->lugares_livres);
        $this->assertSame(0, AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)->where('ocupado', true)->count());
    }

    /**
     * O bug que este teste tranca: 'lugares_livres' era reposto na lotação
     * total ao sair do ponto. O condutor partia com passageiros dentro e o
     * veículo anunciava-se vazio — aparecia aos passageiros com 14 lugares e
     * aceitava mais reservas do que os que lhe sobravam.
     */
    public function test_quem_ja_vai_a_bordo_continua_a_ocupar_o_lugar(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $aBordo = $this->reserva($veiculo, 'Ana Lopes', 'a_bordo');
        $aCaminho = $this->reserva($veiculo, 'Bruno Silva', 'a_caminho');
        $pendente = $this->reserva($veiculo, 'Carla Monteiro', 'pendente');

        $this->assertSame(11, $veiculo->fresh()->lugares_livres);

        $this->sairDaFila($condutor, $veiculo)->assertOk();

        // Só o lugar da pendente é devolvido: 14 - 2 (a bordo + a caminho).
        $this->assertSame(12, $veiculo->fresh()->lugares_livres);
        $this->assertSame('a_bordo', $aBordo->fresh()->estado);
        $this->assertSame('a_caminho', $aCaminho->fresh()->estado);
        $this->assertSame('recusado', $pendente->fresh()->estado);

        // E os assentos contam o mesmo que o contador do veículo.
        $this->assertSame(2, AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)->where('ocupado', true)->count());
    }

    /** Veículo cheio de gente a bordo: sair do ponto não lhe abre lugares. */
    public function test_veiculo_cheio_nao_fica_com_lugares_livres_ao_sair_do_ponto(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        for ($i = 1; $i <= 14; $i++) {
            $this->reserva($veiculo, "Passageiro {$i}", 'a_bordo');
        }

        $this->sairDaFila($condutor, $veiculo)->assertOk();

        $this->assertSame(0, $veiculo->fresh()->lugares_livres);
    }

    private function sairDaFila(Utilizador $condutor, Veiculo $veiculo)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', [
                'veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota',
            ]);
    }

    /**
     * Condutor em dia com veículo aprovado e ancorado ao ponto de partida.
     *
     * @return array{0: Utilizador, 1: Veiculo}
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
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'na_fila', 'posicao_fila' => 1,
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

        return [$condutor, $veiculo];
    }

    /** Reserva com o lugar já preso, como o faz Passageiro\ReservaController. */
    private function reserva(Veiculo $veiculo, string $nome, string $estado): Reserva
    {
        $passageiro = Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => $nome,
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
            'estado' => $estado,
        ]);

        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        return $reserva;
    }
}
