<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\ComunicacaoVeiculo;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
use App\Models\PrecoRota;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\GeoService;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escolher o ponto e escolher o lugar são dois caminhos com alcances
 * diferentes: o pedido feito a partir do ponto é de todos os condutores desse
 * ponto; a reserva feita a partir de um lugar concreto é só do condutor desse
 * veículo.
 */
class PedidoNoPontoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pedido_do_ponto_aparece_a_todos_os_condutores_do_ponto(): void
    {
        [$partida, $destino] = $this->pontos();
        $passageiro = $this->passageiro('Ana Lopes');

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/pedir_ponto', [
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        // Nasce sem veículo: é isso que o torna visível a todo o ponto.
        $reserva = Reserva::query()->where('passageiro_id', $passageiro->id)->firstOrFail();
        $this->assertNull($reserva->veiculo_id);
        $this->assertNull($reserva->assento_id);
        $this->assertSame('intermunicipal', $reserva->tipo_viagem);

        foreach (['ST-01-AA', 'ST-02-BB'] as $matricula) {
            [$condutor, $veiculo] = $this->condutorComVeiculo($matricula, $partida, $destino);

            $this->actingAs($condutor, 'utilizador')
                ->getJson('/api/condutor/passageiros_ponto?veiculo_id='.$veiculo->id)
                ->assertOk()
                ->assertJson(['elegivel' => true])
                ->assertJsonPath('passageiros.0.passageiro_nome', 'Ana Lopes')
                ->assertJsonPath('passageiros.0.mesmo_destino', true);
        }
    }

    public function test_condutor_de_outro_ponto_nao_ve_nem_pode_reclamar_o_pedido(): void
    {
        [$partida, $destino] = $this->pontos();
        $outroPonto = PontoPartida::query()->create([
            'nome' => 'Terra Branca', 'cidade' => 'Praia',
            'lat' => 14.9260, 'lng' => -23.5210, 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $reservaId = $this->pedidoNoPonto($partida, $destino);

        [$condutor, $veiculo] = $this->condutorComVeiculo('ST-09-ZZ', $outroPonto, $destino);

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_ponto?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->assertJsonCount(0, 'passageiros');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/recolher_pedido', [
                'reserva_id' => $reservaId,
                'veiculo_id' => $veiculo->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este pedido é de outro ponto de partida.']);

        $this->assertNull(Reserva::query()->find($reservaId)->veiculo_id);
    }

    public function test_o_primeiro_condutor_a_ir_buscar_fica_com_o_pedido(): void
    {
        [$partida, $destino] = $this->pontos();
        $reservaId = $this->pedidoNoPonto($partida, $destino);

        [$primeiro, $veiculoPrimeiro] = $this->condutorComVeiculo('ST-01-AA', $partida, $destino);
        [$segundo, $veiculoSegundo] = $this->condutorComVeiculo('ST-02-BB', $partida, $destino);

        $this->actingAs($primeiro, 'utilizador')
            ->postJson('/api/condutor/recolher_pedido', [
                'reserva_id' => $reservaId,
                'veiculo_id' => $veiculoPrimeiro->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'tipo_viagem' => 'intermunicipal']);

        $reserva = Reserva::query()->find($reservaId);
        $this->assertSame($veiculoPrimeiro->id, $reserva->veiculo_id);
        $this->assertSame('a_caminho', $reserva->estado);
        $this->assertNotNull($reserva->assento_id);
        $this->assertSame($veiculoPrimeiro->lugares_livres - 1, $veiculoPrimeiro->fresh()->lugares_livres);

        // Reclamado: sai da lista de toda a gente e o segundo já não o apanha.
        $this->actingAs($segundo, 'utilizador')
            ->getJson('/api/condutor/passageiros_ponto?veiculo_id='.$veiculoSegundo->id)
            ->assertOk()
            ->assertJsonCount(0, 'passageiros');

        $this->actingAs($segundo, 'utilizador')
            ->postJson('/api/condutor/recolher_pedido', [
                'reserva_id' => $reservaId,
                'veiculo_id' => $veiculoSegundo->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este pedido já foi reclamado por outro condutor.']);
    }

    public function test_reserva_com_lugar_escolhido_e_so_do_condutor_desse_veiculo(): void
    {
        [$partida, $destino] = $this->pontos();
        [$escolhido, $veiculoEscolhido] = $this->condutorComVeiculo('ST-01-AA', $partida, $destino);
        [$outro, $veiculoOutro] = $this->condutorComVeiculo('ST-02-BB', $partida, $destino);

        $passageiro = $this->passageiro('Ana Lopes');
        $assento = $veiculoEscolhido->assentos()->first();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/reservar', [
                'veiculo_id' => $veiculoEscolhido->id,
                'assento_id' => $assento->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk();

        // Só o condutor escolhido a vê...
        $this->actingAs($escolhido, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculoEscolhido->id)
            ->assertOk()
            ->assertJsonPath('passageiros.0.passageiro_nome', 'Ana Lopes');

        // ...e para o resto do ponto ela nunca esteve em aberto.
        foreach ([[$escolhido, $veiculoEscolhido], [$outro, $veiculoOutro]] as [$condutor, $veiculo]) {
            $this->actingAs($condutor, 'utilizador')
                ->getJson('/api/condutor/passageiros_ponto?veiculo_id='.$veiculo->id)
                ->assertOk()
                ->assertJsonCount(0, 'passageiros');
        }

        $this->actingAs($outro, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculoOutro->id)
            ->assertOk()
            ->assertJsonCount(0, 'passageiros');
    }

    public function test_a_tarifa_da_rota_fixa_de_quem_reclama_prevalece_sobre_a_estimativa(): void
    {
        [$partida, $destino] = $this->pontos();
        $reservaId = $this->pedidoNoPonto($partida, $destino);

        $estimativa = (float) Reserva::query()->find($reservaId)->preco_final;
        $this->assertGreaterThan(0, $estimativa);

        [$condutor, $veiculo] = $this->condutorComVeiculo('ST-07-RF', $partida, $destino);
        // distancia_km igual ao troço todo: o passageiro faz a rota fixa de
        // ponta a ponta, por isso a fração é 1 e paga o preço fixo inteiro.
        $rota = PrecoRota::query()->create([
            'ponto_origem_id' => $partida->id,
            'ponto_destino_id' => $destino->id,
            'preco_fixo_cve' => 800,
            'distancia_km' => app(GeoService::class)->distanciaKm(
                (float) $partida->lat, (float) $partida->lng,
                (float) $destino->lat, (float) $destino->lng
            ),
            'definido_por' => $this->adminId(),
        ]);
        $veiculo->forceFill(['rota_fixa_id' => $rota->id])->save();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/recolher_pedido', [
                'reserva_id' => $reservaId,
                'veiculo_id' => $veiculo->id,
            ])
            ->assertOk();

        // Percurso inteiro da rota fixa (partida -> destino, sem descida a
        // meio): o passageiro paga o preço fixo dela, não a estimativa por km.
        $reserva = Reserva::query()->find($reservaId);
        $this->assertSame(800.0, (float) $reserva->preco_final);
        $this->assertNotSame($estimativa, (float) $reserva->preco_final);

        // A mudança de preço não pode ser silenciosa.
        $this->assertDatabaseHas('comunicacoes_veiculo', [
            'destinatario_id' => $reserva->passageiro_id,
            'tipo' => 'sistema',
        ]);
        $this->assertStringContainsString(
            '800,00 CVE',
            (string) ComunicacaoVeiculo::query()
                ->where('destinatario_id', $reserva->passageiro_id)
                ->value('mensagem')
        );
    }

    public function test_sem_rota_fixa_o_preco_combinado_no_pedido_mantem_se(): void
    {
        [$partida, $destino] = $this->pontos();
        $reservaId = $this->pedidoNoPonto($partida, $destino);
        $estimativa = (float) Reserva::query()->find($reservaId)->preco_final;

        [$condutor, $veiculo] = $this->condutorComVeiculo('ST-08-SF', $partida, $destino);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/recolher_pedido', [
                'reserva_id' => $reservaId,
                'veiculo_id' => $veiculo->id,
            ])
            ->assertOk()
            ->assertJson(['preco_final' => $estimativa]);

        $this->assertSame($estimativa, (float) Reserva::query()->find($reservaId)->preco_final);
    }

    public function test_veiculo_sem_ponto_guardado_nao_recebe_pedidos_do_ponto(): void
    {
        [$partida, $destino] = $this->pontos();
        $this->pedidoNoPonto($partida, $destino);

        [$condutor, $veiculo] = $this->condutorComVeiculo('ST-03-CC', $partida, $destino);
        $veiculo->forceFill(['ponto_partida_id' => null, 'destino_id' => null])->save();

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_ponto?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->assertJson(['elegivel' => false])
            ->assertJsonCount(0, 'passageiros');
    }

    public function test_so_um_pedido_ativo_de_cada_vez(): void
    {
        [$partida, $destino] = $this->pontos();
        $passageiro = $this->passageiro('Ana Lopes');

        $dados = ['ponto_partida_id' => $partida->id, 'destino_id' => $destino->id];

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/passageiro/pedir_ponto', $dados)->assertOk();
        $this->actingAs($passageiro, 'utilizador')->postJson('/api/passageiro/pedir_ponto', $dados)
            ->assertStatus(409)
            ->assertJson(['erro' => 'Já tem uma reserva ativa. Saia dela antes de pedir uma nova viagem.']);
    }

    /** Cria o pedido em aberto e devolve o id da reserva. */
    private function pedidoNoPonto(PontoPartida $partida, PontoPartida $destino): int
    {
        return (int) $this->actingAs($this->passageiro('Ana Lopes'), 'utilizador')
            ->postJson('/api/passageiro/pedir_ponto', [
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk()
            ->json('reserva_id');
    }

    /**
     * @return array{0: PontoPartida, 1: PontoPartida}
     */
    private function pontos(): array
    {
        PrecoKm::query()->create(['zona' => 'urbana', 'preco_por_km_cve' => 5, 'atualizado_por' => $this->adminId()]);
        PrecoKm::query()->create(['zona' => 'intermunicipal', 'preco_por_km_cve' => 10, 'atualizado_por' => $this->adminId()]);

        return [
            PontoPartida::query()->create([
                'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
                'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
                'status' => 'aprovado', 'ativo' => true,
            ]),
            PontoPartida::query()->create([
                'nome' => 'Assomada', 'cidade' => 'Santa Catarina',
                'lat' => 15.0983, 'lng' => -23.6703, 'zona' => 'intermunicipal',
                'status' => 'aprovado', 'ativo' => true,
            ]),
        ];
    }

    /**
     * @return array{0: Utilizador, 1: Veiculo}
     */
    private function condutorComVeiculo(string $matricula, PontoPartida $partida, PontoPartida $destino): array
    {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'Condutor '.$matricula,
            'telefone' => '+23898'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
            'tipo' => 'hiace', 'tipo_servico' => 'intermunicipal', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        // Sem taxa de operação válida o condutor nem vê o mapa
        // (VeiculoService::condutorPodeVerMapa) e todas as listas dão 403.
        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 3000,
            'referencia' => 'REF-'.$veiculo->id,
            'data_pagamento' => now()->subDay(),
            'data_validade' => now()->addMonth(),
            'aprovado_por' => $this->adminId(),
            'aprovado_em' => now()->subDay(),
            'status' => 'aprovado',
        ]);

        return [$condutor, $veiculo->fresh()];
    }

    private function passageiro(string $nome): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => $nome,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }

    private function adminId(): int
    {
        return Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            ['nome' => 'Super Admin', 'senha_hash' => 'senhaforte1', 'nivel' => 'super', 'ativo' => true]
        )->id;
    }
}
