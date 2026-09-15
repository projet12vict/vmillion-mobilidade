<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PontoPartida;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O painel do condutor desenha os pontos de partida no mapa a partir de
 * GET /api/passageiro/pontos (painel.blade.php: carregarPontosDropdowns).
 */
class PontosNoMapaCondutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_condutor_consegue_obter_os_pontos_que_o_mapa_desenha(): void
    {
        $this->ponto();

        $this->actingAs($this->utilizador('condutor'), 'utilizador')
            ->getJson('/api/passageiro/pontos')
            ->assertOk()
            ->assertJsonPath('pontos.0.nome', 'Mercado de Sucupira');
    }

    public function test_passageiro_continua_a_obter_os_pontos(): void
    {
        $this->ponto();

        $this->actingAs($this->utilizador('passageiro'), 'utilizador')
            ->getJson('/api/passageiro/pontos')
            ->assertOk()
            ->assertJsonCount(1, 'pontos');
    }

    private function ponto(): PontoPartida
    {
        return PontoPartida::query()->create([
            'nome' => 'Mercado de Sucupira', 'cidade' => 'Praia',
            'lat' => 14.9175, 'lng' => -23.5094, 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);
    }

    private function utilizador(string $tipo): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => $tipo,
            'nome' => 'Teste '.$tipo,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);
    }
}
