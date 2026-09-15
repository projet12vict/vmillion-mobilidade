<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chamada;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\SubscricaoPush;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Support\CifraWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Notificação push de uma chamada: registo do dispositivo, envio ao serviço
 * de push do fabricante e limpeza das subscrições mortas.
 *
 * O que se verifica no envio é o contrato com o serviço de push (VAPID no
 * Authorization, aes128gcm no corpo) — a cifra em si é provada em
 * Tests\Unit\CifraWebPushTest.
 */
class PushChamadaTest extends TestCase
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

    public function test_o_dispositivo_subscreve_e_a_mesma_subscricao_nao_duplica(): void
    {
        $passageiro = $this->passageiro();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/push/subscrever', $this->subscricaoDoBrowser())
            ->assertOk()->assertJson(['sucesso' => true]);

        // O painel reenvia a subscrição em cada arranque: sem updateOrCreate,
        // o mesmo telemóvel passava a receber a chamada várias vezes.
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/push/subscrever', $this->subscricaoDoBrowser())
            ->assertOk();

        $this->assertSame(1, SubscricaoPush::query()->where('utilizador_id', $passageiro->id)->count());
    }

    public function test_subscrever_exige_sessao(): void
    {
        $this->postJson('/api/push/subscrever', $this->subscricaoDoBrowser())->assertStatus(401);
    }

    public function test_a_chave_publica_e_entregue_ao_browser_mas_a_privada_nunca(): void
    {
        $resposta = $this->actingAs($this->passageiro(), 'utilizador')
            ->getJson('/api/push/chave')
            ->assertOk()
            ->assertJson(['ativo' => true]);

        $this->assertSame(config('vmillion.push.chave_publica'), $resposta->json('chave_publica'));
        $this->assertStringNotContainsString(
            (string) config('vmillion.push.chave_privada'),
            $resposta->getContent(),
        );
    }

    public function test_iniciar_chamada_envia_push_assinado_ao_dispositivo_do_destinatario(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        Http::assertSent(function (Request $pedido) {
            return $pedido->url() === 'https://push.exemplo.cv/dispositivo-1'
                && str_starts_with($pedido->header('Authorization')[0], 'vapid t=')
                && str_contains($pedido->header('Authorization')[0], 'k='.config('vmillion.push.chave_publica'))
                && $pedido->header('Content-Encoding')[0] === 'aes128gcm'
                && $pedido->header('TTL')[0] === (string) config('vmillion.push.ttl_chamada')
                && $pedido->header('Urgency')[0] === 'high'
                // Salt (16) + rs (4) + idlen (1) + chave efémera (65) + cifra.
                && strlen($pedido->body()) > 86;
        });

        // O conteúdo vai cifrado: o serviço de push encaminha sem saber quem
        // ligou a quem.
        Http::assertSent(fn (Request $pedido) => ! str_contains($pedido->body(), $passageiro->nome));
    }

    public function test_uma_subscricao_que_o_fabricante_diz_ter_morrido_e_apagada(): void
    {
        // 410 Gone: app desinstalada, permissão revogada ou dados do site
        // limpos. É o único aviso que existe — sem isto a tabela só crescia.
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 410)]);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        $this->assertSame(0, SubscricaoPush::query()->count());
    }

    public function test_o_servico_de_push_em_baixo_nao_impede_a_chamada(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 500)]);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        // A chamada existe na mesma: o destinatário apanha-a pelo polling de
        // /api/chamada/verificar assim que abrir a app.
        $this->assertSame(1, Chamada::query()->where('estado', 'iniciada')->count());
        $this->assertSame(1, SubscricaoPush::query()->count(), 'Um 500 é temporário e não mata a subscrição.');
    }

    public function test_o_push_so_vai_para_os_dispositivos_do_destinatario(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor, 'dispositivo-condutor');
        $this->subscreverDispositivo($this->passageiro(), 'dispositivo-de-terceiro');

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $p) => $p->url() === 'https://push.exemplo.cv/dispositivo-condutor');
    }

    public function test_desistir_antes_de_atender_manda_fechar_o_aviso(): void
    {
        Http::fake(['https://push.exemplo.cv/*' => Http::response('', 201)]);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor);

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/desligar', ['chamada_id' => $id])
            ->assertOk();

        // Um push a criar o aviso e outro a mandá-lo fechar: sem o segundo, o
        // telemóvel do condutor ficava a tocar para uma chamada que já acabou.
        Http::assertSentCount(2);
    }

    public function test_sem_chaves_vapid_configuradas_nao_ha_envio_nenhum(): void
    {
        Http::fake();
        config(['vmillion.push.chave_publica' => '', 'vmillion.push.chave_privada' => '']);

        [$passageiro, $condutor] = $this->viagemComum();
        $this->subscreverDispositivo($condutor);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        Http::assertNothingSent();

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/push/chave')
            ->assertOk()
            ->assertJson(['ativo' => false, 'chave_publica' => '']);
    }

    /** @return array<string, string> */
    private function subscricaoDoBrowser(string $dispositivo = 'dispositivo-1'): array
    {
        // Chaves com a forma real das que o browser entrega: o p256dh é um
        // ponto não comprimido da P-256 (65 bytes) e o auth tem 16 bytes.
        $chave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($chave)['ec'];
        $alinhar = fn (string $n): string => str_pad(ltrim($n, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return [
            'endpoint' => 'https://push.exemplo.cv/'.$dispositivo,
            'p256dh' => CifraWebPush::base64urlCodificar("\x04".$alinhar($ec['x']).$alinhar($ec['y'])),
            'auth' => CifraWebPush::base64urlCodificar(random_bytes(16)),
        ];
    }

    private function subscreverDispositivo(Utilizador $utilizador, string $dispositivo = 'dispositivo-1'): void
    {
        $this->actingAs($utilizador, 'utilizador')
            ->postJson('/api/push/subscrever', $this->subscricaoDoBrowser($dispositivo))
            ->assertOk();
    }

    /** @return array{0: Utilizador, 1: Utilizador} */
    private function viagemComum(): array
    {
        $partida = PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $partida->id,
        ]);

        $passageiro = $this->passageiro();

        Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => $veiculo->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $partida->id,
            'lugares' => 1,
            'preco_final' => 100,
            'estado' => 'confirmado',
        ]);

        return [$passageiro, $condutor];
    }

    private function passageiro(): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro',
            'nome' => 'Passageiro '.random_int(1, 9999),
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }
}
