<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogAuditoria;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_landing_page_responde(): void
    {
        $this->get('/')->assertOk()->assertSee('V-MILLION', escape: false);
    }

    public function test_registo_cria_um_passageiro_ativo(): void
    {
        $resposta = $this->postJson('/api/auth/registar', [
            'nome' => 'Maria Semedo',
            'telefone' => '+238 9912345',
            'nif' => '123456789',
            'ilha' => 'Santiago',
            'municipio' => 'Praia',
            'senha' => 'senhaforte1',
            'confirmar_senha' => 'senhaforte1',
            'tipo' => 'passageiro',
        ]);

        $resposta->assertOk()->assertJson(['sucesso' => true]);

        // O telefone é guardado sempre normalizado, sem o espaço do indicativo.
        $this->assertDatabaseHas('utilizadores', [
            'telefone' => '+2389912345',
            'tipo' => 'passageiro',
            'status' => 'ativo',
        ]);
    }

    public function test_registo_de_condutor_fica_pendente_de_aprovacao(): void
    {
        $this->postJson('/api/auth/registar', [
            'nome' => 'João Tavares',
            'telefone' => '+2389987654',
            'nif' => '987654321',
            'ilha' => 'São Vicente',
            'municipio' => 'São Vicente',
            'senha' => 'senhaforte1',
            'confirmar_senha' => 'senhaforte1',
            'tipo' => 'condutor',
        ])->assertOk();

        $this->assertDatabaseHas('utilizadores', ['tipo' => 'condutor', 'status' => 'pendente']);
    }

    public function test_registo_recusa_senhas_diferentes(): void
    {
        $this->postJson('/api/auth/registar', [
            'nome' => 'Ana Lopes',
            'telefone' => '+2389911111',
            'nif' => '111111111',
            'ilha' => 'Santiago',
            'municipio' => 'Praia',
            'senha' => 'senhaforte1',
            'confirmar_senha' => 'outracoisa1',
            'tipo' => 'passageiro',
        ])->assertStatus(422)->assertJson(['erro' => 'As senhas não coincidem.']);
    }

    public function test_login_com_credenciais_erradas_devolve_401(): void
    {
        Utilizador::query()->create([
            'tipo' => 'passageiro',
            'nome' => 'Maria Semedo',
            'telefone' => '+2389912345',
            'nif' => '123456789',
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        $this->postJson('/api/auth/login', [
            'identificador' => '+2389912345',
            'senha' => 'errada',
        ])->assertStatus(401)->assertJson(['erro' => 'Credenciais inválidas.']);
    }

    public function test_login_bem_sucedido_encaminha_para_o_painel_certo(): void
    {
        Utilizador::query()->create([
            'tipo' => 'passageiro',
            'nome' => 'Maria Semedo',
            'telefone' => '+2389912345',
            'nif' => '123456789',
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        // O identificador aceita a forma escrita com espaço; a normalização é
        // que faz a ponte para o que está guardado.
        $this->postJson('/api/auth/login', [
            'identificador' => '+238 9912345',
            'senha' => 'senhaforte1',
        ])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'tipo' => 'passageiro'])
            ->assertJsonPath('redirect', route('passageiro.painel'));

        $this->assertAuthenticated('utilizador');
    }

    public function test_condutor_pendente_nao_entra(): void
    {
        Utilizador::query()->create([
            'tipo' => 'condutor',
            'nome' => 'João Tavares',
            'telefone' => '+2389987654',
            'nif' => '987654321',
            'senha_hash' => 'senhaforte1',
            'status' => 'pendente',
        ]);

        $this->postJson('/api/auth/login', [
            'identificador' => '+2389987654',
            'senha' => 'senhaforte1',
        ])->assertStatus(403);

        $this->assertGuest('utilizador');
    }

    public function test_um_identificador_desconhecido_responde_igual_a_uma_senha_errada(): void
    {
        // A resposta não pode deixar distinguir "esta conta não existe" de
        // "a senha está errada" — nem no corpo, nem no estado (o tempo é
        // igualado por HashReferencia, ver LoginController).
        $this->utilizador();

        $senhaErrada = $this->postJson('/api/auth/login', [
            'identificador' => '+2389912345',
            'senha' => 'errada',
        ]);

        $desconhecido = $this->postJson('/api/auth/login', [
            'identificador' => '+2389900000',
            'senha' => 'errada',
        ]);

        $senhaErrada->assertStatus(401);
        $desconhecido->assertStatus(401)->assertExactJson($senhaErrada->json());
    }

    public function test_as_falhas_de_login_ficam_nos_logs_de_auditoria(): void
    {
        $utilizador = $this->utilizador();

        $this->postJson('/api/auth/login', [
            'identificador' => '+2389912345',
            'senha' => 'errada',
        ])->assertStatus(401);

        // admin_id fica nulo (a coluna é uma FK para administradores) e a conta
        // visada vai em entidade/entidade_id.
        $this->assertDatabaseHas('logs_auditoria', [
            'admin_id' => null,
            'acao' => 'login_falhado',
            'entidade' => 'utilizadores',
            'entidade_id' => $utilizador->id,
            'detalhes' => 'senha incorreta',
        ]);

        $this->postJson('/api/auth/login', [
            'identificador' => 'ninguem@exemplo.cv',
            'senha' => 'errada',
        ])->assertStatus(401);

        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'login_falhado',
            'entidade_id' => null,
            'detalhes' => 'email desconhecido',
        ]);
    }

    public function test_os_logs_nao_guardam_o_identificador_tal_como_foi_escrito(): void
    {
        // O painel de administração monta a tabela de logs com innerHTML: o que
        // vem do formulário não pode chegar lá.
        $this->postJson('/api/auth/login', [
            'identificador' => '<img src=x onerror=alert(1)>@exemplo.cv',
            'senha' => 'errada',
        ])->assertStatus(401);

        $this->assertDatabaseHas('logs_auditoria', ['acao' => 'login_falhado']);
        $this->assertFalse(
            LogAuditoria::query()->where('detalhes', 'like', '%onerror%')->exists(),
            'o identificador escrito no formulário não devia estar nos logs'
        );
    }

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
}
