<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Outros condutores na rota" (secção 9.6): quem está parado no ponto e
 * também quem já vai a caminho no mesmo par ponto/destino.
 */
class OutrosCondutoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclui_condutor_em_movimento_na_mesma_rota(): void
    {
        [$eu, $meuVeiculo, $partida, $destino] = $this->cenario();

        $colega = $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-99-ZZ',
            'estado' => 'em_movimento',
            'lat' => 15.0, 'lng' => -23.6,
            'ultima_posicao_em' => now()->subMinute(),
        ]);

        $resposta = $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk();

        $resposta->assertJsonPath('condutores.0.id', $colega->id);
        $resposta->assertJsonPath('condutores.0.em_rota', true);
    }

    public function test_condutor_parado_no_ponto_vem_antes_de_quem_ja_partiu(): void
    {
        [$eu, $meuVeiculo, $partida, $destino] = $this->cenario();

        $emRota = $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-11-AA',
            'estado' => 'em_movimento',
            'lat' => 15.0, 'lng' => -23.6,
            'ultima_posicao_em' => now(),
        ]);

        $naFila = $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-22-BB',
            'estado' => 'na_fila',
            'posicao_fila' => 1,
        ]);

        $condutores = $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk()
            ->json('condutores');

        $this->assertSame([$naFila->id, $emRota->id], array_column($condutores, 'id'));
        $this->assertFalse($condutores[0]['em_rota']);
    }

    public function test_ignora_quem_vai_a_caminho_de_outro_destino(): void
    {
        [$eu, $meuVeiculo, $partida] = $this->cenario();

        $outroDestino = PontoPartida::query()->create([
            'nome' => 'Tarrafal', 'cidade' => 'Tarrafal',
            'lat' => 15.2761, 'lng' => -23.7508, 'zona' => 'intermunicipal',
            'status' => 'aprovado', 'ativo' => true,
        ]);

        $this->veiculoDeOutroCondutor($partida, $outroDestino, [
            'matricula' => 'ST-33-CC',
            'estado' => 'em_movimento',
            'lat' => 15.1, 'lng' => -23.7,
            'ultima_posicao_em' => now(),
        ]);

        $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk()
            ->assertJsonCount(0, 'condutores');
    }

    public function test_ignora_quem_esta_em_movimento_com_gps_parado_ha_muito(): void
    {
        [$eu, $meuVeiculo, $partida, $destino] = $this->cenario();

        // Condutor que fechou a aplicação a meio da viagem: o estado ficou
        // 'em_movimento' para sempre, mas a posição já não diz onde ele está.
        $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-44-DD',
            'estado' => 'em_movimento',
            'lat' => 15.0, 'lng' => -23.6,
            'ultima_posicao_em' => now()->subHours(3),
        ]);

        $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk()
            ->assertJsonCount(0, 'condutores');
    }

    public function test_ignora_colega_de_outro_tipo_de_veiculo(): void
    {
        [$eu, $meuVeiculo, $partida, $destino] = $this->cenario(['tipo' => 'taxi', 'lugares_total' => 3, 'lugares_livres' => 3]);

        // Mesmo ponto, mesmo destino, tudo em dia — mas é um Hiace: um táxi
        // negoceia a vez com táxis, não com Hiaces nem com autocarros.
        $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-55-EE',
            'estado' => 'na_fila',
            'posicao_fila' => 1,
        ]);

        $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-66-FF',
            'tipo' => 'autocarro',
            'estado' => 'na_fila',
            'posicao_fila' => 2,
        ]);

        $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk()
            ->assertJsonCount(0, 'condutores');
    }

    public function test_inclui_colega_do_mesmo_tipo_de_veiculo(): void
    {
        [$eu, $meuVeiculo, $partida, $destino] = $this->cenario(['tipo' => 'taxi', 'lugares_total' => 3, 'lugares_livres' => 3]);

        $colega = $this->veiculoDeOutroCondutor($partida, $destino, [
            'matricula' => 'ST-77-GG',
            'tipo' => 'taxi',
            'lugares_total' => 3, 'lugares_livres' => 3,
            'estado' => 'na_fila',
            'posicao_fila' => 1,
        ]);

        $this->actingAs($eu, 'utilizador')
            ->getJson("/api/condutor/outros_condutores?ponto_id={$partida->id}&veiculo_id={$meuVeiculo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'condutores')
            ->assertJsonPath('condutores.0.id', $colega->id);
    }

    /**
     * @param  array<string, mixed>  $meuExtra
     * @return array{0: Utilizador, 1: Veiculo, 2: PontoPartida, 3: PontoPartida}
     */
    private function cenario(array $meuExtra = []): array
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

        $eu = $this->condutor();
        $meuVeiculo = $this->veiculo($eu, $partida, $destino, $meuExtra + ['matricula' => 'ST-01-AA']);

        return [$eu, $meuVeiculo, $partida, $destino];
    }

    private function veiculoDeOutroCondutor(PontoPartida $partida, PontoPartida $destino, array $extra): Veiculo
    {
        return $this->veiculo($this->condutor(), $partida, $destino, $extra);
    }

    private function veiculo(Utilizador $condutor, PontoPartida $partida, PontoPartida $destino, array $extra): Veiculo
    {
        $veiculo = Veiculo::query()->create($extra + [
            'condutor_id' => $condutor->id,
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida->id, 'destino_id' => $destino->id,
        ]);

        // Sem taxa de operação aprovada e válida o condutor não aparece a
        // ninguém (secção 11.6) — o gate é o mesmo do mapa.
        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 1000,
            'referencia' => 'REF'.random_int(100000, 999999),
            'data_pagamento' => now(),
            'data_validade' => now()->addMonth(),
            'status' => 'aprovado',
        ]);

        return $veiculo;
    }

    private function condutor(): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'condutor',
            'nome' => 'Condutor '.random_int(1, 9999),
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }
}
