<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eliminar um veículo apaga em cascata assentos, reservas e pagamentos — por
 * isso a ação tem de recusar enquanto houver passageiros a bordo ou à espera.
 */
class AdminEliminarVeiculoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_elimina_veiculo_sem_reservas_ativas(): void
    {
        $veiculo = $this->veiculo();

        $this->actingAs($this->administrador(), 'admin')
            ->post('/api/admin/veiculos', ['acao' => 'eliminar', 'id' => $veiculo->id])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('veiculos', ['id' => $veiculo->id]);
        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'eliminou_veiculo',
            'entidade' => 'veiculos',
            'entidade_id' => $veiculo->id,
            'detalhes' => $veiculo->matricula,
        ]);
    }

    public function test_veiculo_com_reserva_ativa_nao_e_eliminado(): void
    {
        $veiculo = $this->veiculo();

        Reserva::query()->create([
            'passageiro_id' => $this->passageiro()->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $veiculo->assentos()->first()->id,
            'ponto_partida_id' => $veiculo->ponto_partida_id,
            'destino_id' => $veiculo->destino_id,
            'preco_final' => 150,
            'estado' => 'confirmado',
        ]);

        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/veiculos', ['acao' => 'eliminar', 'id' => $veiculo->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id]);
    }

    public function test_veiculo_inexistente_devolve_404(): void
    {
        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/veiculos', ['acao' => 'eliminar', 'id' => 999999])
            ->assertStatus(404);
    }

    private function veiculo(): Veiculo
    {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $partida = PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $destino = PontoPartida::query()->create([
            'nome' => 'Assomada', 'cidade' => 'Santa Catarina',
            'lat' => 15.0983, 'lng' => -23.6703, 'zona' => 'intermunicipal',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        return $veiculo;
    }

    private function passageiro(): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => 'Maria Semedo',
            'telefone' => '+2389912345', 'nif' => '123456789',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
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
