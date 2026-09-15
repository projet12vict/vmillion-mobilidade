<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PacotePagamento;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pacote de teste (preço 0): o condutor ativa-o sem comprovativo, fica
 * aprovado logo e só o pode usar uma vez. Os pacotes pagos continuam a exigir
 * comprovativo e a aguardar o admin.
 */
class PacoteTesteCondutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacote_de_teste_sem_comprovativo_fica_aprovado_e_ativa_a_conta(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor('pendente');
        $veiculo = $this->veiculo($condutor);
        $pacote = $this->pacote(['nome' => 'teste', 'preco' => 0, 'duracao_dias' => 7]);

        $this->actingAs($condutor, 'utilizador')
            ->post('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'aprovado' => true]);

        $pagamento = PagamentoCondutor::query()->where('condutor_id', $condutor->id)->sole();

        $this->assertSame('aprovado', $pagamento->status);
        $this->assertNull($pagamento->comprovativo_path);
        $this->assertNull($pagamento->aprovado_por);
        $this->assertSame(0.0, $pagamento->valor_pago);
        $this->assertTrue($pagamento->data_validade->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        Storage::disk('local')->assertExists($pagamento->recibo_path);
        $this->assertSame('ativo', $condutor->fresh()->status);

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/pagamentos')
            ->assertJson(['pagamento_valido' => true, 'pacote_teste_usado' => true]);
    }

    public function test_segundo_pacote_de_teste_e_recusado_mesmo_noutro_veiculo(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor();
        $primeiro = $this->veiculo($condutor, 'ST-01-AA');
        $segundo = $this->veiculo($condutor, 'ST-02-BB');
        $pacote = $this->pacote(['nome' => 'teste', 'preco' => 0]);

        $this->actingAs($condutor, 'utilizador')
            ->post('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $primeiro->id, 'pacote_id' => $pacote->id,
            ])
            ->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->post('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $segundo->id, 'pacote_id' => $pacote->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Já utilizou o seu pacote de teste. Escolha um pacote pago.']);

        $this->assertSame(1, PagamentoCondutor::query()->where('condutor_id', $condutor->id)->count());
    }

    /** Reverter o teste não o devolve: o condutor não pode repeti-lo. */
    public function test_pacote_de_teste_revertido_continua_a_contar_como_usado(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor();
        $veiculo = $this->veiculo($condutor);
        $pacote = $this->pacote(['nome' => 'teste', 'preco' => 0]);

        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id, 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
            'valor_pago' => 0, 'referencia' => 'REF-TESTE', 'status' => 'recusado',
        ]);

        $this->actingAs($condutor, 'utilizador')
            ->post('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
            ])
            ->assertStatus(409);
    }

    public function test_pacote_pago_sem_comprovativo_continua_a_ser_recusado(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor();
        $veiculo = $this->veiculo($condutor);
        $pacote = $this->pacote();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
            ])
            ->assertStatus(422)
            ->assertJson(['erro' => 'Anexe o comprovativo de pagamento (imagem ou PDF).']);

        $this->assertSame(0, PagamentoCondutor::query()->count());
    }

    public function test_pacote_pago_com_comprovativo_fica_pendente(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor();
        $veiculo = $this->veiculo($condutor);
        $pacote = $this->pacote();

        $this->actingAs($condutor, 'utilizador')
            ->post('/api/condutor/pagamentos', [
                'acao' => 'solicitar', 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
                'comprovativo' => UploadedFile::fake()->create('talao.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $pagamento = PagamentoCondutor::query()->sole();
        $this->assertSame('pendente', $pagamento->status);
        $this->assertNotNull($pagamento->comprovativo_path);
    }

    /** A extração para AprovacaoPagamentoService não muda a aprovação do admin. */
    public function test_admin_continua_a_aprovar_pedido_pago_com_recibo(): void
    {
        Storage::fake('local');
        $condutor = $this->condutor('pendente');
        $veiculo = $this->veiculo($condutor);
        $pacote = $this->pacote();
        $admin = $this->administrador();

        $pagamento = PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id, 'veiculo_id' => $veiculo->id, 'pacote_id' => $pacote->id,
            'valor_pago' => 5000, 'referencia' => 'REF-PAGO', 'status' => 'pendente',
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/api/admin/pagamentos', ['acao' => 'aprovar', 'id' => $pagamento->id])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $pagamento->refresh();
        $this->assertSame('aprovado', $pagamento->status);
        $this->assertSame($admin->id, $pagamento->aprovado_por);
        Storage::disk('local')->assertExists($pagamento->recibo_path);
        $this->assertSame('ativo', $condutor->fresh()->status);
    }

    private function condutor(string $status = 'ativo'): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => $status,
        ]);
    }

    private function veiculo(Utilizador $condutor, string $matricula = 'ST-01-AA'): Veiculo
    {
        return Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
            'tipo' => 'hiace', 'tipo_servico' => 'urbano', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function pacote(array $atributos = []): PacotePagamento
    {
        return PacotePagamento::query()->create($atributos + [
            'nome' => 'mensal',
            'tipo_servico' => 'urbano',
            'preco' => 5000,
            'duracao_dias' => 30,
            'ativo' => true,
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
