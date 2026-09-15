<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtro personalizado da lista de veículos do painel de admin: texto,
 * tipo, tipo de serviço, estado, aprovação, ponto trancado, período de
 * registo (dia/semana/mês/ano e intervalo à mão) e ordem. Como no filtro de
 * utilizadores, corre no servidor porque a frota passa do limite de 200 que a
 * API devolve — filtrar no browser só o que já veio esconderia veículos.
 */
class AdminFiltroVeiculosTest extends TestCase
{
    use RefreshDatabase;

    private int $condutoresCriados = 0;

    public function test_filtra_por_texto_em_matricula_modelo_cor_e_condutor(): void
    {
        $this->veiculo(['matricula' => 'ST-01-AA', 'modelo' => 'Toyota Hiace', 'cor' => 'Branco'], ['nome' => 'Ana Lopes', 'telefone' => '+2389911111']);
        $this->veiculo(['matricula' => 'SV-02-BB', 'modelo' => 'Nissan Urvan', 'cor' => 'Azul'], ['nome' => 'Bruno Silva', 'telefone' => '+2389922222']);

        foreach (['ST-01', 'Toyota', 'Branco', 'Lopes', '9911111'] as $procura) {
            $json = $this->actingAs($this->administrador(), 'admin')
                ->getJson('/api/admin/veiculos?q='.urlencode($procura))
                ->assertOk()
                ->json();

            $this->assertSame(['ST-01-AA'], array_column($json['veiculos'], 'matricula'), "procura: {$procura}");
        }
    }

    public function test_filtra_por_tipo_servico_estado_e_aprovacao(): void
    {
        $this->veiculo([
            'matricula' => 'ST-01-AA', 'tipo' => 'hiace', 'tipo_servico' => 'ambos',
            'estado' => 'no_ponto', 'aprovado' => true,
        ], ['nome' => 'Ana Lopes', 'telefone' => '+2389911111']);

        $this->veiculo([
            'matricula' => 'SV-02-BB', 'tipo' => 'taxi', 'tipo_servico' => 'urbano',
            'estado' => 'na_fila', 'aprovado' => false, 'lugares_total' => 4, 'lugares_livres' => 4,
        ], ['nome' => 'Bruno Silva', 'telefone' => '+2389922222']);

        $admin = $this->administrador();

        $casos = [
            'tipo=taxi' => 'SV-02-BB',
            'tipo_servico=urbano' => 'SV-02-BB',
            'estado=na_fila' => 'SV-02-BB',
            'aprovado=0' => 'SV-02-BB',
            'aprovado=1' => 'ST-01-AA',
        ];

        foreach ($casos as $query => $esperado) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/veiculos?'.$query)
                ->assertOk()
                ->json();

            $this->assertSame([$esperado], array_column($json['veiculos'], 'matricula'), "filtro: {$query}");
        }
    }

    /**
     * "Ponto trancado" é o prazo por cumprir, não a coluna preenchida: um
     * bloqueio que já expirou conta como ponto livre, tal como o painel já
     * decide se mostra o botão "Libertar ponto".
     */
    public function test_filtra_por_ponto_trancado_e_ignora_bloqueio_expirado(): void
    {
        $trancado = $this->veiculo(['matricula' => 'ST-01-AA', 'ponto_bloqueado_ate' => now()->addHours(5)], ['nome' => 'Ana Lopes', 'telefone' => '+2389911111']);
        $expirado = $this->veiculo(['matricula' => 'SV-02-BB', 'ponto_bloqueado_ate' => now()->subHour()], ['nome' => 'Bruno Silva', 'telefone' => '+2389922222']);
        $semBloqueio = $this->veiculo(['matricula' => 'BV-03-CC'], ['nome' => 'Carla Monteiro', 'telefone' => '+2389933333']);

        $admin = $this->administrador();

        $bloqueados = $this->actingAs($admin, 'admin')->getJson('/api/admin/veiculos?ponto=bloqueado')->assertOk()->json();
        $this->assertSame([$trancado->id], array_column($bloqueados['veiculos'], 'id'));

        $livres = $this->actingAs($admin, 'admin')->getJson('/api/admin/veiculos?ponto=livre')->assertOk()->json();
        $ids = array_column($livres['veiculos'], 'id');
        sort($ids);
        $esperados = [$expirado->id, $semBloqueio->id];
        sort($esperados);
        $this->assertSame($esperados, $ids);
    }

    public function test_periodos_de_registo_apanham_dia_semana_mes_e_ano(): void
    {
        // Uma segunda-feira em pleno mês, para que "esta semana" e "este mês"
        // não escorreguem para o mês anterior nem para o ano anterior.
        $this->travelTo('2026-09-16 10:00:00');

        $hoje = $this->veiculo(['matricula' => 'ST-01-AA'], ['nome' => 'Hoje', 'telefone' => '+2389911111'], now());
        $ontem = $this->veiculo(['matricula' => 'SV-02-BB'], ['nome' => 'Ontem', 'telefone' => '+2389922222'], now()->copy()->subDay());
        $estaSemana = $this->veiculo(['matricula' => 'BV-03-CC'], ['nome' => 'Segunda', 'telefone' => '+2389933333'], now()->copy()->startOfWeek());
        $esteMes = $this->veiculo(['matricula' => 'SN-04-DD'], ['nome' => 'Mes', 'telefone' => '+2389944444'], now()->copy()->startOfMonth());
        $esteAno = $this->veiculo(['matricula' => 'SA-05-EE'], ['nome' => 'Ano', 'telefone' => '+2389955555'], now()->copy()->startOfYear());
        $anoPassado = $this->veiculo(['matricula' => 'SF-06-FF'], ['nome' => 'Antigo', 'telefone' => '+2389966666'], now()->copy()->subYear());

        $esperado = [
            'hoje' => [$hoje],
            'ontem' => [$ontem],
            'semana' => [$hoje, $ontem, $estaSemana],
            'mes' => [$hoje, $ontem, $estaSemana, $esteMes],
            'ano' => [$hoje, $ontem, $estaSemana, $esteMes, $esteAno],
        ];

        $admin = $this->administrador();

        foreach ($esperado as $periodo => $veiculos) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/veiculos?periodo='.$periodo)
                ->assertOk()
                ->json();

            $ids = array_column($json['veiculos'], 'id');
            sort($ids);
            $esperados = array_map(fn (Veiculo $v) => $v->id, $veiculos);
            sort($esperados);

            $this->assertSame($esperados, $ids, "período: {$periodo}");
            $this->assertNotContains($anoPassado->id, $ids, "período: {$periodo}");
        }
    }

    public function test_intervalo_personalizado_inclui_os_dois_extremos(): void
    {
        $antes = $this->veiculo(['matricula' => 'ST-01-AA'], ['nome' => 'Antes', 'telefone' => '+2389911111'], '2026-09-09 23:59:59');
        $inicio = $this->veiculo(['matricula' => 'SV-02-BB'], ['nome' => 'Inicio', 'telefone' => '+2389922222'], '2026-09-10 00:00:01');
        $fim = $this->veiculo(['matricula' => 'BV-03-CC'], ['nome' => 'Fim', 'telefone' => '+2389933333'], '2026-09-12 23:59:59');
        $depois = $this->veiculo(['matricula' => 'SN-04-DD'], ['nome' => 'Depois', 'telefone' => '+2389944444'], '2026-09-13 00:00:01');

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/veiculos?periodo=personalizado&data_inicio=2026-09-10&data_fim=2026-09-12')
            ->assertOk()
            ->json();

        $ids = array_column($json['veiculos'], 'id');
        sort($ids);

        $this->assertSame([$inicio->id, $fim->id], $ids);
        $this->assertNotContains($antes->id, $ids);
        $this->assertNotContains($depois->id, $ids);
    }

    public function test_ordena_por_registo_matricula_e_condutor(): void
    {
        $this->veiculo(['matricula' => 'SV-02-BB'], ['nome' => 'Carlos', 'telefone' => '+2389911111'], '2026-09-01 08:00:00');
        $this->veiculo(['matricula' => 'ST-01-AA'], ['nome' => 'Ana', 'telefone' => '+2389922222'], '2026-09-02 08:00:00');
        $this->veiculo(['matricula' => 'BV-03-CC'], ['nome' => 'Bruno', 'telefone' => '+2389933333'], '2026-09-03 08:00:00');

        $admin = $this->administrador();

        $ordens = [
            'recentes' => ['BV-03-CC', 'ST-01-AA', 'SV-02-BB'],
            'antigos' => ['SV-02-BB', 'ST-01-AA', 'BV-03-CC'],
            'matricula' => ['BV-03-CC', 'ST-01-AA', 'SV-02-BB'],
            'matricula_desc' => ['SV-02-BB', 'ST-01-AA', 'BV-03-CC'],
            'condutor' => ['ST-01-AA', 'BV-03-CC', 'SV-02-BB'],
        ];

        foreach ($ordens as $ordem => $esperado) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/veiculos?ordem='.$ordem)
                ->assertOk()
                ->json();

            $this->assertSame($esperado, array_column($json['veiculos'], 'matricula'), "ordem: {$ordem}");
        }
    }

    public function test_devolve_o_total_que_corresponde_ao_filtro(): void
    {
        $this->veiculo(['matricula' => 'ST-01-AA', 'aprovado' => true], ['nome' => 'Ana', 'telefone' => '+2389911111']);
        $this->veiculo(['matricula' => 'SV-02-BB', 'aprovado' => false], ['nome' => 'Bruno', 'telefone' => '+2389922222']);

        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/veiculos?aprovado=0')
            ->assertOk()
            ->assertJson(['total' => 1]);
    }

    public function test_periodo_invalido_e_recusado(): void
    {
        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/veiculos?periodo=decada')
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>  $condutor
     */
    private function veiculo(array $atributos, array $condutor, mixed $criadoEm = null): Veiculo
    {
        // NIF por contador e não aleatório: os testes criam vários condutores
        // e a coluna é única — dois sorteios iguais davam uma falha errática.
        $dono = Utilizador::query()->create($condutor + [
            'tipo' => 'condutor',
            'nif' => str_pad((string) ++$this->condutoresCriados, 9, '0', STR_PAD_LEFT),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        $veiculo = Veiculo::query()->create($atributos + [
            'condutor_id' => $dono->id,
            'tipo' => 'hiace',
            'tipo_servico' => 'ambos',
            'cor' => 'Branco',
            'modelo' => 'Toyota Hiace',
            'lugares_total' => 14,
            'lugares_livres' => 14,
            'estado' => 'no_ponto',
            'aprovado' => true,
        ]);

        // criado_em tem useCurrent na migração e o modelo trata-o como
        // CREATED_AT: para datar um registo no passado é preciso escrevê-lo
        // depois, sem passar pelos timestamps.
        if ($criadoEm !== null) {
            Veiculo::query()->whereKey($veiculo->id)->update(['criado_em' => $criadoEm]);
            $veiculo->refresh();
        }

        return $veiculo;
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
