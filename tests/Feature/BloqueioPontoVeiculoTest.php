<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PontoPartida;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Guardar o ponto de partida/destino tranca-o durante 24 horas: o botão
 * "Guardar" do painel do condutor desaparece nesse período e o servidor
 * recusa nova alteração. Antes do prazo, só o administrador liberta.
 */
class BloqueioPontoVeiculoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_o_ponto_tranca_o_veiculo_por_24_horas(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_ponto', [
                'veiculo_id' => $veiculo->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true])
            ->assertJsonPath('ponto_bloqueado_ate', fn ($v) => $v !== null);

        $veiculo->refresh();

        $this->assertTrue($veiculo->pontoBloqueado());
        $this->assertSame(
            now()->addHours(24)->format('Y-m-d H:i'),
            $veiculo->ponto_bloqueado_ate->format('Y-m-d H:i')
        );
    }

    public function test_condutor_nao_altera_o_ponto_antes_das_24_horas(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $veiculo->forceFill(['ponto_bloqueado_ate' => now()->addHours(23)])->save();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_ponto', [
                'veiculo_id' => $veiculo->id,
                'ponto_partida_id' => $destino->id,
                'destino_id' => $partida->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ponto_bloqueado_ate', fn ($v) => $v !== null);

        // O ponto anterior ficou como estava: a recusa não pode gravar nada.
        $this->assertSame($partida->id, (int) $veiculo->refresh()->ponto_partida_id);
    }

    public function test_o_bloqueio_cai_sozinho_passadas_as_24_horas(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $veiculo->forceFill(['ponto_bloqueado_ate' => now()->addHours(24)])->save();

        Carbon::setTestNow(now()->addHours(25));

        try {
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/condutor/veiculo_ponto', [
                    'veiculo_id' => $veiculo->id,
                    'ponto_partida_id' => $destino->id,
                    'destino_id' => $partida->id,
                ])
                ->assertOk();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame($destino->id, (int) $veiculo->refresh()->ponto_partida_id);
    }

    public function test_admin_liberta_o_ponto_antes_do_prazo(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();

        $veiculo->forceFill(['ponto_bloqueado_ate' => now()->addHours(20)])->save();

        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/veiculos', ['acao' => 'libertar_ponto', 'id' => $veiculo->id])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertNull($veiculo->refresh()->ponto_bloqueado_ate);
        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'libertou_ponto_veiculo',
            'entidade' => 'veiculos',
            'entidade_id' => $veiculo->id,
        ]);

        // E o condutor volta mesmo a poder guardar, sem esperar pelas 24 h.
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_ponto', [
                'veiculo_id' => $veiculo->id,
                'ponto_partida_id' => $destino->id,
                'destino_id' => $partida->id,
            ])
            ->assertOk();
    }

    public function test_libertar_ponto_de_veiculo_sem_bloqueio_e_recusado(): void
    {
        [, $veiculo] = $this->cenario();

        $this->actingAs($this->administrador(), 'admin')
            ->postJson('/api/admin/veiculos', ['acao' => 'libertar_ponto', 'id' => $veiculo->id])
            ->assertStatus(422);
    }

    /** @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida, 3: PontoPartida} */
    private function cenario(): array
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

        return [$condutor, $veiculo, $partida, $destino];
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
