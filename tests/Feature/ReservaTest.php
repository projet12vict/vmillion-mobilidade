<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssentoVeiculo;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fluxo de reserva do passageiro: o assento só pode ser vendido uma vez e o
 * lugar volta ao veículo quando a reserva é cancelada.
 */
class ReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservar_ocupa_o_assento_e_desconta_um_lugar(): void
    {
        [$passageiro, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->first();

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/passageiro/reservar', [
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $destino->id,
        ])->assertOk()->assertJson(['sucesso' => true]);

        $this->assertTrue($assento->fresh()->ocupado);
        $this->assertSame($veiculo->lugares_livres - 1, $veiculo->fresh()->lugares_livres);
    }

    public function test_o_mesmo_assento_nao_pode_ser_reservado_duas_vezes(): void
    {
        [$passageiro, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->first();

        $dados = [
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $destino->id,
        ];

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/passageiro/reservar', $dados)->assertOk();

        $outro = $this->passageiro();
        $this->actingAs($outro, 'utilizador')->postJson('/api/passageiro/reservar', $dados)
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este lugar já foi reservado por outro passageiro.']);
    }

    public function test_cancelar_devolve_o_lugar_ao_veiculo(): void
    {
        [$passageiro, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->first();
        $lugaresIniciais = $veiculo->lugares_livres;

        $reservaId = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/reservar', [
                'veiculo_id' => $veiculo->id,
                'assento_id' => $assento->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])->json('reserva_id');

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/cancelar_reserva', ['reserva_id' => $reservaId])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'lugares_livres' => $lugaresIniciais]);

        $this->assertFalse($assento->fresh()->ocupado);
        $this->assertSame('recusado', Reserva::query()->find($reservaId)->estado);
    }

    public function test_quem_ja_embarcou_nao_pode_cancelar(): void
    {
        [$passageiro, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->first();

        $reservaId = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/reservar', [
                'veiculo_id' => $veiculo->id,
                'assento_id' => $assento->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])->json('reserva_id');

        Reserva::query()->whereKey($reservaId)->update(['estado' => 'a_bordo']);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/cancelar_reserva', ['reserva_id' => $reservaId])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Já embarcou nesta viagem — não é possível sair agora.']);
    }

    /**
     * @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida, 3: PontoPartida}
     */
    private function cenario(): array
    {
        PrecoKm::query()->create(['zona' => 'urbana', 'preco_por_km_cve' => 5, 'atualizado_por' => $this->adminId()]);
        PrecoKm::query()->create(['zona' => 'intermunicipal', 'preco_por_km_cve' => 10, 'atualizado_por' => $this->adminId()]);

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

        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'João Tavares',
            'telefone' => '+2389987654', 'nif' => '987654321',
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        return [$this->passageiro(), $veiculo, $partida, $destino];
    }

    private function passageiro(): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro',
            'nome' => 'Passageiro '.random_int(1, 9999),
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }

    private function adminId(): int
    {
        return \App\Models\Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            ['nome' => 'Super Admin', 'senha_hash' => 'senhaforte1', 'nivel' => 'super', 'ativo' => true]
        )->id;
    }
}
