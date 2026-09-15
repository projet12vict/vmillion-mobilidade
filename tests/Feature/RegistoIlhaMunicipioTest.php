<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ilha e município pedidos no registo (config/municipios.php).
 */
class RegistoIlhaMunicipioTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_registo_guarda_a_ilha_e_o_municipio(): void
    {
        $this->registar(['ilha' => 'Fogo', 'municipio' => 'São Filipe'])->assertOk();

        $this->assertDatabaseHas('utilizadores', [
            'nif' => '123456789',
            'ilha' => 'Fogo',
            'municipio' => 'São Filipe',
        ]);
    }

    public function test_a_ilha_e_obrigatoria(): void
    {
        $this->registar(['ilha' => '', 'municipio' => ''])
            ->assertStatus(422)
            ->assertJson(['erro' => 'Escolha a ilha.']);

        $this->assertDatabaseCount('utilizadores', 0);
    }

    public function test_o_municipio_e_obrigatorio(): void
    {
        $this->registar(['municipio' => ''])
            ->assertStatus(422)
            ->assertJson(['erro' => 'Escolha o município.']);

        $this->assertDatabaseCount('utilizadores', 0);
    }

    public function test_uma_ilha_que_nao_existe_e_recusada(): void
    {
        $this->registar(['ilha' => 'Madeira', 'municipio' => 'Funchal'])
            ->assertStatus(422)
            ->assertJson(['erro' => 'Ilha inválida.']);
    }

    public function test_um_municipio_de_outra_ilha_e_recusado(): void
    {
        $this->registar(['ilha' => 'Santiago', 'municipio' => 'São Vicente'])
            ->assertStatus(422)
            ->assertJson(['erro' => 'O município escolhido não pertence a essa ilha.']);

        $this->assertDatabaseCount('utilizadores', 0);
    }

    /**
     * Os nomes do registo têm de ser os mesmos do traçado do mapa, para um
     * município guardado numa conta poder ser comparado com o que o
     * MunicipioService devolve para uma coordenada.
     */
    public function test_a_lista_e_a_mesma_do_mapa(): void
    {
        $geojson = json_decode((string) file_get_contents(public_path('assets/geo/cv-municipios.geojson')), true);

        $doMapa = [];
        foreach ($geojson['features'] as $feature) {
            $doMapa[$feature['properties']['ilha']][] = $feature['properties']['nome'];
        }

        $doRegisto = config('municipios');

        foreach ([&$doMapa, &$doRegisto] as &$lista) {
            ksort($lista);
            foreach ($lista as &$municipios) {
                sort($municipios);
            }
        }
        unset($lista, $municipios);

        $this->assertSame($doMapa, $doRegisto);
    }

    /**
     * @param  array<string, string>  $campos
     */
    private function registar(array $campos = []): TestResponse
    {
        return $this->postJson('/api/auth/registar', array_merge([
            'nome' => 'Maria Semedo',
            'telefone' => '+238 9912345',
            'nif' => '123456789',
            'ilha' => 'Santiago',
            'municipio' => 'Praia',
            'senha' => 'senhaforte1',
            'confirmar_senha' => 'senhaforte1',
            'tipo' => 'passageiro',
        ], $campos));
    }
}
