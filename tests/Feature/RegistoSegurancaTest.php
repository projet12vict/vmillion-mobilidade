<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogAuditoria;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Regressões do endurecimento de /registo e /api/auth/registar.
 *
 * Cada teste aqui corresponde a uma falha que existiu: sem eles, a próxima
 * pessoa a mexer no controller não tem como saber que o formato da resposta
 * de duplicado, ou o limite de pedidos, são decisões de segurança e não
 * detalhes de apresentação.
 */
class RegistoSegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_registo_esta_limitado_por_ip(): void
    {
        $limite = (int) config('vmillion.registo.throttle.ip_por_minuto');

        for ($i = 0; $i < $limite; $i++) {
            $this->registar([
                'telefone' => '+238 99'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'nif' => str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT),
            ])->assertOk();
        }

        // O pedido a seguir ao limite já não chega ao controller.
        $this->registar(['telefone' => '+238 9999999', 'nif' => '199999999'])
            ->assertStatus(429)
            ->assertJsonPath('erro', fn (string $erro) => str_starts_with($erro, 'Demasiadas tentativas.'));

        $this->assertSame($limite, Utilizador::query()->count());
    }

    public function test_a_resposta_de_duplicado_nao_diz_se_foi_o_telefone_ou_o_nif(): void
    {
        $this->registar(['telefone' => '+238 9912345', 'nif' => '123456789'])->assertOk();

        // Telefone repetido, NIF novo.
        $porTelefone = $this->registar(['telefone' => '+238 9912345', 'nif' => '999888777']);
        // NIF repetido, telefone novo.
        $porNif = $this->registar(['telefone' => '+238 9955555', 'nif' => '123456789']);

        $porTelefone->assertStatus(409);
        $porNif->assertStatus(409)->assertExactJson($porTelefone->json());
    }

    public function test_o_nome_nao_aceita_sinais_de_tag(): void
    {
        $this->registar(['nome' => '<script src="https://unpkg.com/x"></script>'])
            ->assertStatus(422)
            ->assertJson(['erro' => 'O nome não pode conter os caracteres < ou >.']);

        $this->assertDatabaseCount('utilizadores', 0);
    }

    public function test_a_senha_curta_e_recusada(): void
    {
        $this->registar(['senha' => 'curta', 'confirmar_senha' => 'curta'])
            ->assertStatus(422);

        $this->assertDatabaseCount('utilizadores', 0);
    }

    public function test_a_criacao_de_conta_fica_nos_logs_de_auditoria(): void
    {
        $this->registar(['tipo' => 'condutor'])->assertOk();

        $condutor = Utilizador::query()->firstOrFail();

        $this->assertDatabaseHas('logs_auditoria', [
            'admin_id' => null,
            'acao' => 'conta_criada',
            'entidade' => 'utilizadores',
            'entidade_id' => $condutor->id,
            'detalhes' => 'condutor (pendente)',
        ]);

        // Como nas falhas de login: o que veio do formulário não entra nos logs.
        $this->assertFalse(
            LogAuditoria::query()->where('detalhes', 'like', '%Maria%')->exists(),
            'o nome escrito no formulário não devia estar nos logs'
        );
    }

    /**
     * @param  array<string, string>  $campos
     */
    private function registar(array $campos = []): TestResponse
    {
        return $this->postJson('/api/auth/registar', array_merge([
            'nome' => 'Maria Semedo',
            'telefone' => '+238 9912345',
            'nif' => '123456789',
            'ilha' => 'Santiago',
            'municipio' => 'Praia',
            'senha' => 'senhaforte1',
            'confirmar_senha' => 'senhaforte1',
            'tipo' => 'passageiro',
        ], $campos));
    }
}
