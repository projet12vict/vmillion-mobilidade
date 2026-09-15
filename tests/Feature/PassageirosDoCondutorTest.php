<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssentoVeiculo;
use App\Models\Notificacao;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O condutor acrescenta e tira passageiros à mão: marca no mapa de "Lugares"
 * quem entrou sem app, e tira da viagem quem não apareceu ou quem sai a meio
 * — sem nunca deixar a contagem de lugares desalinhada.
 */
class PassageirosDoCondutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocupar_um_lugar_para_um_passageiro_sem_app(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $assento = $this->assento($veiculo, 5);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'ocupar', 'assento_id' => $assento->id, 'nome' => 'Sr. Tavares',
            ])
            ->assertOk()
            ->assertJson(['lugares_total' => 14, 'lugares_livres' => 13]);

        $assento->refresh();
        $this->assertTrue($assento->ocupado);
        $this->assertTrue($assento->ocupado_sem_app);
        $this->assertSame('Sr. Tavares', $assento->nome_sem_app);

        // Conta como gente dentro do carro, com nome e lugar, no marcador.
        $ocupacao = app(VeiculoService::class)->ocupacao($veiculo->fresh());
        $this->assertSame(1, $ocupacao['a_bordo']);
        $this->assertSame(0, $ocupacao['reservados']);
        $this->assertSame('Sr. Tavares', $ocupacao['passageiros'][0]['nome']);
        $this->assertSame(5, $ocupacao['passageiros'][0]['assento']);
        $this->assertNull($ocupacao['passageiros'][0]['passageiro_id']);

        $this->assertDatabaseHas('logs_auditoria', ['acao' => 'condutor_ocupou_lugar_sem_app']);
    }

    public function test_libertar_devolve_o_lugar_do_passageiro_sem_app(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $assento = $this->assento($veiculo, 5);

        foreach (['ocupar', 'libertar'] as $acao) {
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/condutor/veiculo_lugares', [
                    'veiculo_id' => $veiculo->id, 'acao' => $acao, 'assento_id' => $assento->id,
                ])
                ->assertOk();
        }

        $assento->refresh();
        $this->assertFalse($assento->ocupado);
        $this->assertFalse($assento->ocupado_sem_app);
        $this->assertNull($assento->nome_sem_app);
        $this->assertSame(14, $veiculo->fresh()->lugares_livres);
    }

    /**
     * Botões "Adicionar passageiro" e "Remover passageiro": sem lugar
     * escolhido, entra no primeiro livre e sai o último sem app — um lugar a
     * mais ocupado, um lugar a mais livre, sem mexer na capacidade.
     */
    public function test_adicionar_e_remover_passageiro_sem_escolher_lugar(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $this->reserva($veiculo, 1, 'confirmado');

        foreach ([['ocupar', 12, 2], ['ocupar', 11, 3]] as [$acao, $livres, $numero]) {
            $this->lugares($condutor, $veiculo, $acao)
                ->assertOk()
                ->assertJson(['lugares_total' => 14, 'lugares_livres' => $livres]);

            // O lugar 1 é da reserva: o passageiro sem app vai para o seguinte.
            $this->assertTrue($this->assento($veiculo, $numero)->ocupado_sem_app);
        }

        $this->lugares($condutor, $veiculo, 'libertar')
            ->assertOk()
            ->assertJson(['lugares_total' => 14, 'lugares_livres' => 12]);

        $this->assertFalse($this->assento($veiculo, 3)->ocupado_sem_app, 'Sai o último sentado.');
        $this->assertTrue($this->assento($veiculo, 2)->ocupado_sem_app);
        $this->assertTrue($this->assento($veiculo, 1)->ocupado, 'A reserva da app fica onde estava.');
    }

    public function test_remover_passageiro_sem_ninguem_sem_app_nao_toca_nas_reservas(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $this->reserva($veiculo, 1, 'a_bordo');
        $livres = $veiculo->fresh()->lugares_livres;

        $this->lugares($condutor, $veiculo, 'libertar')
            ->assertNotFound()
            ->assertJson(['erro' => 'Não há passageiros sem app para remover. Quem reservou pela app sai em "Passageiros".']);

        $this->assertSame($livres, $veiculo->fresh()->lugares_livres);
    }

    public function test_adicionar_passageiro_com_o_carro_cheio_e_recusado(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        for ($i = 0; $i < 14; $i++) {
            $this->lugares($condutor, $veiculo, 'ocupar')->assertOk();
        }

        $this->lugares($condutor, $veiculo, 'ocupar')->assertStatus(409);
        $this->assertSame(0, $veiculo->fresh()->lugares_livres);
    }

    private function lugares(Utilizador $condutor, Veiculo $veiculo, string $acao)
    {
        return $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => $acao]);
    }

    public function test_nao_se_ocupa_um_lugar_reservado_nem_um_retirado(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $this->reserva($veiculo, 3, 'confirmado');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'ocupar', 'assento_id' => $this->assento($veiculo, 3)->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'O lugar 3 já está ocupado.']);

        $retirado = $this->assento($veiculo, 14);
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'remover', 'assento_id' => $retirado->id,
            ])
            ->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'ocupar', 'assento_id' => $retirado->id,
            ])
            ->assertNotFound();

        $this->assertSame(12, $veiculo->fresh()->lugares_livres);
    }

    public function test_quem_vai_sem_app_continua_a_contar_depois_de_sair_da_fila(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        foreach ([4, 5] as $numero) {
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/condutor/veiculo_lugares', [
                    'veiculo_id' => $veiculo->id, 'acao' => 'ocupar', 'assento_id' => $this->assento($veiculo, $numero)->id,
                ])
                ->assertOk();
        }

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/fila_sair', ['veiculo_id' => $veiculo->id, 'motivo' => 'Carro cheio'])
            ->assertOk();

        $this->assertSame(12, $veiculo->fresh()->lugares_livres);
        $this->assertSame(2, $veiculo->assentos()->where('ocupado_sem_app', true)->count());
    }

    public function test_nao_apareceu_liberta_o_lugar_e_avisa_o_passageiro(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 3, 'a_caminho');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'nao_apareceu'])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'estado' => 'recusado', 'lugares_livres' => 14]);

        $this->assertSame('recusado', $reserva->fresh()->estado);
        $this->assertFalse($this->assento($veiculo, 3)->ocupado);
        $this->assertDatabaseHas('logs_auditoria', ['acao' => 'condutor_marcou_nao_apareceu', 'entidade_id' => $reserva->id]);
        $this->assertTrue(Notificacao::query()
            ->where('destinatario_id', $reserva->passageiro_id)
            ->where('titulo', 'Reserva libertada')
            ->exists());
    }

    public function test_nao_apareceu_nao_serve_para_quem_ja_esta_a_bordo(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 3, 'a_bordo');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'nao_apareceu'])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este passageiro já está a bordo — use "Remover".']);

        $this->assertSame('a_bordo', $reserva->fresh()->estado);
    }

    public function test_remover_tira_da_viagem_quem_esta_a_bordo_sem_a_dar_como_concluida(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 3, 'a_bordo');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'remover'])
            ->assertOk()
            ->assertJson(['estado' => 'recusado', 'lugares_livres' => 14])
            ->assertJsonPath('ocupacao.a_bordo', 0);

        $this->assertSame('recusado', $reserva->fresh()->estado);
        $this->assertFalse($this->assento($veiculo, 3)->ocupado);
        $this->assertDatabaseHas('logs_auditoria', ['acao' => 'condutor_removeu_passageiro_a_bordo', 'entidade_id' => $reserva->id]);
        $this->assertTrue(Notificacao::query()
            ->where('destinatario_id', $reserva->passageiro_id)
            ->where('titulo', 'A sua viagem terminou')
            ->exists());
    }

    public function test_remover_so_serve_para_quem_esta_a_bordo(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 3, 'confirmado');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'remover'])
            ->assertStatus(409);

        $this->assertSame('confirmado', $reserva->fresh()->estado);
        $this->assertSame(13, $veiculo->fresh()->lugares_livres);
    }

    public function test_um_condutor_nao_tira_passageiros_do_veiculo_de_outro(): void
    {
        [, $veiculo] = $this->cenario();
        $reserva = $this->reserva($veiculo, 3, 'a_bordo');
        $outro = $this->utilizador('condutor', '+2389911111');

        $this->actingAs($outro, 'utilizador')
            ->postJson('/api/condutor/reserva_estado', ['reserva_id' => $reserva->id, 'acao' => 'remover'])
            ->assertNotFound();

        $this->actingAs($outro, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'ocupar', 'assento_id' => $this->assento($veiculo, 5)->id,
            ])
            ->assertNotFound();

        $this->assertSame('a_bordo', $reserva->fresh()->estado);
    }

    /** @return array{0: Utilizador, 1: Veiculo} */
    private function cenario(): array
    {
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

        $condutor = $this->utilizador('condutor', '+2389987654');

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
            'lat' => $partida->lat, 'lng' => $partida->lng,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        return [$condutor, $veiculo];
    }

    /** Reserva de um passageiro com conta, já com o lugar preso — como a deixa o fluxo de reserva. */
    private function reserva(Veiculo $veiculo, int $numero, string $estado): Reserva
    {
        $assento = $this->assento($veiculo, $numero);

        $reserva = Reserva::query()->create([
            'passageiro_id' => $this->utilizador('passageiro', '+23899'.random_int(10000, 99999))->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $veiculo->ponto_partida_id,
            'destino_id' => $veiculo->destino_id,
            'preco_final' => 250,
            'estado' => $estado,
        ]);

        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        return $reserva;
    }

    private function assento(Veiculo $veiculo, int $numero): AssentoVeiculo
    {
        return $veiculo->assentos()->where('numero', $numero)->firstOrFail();
    }

    private function utilizador(string $tipo, string $telefone): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo, 'nome' => ucfirst($tipo).' '.$telefone,
            'telefone' => $telefone, 'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }
}
