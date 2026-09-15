<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Utilizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtro personalizado da lista de utilizadores do painel de admin: texto,
 * tipo, estado, período de registo (dia/semana/mês/ano e intervalo à mão) e
 * ordem de registo. O filtro corre no servidor porque a lista passa do limite
 * de 200 que a API devolve — filtrar no browser só o que já veio daria
 * resultados errados sem ninguém dar por isso.
 */
class AdminFiltroUtilizadoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_texto_em_nome_telefone_nif_e_email(): void
    {
        $this->utilizador(['nome' => 'Ana Lopes', 'telefone' => '+2389911111', 'nif' => '111111111', 'email' => 'ana@vmillion.cv']);
        $this->utilizador(['nome' => 'Bruno Silva', 'telefone' => '+2389922222', 'nif' => '222222222', 'email' => 'bruno@vmillion.cv']);

        foreach (['Lopes', '9911111', '111111111', 'ana@'] as $procura) {
            $json = $this->actingAs($this->administrador(), 'admin')
                ->getJson('/api/admin/utilizadores?q='.urlencode($procura))
                ->assertOk()
                ->json();

            $this->assertSame(['Ana Lopes'], array_column($json['utilizadores'], 'nome'), "procura: {$procura}");
        }
    }

    public function test_filtra_por_tipo_e_por_estado(): void
    {
        $this->utilizador(['nome' => 'Passageira Ativa', 'telefone' => '+2389911111', 'nif' => '111111111']);
        $this->utilizador(['nome' => 'Condutor Pendente', 'telefone' => '+2389922222', 'nif' => '222222222', 'tipo' => 'condutor', 'status' => 'pendente']);

        $admin = $this->administrador();

        $porTipo = $this->actingAs($admin, 'admin')->getJson('/api/admin/utilizadores?tipo=condutor')->assertOk()->json();
        $this->assertSame(['Condutor Pendente'], array_column($porTipo['utilizadores'], 'nome'));

        $porEstado = $this->actingAs($admin, 'admin')->getJson('/api/admin/utilizadores?status=pendente')->assertOk()->json();
        $this->assertSame(['Condutor Pendente'], array_column($porEstado['utilizadores'], 'nome'));
    }

    public function test_periodos_de_registo_apanham_dia_semana_mes_e_ano(): void
    {
        // Uma segunda-feira em pleno mês, para que "esta semana" e "este mês"
        // não escorreguem para o mês anterior nem para o ano anterior.
        $this->travelTo('2026-09-16 10:00:00');

        $hoje = $this->utilizador(['nome' => 'Hoje', 'telefone' => '+2389911111', 'nif' => '111111111'], now());
        $ontem = $this->utilizador(['nome' => 'Ontem', 'telefone' => '+2389922222', 'nif' => '222222222'], now()->copy()->subDay());
        $estaSemana = $this->utilizador(['nome' => 'Segunda', 'telefone' => '+2389933333', 'nif' => '333333333'], now()->copy()->startOfWeek());
        $esteMes = $this->utilizador(['nome' => 'Mes', 'telefone' => '+2389944444', 'nif' => '444444444'], now()->copy()->startOfMonth());
        $esteAno = $this->utilizador(['nome' => 'Ano', 'telefone' => '+2389955555', 'nif' => '555555555'], now()->copy()->startOfYear());
        $anoPassado = $this->utilizador(['nome' => 'Antigo', 'telefone' => '+2389966666', 'nif' => '666666666'], now()->copy()->subYear());

        $esperado = [
            'hoje' => [$hoje],
            'ontem' => [$ontem],
            'semana' => [$hoje, $ontem, $estaSemana],
            'mes' => [$hoje, $ontem, $estaSemana, $esteMes],
            'ano' => [$hoje, $ontem, $estaSemana, $esteMes, $esteAno],
        ];

        $admin = $this->administrador();

        foreach ($esperado as $periodo => $utilizadores) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/utilizadores?periodo='.$periodo)
                ->assertOk()
                ->json();

            $ids = array_column($json['utilizadores'], 'id');
            sort($ids);
            $esperados = array_map(fn (Utilizador $u) => $u->id, $utilizadores);
            sort($esperados);

            $this->assertSame($esperados, $ids, "período: {$periodo}");
            $this->assertNotContains($anoPassado->id, $ids, "período: {$periodo}");
        }
    }

    public function test_intervalo_personalizado_inclui_os_dois_extremos(): void
    {
        $antes = $this->utilizador(['nome' => 'Antes', 'telefone' => '+2389911111', 'nif' => '111111111'], '2026-09-09 23:59:59');
        $inicio = $this->utilizador(['nome' => 'Inicio', 'telefone' => '+2389922222', 'nif' => '222222222'], '2026-09-10 00:00:01');
        $fim = $this->utilizador(['nome' => 'Fim', 'telefone' => '+2389933333', 'nif' => '333333333'], '2026-09-12 23:59:59');
        $depois = $this->utilizador(['nome' => 'Depois', 'telefone' => '+2389944444', 'nif' => '444444444'], '2026-09-13 00:00:01');

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/utilizadores?periodo=personalizado&data_inicio=2026-09-10&data_fim=2026-09-12')
            ->assertOk()
            ->json();

        $ids = array_column($json['utilizadores'], 'id');
        sort($ids);

        $this->assertSame([$inicio->id, $fim->id], $ids);
        $this->assertNotContains($antes->id, $ids);
        $this->assertNotContains($depois->id, $ids);
    }

    public function test_ordena_por_registo_e_por_nome(): void
    {
        $this->utilizador(['nome' => 'Carlos', 'telefone' => '+2389911111', 'nif' => '111111111'], '2026-09-01 08:00:00');
        $this->utilizador(['nome' => 'Ana', 'telefone' => '+2389922222', 'nif' => '222222222'], '2026-09-02 08:00:00');
        $this->utilizador(['nome' => 'Bruno', 'telefone' => '+2389933333', 'nif' => '333333333'], '2026-09-03 08:00:00');

        $admin = $this->administrador();

        $ordens = [
            'recentes' => ['Bruno', 'Ana', 'Carlos'],
            'antigos' => ['Carlos', 'Ana', 'Bruno'],
            'nome' => ['Ana', 'Bruno', 'Carlos'],
            'nome_desc' => ['Carlos', 'Bruno', 'Ana'],
        ];

        foreach ($ordens as $ordem => $esperado) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/utilizadores?ordem='.$ordem)
                ->assertOk()
                ->json();

            $this->assertSame($esperado, array_column($json['utilizadores'], 'nome'), "ordem: {$ordem}");
        }
    }

    public function test_devolve_o_total_que_corresponde_ao_filtro(): void
    {
        $this->utilizador(['nome' => 'Ana', 'telefone' => '+2389911111', 'nif' => '111111111']);
        $this->utilizador(['nome' => 'Bruno', 'telefone' => '+2389922222', 'nif' => '222222222', 'tipo' => 'condutor']);

        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/utilizadores?tipo=condutor')
            ->assertOk()
            ->assertJson(['total' => 1]);
    }

    public function test_periodo_invalido_e_recusado(): void
    {
        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/utilizadores?periodo=decada')
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function utilizador(array $atributos, mixed $criadoEm = null): Utilizador
    {
        $utilizador = Utilizador::query()->create($atributos + [
            'tipo' => 'passageiro',
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        // criado_em tem useCurrent na migração e o modelo trata-o como
        // CREATED_AT: para datar um registo no passado é preciso escrevê-lo
        // depois, sem passar pelos timestamps.
        if ($criadoEm !== null) {
            Utilizador::query()->whereKey($utilizador->id)->update(['criado_em' => $criadoEm]);
            $utilizador->refresh();
        }

        return $utilizador;
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
