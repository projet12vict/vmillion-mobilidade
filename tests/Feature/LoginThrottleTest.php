<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As duas camadas que travam tentativas de login: o bloqueio por conta
 * (LoginThrottleService, gravado na linha da conta) e o limitador por pedido
 * ('login', registado no AppServiceProvider e aplicado em routes/api.php).
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function utilizador(array $atributos = []): Utilizador
    {
        return Utilizador::query()->create(array_merge([
            'tipo' => 'passageiro',
            'nome' => 'Maria Semedo',
            'telefone' => '+2389912345',
            'nif' => '123456789',
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ], $atributos));
    }

    private function tentar(string $identificador, string $senha = 'errada')
    {
        return $this->postJson('/api/auth/login', [
            'identificador' => $identificador,
            'senha' => $senha,
        ]);
    }

    private function assertDemasiadasTentativas($resposta): void
    {
        $resposta->assertStatus(429)->assertJsonPath(
            'erro',
            fn (string $erro) => str_starts_with($erro, 'Demasiadas tentativas.')
        );
    }

    public function test_a_conta_bloqueia_ao_fim_das_tentativas_configuradas(): void
    {
        config(['vmillion.login.max_tentativas' => 5, 'vmillion.login.bloqueio_minutos' => 15]);
        $this->utilizador();

        foreach (range(1, 5) as $tentativa) {
            $this->tentar('+2389912345')->assertStatus(401);
        }

        // Bloqueada é bloqueada: nem com a senha certa entra.
        $this->assertDemasiadasTentativas($this->tentar('+2389912345', 'senhaforte1'));
        $this->assertGuest('utilizador');
    }

    public function test_uma_falha_isolada_depois_do_bloqueio_expirar_nao_volta_a_bloquear(): void
    {
        // Este era o caminho que permitia negar a conta a outra pessoa: como o
        // contador nunca voltava atrás, a primeira falha depois de um bloqueio
        // expirado bloqueava logo outros quinze minutos, para sempre.
        config(['vmillion.login.max_tentativas' => 5, 'vmillion.login.bloqueio_minutos' => 15]);
        $utilizador = $this->utilizador();

        foreach (range(1, 5) as $tentativa) {
            $this->tentar('+2389912345')->assertStatus(401);
        }

        $this->travel(16)->minutes();

        $this->tentar('+2389912345')->assertStatus(401);

        $utilizador->refresh();
        $this->assertNull($utilizador->bloqueado_ate);

        // E quem sabe a senha continua a entrar.
        $this->tentar('+2389912345', 'senhaforte1')->assertOk();
        $this->assertAuthenticated('utilizador');
    }

    public function test_o_segundo_bloqueio_consecutivo_dura_o_dobro_do_primeiro(): void
    {
        config([
            'vmillion.login.max_tentativas' => 5,
            'vmillion.login.bloqueio_minutos' => 15,
            'vmillion.login.bloqueio_max_minutos' => 60,
        ]);
        $utilizador = $this->utilizador();

        foreach (range(1, 5) as $tentativa) {
            $this->tentar('+2389912345')->assertStatus(401);
        }

        $utilizador->refresh();
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($utilizador->bloqueado_ate), 1);

        $this->travel(16)->minutes();

        foreach (range(1, 5) as $tentativa) {
            $this->tentar('+2389912345');
        }

        $utilizador->refresh();
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($utilizador->bloqueado_ate), 1);
        $this->assertSame(10, $utilizador->tentativas_login);
    }

    public function test_o_login_bem_sucedido_limpa_o_contador(): void
    {
        config(['vmillion.login.max_tentativas' => 5]);
        $utilizador = $this->utilizador();

        $this->tentar('+2389912345')->assertStatus(401);
        $this->tentar('+2389912345', 'senhaforte1')->assertOk();

        $utilizador->refresh();
        $this->assertSame(0, $utilizador->tentativas_login);
        $this->assertNull($utilizador->bloqueado_ate);
    }

    public function test_o_limitador_por_ip_trava_tentativas_contra_contas_diferentes(): void
    {
        // O caso que o bloqueio por conta não cobria: uma senha comum testada
        // contra muitos telefones, sem nunca esgotar as tentativas de nenhum.
        config([
            'vmillion.login.throttle.ip_por_minuto' => 3,
            'vmillion.login.throttle.conta_por_minuto' => 99,
        ]);

        foreach (range(1, 3) as $n) {
            $this->tentar("+23899123{$n}0")->assertStatus(401);
        }

        $this->assertDemasiadasTentativas($this->tentar('+2389912399'));
    }

    public function test_o_limitador_por_identificador_e_independente_entre_contas(): void
    {
        config([
            'vmillion.login.throttle.conta_por_minuto' => 3,
            'vmillion.login.throttle.ip_por_minuto' => 99,
        ]);

        foreach (range(1, 3) as $n) {
            $this->tentar('+2389912345')->assertStatus(401);
        }

        $this->assertDemasiadasTentativas($this->tentar('+2389912345'));

        // Outro identificador tem contador próprio.
        $this->tentar('+2389987654')->assertStatus(401);
    }

    public function test_o_contador_por_identificador_ignora_o_espaco_do_indicativo(): void
    {
        config([
            'vmillion.login.throttle.conta_por_minuto' => 2,
            'vmillion.login.throttle.ip_por_minuto' => 99,
        ]);

        $this->tentar('+2389912345')->assertStatus(401);
        $this->tentar('+238 9912345')->assertStatus(401);

        $this->assertDemasiadasTentativas($this->tentar('+238 9912345'));
    }
}
