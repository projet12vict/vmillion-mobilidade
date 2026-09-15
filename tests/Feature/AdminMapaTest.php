<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editor de mapa do admin: a vista geral mostra condutores e passageiros de
 * todo o sistema, sem os recortes do mapa do condutor.
 */
class AdminMapaTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolve_veiculos_com_posicao_e_passageiros_em_curso(): void
    {
        [$partida, $destino] = $this->pontos();

        $parado = $this->veiculo($partida, $destino, ['matricula' => 'ST-01-AA', 'estado' => 'na_fila', 'posicao_fila' => 1]);
        $emRota = $this->veiculo($partida, $destino, [
            'matricula' => 'ST-02-BB', 'estado' => 'em_movimento',
            'lat' => 15.0, 'lng' => -23.6, 'ultima_posicao_em' => now()->subHours(2),
        ]);
        // Sem ponto nem GPS: não há onde o desenhar.
        $this->veiculo(null, null, ['matricula' => 'ST-03-CC', 'estado' => 'em_movimento']);
        // Por aprovar: fica fora do mapa.
        $this->veiculo($partida, $destino, ['matricula' => 'ST-04-DD', 'aprovado' => false]);

        $comGps = $this->reserva($partida, $destino, [
            'estado' => 'pendente', 'passageiro_lat' => 14.92, 'passageiro_lng' => -23.51,
        ]);
        $semGps = $this->reserva($partida, $destino, ['estado' => 'confirmado', 'veiculo_id' => $parado->id]);
        $this->reserva($partida, $destino, ['estado' => 'concluido']);

        $json = $this->actingAs($this->admin(), 'admin')
            ->getJson('/api/admin/mapa')
            ->assertOk()
            ->json();

        $veiculos = collect($json['veiculos'])->keyBy('id');
        $this->assertEqualsCanonicalizing([$parado->id, $emRota->id], $veiculos->keys()->all());
        $this->assertSame(14.9175, $veiculos[$parado->id]['lat']);
        $this->assertTrue($veiculos[$emRota->id]['posicao_antiga']);
        $this->assertArrayHasKey('condutor_telefone', $veiculos[$parado->id]);
        $this->assertSame(14, $veiculos[$parado->id]['ocupacao']['lugares_total']);

        $passageiros = collect($json['passageiros'])->keyBy('id');
        $this->assertEqualsCanonicalizing([$comGps->id, $semGps->id], $passageiros->keys()->all());
        $this->assertTrue($passageiros[$comGps->id]['com_gps']);
        $this->assertSame(14.9175, $passageiros[$semGps->id]['lat']);
        $this->assertSame('ST-01-AA', $passageiros[$semGps->id]['veiculo_matricula']);
    }

    public function test_utilizador_nao_acede_ao_mapa_do_admin(): void
    {
        $passageiro = $this->utilizador('passageiro');

        $this->actingAs($passageiro, 'utilizador')
            ->getJson('/api/admin/mapa')
            ->assertUnauthorized();
    }

    /** @return array{0: PontoPartida, 1: PontoPartida} */
    private function pontos(): array
    {
        return [
            PontoPartida::query()->create([
                'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
                'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
                'status' => 'aprovado', 'ativo' => true,
            ]),
            PontoPartida::query()->create([
                'nome' => 'Assomada', 'cidade' => 'Santa Catarina',
                'lat' => 15.0983, 'lng' => -23.6703, 'zona' => 'intermunicipal',
                'status' => 'aprovado', 'ativo' => true,
            ]),
        ];
    }

    private function veiculo(?PontoPartida $partida, ?PontoPartida $destino, array $extra): Veiculo
    {
        return Veiculo::query()->create($extra + [
            'condutor_id' => $this->utilizador('condutor')->id,
            'tipo' => 'hiace', 'tipo_servico' => 'ambos', 'cor' => 'Branco', 'modelo' => 'Toyota',
            'lugares_total' => 14, 'lugares_livres' => 14, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $partida?->id, 'destino_id' => $destino?->id,
        ]);
    }

    private function reserva(PontoPartida $partida, PontoPartida $destino, array $extra): Reserva
    {
        return Reserva::query()->create($extra + [
            'passageiro_id' => $this->utilizador('passageiro')->id,
            'ponto_partida_id' => $partida->id,
            'destino_id' => $destino->id,
            'preco_final' => 250,
        ]);
    }

    private function utilizador(string $tipo): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo,
            'nome' => ucfirst($tipo).' '.random_int(1, 9999),
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }

    private function admin(): Administrador
    {
        return Administrador::query()->create([
            'nome' => 'Admin',
            'email' => 'admin'.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'admin',
            'senha_temporaria' => false,
            'ativo' => true,
        ]);
    }
}
