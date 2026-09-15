<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
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
 * O condutor distraído — telemóvel bloqueado no suporte, app fechada — é
 * avisado por push quando um passageiro faz um pedido que ele pode aceitar.
 *
 * Os destinatários são os mesmos que veem o pedido na lista: avisar alguém
 * que depois abre o painel e não encontra nada é pior do que não avisar.
 */
class PushPedidoCondutorTest extends TestCase
{
    use RefreshDatabase;

    private const PRAIA = [14.9177, -23.5092];

    private const TARRAFAL = [15.2785, -23.7519];

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

    public function test_pedido_urbano_avisa_so_os_condutores_urbanos_ativos_do_mesmo_municipio(): void
    {
        $espia = $this->espiarPush();
        [$partida] = $this->pontos();

        [$naPraia] = $this->condutor('ST-01-AA', 'urbano', self::PRAIA);
        [$noTarrafal] = $this->condutor('ST-02-BB', 'urbano', self::TARRAFAL);
        [$parado] = $this->condutor('ST-03-CC', 'urbano', self::PRAIA, now()->subHours(5));
        [$intermunicipal] = $this->condutor('ST-04-DD', 'intermunicipal', self::PRAIA);
        [$semTaxa] = $this->condutor('ST-05-EE', 'urbano', self::PRAIA, pago: false);

        $this->actingAs($this->passageiro(), 'utilizador')
            ->postJson('/api/passageiro/pedir_urbano', [
                'ponto_partida_id' => $partida->id,
                'origem_lat' => self::PRAIA[0],
                'origem_lng' => self::PRAIA[1],
                'ponto_descida_nome' => 'Plateau',
                'ponto_descida_lat' => self::PRAIA[0] + 0.005,
                'ponto_descida_lng' => self::PRAIA[1] + 0.005,
            ])
            ->assertOk();

        $avisos = $espia->porEvento('pedido_urbano');
        $this->assertSame([$naPraia->id], array_column($avisos, 'utilizadorId'));
        $this->assertSame('pedido', $avisos[0]['dados']['tipo']);
        $this->assertSame('/condutor/painel', $avisos[0]['dados']['url']);
        $this->assertStringContainsString('Plateau', $avisos[0]['dados']['corpo']);
        $this->assertSame('vm-pedido', $avisos[0]['topico']);
        $this->assertSame((int) config('vmillion.push.ttl_pedido'), $avisos[0]['ttl']);

        foreach ([$noTarrafal, $parado, $intermunicipal, $semTaxa] as $fora) {
            $this->assertNotContains($fora->id, array_column($espia->envios, 'utilizadorId'));
        }
    }

    public function test_pedido_no_ponto_avisa_os_condutores_ancorados_nesse_ponto(): void
    {
        $espia = $this->espiarPush();
        [$partida, $destino, $outroPonto] = $this->pontos();

        [$noPonto] = $this->condutor('ST-01-AA', 'intermunicipal', self::PRAIA, ponto: $partida, destino: $destino);
        [$ambos] = $this->condutor('ST-02-BB', 'ambos', self::PRAIA, ponto: $partida, destino: $destino);
        [$noutroPonto] = $this->condutor('ST-03-CC', 'intermunicipal', self::PRAIA, ponto: $outroPonto, destino: $destino);
        [$semDestino] = $this->condutor('ST-04-DD', 'intermunicipal', self::PRAIA, ponto: $partida);

        $this->actingAs($this->passageiro(), 'utilizador')
            ->postJson('/api/passageiro/pedir_ponto', [
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk();

        $avisos = $espia->porEvento('pedido_ponto');
        $this->assertEqualsCanonicalizing([$noPonto->id, $ambos->id], array_column($avisos, 'utilizadorId'));
        $this->assertSame(
            'Passageiro à espera em Mercado de Sucupira, com destino a Assomada. O primeiro a ir buscar fica com ele.',
            $avisos[0]['dados']['corpo'],
        );
        $this->assertNotContains($noutroPonto->id, array_column($espia->envios, 'utilizadorId'));
        $this->assertNotContains($semDestino->id, array_column($espia->envios, 'utilizadorId'));
    }

    public function test_reserva_num_lugar_avisa_so_o_condutor_desse_veiculo(): void
    {
        $espia = $this->espiarPush();
        [$partida, $destino] = $this->pontos();

        [$escolhido, $veiculo] = $this->condutor('ST-01-AA', 'intermunicipal', self::PRAIA, ponto: $partida, destino: $destino);
        $this->condutor('ST-02-BB', 'intermunicipal', self::PRAIA, ponto: $partida, destino: $destino);

        $this->actingAs($this->passageiro(), 'utilizador')
            ->postJson('/api/passageiro/reservar', [
                'veiculo_id' => $veiculo->id,
                'assento_id' => $veiculo->assentos()->orderBy('numero')->value('id'),
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk();

        $this->assertSame([$escolhido->id], array_column($espia->envios, 'utilizadorId'));
        $aviso = $espia->porEvento('reserva_veiculo')[0];
        $this->assertSame('Nova reserva em ST-01-AA', $aviso['dados']['titulo']);
    }

    public function test_o_servico_de_push_em_baixo_nao_impede_o_pedido(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 500)]);
        [$partida, $destino] = $this->pontos();

        [$condutor] = $this->condutor('ST-01-AA', 'intermunicipal', self::PRAIA, ponto: $partida, destino: $destino);
        $this->subscreverDispositivo($condutor);

        $this->actingAs($this->passageiro(), 'utilizador')
            ->postJson('/api/passageiro/pedir_ponto', [
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        Http::assertSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-1'
            && $p->header('Topic')[0] === 'vm-pedido');
    }

    // -----------------------------------------------------------------
    // Cenário
    // -----------------------------------------------------------------

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
     * @return array{0: PontoPartida, 1: PontoPartida, 2: PontoPartida}
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
            PontoPartida::query()->create([
                'nome' => 'Terra Branca', 'cidade' => 'Praia',
                'lat' => 14.9260, 'lng' => -23.5210, 'zona' => 'urbana',
                'status' => 'aprovado', 'ativo' => true,
            ]),
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $posicao
     * @return array{0: Utilizador, 1: Veiculo}
     */
    private function condutor(
        string $matricula,
        string $tipoServico,
        array $posicao,
        mixed $posicaoEm = null,
        bool $pago = true,
        ?PontoPartida $ponto = null,
        ?PontoPartida $destino = null,
    ): array {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'Condutor '.$matricula,
            'telefone' => '+23898'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
            'tipo' => 'hiace', 'tipo_servico' => $tipoServico, 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $ponto?->id, 'destino_id' => $destino?->id,
            'lat' => $posicao[0], 'lng' => $posicao[1], 'ultima_posicao_em' => $posicaoEm ?? now(),
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        if ($pago) {
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
        }

        return [$condutor, $veiculo];
    }

    private function passageiro(): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => 'Ana Lopes',
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }

    private function subscreverDispositivo(Utilizador $utilizador, string $dispositivo = 'dispositivo-1'): void
    {
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

    private function adminId(): int
    {
        return Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            ['nome' => 'Super Admin', 'senha_hash' => 'senhaforte1', 'nivel' => 'super', 'ativo' => true]
        )->id;
    }
}
