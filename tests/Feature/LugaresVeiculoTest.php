<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O condutor tira e repõe lugares ao veículo (botão "Lugares" do painel):
 * um lugar tirado fica desativado — nunca apagado, por causa do histórico —
 * e a capacidade anda entre 1 e o teto do tipo sem nunca deixar sem lugar
 * quem já reservou.
 */
class LugaresVeiculoTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirar_um_lugar_desativa_o_livre_de_numero_mais_alto(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'lugares_total' => 13, 'lugares_livres' => 13, 'lugares_max' => 16]);

        $assento = $veiculo->assentos()->where('numero', 14)->first();

        $this->assertNotNull($assento, 'O lugar retirado fica desativado, não apagado.');
        $this->assertFalse($assento->ativo);
        $this->assertSame(13, $veiculo->assentos()->where('ativo', true)->count());
        $this->assertSame([13, 13], [$veiculo->fresh()->lugares_total, $veiculo->fresh()->lugares_livres]);
        $this->assertDatabaseHas('logs_auditoria', ['acao' => 'condutor_retirou_lugar']);
    }

    public function test_retirar_um_lugar_nao_apaga_as_viagens_feitas_nele(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->where('numero', 14)->first();

        $viagem = Reserva::query()->create([
            'passageiro_id' => $this->passageiro()->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $destino->id,
            'preco_final' => 250,
            'estado' => 'concluido',
        ]);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'remover', 'assento_id' => $assento->id,
            ])
            ->assertOk();

        $this->assertSame($assento->id, (int) $viagem->fresh()?->assento_id);
    }

    public function test_um_lugar_reservado_nao_pode_ser_retirado(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->where('numero', 3)->first();

        $reserva = Reserva::query()->create([
            'passageiro_id' => $this->passageiro()->id,
            'veiculo_id' => $veiculo->id,
            'assento_id' => $assento->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $destino->id,
            'preco_final' => 250,
            'estado' => 'pendente',
        ]);
        $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
        $veiculo->decrement('lugares_livres');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'remover', 'assento_id' => $assento->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'O lugar 3 está reservado ou ocupado e não pode ser retirado.']);

        $this->assertTrue($assento->fresh()->ativo);
        $this->assertSame([14, 13], [$veiculo->fresh()->lugares_total, $veiculo->fresh()->lugares_livres]);
    }

    public function test_sem_lugares_livres_a_capacidade_nao_baixa(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        // Todos os lugares presos por reservas ou gente a bordo: tirar um
        // deixava alguém sem lugar, mesmo que um assento pareça livre.
        $veiculo->forceFill(['lugares_livres' => 0])->save();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertStatus(409);

        $this->assertSame(14, $veiculo->assentos()->where('ativo', true)->count());
        $this->assertSame(14, $veiculo->fresh()->lugares_total);
    }

    public function test_o_veiculo_fica_sempre_com_pelo_menos_um_lugar(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        foreach (range(1, 13) as $_) {
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
                ->assertOk();
        }

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertStatus(422);

        $this->assertSame([1, 1], [$veiculo->fresh()->lugares_total, $veiculo->fresh()->lugares_livres]);
        $this->assertSame([1], $veiculo->assentos()->where('ativo', true)->pluck('numero')->all());
    }

    public function test_adicionar_repoe_o_lugar_retirado_no_mesmo_sitio(): void
    {
        [$condutor, $veiculo] = $this->cenario();
        $assento = $veiculo->assentos()->where('numero', 5)->first();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', [
                'veiculo_id' => $veiculo->id, 'acao' => 'remover', 'assento_id' => $assento->id,
            ])
            ->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'adicionar'])
            ->assertOk()
            ->assertJson(['lugares_total' => 14, 'lugares_livres' => 14]);

        $reposto = $assento->fresh();

        $this->assertTrue($reposto->ativo);
        $this->assertSame([5, 1, 3], [$reposto->numero, $reposto->fila, $reposto->coluna]);
        $this->assertSame(14, $veiculo->assentos()->count(), 'Repor não cria um assento novo.');
    }

    public function test_acima_da_capacidade_de_origem_o_lugar_novo_vai_para_uma_fila_nova(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'adicionar'])
            ->assertOk()
            ->assertJson(['lugares_total' => 15, 'lugares_livres' => 15]);

        $novo = $veiculo->assentos()->where('numero', 15)->first();

        $this->assertNotNull($novo);
        $this->assertTrue($novo->ativo);
        $this->assertSame([5, 1], [$novo->fila, $novo->coluna]);
    }

    public function test_nao_passa_do_teto_do_tipo(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        config(['vmillion.veiculos.lugares_max_por_tipo.hiace' => 14]);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'adicionar'])
            ->assertStatus(422)
            ->assertJson(['erro' => 'Este tipo de veículo leva no máximo 14 lugares.']);

        $this->assertSame(14, $veiculo->assentos()->count());
        $this->assertSame(14, $veiculo->fresh()->lugares_total);
    }

    public function test_o_lugar_retirado_sai_do_mapa_do_passageiro_e_nao_se_reserva(): void
    {
        [$condutor, $veiculo, $partida, $destino] = $this->cenario();
        $assento = $veiculo->assentos()->where('numero', 14)->first();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertOk();

        $passageiro = $this->passageiro();

        $numeros = $this->actingAs($passageiro, 'utilizador')
            ->getJson('/api/passageiro/assentos?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->json('assentos.*.numero');

        $this->assertSame(range(1, 13), $numeros);

        // Um mapa de assentos aberto antes de o condutor retirar o lugar
        // ainda o mostra: o servidor é que tem de o recusar.
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/reservar', [
                'veiculo_id' => $veiculo->id,
                'assento_id' => $assento->id,
                'ponto_partida_id' => $partida->id,
                'destino_id' => $destino->id,
            ])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Este lugar já não está disponível neste veículo.']);

        $this->assertFalse($assento->fresh()->ocupado);
    }

    public function test_o_mapa_do_condutor_mostra_os_lugares_retirados(): void
    {
        [$condutor, $veiculo] = $this->cenario();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertOk();

        $resposta = $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/assentos?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->assertJson(['lugares_total' => 13, 'lugares_livres' => 13, 'lugares_max' => 16]);

        $this->assertCount(14, $resposta->json('assentos'));
        $this->assertFalse($resposta->json('assentos.13.ativo'));
    }

    public function test_um_condutor_nao_mexe_nos_lugares_do_veiculo_de_outro(): void
    {
        [, $veiculo] = $this->cenario();
        $outro = $this->condutor('+2389911111');

        $this->actingAs($outro, 'utilizador')
            ->getJson('/api/condutor/assentos?veiculo_id='.$veiculo->id)
            ->assertNotFound();

        $this->actingAs($outro, 'utilizador')
            ->postJson('/api/condutor/veiculo_lugares', ['veiculo_id' => $veiculo->id, 'acao' => 'remover'])
            ->assertNotFound();

        $this->assertSame(14, $veiculo->assentos()->where('ativo', true)->count());
    }

    public function test_registar_veiculo_com_o_numero_de_lugares_indicado(): void
    {
        $condutor = $this->condutor('+2389911111');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculos', $this->dadosRegisto(['tipo' => 'taxi', 'lugares' => 6]))
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $veiculo = Veiculo::query()->where('condutor_id', $condutor->id)->firstOrFail();

        $this->assertSame([6, 6], [$veiculo->lugares_total, $veiculo->lugares_livres]);
        $this->assertSame(range(1, 6), $veiculo->assentos()->orderBy('numero')->pluck('numero')->all());
        $this->assertSame(6, $veiculo->assentos()->where('ativo', true)->where('ocupado', false)->count());
    }

    /** Sem o campo (um cliente antigo), vale a capacidade do tipo, como antes. */
    public function test_registar_veiculo_sem_lugares_usa_a_capacidade_do_tipo(): void
    {
        $condutor = $this->condutor('+2389922222');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculos', $this->dadosRegisto(['tipo' => 'taxi']))
            ->assertOk();

        $veiculo = Veiculo::query()->where('condutor_id', $condutor->id)->firstOrFail();
        $this->assertSame(4, $veiculo->lugares_total);
        $this->assertSame(4, $veiculo->assentos()->count());
    }

    public function test_registar_veiculo_recusa_lugares_fora_dos_limites_do_tipo(): void
    {
        $condutor = $this->condutor('+2389933333');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculos', $this->dadosRegisto(['tipo' => 'taxi', 'lugares' => 8]))
            ->assertStatus(422)
            ->assertJson(['erro' => 'Este tipo de veículo leva no máximo 7 lugares.']);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/condutor/veiculos', $this->dadosRegisto(['lugares' => 0]))
            ->assertStatus(422);

        $this->assertSame(0, Veiculo::query()->where('condutor_id', $condutor->id)->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function dadosRegisto(array $extra = []): array
    {
        return $extra + [
            'matricula' => 'ST-'.random_int(10, 99).'-ZZ', 'tipo' => 'hiace',
            'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
        ];
    }

    /** @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida, 3: PontoPartida} */
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

        $condutor = $this->condutor('+2389987654');

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => 'ST-01-AA',
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        return [$condutor, $veiculo, $partida, $destino];
    }

    private function condutor(string $telefone): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'Condutor '.$telefone,
            'telefone' => $telefone, 'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
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
}
