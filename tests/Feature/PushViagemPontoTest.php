<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssentoVeiculo;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\SubscricaoPush;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use App\Services\WebPushService;
use App\Support\CifraWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O passageiro é avisado quando o veículo sai do ponto e quando chega ao
 * ponto — os dois instantes que ele não tem como adivinhar: a partida
 * decide-se dentro do carro ("Iniciar rota") e a chegada é detetada pelo GPS,
 * sem ninguém carregar em nada.
 *
 * O conteúdo dos avisos verifica-se com um WebPushService espião (no envio
 * real vai cifrado, e nem o serviço do fabricante o consegue ler); o contrato
 * com esse serviço — VAPID, aes128gcm, TTL, tópico — verifica-se com
 * Http::fake, como em PushChamadaTest.
 */
class PushViagemPontoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $chaves = CifraWebPush::gerarChavesVapid();

        config([
            'vmillion.push.chave_publica' => $chaves['publica'],
            'vmillion.push.chave_privada' => $chaves['privada'],
            'vmillion.push.assunto' => 'mailto:suporte@vmillion.cv',
        ]);
    }

    public function test_a_saida_do_ponto_avisa_quem_segue_viagem(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo] = $this->cenario();
        $aBordo = $this->reserva($veiculo, 'a_bordo');
        $aCaminho = $this->reserva($veiculo, 'a_caminho');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        $avisos = $espia->porEvento('partida');
        $this->assertSame(
            [$aBordo->passageiro_id, $aCaminho->passageiro_id],
            array_column($avisos, 'utilizadorId'),
        );
        $this->assertSame('O veículo saiu do ponto', $avisos[0]['dados']['titulo']);
        $this->assertSame(
            'ST-01-AA saiu de Mercado de Sucupira, a caminho de Assomada.',
            $avisos[0]['dados']['corpo'],
        );
        // Tocar no aviso abre o painel do passageiro, não a raiz do site.
        $this->assertSame('/passageiro/painel', $avisos[0]['dados']['url']);
    }

    /**
     * Quem esperava no ponto e ficou sem lugar é o que mais precisa do aviso —
     * e a mensagem tem de lhe dizer isso mesmo, senão fica à espera de um
     * carro que já partiu sem ele.
     */
    public function test_quem_fica_sem_lugar_e_avisado_de_que_a_reserva_foi_libertada(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo] = $this->cenario();
        $confirmada = $this->reserva($veiculo, 'confirmado');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        $avisos = $espia->porEvento('partida_sem_lugar');
        $this->assertCount(1, $avisos);
        $this->assertSame($confirmada->passageiro_id, $avisos[0]['utilizadorId']);
        $this->assertStringContainsString('a sua reserva foi libertada', $avisos[0]['dados']['corpo']);

        // E não leva também o aviso de quem segue viagem.
        $this->assertSame([], $espia->porEvento('partida'));
    }

    public function test_a_chegada_ao_ponto_avisa_quem_vinha_a_bordo(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo, , $destino] = $this->cenario();
        $reserva = $this->reserva($veiculo, 'a_bordo');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();
        $this->posicao($condutor, $veiculo, (float) $destino->lat, (float) $destino->lng)
            ->assertOk()
            ->assertJsonPath('chegou_destino', true);

        $avisos = $espia->porEvento('chegada');
        $this->assertCount(1, $avisos);
        $this->assertSame($reserva->passageiro_id, $avisos[0]['utilizadorId']);
        // O nome do ponto é lido antes da inversão automática de partida e
        // destino: depois dela o veículo já diz que vai para Sucupira.
        $this->assertSame(
            'ST-01-AA chegou a Assomada. A sua viagem está concluída.',
            $avisos[0]['dados']['corpo'],
        );
        $this->assertSame('concluido', $reserva->fresh()->estado);
    }

    /** A chegada que não aconteceu não avisa ninguém. */
    public function test_a_caminho_do_ponto_ainda_nao_ha_aviso_de_chegada(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo] = $this->cenario();
        $this->reserva($veiculo, 'a_bordo');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();
        $this->posicao($condutor, $veiculo, 15.05, -23.64)->assertOk();

        $this->assertSame([], $espia->porEvento('chegada'));
    }

    /**
     * Um veículo sem ponto guardado (urbano, a circular livremente) não pode
     * nomear um ponto que não tem: a frase fica pelo genérico, em vez de
     * sair com um "saiu de " a meio.
     */
    public function test_sem_ponto_guardado_a_mensagem_fica_pelo_generico(): void
    {
        $espia = $this->espiarPush();
        [$condutor, $veiculo] = $this->cenario();
        $this->reserva($veiculo, 'a_bordo');
        $veiculo->forceFill(['ponto_partida_id' => null, 'destino_id' => null])->save();

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        $this->assertSame(
            'ST-01-AA saiu do ponto.',
            $espia->porEvento('partida')[0]['dados']['corpo'],
        );
    }

    public function test_o_aviso_vai_cifrado_com_topico_e_validade_proprios(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 'a_bordo');
        $this->subscreverDispositivo($reserva->passageiro, 'dispositivo-passageiro');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        Http::assertSent(function (Request $pedido) {
            return $pedido->url() === 'https://push.exemplo.cv/dispositivo-passageiro'
                && str_starts_with($pedido->header('Authorization')[0], 'vapid t=')
                && $pedido->header('Content-Encoding')[0] === 'aes128gcm'
                // Tópico próprio: com o das chamadas, um aviso do ponto ainda
                // por entregar apagava uma chamada a tocar (e vice-versa).
                && $pedido->header('Topic')[0] === 'vm-viagem'
                && $pedido->header('TTL')[0] === (string) config('vmillion.push.ttl_viagem')
                // Salt (16) + rs (4) + idlen (1) + chave efémera (65) + cifra.
                && strlen($pedido->body()) > 86;
        });

        // Nem a matrícula nem o nome do ponto viajam em claro.
        Http::assertSent(fn (Request $pedido) => ! str_contains($pedido->body(), 'ST-01-AA'));
    }

    /**
     * O condutor recebe a confirmação do que acabou de fazer; quem já vai a
     * bordo de outro carro não recebe nada — saiu do mercado e um veículo a
     * mais ou a menos naquele ponto já não lhe muda a viagem.
     */
    public function test_o_aviso_do_ponto_vai_para_o_condutor_mas_nao_para_terceiros(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 'a_bordo');
        $this->subscreverDispositivo($reserva->passageiro, 'dispositivo-passageiro');
        $this->subscreverDispositivo($condutor, 'dispositivo-condutor');
        $this->subscreverDispositivo($this->reserva($this->outroVeiculo(), 'a_bordo')->passageiro, 'dispositivo-terceiro');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-passageiro');
        Http::assertSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-condutor');
        Http::assertNotSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-terceiro');
    }

    public function test_o_servico_de_push_em_baixo_nao_impede_a_saida_do_ponto(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 500)]);

        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 'a_bordo');
        $this->subscreverDispositivo($reserva->passageiro);

        $this->sairDoPonto($condutor, $veiculo)
            ->assertOk()
            ->assertJsonPath('estado', 'partiu_da_fila');

        // A viagem seguiu na mesma: o painel do passageiro apanha o estado
        // novo no ciclo seguinte de /api/passageiro/minha_reserva.
        $this->assertSame('partiu_da_fila', $veiculo->fresh()->estado);
        $this->assertSame('a_bordo', $reserva->fresh()->estado);
        $this->assertSame(1, SubscricaoPush::query()->count(), 'Um 500 é temporário e não mata a subscrição.');
    }

    /** Sem passageiros nem ninguém à espera no ponto, sobra o próprio condutor. */
    public function test_um_veiculo_vazio_a_sair_do_ponto_so_avisa_o_condutor(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$condutor, $veiculo] = $this->cenario();
        $this->subscreverDispositivo($condutor, 'dispositivo-condutor');

        $this->sairDoPonto($condutor, $veiculo)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-condutor');
    }

    // -----------------------------------------------------------------
    // Cenário
    // -----------------------------------------------------------------

    private function sairDoPonto(Utilizador $condutor, Veiculo $veiculo)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', [
                'veiculo_id' => $veiculo->id, 'motivo' => 'Iniciar rota',
            ]);
    }

    private function posicao(Utilizador $condutor, Veiculo $veiculo, float $lat, float $lng)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/posicao', [
                'veiculo_id' => $veiculo->id, 'lat' => $lat, 'lng' => $lng, 'accuracy' => 10,
            ]);
    }

    /**
     * Substitui o envio real por um registo do que seria enviado: o conteúdo
     * do push vai cifrado no corpo HTTP e não há como o ler do outro lado.
     */
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
     * Condutor em dia, veículo intermunicipal na fila do ponto de partida com
     * destino guardado.
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
        $veiculo = $this->veiculo($condutor, $partida, $destino);

        return [$condutor, $veiculo, $partida, $destino];
    }

    /** Um segundo veículo, de outro condutor, com a sua própria viagem. */
    private function outroVeiculo(): Veiculo
    {
        $ponto = PontoPartida::query()->firstOrFail();

        return $this->veiculo($this->utilizador('condutor', 'Mário Fortes'), $ponto, $ponto, 'ST-02-BB');
    }

    private function veiculo(Utilizador $condutor, PontoPartida $partida, PontoPartida $destino, string $matricula = 'ST-01-AA'): Veiculo
    {
        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
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

        return $veiculo;
    }

    /** Reserva com o lugar já preso, como o faz Passageiro\ReservaController. */
    private function reserva(Veiculo $veiculo, string $estado): Reserva
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
            'ponto_partida_id' => $veiculo->ponto_partida_id,
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

    private function subscreverDispositivo(Utilizador $utilizador, string $dispositivo = 'dispositivo-1'): void
    {
        // Chaves com a forma real das que o browser entrega: o p256dh é um
        // ponto não comprimido da P-256 (65 bytes) e o auth tem 16 bytes.
        $chave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($chave)['ec'];
        $alinhar = fn (string $n): string => str_pad(ltrim($n, "\x00"), 32, "\x00", STR_PAD_LEFT);

        SubscricaoPush::query()->create([
            'utilizador_id' => $utilizador->id,
            'endpoint' => 'https://push.exemplo.cv/'.$dispositivo,
            'chave_p256dh' => CifraWebPush::base64urlCodificar("\x04".$alinhar($ec['x']).$alinhar($ec['y'])),
            'chave_auth' => CifraWebPush::base64urlCodificar(random_bytes(16)),
        ]);
    }
}
