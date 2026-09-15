<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As duas populações de contas são estanques e cada painel só alcança as
 * suas rotas — era o que kg_exigir_utilizador()/kg_exigir_admin() garantiam.
 */
class AutorizacaoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_sem_sessao_devolve_401_no_formato_do_frontend(): void
    {
        $this->getJson('/api/passageiro/pontos')
            ->assertStatus(401)
            ->assertJson(['erro' => 'Não autenticado.']);
    }

    public function test_condutor_nao_alcanca_rotas_de_passageiro(): void
    {
        // A reserva é exclusiva do passageiro; os pontos de partida, esses,
        // são partilhados com o painel do condutor (ver PontosNoMapaCondutorTest).
        $this->actingAs($this->utilizador('condutor'), 'utilizador')
            ->getJson('/api/passageiro/minha_reserva')
            ->assertStatus(401);
    }

    public function test_passageiro_nao_alcanca_rotas_de_condutor(): void
    {
        $this->actingAs($this->utilizador('passageiro'), 'utilizador')
            ->getJson('/api/condutor/veiculos')
            ->assertStatus(401);
    }

    public function test_utilizador_nao_alcanca_rotas_de_administracao(): void
    {
        $this->actingAs($this->utilizador('passageiro'), 'utilizador')
            ->getJson('/api/admin/dashboard')
            ->assertStatus(401);
    }

    public function test_admin_sem_nivel_super_nao_ve_precos(): void
    {
        $this->actingAs($this->admin('admin'), 'admin')
            ->getJson('/api/admin/precos')
            ->assertStatus(403)
            ->assertJson(['erro' => 'Apenas o Super Admin pode executar esta ação.']);
    }

    public function test_super_admin_ve_precos(): void
    {
        $this->actingAs($this->admin('super'), 'admin')
            ->getJson('/api/admin/precos')
            ->assertOk()
            ->assertJsonStructure(['precos_km', 'precos_rotas', 'config', 'pacotes']);
    }

    public function test_utilizador_suspenso_e_expulso_a_meio_da_sessao(): void
    {
        $utilizador = $this->utilizador('passageiro');
        $utilizador->forceFill(['status' => 'suspenso'])->save();

        $this->actingAs($utilizador, 'utilizador')
            ->getJson('/api/passageiro/pontos')
            ->assertStatus(403)
            ->assertJson(['erro' => 'A sua conta está suspensa. Contacte o administrador.']);
    }

    private function utilizador(string $tipo): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo,
            'nome' => 'Conta '.$tipo,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }

    private function admin(string $nivel): Administrador
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
