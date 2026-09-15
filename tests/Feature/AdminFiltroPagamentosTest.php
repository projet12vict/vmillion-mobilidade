<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\PacotePagamento;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtro personalizado da lista "Pagamentos (taxa por rota)" do painel de
 * admin: texto, estado, pacote, tipo de serviço, comprovativo, validade,
 * intervalo de valor, período de submissão (dia/semana/mês/ano e intervalo à
 * mão) e ordem. Como nos filtros de utilizadores e de veículos, corre no
 * servidor porque os pagamentos passam do limite de 200 que a API devolve —
 * filtrar no browser só o que já veio esconderia pedidos.
 */
class AdminFiltroPagamentosTest extends TestCase
{
    use RefreshDatabase;

    private int $condutoresCriados = 0;

    private int $referencias = 0;

    public function test_filtra_por_texto_em_condutor_telefone_matricula_referencia_e_pacote(): void
    {
        $mensal = $this->pacote(['nome' => 'Mensal']);
        $alvo = $this->pagamento(
            ['referencia' => 'REF-ABC-001', 'pacote_id' => $mensal->id],
            ['nome' => 'Ana Lopes', 'telefone' => '+2389911111'],
            ['matricula' => 'ST-01-AA'],
        );
        $this->pagamento(
            ['referencia' => 'REF-XYZ-002'],
            ['nome' => 'Bruno Silva', 'telefone' => '+2389922222'],
            ['matricula' => 'SV-02-BB'],
        );

        foreach (['Lopes', '9911111', 'ST-01', 'ABC-001', 'Mensal'] as $procura) {
            $json = $this->actingAs($this->administrador(), 'admin')
                ->getJson('/api/admin/pagamentos?q='.urlencode($procura))
                ->assertOk()
                ->json();

            $this->assertSame([$alvo->id], array_column($json['pagamentos'], 'id'), "procura: {$procura}");
        }
    }

    public function test_filtra_por_estado_pacote_e_tipo_de_servico(): void
    {
        $urbano = $this->pacote(['nome' => 'Urbano diário', 'tipo_servico' => 'urbano']);
        $intermunicipal = $this->pacote(['nome' => 'Intermunicipal', 'tipo_servico' => 'intermunicipal']);

        $pendente = $this->pagamento(['status' => 'pendente', 'pacote_id' => $urbano->id]);
        $aprovado = $this->pagamento(['status' => 'aprovado', 'pacote_id' => $intermunicipal->id]);
        // Pagamento anterior aos pacotes: taxa fixa, sem pacote nenhum.
        $semPacote = $this->pagamento(['status' => 'recusado', 'pacote_id' => null]);

        $admin = $this->administrador();

        $casos = [
            'status=pendente' => [$pendente],
            'status=recusado' => [$semPacote],
            'pacote='.$urbano->id => [$pendente],
            'pacote=sem' => [$semPacote],
            'tipo_servico=intermunicipal' => [$aprovado],
        ];

        foreach ($casos as $query => $esperados) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/pagamentos?'.$query)
                ->assertOk()
                ->json();

            $this->assertSame(
                array_map(fn (PagamentoCondutor $p) => $p->id, $esperados),
                array_column($json['pagamentos'], 'id'),
                "filtro: {$query}",
            );
        }
    }

    public function test_filtra_por_comprovativo_e_pelo_tipo_de_ficheiro(): void
    {
        $foto = $this->pagamento(['comprovativo_path' => 'comprovativos/1.jpg', 'comprovativo_tipo' => 'imagem']);
        $pdf = $this->pagamento(['comprovativo_path' => 'comprovativos/2.pdf', 'comprovativo_tipo' => 'pdf']);
        $sem = $this->pagamento(['comprovativo_path' => null, 'comprovativo_tipo' => null]);

        $admin = $this->administrador();

        $casos = [
            'com' => [$foto->id, $pdf->id],
            'sem' => [$sem->id],
            'imagem' => [$foto->id],
            'pdf' => [$pdf->id],
        ];

        foreach ($casos as $valor => $esperados) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/pagamentos?comprovativo='.$valor)
                ->assertOk()
                ->json();

            $ids = array_column($json['pagamentos'], 'id');
            sort($ids);
            sort($esperados);

            $this->assertSame($esperados, $ids, "comprovativo: {$valor}");
        }
    }

    /**
     * "Válido" é o que abre o mapa ao condutor (PagamentoCondutor::validos):
     * um aprovado cuja validade já passou não conta, e um recusado com data
     * no futuro também não.
     */
    public function test_validade_separa_o_que_da_acesso_do_que_expirou(): void
    {
        $valido = $this->pagamento(['status' => 'aprovado', 'data_validade' => now()->addDays(10)]);
        $expirado = $this->pagamento(['status' => 'aprovado', 'data_validade' => now()->subDay()]);
        $recusadoComData = $this->pagamento(['status' => 'recusado', 'data_validade' => now()->addDays(10)]);
        $semValidade = $this->pagamento(['status' => 'pendente', 'data_validade' => null]);

        $admin = $this->administrador();

        $casos = [
            'valido' => [$valido->id],
            'expirado' => [$expirado->id],
            'sem' => [$semValidade->id],
        ];

        foreach ($casos as $valor => $esperados) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/pagamentos?validade='.$valor)
                ->assertOk()
                ->json();

            $ids = array_column($json['pagamentos'], 'id');
            sort($ids);

            $this->assertSame($esperados, $ids, "validade: {$valor}");
        }

        $validos = $this->actingAs($admin, 'admin')->getJson('/api/admin/pagamentos?validade=valido')->assertOk()->json();
        $this->assertNotContains($recusadoComData->id, array_column($validos['pagamentos'], 'id'));
    }

    public function test_intervalo_de_valor_inclui_os_dois_extremos(): void
    {
        $abaixo = $this->pagamento(['valor_pago' => 999]);
        $minimo = $this->pagamento(['valor_pago' => 1000]);
        $maximo = $this->pagamento(['valor_pago' => 5000]);
        $acima = $this->pagamento(['valor_pago' => 5001]);

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos?valor_min=1000&valor_max=5000')
            ->assertOk()
            ->json();

        $ids = array_column($json['pagamentos'], 'id');
        sort($ids);

        $this->assertSame([$minimo->id, $maximo->id], $ids);
        $this->assertNotContains($abaixo->id, $ids);
        $this->assertNotContains($acima->id, $ids);
    }

    public function test_periodos_de_submissao_apanham_dia_semana_mes_e_ano(): void
    {
        // Uma segunda-feira em pleno mês, para que "esta semana" e "este mês"
        // não escorreguem para o mês anterior nem para o ano anterior.
        $this->travelTo('2026-09-16 10:00:00');

        $hoje = $this->pagamento([], null, null, now());
        $ontem = $this->pagamento([], null, null, now()->copy()->subDay());
        $estaSemana = $this->pagamento([], null, null, now()->copy()->startOfWeek());
        $esteMes = $this->pagamento([], null, null, now()->copy()->startOfMonth());
        $esteAno = $this->pagamento([], null, null, now()->copy()->startOfYear());
        $anoPassado = $this->pagamento([], null, null, now()->copy()->subYear());

        $esperado = [
            'hoje' => [$hoje],
            'ontem' => [$ontem],
            'semana' => [$hoje, $ontem, $estaSemana],
            'mes' => [$hoje, $ontem, $estaSemana, $esteMes],
            'ano' => [$hoje, $ontem, $estaSemana, $esteMes, $esteAno],
        ];

        $admin = $this->administrador();

        foreach ($esperado as $periodo => $pagamentos) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/pagamentos?periodo='.$periodo)
                ->assertOk()
                ->json();

            $ids = array_column($json['pagamentos'], 'id');
            sort($ids);
            $esperados = array_map(fn (PagamentoCondutor $p) => $p->id, $pagamentos);
            sort($esperados);

            $this->assertSame($esperados, $ids, "período: {$periodo}");
            $this->assertNotContains($anoPassado->id, $ids, "período: {$periodo}");
        }
    }

    public function test_intervalo_personalizado_inclui_os_dois_extremos(): void
    {
        $antes = $this->pagamento([], null, null, '2026-09-09 23:59:59');
        $inicio = $this->pagamento([], null, null, '2026-09-10 00:00:01');
        $fim = $this->pagamento([], null, null, '2026-09-12 23:59:59');
        $depois = $this->pagamento([], null, null, '2026-09-13 00:00:01');

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos?periodo=personalizado&data_inicio=2026-09-10&data_fim=2026-09-12')
            ->assertOk()
            ->json();

        $ids = array_column($json['pagamentos'], 'id');
        sort($ids);

        $this->assertSame([$inicio->id, $fim->id], $ids);
        $this->assertNotContains($antes->id, $ids);
        $this->assertNotContains($depois->id, $ids);
    }

    public function test_ordena_por_submissao_valor_condutor_e_validade(): void
    {
        $carlos = $this->pagamento(
            ['valor_pago' => 3000, 'data_validade' => '2026-10-01 00:00:00'],
            ['nome' => 'Carlos', 'telefone' => '+2389911111'],
            [],
            '2026-09-01 08:00:00',
        );
        $ana = $this->pagamento(
            ['valor_pago' => 1000, 'data_validade' => '2026-12-01 00:00:00'],
            ['nome' => 'Ana', 'telefone' => '+2389922222'],
            [],
            '2026-09-02 08:00:00',
        );
        $bruno = $this->pagamento(
            ['valor_pago' => 5000, 'data_validade' => '2026-11-01 00:00:00'],
            ['nome' => 'Bruno', 'telefone' => '+2389933333'],
            [],
            '2026-09-03 08:00:00',
        );

        $admin = $this->administrador();

        $ordens = [
            'recentes' => [$bruno, $ana, $carlos],
            'antigos' => [$carlos, $ana, $bruno],
            'valor' => [$ana, $carlos, $bruno],
            'valor_desc' => [$bruno, $carlos, $ana],
            'condutor' => [$ana, $bruno, $carlos],
            'validade' => [$ana, $bruno, $carlos],
        ];

        foreach ($ordens as $ordem => $esperados) {
            $json = $this->actingAs($admin, 'admin')
                ->getJson('/api/admin/pagamentos?ordem='.$ordem)
                ->assertOk()
                ->json();

            $this->assertSame(
                array_map(fn (PagamentoCondutor $p) => $p->id, $esperados),
                array_column($json['pagamentos'], 'id'),
                "ordem: {$ordem}",
            );
        }
    }

    /** Sem "ordenar por" escolhido, o que espera decisão aparece primeiro. */
    public function test_ordem_padrao_poe_os_pendentes_a_frente(): void
    {
        $aprovadoRecente = $this->pagamento(['status' => 'aprovado'], null, null, '2026-09-05 08:00:00');
        $pendenteAntigo = $this->pagamento(['status' => 'pendente'], null, null, '2026-09-01 08:00:00');

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos')
            ->assertOk()
            ->json();

        $this->assertSame([$pendenteAntigo->id, $aprovadoRecente->id], array_column($json['pagamentos'], 'id'));
    }

    public function test_devolve_o_total_que_corresponde_ao_filtro(): void
    {
        $this->pagamento(['status' => 'pendente']);
        $this->pagamento(['status' => 'aprovado']);

        $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos?status=pendente')
            ->assertOk()
            ->assertJson(['total' => 1]);
    }

    /**
     * As opções do filtro "Pacote" saem dos pagamentos já feitos e não da
     * tabela de pacotes: um pacote que ninguém pagou seria uma opção que
     * nunca devolve nada, e a lista de pacotes só o Super Admin a pode ler.
     */
    public function test_devolve_apenas_os_pacotes_com_pagamentos(): void
    {
        $usado = $this->pacote(['nome' => 'Mensal']);
        $this->pacote(['nome' => 'Anual']);
        $this->pagamento(['pacote_id' => $usado->id]);
        $this->pagamento(['pacote_id' => null]);

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos')
            ->assertOk()
            ->json();

        $this->assertSame([$usado->id], array_column($json['pacotes'], 'id'));
        $this->assertSame(['Mensal'], array_column($json['pacotes'], 'nome'));
    }

    /** As opções não encolhem com o filtro escolhido — só assim se volta atrás. */
    public function test_lista_de_pacotes_nao_depende_do_filtro_escolhido(): void
    {
        $mensal = $this->pacote(['nome' => 'Mensal']);
        $anual = $this->pacote(['nome' => 'Anual']);
        $this->pagamento(['pacote_id' => $mensal->id]);
        $this->pagamento(['pacote_id' => $anual->id]);

        $json = $this->actingAs($this->administrador(), 'admin')
            ->getJson('/api/admin/pagamentos?pacote='.$mensal->id)
            ->assertOk()
            ->json();

        $ids = array_column($json['pacotes'], 'id');
        sort($ids);
        $esperados = [$mensal->id, $anual->id];
        sort($esperados);

        $this->assertCount(1, $json['pagamentos']);
        $this->assertSame($esperados, $ids);
    }

    public function test_periodo_e_pacote_invalidos_sao_recusados(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/pagamentos?periodo=decada')
            ->assertStatus(422);

        // Um pacote que não é nem um id nem "sem" é um pedido mal formado, e
        // não um filtro que por acaso não encontra nada.
        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/pagamentos?pacote=abc')
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>|null  $condutor
     * @param  array<string, mixed>|null  $veiculo
     */
    private function pagamento(array $atributos = [], ?array $condutor = null, ?array $veiculo = null, mixed $criadoEm = null): PagamentoCondutor
    {
        // NIF, telefone e matrícula por contador e não aleatórios: os testes
        // criam vários condutores e as colunas são únicas — dois sorteios
        // iguais davam uma falha errática.
        $n = ++$this->condutoresCriados;

        $dono = Utilizador::query()->create(($condutor ?? []) + [
            'tipo' => 'condutor',
            'nome' => 'Condutor '.$n,
            'telefone' => '+23899'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'nif' => str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            'senha_hash' => 'senhaforte1',
            'status' => 'ativo',
        ]);

        $carro = Veiculo::query()->create(($veiculo ?? []) + [
            'condutor_id' => $dono->id,
            'matricula' => 'ST-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).'-AA',
            'tipo' => 'hiace',
            'tipo_servico' => 'ambos',
            'cor' => 'Branco',
            'modelo' => 'Toyota Hiace',
            'lugares_total' => 14,
            'lugares_livres' => 14,
            'estado' => 'no_ponto',
            'aprovado' => true,
        ]);

        $pagamento = PagamentoCondutor::query()->create($atributos + [
            'condutor_id' => $dono->id,
            'veiculo_id' => $carro->id,
            'valor_pago' => 5000,
            'referencia' => 'REF-'.str_pad((string) ++$this->referencias, 4, '0', STR_PAD_LEFT),
            'status' => 'pendente',
        ]);

        // criado_em tem useCurrent na migração e o modelo trata-o como
        // CREATED_AT: para datar um registo no passado é preciso escrevê-lo
        // depois, sem passar pelos timestamps.
        if ($criadoEm !== null) {
            PagamentoCondutor::query()->whereKey($pagamento->id)->update(['criado_em' => $criadoEm]);
            $pagamento->refresh();
        }

        return $pagamento;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function pacote(array $atributos = []): PacotePagamento
    {
        return PacotePagamento::query()->create($atributos + [
            'nome' => 'Mensal',
            'tipo_servico' => 'ambos',
            'descricao' => 'Trinta dias de acesso',
            'preco' => 5000,
            'duracao_dias' => 30,
            'ativo' => true,
        ]);
    }

    private function administrador(): Administrador
    {
        return Administrador::query()->create([
            'nome' => 'Admin',
            'email' => 'admin'.random_int(1000, 9999).'@vmillion.cv',
            'senha_hash' => 'senhaforte1',
            'nivel' => 'super',
            'senha_temporaria' => false,
            'ativo' => true,
        ]);
    }
}
