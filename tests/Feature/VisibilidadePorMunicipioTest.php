<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Condutor e passageiro só se veem um ao outro dentro do mesmo concelho.
 *
 * A fronteira é a delimitação administrativa que o mapa desenha
 * (App\Services\MunicipioService sobre public/assets/geo/cv-municipios.geojson),
 * e não um raio em quilómetros: na Praia dois pontos a 5 km podem estar em
 * concelhos diferentes, e no Porto Novo 20 km continuam a ser o mesmo.
 */
class VisibilidadePorMunicipioTest extends TestCase
{
    use RefreshDatabase;

    // Três coordenadas em três concelhos da mesma ilha (Santiago): o caso
    // difícil é este, não o de duas ilhas — por mar não há dúvidas.
    private const PRAIA = [14.9177, -23.5092];

    private const ASSOMADA = [15.0983, -23.6703];   // Santa Catarina

    private const TARRAFAL = [15.2785, -23.7519];   // Tarrafal

    public function test_condutor_so_ve_os_pedidos_urbanos_do_seu_municipio(): void
    {
        $ponto = $this->ponto('Mercado de Sucupira', 'Praia', self::PRAIA);

        $this->pedidoUrbano($this->passageiro('Ana da Praia'), $ponto, self::PRAIA);
        $this->pedidoUrbano($this->passageiro('Beto do Tarrafal'), $ponto, self::TARRAFAL);

        [$condutor] = $this->condutorUrbano('ST-11-AA');

        // Na Praia vê a Ana e não vê o Beto, que está a 60 km e noutro concelho.
        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_urbanos?lat='.self::PRAIA[0].'&lng='.self::PRAIA[1])
            ->assertOk()
            ->assertJson(['elegivel' => true, 'municipio' => 'Praia'])
            ->assertJsonCount(1, 'passageiros')
            ->assertJsonPath('passageiros.0.passageiro_nome', 'Ana da Praia');

        // O mesmo condutor no Tarrafal vê exatamente o contrário.
        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_urbanos?lat='.self::TARRAFAL[0].'&lng='.self::TARRAFAL[1])
            ->assertOk()
            ->assertJson(['municipio' => 'Tarrafal'])
            ->assertJsonCount(1, 'passageiros')
            ->assertJsonPath('passageiros.0.passageiro_nome', 'Beto do Tarrafal');

        // E num concelho sem procura nenhuma a lista fica vazia, sem cair no
        // recurso de mostrar o país todo.
        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_urbanos?lat='.self::ASSOMADA[0].'&lng='.self::ASSOMADA[1])
            ->assertOk()
            ->assertJson(['municipio' => 'Santa Catarina'])
            ->assertJsonCount(0, 'passageiros');
    }

    public function test_condutor_sem_posicao_conhecida_nao_recebe_procura_nenhuma(): void
    {
        $ponto = $this->ponto('Mercado de Sucupira', 'Praia', self::PRAIA);
        $this->pedidoUrbano($this->passageiro('Ana da Praia'), $ponto, self::PRAIA);

        // Veículo urbano sem ponto e sem posição gravada: não há de onde tirar
        // o concelho, e sem concelho não há lista — mostrar o país todo era
        // precisamente o que esta regra veio impedir.
        [$condutor] = $this->condutorUrbano('ST-12-BB');

        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_urbanos')
            ->assertOk()
            ->assertJson(['elegivel' => false])
            ->assertJsonCount(0, 'passageiros')
            ->assertJsonPath('motivo', fn (?string $motivo) => str_contains((string) $motivo, 'GPS'));
    }

    public function test_condutor_fora_da_posicao_do_gps_usa_a_ultima_posicao_do_veiculo(): void
    {
        $ponto = $this->ponto('Mercado de Sucupira', 'Praia', self::PRAIA);
        $this->pedidoUrbano($this->passageiro('Ana da Praia'), $ponto, self::PRAIA);

        [$condutor, $veiculo] = $this->condutorUrbano('ST-13-CC');
        $veiculo->forceFill(['lat' => self::PRAIA[0], 'lng' => self::PRAIA[1]])->save();

        // O painel ainda não teve leitura de GPS para mandar: vale a última
        // posição gravada do veículo em vez de deixar o condutor sem procura.
        $this->actingAs($condutor, 'utilizador')
            ->getJson('/api/condutor/passageiros_urbanos?veiculo_id='.$veiculo->id)
            ->assertOk()
            ->assertJson(['elegivel' => true, 'municipio' => 'Praia'])
            ->assertJsonCount(1, 'passageiros');
    }

    public function test_passageiro_deixa_de_ver_o_taxi_que_saiu_do_seu_municipio(): void
    {
        $ponto = $this->ponto('Mercado de Sucupira', 'Praia', self::PRAIA);
        [, $veiculo] = $this->condutorUrbano('ST-14-DD', $ponto);
        $veiculo->forceFill([
            'estado' => 'em_movimento',
            'lat' => self::PRAIA[0], 'lng' => self::PRAIA[1],
        ])->save();

        $passageiro = $this->passageiro('Ana da Praia');
        $procura = '/api/passageiro/veiculos_disponiveis?viagem_tipo=urbana&ponto_id='.$ponto->id
            .'&lat='.self::PRAIA[0].'&lng='.self::PRAIA[1];

        $this->actingAs($passageiro, 'utilizador')
            ->getJson($procura)
            ->assertOk()
            ->assertJson(['municipio' => 'Praia'])
            ->assertJsonCount(1, 'veiculos')
            ->assertJsonPath('veiculos.0.matricula', 'ST-14-DD');

        // O táxi continua ancorado ao ponto da Praia (é o que a cidade do
        // ponto diz), mas já está a conduzir em Santa Catarina: para quem
        // ficou na Praia deixa de existir.
        $veiculo->forceFill(['lat' => self::ASSOMADA[0], 'lng' => self::ASSOMADA[1]])->save();

        $this->actingAs($passageiro, 'utilizador')
            ->getJson($procura)
            ->assertOk()
            ->assertJsonCount(0, 'veiculos');
    }

    public function test_o_taxi_parado_no_ponto_escolhido_continua_a_aparecer(): void
    {
        // A regra não pode roubar o caso normal: o veículo que espera no ponto
        // que o passageiro escolheu está, por definição, no concelho do ponto.
        $ponto = $this->ponto('Mercado de Sucupira', 'Praia', self::PRAIA);
        $destino = $this->ponto('Assomada', 'Santa Catarina', self::ASSOMADA);
        [, $veiculo] = $this->condutorUrbano('ST-15-EE', $ponto, 'intermunicipal');
        $veiculo->forceFill([
            'destino_id' => $destino->id, 'estado' => 'no_ponto',
            'lat' => self::PRAIA[0], 'lng' => self::PRAIA[1],
        ])->save();

        // O passageiro compra o lugar de véspera, a partir de casa noutro
        // concelho: a carreira sai do ponto que ele escolheu, e é o concelho
        // desse ponto que manda.
        $this->actingAs($this->passageiro('Ana da Praia'), 'utilizador')
            ->getJson('/api/passageiro/veiculos_disponiveis?viagem_tipo=intermunicipal&ponto_id='.$ponto->id
                .'&lat='.self::TARRAFAL[0].'&lng='.self::TARRAFAL[1])
            ->assertOk()
            ->assertJson(['municipio' => 'Praia'])
            ->assertJsonCount(1, 'veiculos')
            ->assertJsonPath('veiculos.0.matricula', 'ST-15-EE');
    }

    // ------------------------------------------------------------------
    // Cenário
    // ------------------------------------------------------------------

    /**
     * @param  array{0: float, 1: float}  $coordenadas
     */
    private function ponto(string $nome, string $cidade, array $coordenadas): PontoPartida
    {
        PrecoKm::query()->firstOrCreate(['zona' => 'urbana'], ['preco_por_km_cve' => 5, 'atualizado_por' => $this->adminId()]);
        PrecoKm::query()->firstOrCreate(['zona' => 'intermunicipal'], ['preco_por_km_cve' => 10, 'atualizado_por' => $this->adminId()]);

        return PontoPartida::query()->create([
            'nome' => $nome, 'cidade' => $cidade,
            'lat' => $coordenadas[0], 'lng' => $coordenadas[1], 'zona' => 'urbana',
            'status' => 'aprovado', 'ativo' => true,
        ]);
    }

    /**
     * Pedido urbano em aberto feito a partir de uma coordenada concreta — é
     * ela, e não o ponto de referência, que diz em que concelho o passageiro
     * está à espera.
     *
     * @param  array{0: float, 1: float}  $origem
     */
    private function pedidoUrbano(Utilizador $passageiro, PontoPartida $ponto, array $origem): void
    {
        $this->actingAs($passageiro, 'utilizador')
            ->postJson('/api/passageiro/pedir_urbano', [
                'ponto_partida_id' => $ponto->id,
                'origem_lat' => $origem[0],
                'origem_lng' => $origem[1],
                'ponto_descida_nome' => 'Casa',
                'ponto_descida_lat' => $origem[0] + 0.005,
                'ponto_descida_lng' => $origem[1] + 0.005,
            ])
            ->assertOk();
    }

    /**
     * @return array{0: Utilizador, 1: Veiculo}
     */
    private function condutorUrbano(string $matricula, ?PontoPartida $ponto = null, string $tipoServico = 'urbano'): array
    {
        $condutor = Utilizador::query()->create([
            'tipo' => 'condutor', 'nome' => 'Condutor '.$matricula,
            'telefone' => '+23898'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create([
            'condutor_id' => $condutor->id, 'matricula' => $matricula,
            'tipo' => 'taxi', 'tipo_servico' => $tipoServico, 'cor' => 'Amarelo', 'modelo' => 'Toyota',
            'lugares_total' => 4, 'lugares_livres' => 4, 'estado' => 'no_ponto',
            'aprovado' => true, 'ponto_partida_id' => $ponto?->id, 'destino_id' => null,
        ]);

        app(VeiculoService::class)->criarAssentos($veiculo);

        // Sem taxa de operação válida o condutor nem vê o mapa
        // (VeiculoService::condutorPodeVerMapa) e todas as listas dão 403.
        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'valor_pago' => 3000,
            'referencia' => 'REF-'.$veiculo->id,
            'data_pagamento' => now()->subDay(),
            'data_validade' => now()->addMonth(),
            'aprovado_por' => $this->adminId(),
            'aprovado_em' => now()->subDay(),
            'status' => 'aprovado',
        ]);

        return [$condutor, $veiculo->fresh()];
    }

    private function passageiro(string $nome): Utilizador
    {
        return Utilizador::query()->create([
            'tipo' => 'passageiro', 'nome' => $nome,
            'telefone' => '+23899'.random_int(10000, 99999),
            'nif' => (string) random_int(100000000, 999999999),
            'senha_hash' => 'senhaforte1', 'status' => 'ativo',
        ]);
    }

    private function adminId(): int
    {
        return Administrador::query()->firstOrCreate(
            ['email' => 'admin@vmillion.cv'],
            ['nome' => 'Super Admin', 'senha_hash' => 'senhaforte1', 'nivel' => 'super', 'ativo' => true]
        )->id;
    }
}
