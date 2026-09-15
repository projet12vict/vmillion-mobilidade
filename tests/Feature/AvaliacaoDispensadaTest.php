<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Classificar o condutor no fim da viagem é opcional: o passageiro pode
 * fechar o modal. Sem o registo da dispensa na reserva, /minha_reserva
 * continuava a devolver 'avaliar_pendente' e o painel reabria o modal a cada
 * ciclo de 8 s — na prática tornando a avaliação obrigatória.
 */
class AvaliacaoDispensadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserva_concluida_sem_avaliacao_e_proposta_ao_passageiro(): void
    {
        [$passageiro, $reserva] = $this->cenario();

        $this->actingAs($passageiro, 'utilizador')
            ->getJson('/api/passageiro/minha_reserva')
            ->assertOk()
            ->assertJsonPath('avaliar_pendente.reserva_id', $reserva->id);
    }

    public function test_dispensar_deixa_de_propor_a_avaliacao(): void
    {
        [$passageiro, $reserva] = $this->cenario();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/avaliacao/dispensar', ['reserva_id' => $reserva->id])
            ->assertOk()
            ->assertJsonPath('sucesso', true);

        $this->assertNotNull($reserva->fresh()->avaliacao_dispensada_em);

        $this->actingAs($passageiro, 'utilizador')
            ->getJson('/api/passageiro/minha_reserva')
            ->assertOk()
            ->assertJsonPath('avaliar_pendente', null);
    }

    /** Dispensar duas vezes (dois separadores abertos) não é erro. */
    public function test_dispensar_duas_vezes_mantem_a_primeira_data(): void
    {
        [$passageiro, $reserva] = $this->cenario();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/avaliacao/dispensar', ['reserva_id' => $reserva->id])
            ->assertOk();
        $primeira = $reserva->fresh()->avaliacao_dispensada_em;

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/avaliacao/dispensar', ['reserva_id' => $reserva->id])
            ->assertOk();

        $this->assertTrue($primeira->equalTo($reserva->fresh()->avaliacao_dispensada_em));
    }

    /** A dispensa é da reserva de quem a fez — não da de outro passageiro. */
    public function test_nao_se_dispensa_a_avaliacao_de_outro_passageiro(): void
    {
        [, $reserva] = $this->cenario();
        $intruso = $this->passageiro('Ana Lopes');

        $this->actingAs($intruso, 'utilizador')
            ->postJson('/api/avaliacao/dispensar', ['reserva_id' => $reserva->id])
            ->assertNotFound();

        $this->assertNull($reserva->fresh()->avaliacao_dispensada_em);
    }

    /**
     * Passageiro com uma viagem concluída e ainda por classificar.
     *
     * @return array{0: Utilizador, 1: Reserva}
     */
    private function cenario(): array
    {
        $ponto = PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
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
            'aprovado' => true, 'ponto_partida_id' => $ponto->id, 'destino_id' => $ponto->id,
            'lat' => $ponto->lat, 'lng' => $ponto->lng,
        ]);

        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 3000,
            'referencia' => 'REF-'.$veiculo->id,
            'data_pagamento' => now()->subDay(),
            'data_validade' => now()->addMonth(),
            'status' => 'aprovado',
        ]);

        $passageiro = $this->passageiro('Maria Semedo');

        $reserva = Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => $veiculo->id,
            'ponto_partida_id' => $ponto->id,
            'destino_id' => $ponto->id,
            'lugares' => 1,
            'tipo_viagem' => 'urbano',
            'preco_final' => 250,
            'estado' => 'concluido',
        ]);

        return [$passageiro, $reserva];
    }

    private function passageiro(string $nome): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => $nome,
            'telefone' => '+23859'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }
}
