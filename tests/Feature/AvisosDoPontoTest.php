<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\AssentoVeiculo;
use App\Models\Notificacao;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O movimento do veículo no ponto — entrou no ponto, entrou na fila, saiu do
 * ponto — avisa quem ainda espera nesse ponto, o próprio condutor e o
 * backoffice.
 *
 * A saída já avisava os passageiros do próprio veículo (PushViagemPontoTest);
 * o que se verifica aqui é a outra metade: quem está no ponto à espera de um
 * carro qualquer, e não daquele.
 */
class AvisosDoPontoTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrar_no_ponto_avisa_quem_espera_no_ponto(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $aEspera = $this->reservaNoPonto($partida, $this->outroVeiculo($partida));

        $this->definirPonto($condutor, $veiculo, $partida, $destino)->assertOk();

        $avisos = $espia->porEvento('entrada_ponto');
        $this->assertContains($aEspera->passageiro_id, array_column($avisos, 'utilizadorId'));
        $this->assertSame('Veículo entrou no ponto', $avisos[0]['dados']['titulo']);
        $this->assertSame(
            'ST-01-AA entrou em Mercado de Sucupira, com destino a Assomada.',
            $avisos[0]['dados']['corpo'],
        );
    }

    /** A posição vai na mensagem: diz a quem espera quantos carros saem antes deste. */
    public function test_entrar_na_fila_avisa_com_a_posicao(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, $partida] = $this->cenario();
        $aEspera = $this->reservaNoPonto($partida, $this->outroVeiculo($partida));

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_entrar', ['veiculo_id' => $veiculo->id])
            ->assertOk()
            ->assertJsonPath('posicao_fila', 1);

        $avisos = $espia->porEvento('entrada_fila');
        $this->assertContains($aEspera->passageiro_id, array_column($avisos, 'utilizadorId'));
        $this->assertSame(
            'ST-01-AA entrou na fila de Mercado de Sucupira, na posição 1.',
            $avisos[0]['dados']['corpo'],
        );
    }

    public function test_sair_do_ponto_avisa_quem_fica_a_espera_de_outro_carro(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, $partida] = $this->cenario();
        $aEspera = $this->reservaNoPonto($partida, $this->outroVeiculo($partida));

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        $avisos = $espia->porEvento('saida_ponto');
        $this->assertContains($aEspera->passageiro_id, array_column($avisos, 'utilizadorId'));
        $this->assertSame(
            'ST-01-AA saiu de Mercado de Sucupira, a caminho de Assomada.',
            $avisos[0]['dados']['corpo'],
        );
    }

    /**
     * Quem já tem lugar preso naquele veículo leva a mensagem que fala da
     * reserva dele ('partida'), e não também a genérica do ponto — seria o
     * mesmo aviso duas vezes, com dois textos diferentes.
     */
    public function test_a_saida_nao_repete_o_aviso_a_quem_ia_no_veiculo(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo] = $this->cenario();
        $aBordo = $this->reserva($veiculo, 'a_bordo');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        $this->assertNotContains(
            $aBordo->passageiro_id,
            array_column($espia->porEvento('saida_ponto'), 'utilizadorId'),
        );
    }

    /**
     * O aviso é sobre um carro que se pode reservar. Um veículo ainda por
     * aprovar não aparece na lista de disponíveis, por isso não tem nada a
     * dizer a quem espera no ponto — mas o condutor e o backoffice ficam a
     * saber na mesma.
     */
    public function test_veiculo_por_aprovar_nao_incomoda_quem_espera_no_ponto(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $veiculo->forceFill(['aprovado' => false])->save();
        $aEspera = $this->reservaNoPonto($partida, $this->outroVeiculo($partida));

        $this->definirPonto($condutor, $veiculo, $partida, $destino)->assertOk();

        $destinatarios = array_column($espia->porEvento('entrada_ponto'), 'utilizadorId');
        $this->assertNotContains($aEspera->passageiro_id, $destinatarios);
        $this->assertContains($condutor->id, $destinatarios);
    }

    public function test_o_condutor_recebe_a_confirmacao_no_painel_dele(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $this->definirPonto($condutor, $veiculo, $partida, $destino)->assertOk();

        $doCondutor = array_values(array_filter(
            $espia->porEvento('entrada_ponto'),
            fn (array $envio) => $envio['utilizadorId'] === $condutor->id,
        ));

        $this->assertCount(1, $doCondutor);
        // Tocar no aviso abre o painel do condutor, não o do passageiro.
        $this->assertSame('/condutor/painel', $doCondutor[0]['dados']['url']);
    }

    /**
     * O push é efémero e só chega a quem tem dispositivo subscrito: o mesmo
     * aviso fica no sino do painel para quem só abrir a app mais tarde.
     */
    public function test_o_aviso_fica_no_sino_do_passageiro_e_do_condutor(): void
    {
        $this->espiarPush();
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $aEspera = $this->reservaNoPonto($partida, $this->outroVeiculo($partida));

        $this->definirPonto($condutor, $veiculo, $partida, $destino)->assertOk();

        $doPassageiro = Notificacao::query()->where('destinatario_id', $aEspera->passageiro_id)->get();
        $this->assertCount(1, $doPassageiro);
        $this->assertSame('passageiros', $doPassageiro[0]->destinatario_tipo);
        $this->assertSame('Veículo entrou no ponto', $doPassageiro[0]->titulo);
        // Não é ninguém a escrever a ninguém: é o sistema a relatar o veículo.
        $this->assertNull($doPassageiro[0]->remetente_id);
        $this->assertFalse($doPassageiro[0]->lida);

        $this->assertTrue(
            Notificacao::query()
                ->where('destinatario_id', $condutor->id)
                ->where('destinatario_tipo', 'condutores')
                ->exists(),
        );

        // E o passageiro vê-o na lista do painel dele.
        $this->actingAs($aEspera->passageiro, 'utilizador')
            ->getJson('/api/notificacoes/listar')
            ->assertOk()
            ->assertJsonPath('nao_lidas', 1)
            ->assertJsonPath('notificacoes.0.titulo', 'Veículo entrou no ponto');
    }

    /**
     * Os administradores acompanham a movimentação pelo sino do backoffice —
     * não há push para eles (as subscrições são de utilizadores) — e a
     * mensagem deles diz também de quem é o carro.
     */
    public function test_o_backoffice_fica_com_o_registo_do_movimento(): void
    {
        $this->espiarPush();
        $admin = $this->administrador();
        $inativo = $this->administrador(['ativo' => false]);
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $this->definirPonto($condutor, $veiculo, $partida, $destino)->assertOk();

        $doAdmin = Notificacao::query()
            ->where('destinatario_tipo', 'admins')
            ->get();

        $this->assertSame([$admin->id], $doAdmin->pluck('destinatario_id')->all());
        $this->assertSame(
            'ST-01-AA entrou em Mercado de Sucupira, com destino a Assomada. Condutor: João Tavares.',
            $doAdmin[0]->mensagem,
        );
        $this->assertNotContains($inativo->id, $doAdmin->pluck('destinatario_id')->all());
    }

    /** Um sino cheio não pode impedir o condutor de entrar na fila. */
    public function test_o_aviso_gorado_nao_impede_a_entrada_na_fila(): void
    {
        $espia = new class extends WebPushService
        {
            public function enviarParaUtilizador(int $utilizadorId, array $dados, int $ttl = 60, string $topico = 'vm-chamada'): int
            {
                throw new \RuntimeException('serviço de push em baixo');
            }
        };
        $this->app->instance(WebPushService::class, $espia);

        [$condutor, $veiculo] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_entrar', ['veiculo_id' => $veiculo->id])
            ->assertOk();

        $this->assertSame('na_fila', $veiculo->fresh()->estado);
    }

    // -----------------------------------------------------------------
    // Cenário
    // -----------------------------------------------------------------

    private function definirPonto(Utilizador $condutor, Veiculo $veiculo, PontoPartida $partida, PontoPartida $destino)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_ponto', [
                'veiculo_id' => $veiculo->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ]);
    }

    private function sairDoPonto(Utilizador $condutor, Veiculo $veiculo)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', [
                'veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota',
            ]);
    }

    /** Substitui o envio real por um registo do que seria enviado. */
    private function espiarPush(): object
    {
        $espia = new class extends WebPushService
        {
            /** @var array<int, array{utilizadorId: int, dados: array<string, mixed>, ttl: int, topico: string}> */
            public array $envios = [];

            public function enviarParaUtilizador(int $utilizadorId, array $dados, int $ttl = 60, string $topico = 'vm-chamada'): int
            {
                $this->envios[] = compact('utilizadorId', 'dados', 'ttl', 'topico');

                return 1;
            }

            /** @return array<int, array<string, mixed>> */
            public function porEvento(string $evento): array
            {
                return array_values(array_filter(
                    $this->envios,
                    fn (array $envio) => ($envio['dados']['evento'] ?? null) === $evento,
                ));
            }
        };

        $this->app->instance(WebPushService::class, $espia);

        return $espia;
    }

    /**
     * Condutor em dia, veículo aprovado no ponto de partida com destino
     * guardado.
     *
     * @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida, 3: PontoPartida}
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

        $condutor = $this->utilizador('condutor', 'João Tavares');

        return [$condutor, $this->veiculo($condutor, $partida, $destino), $partida, $destino];
    }

    /** Um segundo veículo no mesmo ponto, para haver onde esperar. */
    private function outroVeiculo(PontoPartida $ponto): Veiculo
    {
        return $this->veiculo($this->utilizador('condutor', 'Mário Fortes'), $ponto, $ponto, 'ST-02-BB');
    }

    private function veiculo(Utilizador $condutor, PontoPartida $partida, PontoPartida $destino, string $matricula = 'ST-01-AA'): Veiculo
    {
        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
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

        return $veiculo;
    }

    /** Passageiro à espera no ponto, com pedido pendente noutro veículo. */
    private function reservaNoPonto(PontoPartida $ponto, Veiculo $veiculo): Reserva
    {
        return $this->reserva($veiculo, 'pendente', $ponto);
    }

    /** Reserva com o lugar já preso, como o faz Passageiro\ReservaController. */
    private function reserva(Veiculo $veiculo, string $estado, ?PontoPartida $ponto = null): Reserva
    {
        $passageiro = $this->utilizador('passageiro', 'Passageiro '.random_int(1, 9999));

        $assento = AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->where('ocupado', false)
            ->firstOrFail();

        $reserva = Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $ponto?->id ?? $veiculo->ponto_partida_id,
            'destino_id' => $veiculo->destino_id,
            'preco_final' => 250,
            'estado' => $estado,
        ]);

        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        return $reserva;
    }

    private function utilizador(string $tipo, string $nome): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo, 'nome' => $nome,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function administrador(array $extra = []): Administrador
    {
        return Administrador::query()->create(array_merge([
            'nome' => 'Admin de teste',
            'email' => 'admin'.random_int(1000, 99999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'admin',
            'senha_temporaria' => false,
            'ativo' => true,
        ], $extra));
    }
}
