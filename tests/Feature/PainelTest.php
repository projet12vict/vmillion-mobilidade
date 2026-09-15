<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Os três painéis são views Blade com milhares de linhas de JS inline, herdadas
 * do sistema original. Renderizá-los é o que garante que a conversão não deixou
 * nenhuma diretiva Blade partida nem nenhuma variável por passar.
 */
class PainelTest extends TestCase
{
    use RefreshDatabase;

    public function test_painel_do_passageiro_renderiza_com_o_nome_e_o_token(): void
    {
        $passageiro = $this->utilizador('passageiro', 'Maria Semedo');

        $this->actingAs($passageiro, 'utilizador')
            ->get(route('passageiro.painel'))
            ->assertOk()
            ->assertSee('Maria Semedo')
            ->assertSee('const CSRF_TOKEN', escape: false)
            ->assertSee('/api/passageiro/minha_reserva', escape: false);
    }

    public function test_painel_do_condutor_renderiza_e_mostra_o_mapa_sem_veiculo_aprovado(): void
    {
        $condutor = $this->utilizador('condutor', 'João Tavares');

        // Sem veículo aprovado a candidatura ainda está em curso: o mapa fica
        // visível, que é o que permite registar o primeiro veículo.
        $this->actingAs($condutor, 'utilizador')
            ->get(route('condutor.painel'))
            ->assertOk()
            ->assertSee('João Tavares')
            ->assertSee('const MOSTRAR_MAPA = true', escape: false);
    }

    public function test_painel_de_administracao_esconde_as_seccoes_do_super_admin(): void
    {
        $this->actingAs($this->administrador('admin'), 'admin')
            ->get(route('admin.painel'))
            ->assertOk()
            ->assertSee('const EH_SUPER = false', escape: false)
            ->assertDontSee('data-secao="precos"', escape: false)
            ->assertSee('Reclamações');
    }

    public function test_painel_de_administracao_mostra_tudo_ao_super_admin(): void
    {
        $this->actingAs($this->administrador('super'), 'admin')
            ->get(route('admin.painel'))
            ->assertOk()
            ->assertSee('const EH_SUPER = true', escape: false)
            ->assertSee('data-secao="precos"', escape: false)
            ->assertSee('Sugestões e reclamações');
    }

    public function test_admin_com_senha_temporaria_e_obrigado_a_troca_la(): void
    {
        $admin = $this->administrador('admin');
        $admin->forceFill(['senha_temporaria' => true])->save();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.painel'))
            ->assertRedirect(route('admin.trocar-senha'));
    }

    public function test_trocar_senha_aceita_os_campos_que_o_formulario_envia(): void
    {
        $admin = $this->administrador('admin');
        $admin->forceFill(['senha_temporaria' => true])->save();

        // Os nomes dos campos têm de ser exatamente os da view trocar-senha:
        // a regra 'confirmed' espera o sufixo _confirmation.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.trocar-senha'))
            ->assertOk()
            ->assertSee('name="nova_senha"', escape: false)
            ->assertSee('name="nova_senha_confirmation"', escape: false);

        $this->actingAs($admin, 'admin')
            ->post(route('api.admin.trocar_senha'), [
                'nova_senha' => 'novasenhaforte1',
                'nova_senha_confirmation' => 'novasenhaforte1',
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $admin->refresh();

        $this->assertFalse($admin->senha_temporaria);
        $this->assertTrue(Hash::check('novasenhaforte1', $admin->senha_hash));
    }

    /**
     * Os ficheiros de public/assets não passam pelo Vite (o nome nunca muda) e
     * durante muito tempo saíram sem versão nenhuma no URL: o browser e a
     * cache da PWA continuavam a servir a cópia guardada meses antes. Foi
     * assim que os painéis ficaram a pedir /api/chamada/iniciar.php muito
     * depois de o .php ter desaparecido das rotas, com o Laravel a responder
     * "The route api/chamada/iniciar.php could not be found" a cada chamada.
     */
    public function test_os_scripts_estaticos_dos_paineis_saem_com_versao_no_url(): void
    {
        $html = $this->actingAs($this->utilizador('passageiro'), 'utilizador')
            ->get(route('passageiro.painel'))
            ->assertOk()
            ->getContent();

        // Só o código: os ícones da PWA são ficheiros estáveis e o manifest
        // aponta-lhes o caminho nu, sem passar por aqui.
        preg_match_all('#(?:src|href)="(/assets/(?:js|css)/[^"]+)"#', (string) $html, $encontrados);

        $this->assertNotEmpty($encontrados[1], 'Nenhum JS ou CSS de /assets no painel — o varrimento deixou de funcionar.');
        $this->assertContains(
            true,
            array_map(fn ($url) => str_contains($url, '/assets/js/kg-chamada.js?v='), $encontrados[1]),
            'O kg-chamada.js saiu sem ?v= — uma cópia antiga do browser volta a pedir /api/chamada/iniciar.php.'
        );

        foreach ($encontrados[1] as $url) {
            $this->assertMatchesRegularExpression('#\?v=\d+$#', $url, "{$url} saiu sem versão.");
        }
    }

    public function test_login_redireciona_quem_ja_tem_sessao_iniciada(): void
    {
        $this->actingAs($this->utilizador('condutor'), 'utilizador')
            ->get(route('login'))
            ->assertRedirect(route('condutor.painel'));
    }

    private function utilizador(string $tipo, string $nome = 'Conta'): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo,
            'nome' => $nome,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }

    private function administrador(string $nivel): Administrador
    {
        return Administrador::query()->create([
            'nome' => 'Admin '.$nivel,
            'email' => $nivel.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => $nivel,
            'senha_temporaria' => false,
            'ativo' => true,
        ]);
    }
}
