<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chamada;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fluxo completo da chamada de voz: quem pode chamar, atender/recusar,
 * desligar, sinalizacao WebRTC isolada por participante e expiracao.
 */
class ChamadaFluxoTest extends TestCase
{
    use RefreshDatabase;

    public function test_passageiro_com_reserva_ativa_pode_chamar_o_condutor(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $resposta = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertSame('iniciada', Chamada::query()->findOrFail($resposta->json('chamada_id'))->estado);
    }

    public function test_condutor_tambem_pode_chamar_o_passageiro(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $passageiro->id])
            ->assertOk();
    }

    public function test_sem_viagem_em_comum_a_chamada_e_recusada(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();
        $estranho = $this->passageiro();

        $this->actingAs($estranho, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertStatus(403)
            ->assertJson(['erro' => 'Sem relação ativa com este utilizador.']);
    }

    public function test_segunda_chamada_para_quem_ja_esta_em_chamada_devolve_ocupado(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->assertOk();

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $passageiro->id])
            ->assertStatus(409)
            ->assertJson(['erro' => 'ocupado']);
    }

    public function test_atender_recusar_e_desligar(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        // Só o destinatário responde.
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'atender'])
            ->assertStatus(404);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'atender'])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'remetente_id' => $passageiro->id]);

        $this->assertSame('atendida', Chamada::query()->findOrFail($id)->estado);
        $this->assertNotNull(Chamada::query()->findOrFail($id)->atendida_em);

        // Já não está a tocar.
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'recusar'])
            ->assertStatus(409);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/desligar', ['chamada_id' => $id])
            ->assertOk()
            ->assertJson(['sucesso' => true, 'outro_id' => $condutor->id]);

        $this->assertSame('terminada', Chamada::query()->findOrFail($id)->estado);

        // Desligar de novo é idempotente.
        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/desligar', ['chamada_id' => $id])
            ->assertOk()->assertJson(['sucesso' => true]);
    }

    public function test_verificar_devolve_a_chamada_do_utilizador_com_o_nome_do_remetente(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/chamada/verificar')
            ->assertOk()
            ->assertJson(['chamada' => [
                'id' => $id,
                'estado' => 'iniciada',
                'destinatario_id' => $condutor->id,
                'remetente_nome' => $passageiro->nome,
            ]]);
    }

    public function test_sinalizacao_so_devolve_os_sinais_do_outro_lado_e_so_a_participantes(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();
        $estranho = $this->passageiro();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/chamada/sinalizar', [
            'chamada_id' => $id, 'tipo' => 'offer', 'payload' => '{"sdp":"v=0"}',
        ])->assertOk();

        // O próprio não recebe o que enviou.
        $this->actingAs($passageiro, 'utilizador')
            ->getJson("/api/chamada/sinais?chamada_id={$id}")
            ->assertOk()->assertJson(['sinais' => []]);

        $sinais = $this->actingAs($condutor, 'utilizador')
            ->getJson("/api/chamada/sinais?chamada_id={$id}")
            ->assertOk()->json('sinais');

        $this->assertCount(1, $sinais);
        $this->assertSame('offer', $sinais[0]['tipo']);

        // desde_id filtra o que já foi lido.
        $this->actingAs($condutor, 'utilizador')
            ->getJson("/api/chamada/sinais?chamada_id={$id}&desde_id={$sinais[0]['id']}")
            ->assertOk()->assertJson(['sinais' => []]);

        // Um terceiro não lê nem escreve nesta chamada.
        $this->actingAs($estranho, 'utilizador')
            ->getJson("/api/chamada/sinais?chamada_id={$id}")->assertStatus(404);
        $this->actingAs($estranho, 'utilizador')->postJson('/api/chamada/sinalizar', [
            'chamada_id' => $id, 'tipo' => 'ice', 'payload' => '{"candidate":"x"}',
        ])->assertStatus(404);

        // Payload tem de ser JSON válido.
        $this->actingAs($passageiro, 'utilizador')->postJson('/api/chamada/sinalizar', [
            'chamada_id' => $id, 'tipo' => 'ice', 'payload' => 'nao-e-json',
        ])->assertStatus(422);
    }

    public function test_sinalizar_depois_da_chamada_terminar_e_recusado(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/chamada/desligar', ['chamada_id' => $id]);

        $this->actingAs($passageiro, 'utilizador')->postJson('/api/chamada/sinalizar', [
            'chamada_id' => $id, 'tipo' => 'offer', 'payload' => '{"sdp":"v=0"}',
        ])->assertStatus(409);
    }

    public function test_chamada_sem_resposta_expira_e_liberta_a_linha(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        Chamada::query()->whereKey($id)->update([
            'iniciada_em' => now()->subSeconds((int) config('vmillion.chamadas.timeout_segundos') + 5),
        ]);

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        $this->assertSame('terminada', Chamada::query()->findOrFail($id)->estado);
    }

    public function test_chamada_atendida_e_abandonada_liberta_a_linha(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'atender'])->assertOk();

        // Os dois fecham o browser sem clicar em Desligar: o pulso pára.
        $this->travel((int) config('vmillion.chamadas.pulso_timeout_segundos') + 5)->seconds();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])
            ->assertOk();

        $this->assertSame('terminada', Chamada::query()->findOrFail($id)->estado);
    }

    public function test_o_pulso_mantem_a_chamada_viva(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'atender'])->assertOk();

        $limite = (int) config('vmillion.chamadas.pulso_timeout_segundos');

        // Uma chamada longa: pulsos regulares, sempre antes do limite.
        for ($i = 0; $i < 4; $i++) {
            $this->travel($limite - 10)->seconds();
            $this->actingAs($condutor, 'utilizador')
                ->postJson('/api/chamada/pulso', ['chamada_id' => $id])
                ->assertOk()->assertJson(['sucesso' => true]);
        }

        $this->actingAs($passageiro, 'utilizador')->getJson('/api/chamada/verificar')->assertOk();

        $this->assertSame('atendida', Chamada::query()->findOrFail($id)->estado);
    }

    public function test_pulso_so_de_participante_e_so_com_a_chamada_em_curso(): void
    {
        [$passageiro, $condutor] = $this->viagemComum();
        $estranho = $this->passageiro();

        $id = $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/iniciar', ['destinatario_id' => $condutor->id])->json('chamada_id');

        $this->actingAs($estranho, 'utilizador')
            ->postJson('/api/chamada/pulso', ['chamada_id' => $id])->assertStatus(404);

        // Ainda a tocar, não em curso.
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/pulso', ['chamada_id' => $id])
            ->assertStatus(409)
            ->assertJson(['erro' => 'Esta chamada não está em curso.']);

        $this->actingAs($condutor, 'utilizador')
            ->postJson('/api/chamada/responder', ['chamada_id' => $id, 'acao' => 'atender'])->assertOk();
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/pulso', ['chamada_id' => $id])->assertOk();

        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/desligar', ['chamada_id' => $id])->assertOk();
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/chamada/pulso', ['chamada_id' => $id])->assertStatus(409);
    }

    public function test_sem_sessao_os_endpoints_de_chamada_ficam_fechados(): void
    {
        $this->postJson('/api/chamada/iniciar', ['destinatario_id' => 1])->assertStatus(401);
        $this->postJson('/api/chamada/pulso', ['chamada_id' => 1])->assertStatus(401);
        $this->getJson('/api/chamada/verificar')->assertStatus(401);
    }

    /** @return array{0: Utilizador, 1: Utilizador} */
    private function viagemComum(): array
    {
        $partida = PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
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
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $partida->id,
        ]);

        $passageiro = $this->passageiro();

        Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => $veiculo->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $partida->id,
            'lugares' => 1,
            'preco_final' => 100,
            'estado' => 'confirmado',
        ]);

        return [$passageiro, $condutor];
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
