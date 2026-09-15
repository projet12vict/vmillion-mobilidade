<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Os painéis do condutor e do passageiro ficam abertos entre turnos e entre
 * viagens: uma vez autenticado, o utilizador só sai quando carrega em "Sair".
 * Nem o timeout de inatividade (TimeoutInatividade) nem o fim do cookie de
 * sessão o podem mandar para o /login.
 */
class SessaoPersistenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{string}>
     */
    public static function tipos(): array
    {
        return [['condutor'], ['passageiro']];
    }

    #[DataProvider('tipos')]
    public function test_login_emite_o_cookie_de_manter_sessao_iniciada(string $tipo): void
    {
        $utilizador = $this->utilizador($tipo);

        $resposta = $this->postJson('/api/auth/login', [
            'identificador' => $utilizador->telefone,
            'senha' => 'senhaforte1',
        ])->assertOk();

        $resposta->assertCookie(Auth::guard('utilizador')->getRecallerName());
        $this->assertNotNull($utilizador->fresh()->remember_token);
    }

    #[DataProvider('tipos')]
    public function test_parado_muito_para_la_do_timeout_continua_no_painel(string $tipo): void
    {
        $utilizador = $this->utilizador($tipo);
        $paradoHa = time() - (config('vmillion.sessao.inactivity_timeout') * 60) - 3600;

        $this->actingAs($utilizador, 'utilizador')
            ->withSession(['vmillion_ultimo_acesso' => $paradoHa])
            ->get(route($tipo.'.painel'))
            ->assertOk();

        $this->assertAuthenticated('utilizador');
    }

    #[DataProvider('tipos')]
    public function test_com_a_sessao_perdida_o_cookie_de_recordacao_devolve_ao_painel(string $tipo): void
    {
        // É o caso de fechar o browser e voltar no dia seguinte: a sessão já
        // não existe e só o cookie de recordação identifica o utilizador.
        $utilizador = $this->utilizador($tipo);
        $utilizador->forceFill(['remember_token' => 'um-token-de-recordacao'])->save();

        $recaller = $utilizador->id.'|um-token-de-recordacao|'.$utilizador->senha_hash;

        $this->withCookie(Auth::guard('utilizador')->getRecallerName(), $recaller)
            ->get(route($tipo.'.painel'))
            ->assertOk();

        $this->assertAuthenticatedAs($utilizador, 'utilizador');
    }

    #[DataProvider('tipos')]
    public function test_sair_apaga_a_sessao_persistente(string $tipo): void
    {
        $utilizador = $this->utilizador($tipo);
        $utilizador->forceFill(['remember_token' => 'um-token-de-recordacao'])->save();

        $this->actingAs($utilizador, 'utilizador')
            ->post('/logout')
            ->assertRedirect(route('inicio'));

        $this->assertGuest('utilizador');
        $this->assertNotSame('um-token-de-recordacao', $utilizador->fresh()->remember_token);
    }

    private function utilizador(string $tipo): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo,
            'nome' => $tipo === 'condutor' ? 'João Tavares' : 'Maria Semedo',
            'telefone' => $tipo === 'condutor' ? '+2389987654' : '+2389912345',
            'nif' => $tipo === 'condutor' ? '987654321' : '123456789',
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }
}
