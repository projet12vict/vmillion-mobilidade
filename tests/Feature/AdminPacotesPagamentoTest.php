<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PacotePagamento;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pacotes de pagamento no painel do Super Admin: editar (incluindo o nome) e
 * eliminar. Eliminar apaga mesmo a linha, por isso só pode acontecer enquanto
 * nenhum pagamento apontar para o pacote — a chave estrangeira é nullOnDelete
 * e o histórico de recibos ficaria sem pacote nenhum.
 */
class AdminPacotesPagamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_edita_pacote_incluindo_o_nome(): void
    {
        $pacote = $this->pacote();

        $this->actingAs($this->administrador(), 'admin')
            ->post('/api/admin/precos', [
                'acao' => 'guardar_pacote',
                'id' => $pacote->id,
                'nome' => 'Quinzenal',
                'tipo_servico' => 'urbano',
                'descricao' => 'Quinze dias de acesso',
                'preco' => 2500,
                'duracao_dias' => 15,
                'ativo' => 1,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('pacotes_pagamento', [
            'id' => $pacote->id,
            'nome' => 'Quinzenal',
            'tipo_servico' => 'urbano',
            'preco' => 2500,
            'duracao_dias' => 15,
        ]);
    }

    public function test_editar_sem_nome_mantem_o_atual(): void
    {
        $pacote = $this->pacote();

        $this->actingAs($this->administrador(), 'admin')
            ->post('/api/admin/precos', [
                'acao' => 'guardar_pacote',
                'id' => $pacote->id,
                'tipo_servico' => $pacote->tipo_servico,
                'preco' => 3000,
                'duracao_dias' => $pacote->duracao_dias,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pacotes_pagamento', ['id' => $pacote->id, 'nome' => 'Mensal', 'preco' => 3000]);
    }

    public function test_nome_repetido_no_mesmo_tipo_de_servico_e_recusado(): void
    {
        $pacote = $this->pacote();
        $outro = $this->pacote(['nome' => 'Semanal', 'duracao_dias' => 7]);

        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/precos', [
                'acao' => 'guardar_pacote',
                'id' => $outro->id,
                'nome' => $pacote->nome,
                'tipo_servico' => $pacote->tipo_servico,
                'preco' => $outro->preco,
                'duracao_dias' => $outro->duracao_dias,
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('pacotes_pagamento', ['id' => $outro->id, 'nome' => 'Semanal']);
    }

    public function test_super_admin_elimina_pacote_sem_pagamentos(): void
    {
        $pacote = $this->pacote();
        $admin = $this->administrador();

        $this->actingAs($admin, 'admin')
            ->post('/api/admin/precos', ['acao' => 'eliminar_pacote', 'id' => $pacote->id])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('pacotes_pagamento', ['id' => $pacote->id]);
        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'eliminou_pacote_pagamento',
            'entidade' => 'pacotes_pagamento',
            'entidade_id' => $pacote->id,
        ]);
    }

    public function test_pacote_ja_usado_em_pagamentos_nao_e_eliminado(): void
    {
        $pacote = $this->pacote();
        $veiculo = $this->veiculo();

        PagamentoCondutor::query()->create([
            'condutor_id' => $veiculo->condutor_id,
            'veiculo_id' => $veiculo->id,
            'pacote_id' => $pacote->id,
            'valor_pago' => $pacote->preco,
            'referencia' => 'REF-0001',
            'status' => 'aprovado',
        ]);

        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/precos', ['acao' => 'eliminar_pacote', 'id' => $pacote->id])
            ->assertStatus(409);

        $this->assertDatabaseHas('pacotes_pagamento', ['id' => $pacote->id]);
    }

    public function test_pacote_inexistente_devolve_404(): void
    {
        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/precos', ['acao' => 'eliminar_pacote', 'id' => 999999])
            ->assertStatus(404);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function pacote(array $atributos = []): PacotePagamento
    {
        return PacotePagamento::query()->create($atributos + [
            'nome' => 'Mensal',
            'tipo_servico' => 'urbano',
            'descricao' => 'Trinta dias de acesso',
            'preco' => 5000,
            'duracao_dias' => 30,
            'ativo' => true,
        ]);
    }

    private function veiculo(): Veiculo
    {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        return Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true,
        ]);
    }

    private function administrador(): Administrador
    {
        return Administrador::query()->create([
            'nome' => 'Super Admin',
            'email' => 'super'.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'super',
            'senha_temporaria' => false,
            'ativo' => true,
        ]);
    }
}
