<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O estado da conta de backoffice só era lido no login. Estes testes fixam as
 * duas consequências disso, que valiam ambas acesso total ao painel:
 * desativar um administrador não lhe tirava a sessão já aberta, e a obrigação
 * de trocar a senha temporária vivia só no redirect da página — a /api/admin/*
 * respondia à mesma.
 *
 * O login é feito pelo endpoint real e não com actingAs(): actingAs() fixa a
 * instância do modelo na guard, e um teste assim passava mesmo com a
 * verificação por pedido em falta.
 *
 * Pela mesma razão, mexer na conta a meio de uma sessão é seguido de
 * pedidoNovo(): dentro de um teste o container sobrevive de pedido para
 * pedido e a SessionGuard guarda o utilizador que já resolveu, enquanto em
 * produção cada pedido o vai buscar de novo à base de dados. Sem isto o teste
 * lê o modelo antigo e passa por engano.
 */
class SessaoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_desativar_um_admin_corta_a_sessao_ja_aberta_na_api(): void
    {
        $admin = $this->administradorAutenticado();

        $this->getJson(route('api.admin.dashboard'))->assertOk();

        Administrador::query()->whereKey($admin->id)->update(['ativo' => false]);
        $this->pedidoNovo();

        // 401 é o código que o painel trata (kgApiJSON) redirecionando para o
        // login; a sessão já foi destruída, portanto não é um 403.
        $this->getJson(route('api.admin.dashboard'))->assertUnauthorized();
        $this->getJson(route('api.admin.utilizadores.listar'))->assertUnauthorized();
        $this->getJson(route('api.admin.logs'))->assertUnauthorized();
    }

    public function test_desativar_um_admin_corta_a_sessao_ja_aberta_nas_paginas(): void
    {
        $admin = $this->administradorAutenticado();

        $this->get(route('admin.painel'))->assertOk();

        Administrador::query()->whereKey($admin->id)->update(['ativo' => false]);
        $this->pedidoNovo();

        $this->get(route('admin.painel'))->assertRedirect(route('admin.login'));

        // A sessão foi destruída, não só este pedido recusado: o seguinte já
        // entra como convidado.
        $this->get(route('admin.painel'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_admin_ativo_nao_e_afetado(): void
    {
        $this->administradorAutenticado();

        $this->get(route('admin.painel'))->assertOk();
        $this->getJson(route('api.admin.dashboard'))->assertOk();
    }

    public function test_senha_temporaria_fecha_a_api_de_administracao(): void
    {
        $admin = $this->administrador(['senha_temporaria' => true]);

        foreach (['api.admin.dashboard', 'api.admin.utilizadores.listar', 'api.admin.logs'] as $rota) {
            $this->actingAs($admin, 'admin')
                ->getJson(route($rota))
                ->assertForbidden()
                ->assertJson(['erro' => 'Defina uma senha definitiva antes de continuar.']);
        }
    }

    public function test_senha_temporaria_deixa_passar_a_propria_troca_de_senha(): void
    {
        $admin = $this->administrador(['senha_temporaria' => true]);

        $this->actingAs($admin, 'admin')->get(route('admin.trocar-senha'))->assertOk();

        $this->actingAs($admin, 'admin')
            ->post(route('api.admin.trocar_senha'), [
                'nova_senha' => 'novasenhaforte1',
                'nova_senha_confirmation' => 'novasenhaforte1',
            ])
            ->assertOk();

        // E com a senha definitiva no lugar o backoffice abre.
        $this->actingAs($admin->refresh(), 'admin')
            ->getJson(route('api.admin.dashboard'))
            ->assertOk();
    }

    /**
     * Simula a fronteira entre dois pedidos reais: larga as guards já
     * resolvidas para o pedido seguinte ir buscar a conta à base de dados.
     */
    private function pedidoNovo(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function administrador(array $extra = []): Administrador
    {
        return Administrador::query()->create(array_merge([
            'nome' => 'Admin de teste',
            'email' => 'admin'.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'admin',
            'senha_temporaria' => false,
            'ativo' => true,
        ], $extra));
    }

    private function administradorAutenticado(): Administrador
    {
        $admin = $this->administrador();

        $this->post(route('api.auth.login_admin'), [
            'email' => $admin->email,
            'senha' => 'senhaforte1',
        ])->assertOk();

        return $admin;
    }
}
