<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
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
 * Ciclo de recolha do condutor (secção 9.4): "Ir buscar" põe o passageiro no
 * mapa, "Embarcar" mete-o dentro do carro. Ambas as transições vivem na base
 * de dados — o mapa tem de ficar igual depois de um refresh — e só o condutor
 * da viagem as pode executar.
 */
class EmbarqueTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO_PONTO = 'Escolha o ponto de partida e o destino em "Veículo ativo" e carregue em "Guardar" antes de embarcar ou entregar passageiros.';

    public function test_ir_buscar_poe_o_passageiro_no_mapa_e_esconde_o_botao(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'ir_buscar'])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'estado' => 'a_caminho']);

        $this->assertSame('a_caminho', $reserva->fresh()->estado);

        // Releitura do endpoint que enche o mapa: é o equivalente a recarregar
        // a página, e é onde se vê que o estado não ficou só no cliente.
        $p = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->json('passageiros.0');

        $this->assertTrue($p['no_mapa']);
        $this->assertFalse($p['pode_ir_buscar']);
        $this->assertTrue($p['pode_embarcar']);
    }

    public function test_embarcar_conta_o_passageiro_dentro_do_condutor(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();

        $this->irBuscar($condutor, $reserva);

        $ocupacao = $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'embarcar'])
            ->assertOk()
            ->json('ocupacao');

        $this->assertSame('a_bordo', $reserva->fresh()->estado);
        $this->assertSame(1, $ocupacao['a_bordo']);
        $this->assertSame(14, $ocupacao['lugares_total']);
        $this->assertSame(13, $ocupacao['lugares_livres']);
        $this->assertSame(['Ana Lopes'], array_column($ocupacao['passageiros'], 'nome'));

        // Sem marcador autónomo a partir daqui: quem está a bordo conta dentro
        // do marcador do condutor.
        $p = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->json('passageiros.0');

        $this->assertFalse($p['no_mapa']);
    }

    /**
     * O popup do veículo, no mapa do condutor, responde a "quem levo, para
     * onde e em que lugar" — e é 'ocupacao' que o alimenta. Cada passageiro a
     * bordo tem de trazer o destino que escolheu e o número do assento; quem
     * já reservou mas ainda não embarcou fica à parte, em 'aguardam', porque
     * ocupa lugar sem estar dentro do carro.
     */
    public function test_a_ocupacao_traz_o_destino_e_o_assento_de_cada_passageiro(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();
        $reserva->forceFill(['ponto_descida_nome' => 'Palmarejo'])->save();

        $porEmbarcar = $this->reservaPara($veiculo, 'Bruno Silva');
        $porEmbarcar->forceFill(['estado' => 'confirmado', 'ponto_descida_nome' => 'Achada Santo António'])->save();

        $this->embarcarMesmo($condutor, $reserva);

        $ocupacao = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->json('ocupacao');

        $this->assertCount(1, $ocupacao['passageiros']);
        $this->assertSame('Ana Lopes', $ocupacao['passageiros'][0]['nome']);
        $this->assertSame('Palmarejo', $ocupacao['passageiros'][0]['destino']);
        $this->assertSame(
            (int) AssentoVeiculo::query()->whereKey($reserva->assento_id)->value('numero'),
            $ocupacao['passageiros'][0]['assento']
        );

        $this->assertCount(1, $ocupacao['aguardam']);
        $this->assertSame('Bruno Silva', $ocupacao['aguardam'][0]['nome']);
        $this->assertSame('confirmado', $ocupacao['aguardam'][0]['estado']);
        $this->assertSame('Achada Santo António', $ocupacao['aguardam'][0]['destino']);

        // Dois lugares presos (um a bordo, um reservado) num veículo de 14.
        $this->assertSame(1, $ocupacao['a_bordo']);
        $this->assertSame(1, $ocupacao['reservados']);
        $this->assertSame(12, $ocupacao['lugares_livres']);
    }

    public function test_entregue_tira_o_passageiro_de_bordo_e_devolve_o_lugar(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();

        $this->embarcarMesmo($condutor, $reserva);

        $ocupacao = $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'chegou'])
            ->assertOk()
            ->json('ocupacao');

        $this->assertSame('concluido', $reserva->fresh()->estado);
        $this->assertSame(0, $ocupacao['a_bordo']);
        $this->assertSame([], $ocupacao['passageiros']);
        // O assento volta ao veículo: quem saiu deixa de o ocupar e o lugar
        // fica outra vez disponível para novas reservas.
        $this->assertSame(14, $ocupacao['lugares_livres']);

        // E o passageiro sai da lista do condutor (deixou de estar ativo).
        $this->assertSame([], $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->json('passageiros'));
    }

    public function test_o_veiculo_vazio_mostra_capacidade_e_lugares_livres(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        $ocupacao = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->json('ocupacao');

        $this->assertSame(0, $ocupacao['a_bordo']);
        $this->assertSame(14, $ocupacao['lugares_total']);
        $this->assertSame([], $ocupacao['passageiros']);
    }

    public function test_embarcar_sem_ir_buscar_e_recusado(): void
    {
        [$condutor, , $reserva] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'embarcar'])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Carregue primeiro em "Ir buscar" para pôr o passageiro no mapa.']);

        $this->assertSame('pendente', $reserva->fresh()->estado);
    }

    public function test_ir_buscar_repetido_nao_da_erro(): void
    {
        [$condutor, , $reserva] = $this->cenario();

        $this->irBuscar($condutor, $reserva);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'ir_buscar'])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'estado' => 'a_caminho']);
    }

    public function test_sem_lugares_livres_o_embarque_e_bloqueado(): void
    {
        [$condutor, $veiculo, $primeira] = $this->cenario();

        // O CHECK 'chk_veiculo_lugares' fixa a lotação em 14, por isso o
        // veículo é enchido a sério: 14 lugares, 14 pessoas dentro.
        $this->embarcarMesmo($condutor, $primeira);

        for ($i = 2; $i <= 14; $i++) {
            $this->embarcarMesmo($condutor, $this->reservaPara($veiculo, "Passageiro {$i}"));
        }

        // A 15.ª reserva não chega a apanhar assento (já não há nenhum livre);
        // é a contagem de pessoas a bordo que a trava, e não o assento.
        $aMais = $this->reservaSemAssento($veiculo, 'Bruno Silva');
        $this->irBuscar($condutor, $aMais);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $aMais->id, 'acao' => 'embarcar'])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Veículo cheio — nenhum lugar livre para embarcar.']);

        $this->assertSame('a_caminho', $aMais->fresh()->estado);

        // E o painel já mostra o botão bloqueado, com o motivo, sem ser
        // preciso carregar nele para descobrir que não dá.
        $bloqueada = collect($this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->json('passageiros'))
            ->firstWhere('id', $aMais->id);

        $this->assertFalse($bloqueada['pode_embarcar']);
        $this->assertSame('Veículo cheio — nenhum lugar livre para embarcar.', $bloqueada['motivo_bloqueio']);
    }

    /**
     * O "Guardar" do cartão "Veículo ativo" é o que ancora o veículo a um
     * ponto de partida e a um destino. Sem ele, uma viagem intermunicipal não
     * tem para onde ir — e nem "Embarcar" nem "Entregue" podem avançar.
     */
    public function test_intermunicipal_sem_ponto_guardado_nao_embarca(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();
        $this->apagarPontoGuardado($veiculo);

        $this->irBuscar($condutor, $reserva);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'embarcar'])
            ->assertStatus(409)
            ->assertJson(['erro' => self::AVISO_PONTO]);

        $this->assertSame('a_caminho', $reserva->fresh()->estado);

        // E o painel mostra logo o botão travado, com o motivo.
        $p = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->json('passageiros.0');

        $this->assertFalse($p['pode_embarcar']);
        $this->assertSame(self::AVISO_PONTO, $p['motivo_bloqueio']);
    }

    public function test_intermunicipal_sem_ponto_guardado_nao_entrega(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();

        // Embarcou com o ponto guardado; o ponto só desaparece a seguir (é o
        // caso do veículo que nunca chegou a carregar em "Guardar" e recebeu
        // o passageiro por outra via).
        $this->embarcarMesmo($condutor, $reserva);
        $this->apagarPontoGuardado($veiculo);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'chegou'])
            ->assertStatus(409)
            ->assertJson(['erro' => self::AVISO_PONTO]);

        $this->assertSame('a_bordo', $reserva->fresh()->estado);

        $p = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros?veiculo_id='.$veiculo->id)
            ->json('passageiros.0');

        $this->assertFalse($p['pode_entregar']);
        $this->assertSame(self::AVISO_PONTO, $p['motivo_bloqueio']);
    }

    public function test_depois_de_guardar_o_ponto_o_embarque_e_a_entrega_funcionam(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();
        $this->apagarPontoGuardado($veiculo);
        [$partida, $destino] = $this->pontos();

        // Exatamente o que o botão "Guardar" faz.
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_ponto', [
                'veiculo_id' => $veiculo->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk();

        $this->irBuscar($condutor, $reserva);

        foreach (['embarcar', 'chegou'] as $acao) {
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => $acao])
                ->assertOk();
        }

        $this->assertSame('concluido', $reserva->fresh()->estado);
    }

    /**
     * A regra é só do intermunicipal: o táxi (e o 'ambos' enquanto anda sem
     * ponto) recolhe pedidos urbanos de propósito, e continuaria parado se o
     * embarque lhe exigisse um ponto que ele nunca define.
     */
    public function test_viagem_urbana_embarca_sem_ponto_guardado(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();
        $reserva->forceFill(['tipo_viagem' => 'urbano'])->save();
        $this->apagarPontoGuardado($veiculo);

        $this->embarcarMesmo($condutor, $reserva);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'chegou'])
            ->assertOk();

        $this->assertSame('concluido', $reserva->fresh()->estado);
    }

    public function test_passageiro_ja_a_bordo_noutro_veiculo_e_bloqueado(): void
    {
        [$condutor, $veiculo, $reserva] = $this->cenario();

        $outroVeiculo = $this->veiculoDe($this->condutor('Outro Condutor'), 'ST-02-BB');
        Reserva::query()->create([
            'passageiro_id' => $reserva->passageiro_id,
            'veiculo_id' => $outroVeiculo->id,
            'ponto_partida_id' => $reserva->ponto_partida_id,
            'destino_id' => $reserva->destino_id,
            'preco_final' => 250,
            'estado' => 'a_bordo',
        ]);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'ir_buscar'])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este passageiro já embarcou noutro veículo.']);

        $this->assertSame('pendente', $reserva->fresh()->estado);
        $this->assertSame($veiculo->id, $reserva->fresh()->veiculo_id);
    }

    public function test_so_o_condutor_da_viagem_pode_ir_buscar_e_embarcar(): void
    {
        [, , $reserva] = $this->cenario();
        $intruso = $this->condutor('Condutor Intruso');

        foreach (['ir_buscar', 'embarcar'] as $acao) {
            $this->actingAs($intruso, 'utilizador')
                ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => $acao])
                ->assertStatus(404)
                ->assertJson(['erro' => 'Reserva não encontrada.']);
        }

        $this->assertSame('pendente', $reserva->fresh()->estado);
    }

    /** Veículo que nunca carregou em "Guardar": sem partida nem destino. */
    private function apagarPontoGuardado(Veiculo $veiculo): void
    {
        $veiculo->forceFill([
            'ponto_partida_id' => null,
            'destino_id' => null,
            'ponto_bloqueado_ate' => null,
        ])->save();
    }

    private function irBuscar(Utilizador $condutor, Reserva $reserva): void
    {
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'ir_buscar'])
            ->assertOk();
    }

    /** Os dois passos do ciclo de recolha, para os testes que só querem o fim. */
    private function embarcarMesmo(Utilizador $condutor, Reserva $reserva): void
    {
        $this->irBuscar($condutor, $reserva);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'embarcar'])
            ->assertOk();
    }

    /**
     * Condutor em dia, com veículo aprovado no ponto, e um passageiro com
     * reserva pendente nesse veículo.
     *
     * @return array{0: Utilizador, 1: Veiculo, 2: Reserva}
     */
    private function cenario(): array
    {
        $condutor = $this->condutor('João Tavares');
        $veiculo = $this->veiculoDe($condutor, 'ST-01-AA');

        // Sem taxa de operação aprovada e válida o condutor nem vê o mapa
        // (VeiculoService::condutorPodeVerMapa) — /api/condutor/passageiros
        // devolveria 403 e o teste não chegaria a testar o embarque.
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

        return [$condutor, $veiculo, $this->reservaPara($veiculo, 'Ana Lopes')];
    }

    private function reservaPara(Veiculo $veiculo, string $nome): Reserva
    {
        $passageiro = $this->passageiro($nome);

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
            'estado' => 'pendente',
        ]);

        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        return $reserva;
    }

    /** Reserva de quem ficou sem assento porque o veículo já estava cheio. */
    private function reservaSemAssento(Veiculo $veiculo, string $nome): Reserva
    {
        return Reserva::query()->create([
            'passageiro_id' => $this->passageiro($nome)->id,
            'veiculo_id' => $veiculo->id,
            'ponto_partida_id' => $veiculo->ponto_partida_id,
            'destino_id' => $veiculo->destino_id,
            'preco_final' => 250,
            'estado' => 'pendente',
        ]);
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

    private function condutor(string $nome): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => $nome,
            'telefone' => '+23898'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }

    private function veiculoDe(Utilizador $condutor, string $matricula): Veiculo
    {
        [$partida, $destino] = $this->pontos();

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
            'lat' => $partida->lat, 'lng' => $partida->lng,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        return $veiculo;
    }

    /** @return array{0: PontoPartida, 1: PontoPartida} */
    private function pontos(): array
    {
        return [
            PontoPartida::query()->firstOrCreate(
                ['nome' => 'Mercado de Sucupira'],
                ['cidade' => 'Praia', 'lat' => 14.9175, 'lng' => -23.5094,
                    'zona' => 'urbana', 'status' => 'aprovado', 'ativo' => true]
            ),
            PontoPartida::query()->firstOrCreate(
                ['nome' => 'Assomada'],
                ['cidade' => 'Santa Catarina', 'lat' => 15.0983, 'lng' => -23.6703,
                    'zona' => 'intermunicipal', 'status' => 'aprovado', 'ativo' => true]
            ),
        ];
    }

    private function adminId(): int
    {
        return Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            ['nome' => 'Super Admin', 'senha_hash' => 'senhaforte1', 'nivel' => 'super', 'ativo' => true]
        )->id;
    }
}
