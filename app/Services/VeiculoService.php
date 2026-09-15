<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssentoVeiculo;
use App\Models\PagamentoCondutor;
use App\Models\Reserva;
use App\Models\Veiculo;
use RuntimeException;

/**
 * Layout de assentos e gate de operação do condutor.
 * Porta de includes/veiculos.php.
 */
class VeiculoService
{
    /**
     * Lugares para passageiros de um tipo de veículo: 14 no Hiace, 30 no
     * autocarro e 4 no táxi. Um tipo sem entrada na configuração fica com o
     * valor por omissão em vez de zero lugares.
     */
    public function lugaresTotal(string $tipo): int
    {
        $porTipo = (array) config('vmillion.veiculos.lugares_por_tipo', []);

        return (int) ($porTipo[$tipo] ?? config('vmillion.veiculos.lugares_total'));
    }

    /**
     * Teto de lugares que o condutor pode dar ao veículo no botão "Lugares"
     * do painel. Nunca fica abaixo da capacidade de origem do tipo: um tipo
     * sem entrada em 'lugares_max_por_tipo' (ou com um teto mais baixo por
     * engano) pode sempre voltar aos lugares com que nasceu.
     */
    public function lugaresMax(string $tipo): int
    {
        $porTipo = (array) config('vmillion.veiculos.lugares_max_por_tipo', []);

        return max($this->lugaresTotal($tipo), (int) ($porTipo[$tipo] ?? 0));
    }

    /**
     * Layout de assentos do tipo de veículo, derivado da sua capacidade: as
     * filas de trás levam 3 bancos cada e o que sobra vai para a fila 0,
     * junto ao condutor — 2 no Hiace (14 = 2 + 4x3) e 1 no táxi (4 = 1 + 3);
     * o autocarro (30 = 10x3) não tem. Uma capacidade múltipla de 3 não tem fila 0 de todo:
     * os passageiros vão todos nos bancos de trás.
     *
     * Com $total acima da capacidade do tipo, os lugares a mais vão em filas
     * novas atrás das de origem: a fila 0 é do tipo de veículo e não cresce,
     * por isso os lugares de origem ficam exatamente onde estavam.
     *
     * @return array<int, array{numero: int, fila: int, coluna: int}>
     */
    public function layoutAssentos(string $tipo = 'hiace', ?int $total = null): array
    {
        $total ??= $this->lugaresTotal($tipo);
        $juntoAoCondutor = min($total, $this->lugaresTotal($tipo) % 3);
        $layout = [];
        $numero = 1;

        for ($coluna = 1; $coluna <= $juntoAoCondutor; $coluna++) {
            $layout[] = ['numero' => $numero++, 'fila' => 0, 'coluna' => $coluna];
        }

        for ($fila = 1; $numero <= $total; $fila++) {
            for ($coluna = 1; $coluna <= 3 && $numero <= $total; $coluna++) {
                $layout[] = ['numero' => $numero++, 'fila' => $fila, 'coluna' => $coluna];
            }
        }

        return $layout;
    }

    /**
     * Assentos de um veículo novo: a capacidade do tipo, ou $total quando o
     * condutor indicou outro número ao registar (ver layoutAssentos para onde
     * vão os lugares a mais ou a menos).
     */
    public function criarAssentos(Veiculo $veiculo, ?int $total = null): void
    {
        AssentoVeiculo::query()->insert(array_map(
            fn (array $lugar) => $lugar + ['veiculo_id' => $veiculo->id, 'ocupado' => false],
            $this->layoutAssentos((string) $veiculo->tipo, $total)
        ));
    }

    /**
     * Tira um lugar ao veículo (botão "Lugares" do painel do condutor): o
     * assento indicado ou, sem indicação, o livre de número mais alto.
     *
     * O assento é desativado e não apagado: a FK reservas.assento_id é ON
     * DELETE CASCADE, e apagar um assento levava consigo as viagens já feitas
     * nesse lugar (e as avaliações penduradas nelas). Desativado, sai do mapa
     * de assentos do passageiro e deixa de ser vendido, mas o histórico
     * continua a dizer em que lugar cada um viajou.
     *
     * Só sai um lugar livre, e só se o veículo tiver um lugar livre para dar:
     * 'lugares_livres' já desconta quem reservou ou vai a bordo, e baixar a
     * capacidade abaixo disso deixava passageiros sem lugar.
     *
     * Chamar dentro de uma transação, com a linha do veículo bloqueada — é o
     * mesmo bloqueio que Passageiro\ReservaController::reservar tira antes de
     * vender um assento, e é ele que impede os dois de apanharem o mesmo.
     */
    public function retirarLugar(Veiculo $veiculo, ?int $assentoId = null): AssentoVeiculo
    {
        if ($veiculo->lugares_total <= 1) {
            throw new RuntimeException('O veículo tem de ficar com pelo menos um lugar.', 422);
        }

        if ($veiculo->lugares_livres < 1) {
            throw new RuntimeException('Não há lugares livres para retirar: estão todos reservados ou ocupados.', 409);
        }

        $assento = AssentoVeiculo::query()
            ->lockForUpdate()
            ->where('veiculo_id', $veiculo->id)
            ->where('ativo', true)
            ->when(
                $assentoId,
                fn ($q, $id) => $q->whereKey($id),
                fn ($q) => $q->where('ocupado', false)->whereNull('reserva_id')->orderByDesc('numero')
            )
            ->first();

        if (! $assento) {
            throw $assentoId
                ? new RuntimeException('Lugar não encontrado.', 404)
                : new RuntimeException('Não há lugares livres para retirar.', 409);
        }

        if ($assento->ocupado || $assento->reserva_id !== null) {
            throw new RuntimeException("O lugar {$assento->numero} está reservado ou ocupado e não pode ser retirado.", 409);
        }

        $assento->forceFill(['ativo' => false])->save();

        $veiculo->forceFill([
            'lugares_total' => $veiculo->lugares_total - 1,
            'lugares_livres' => $veiculo->lugares_livres - 1,
        ])->save();

        return $assento;
    }

    /**
     * Dá mais um lugar ao veículo, até ao teto do tipo (lugaresMax): repõe um
     * lugar retirado — o indicado ou, sem indicação, o de número mais baixo —
     * e só quando já não há nenhum retirado é que cria um assento novo.
     *
     * Mesmas condições de chamada que retirarLugar.
     */
    public function reporLugar(Veiculo $veiculo, ?int $assentoId = null): AssentoVeiculo
    {
        $max = $this->lugaresMax((string) $veiculo->tipo);

        if ($veiculo->lugares_total >= $max) {
            throw new RuntimeException("Este tipo de veículo leva no máximo {$max} lugares.", 422);
        }

        $assento = AssentoVeiculo::query()
            ->lockForUpdate()
            ->where('veiculo_id', $veiculo->id)
            ->where('ativo', false)
            ->when($assentoId, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->orderBy('numero'))
            ->first();

        if ($assentoId && ! $assento) {
            throw new RuntimeException('Lugar não encontrado.', 404);
        }

        if ($assento) {
            $assento->forceFill(['ativo' => true])->save();
        } else {
            $assento = $this->criarAssentoNovo($veiculo);
        }

        $veiculo->forceFill([
            'lugares_total' => $veiculo->lugares_total + 1,
            'lugares_livres' => $veiculo->lugares_livres + 1,
        ])->save();

        return $assento;
    }

    /**
     * O assento novo leva o número a seguir ao mais alto que o veículo já
     * teve — nunca se reaproveita um número, que está impresso nas reservas
     * antigas — e vai para a primeira posição vaga do layout alargado do
     * tipo. Num veículo com o layout de origem, é a posição que o layout lhe
     * daria de qualquer forma; num táxi antigo (lugar 4 na fila 0, ver a
     * migration taxi_passa_a_quatro_lugares) cai na fila de trás seguinte em
     * vez de ficar em cima de outro banco.
     */
    private function criarAssentoNovo(Veiculo $veiculo): AssentoVeiculo
    {
        $existentes = AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->get(['numero', 'fila', 'coluna']);

        $tomadas = $existentes->map(fn ($a) => "{$a->fila}-{$a->coluna}")->all();

        // Um lugar a mais do que os que existem garante pelo menos uma
        // posição do layout por ocupar, siga o veículo o layout ou não.
        $posicao = collect($this->layoutAssentos((string) $veiculo->tipo, $existentes->count() + 1))
            ->first(fn (array $lugar) => ! in_array("{$lugar['fila']}-{$lugar['coluna']}", $tomadas, true));

        return AssentoVeiculo::query()->create([
            'veiculo_id' => $veiculo->id,
            'numero' => (int) $existentes->max('numero') + 1,
            'fila' => $posicao['fila'],
            'coluna' => $posicao['coluna'],
            'ocupado' => false,
            'ativo' => true,
        ]);
    }

    /**
     * Passageiro que entrou sem reservar pela app (apanhado na rua): o
     * condutor marca-lhe o lugar no mapa de "Lugares". O lugar fica ocupado
     * como o de uma reserva — o passageiro da app vê-o preso e
     * 'lugares_livres' desce —, mas sem reserva por trás: não há conta a quem
     * avisar nem preço. Liberta-se com libertarSemApp ou sozinho na chegada
     * ao destino (Condutor\PosicaoController::concluirChegada).
     *
     * Mesmas condições de chamada que retirarLugar.
     */
    public function ocuparSemApp(Veiculo $veiculo, ?int $assentoId = null, ?string $nome = null): AssentoVeiculo
    {
        if ($veiculo->lugares_livres < 1) {
            throw new RuntimeException('Não há lugares livres: estão todos reservados ou ocupados.', 409);
        }

        // Sem lugar indicado (botão "Adicionar passageiro"), o passageiro vai
        // para o livre de número mais baixo — da frente para trás, como se
        // enche um carro.
        $assento = AssentoVeiculo::query()
            ->lockForUpdate()
            ->where('veiculo_id', $veiculo->id)
            ->where('ativo', true)
            ->when(
                $assentoId !== null,
                fn ($q) => $q->whereKey($assentoId),
                fn ($q) => $q->where('ocupado', false)->whereNull('reserva_id')->orderBy('numero'),
            )
            ->first();

        if (! $assento) {
            throw new RuntimeException('Lugar não encontrado.', 404);
        }

        if ($assento->ocupado || $assento->reserva_id !== null) {
            throw new RuntimeException("O lugar {$assento->numero} já está ocupado.", 409);
        }

        $nome = trim((string) $nome);

        $assento->forceFill([
            'ocupado' => true,
            'ocupado_sem_app' => true,
            'nome_sem_app' => $nome !== '' ? $nome : null,
        ])->save();

        $veiculo->forceFill(['lugares_livres' => $veiculo->lugares_livres - 1])->save();

        return $assento;
    }

    /**
     * O passageiro sem app saiu: o lugar volta a ficar livre. Só mexe em
     * lugares marcados por ocuparSemApp — o de uma reserva liberta-se pelo
     * ciclo da própria reserva ("Entregue", "Não apareceu", "Remover").
     */
    public function libertarSemApp(Veiculo $veiculo, ?int $assentoId = null): AssentoVeiculo
    {
        // Sem lugar indicado (botão "Remover passageiro"), sai o de número
        // mais alto: o inverso de ocuparSemApp, que enche da frente para trás.
        $assento = AssentoVeiculo::query()
            ->lockForUpdate()
            ->where('veiculo_id', $veiculo->id)
            ->where('ocupado_sem_app', true)
            ->when(
                $assentoId !== null,
                fn ($q) => $q->whereKey($assentoId),
                fn ($q) => $q->orderByDesc('numero'),
            )
            ->first();

        if (! $assento) {
            throw new RuntimeException(
                $assentoId !== null
                    ? 'Este lugar não tem nenhum passageiro sem app.'
                    : 'Não há passageiros sem app para remover. Quem reservou pela app sai em "Passageiros".',
                404,
            );
        }

        $assento->forceFill(['ocupado' => false, 'ocupado_sem_app' => false, 'nome_sem_app' => null])->save();

        $veiculo->forceFill([
            'lugares_livres' => min($veiculo->lugares_total, $veiculo->lugares_livres + 1),
        ])->save();

        return $assento;
    }

    /**
     * Quantos passageiros sem app vão no carro. Não têm reserva, por isso
     * tudo o que conta a lotação a partir das reservas tem de os somar à
     * parte (embarcar, sair da fila, ocupacao).
     */
    public function passageirosSemApp(int $veiculoId): int
    {
        return AssentoVeiculo::query()
            ->where('veiculo_id', $veiculoId)
            ->where('ocupado_sem_app', true)
            ->count();
    }

    public function normalizarMatricula(string $matricula): string
    {
        return mb_strtoupper(trim($matricula));
    }

    /**
     * Ocupação do veículo tal como o marcador do condutor a mostra: capacidade
     * total, quem está mesmo dentro do carro, quantos lugares estão presos por
     * reservas ainda por embarcar e quantos sobram para novas reservas.
     *
     * São duas contagens diferentes de propósito. O lugar é ocupado no momento
     * da reserva (ver Passageiro\ReservaController::reservar), muito antes de
     * o passageiro entrar no carro: 'lugares_livres' responde a "ainda posso
     * receber reservas?" e 'a_bordo' a "quem é que vem comigo agora?". Mostrar
     * só uma delas dava sempre um número errado a metade das perguntas.
     *
     * Cada passageiro a bordo vem com o destino que escolheu e o número do
     * assento que ocupa: é isso que o condutor procura quando carrega no seu
     * veículo no mapa ("quem levo, para onde e em que lugar"), e sem o
     * destino a lista de nomes não lhe dizia onde parar. Os que já reservaram
     * mas ainda não embarcaram vêm à parte, em 'aguardam', porque ocupam
     * lugar sem estar no carro.
     *
     * @return array{
     *     lugares_total: int, lugares_livres: int, a_bordo: int, reservados: int,
     *     passageiros: array<int, array{reserva_id: int, passageiro_id: int, nome: string,
     *         destino: ?string, assento: ?int, lugares: int}>,
     *     aguardam: array<int, array{reserva_id: int, nome: string, estado: string,
     *         destino: ?string, assento: ?int, lugares: int}>
     * }
     */
    public function ocupacao(Veiculo $veiculo): array
    {
        // assentos_veiculo entra por LEFT JOIN: a reserva guarda 'assento_id',
        // mas uma reserva urbana reclamada antes de haver assentos criados
        // (ou uma linha antiga) pode tê-lo a null — o passageiro tem de
        // aparecer na mesma, só sem número de lugar.
        $reservas = Reserva::query()
            ->join('utilizadores as u', 'u.id', '=', 'reservas.passageiro_id')
            ->leftJoin('assentos_veiculo as a', 'a.id', '=', 'reservas.assento_id')
            ->where('reservas.veiculo_id', $veiculo->id)
            ->whereIn('reservas.estado', ['confirmado', 'a_caminho', 'a_bordo'])
            ->orderBy('reservas.criado_em')
            ->get([
                'reservas.id', 'reservas.passageiro_id', 'reservas.estado',
                'reservas.ponto_descida_nome', 'reservas.lugares',
                'u.nome', 'a.numero as assento',
            ]);

        $aBordo = $reservas->where('estado', 'a_bordo')->values();
        $aguardam = $reservas->where('estado', '!=', 'a_bordo')->values();

        // Quem entrou sem app (ocuparSemApp) também vai dentro do carro e
        // ocupa um lugar, mas não tem reserva — entra à parte, sem conta
        // (passageiro_id/reserva_id a null) e sem destino conhecido.
        $semApp = AssentoVeiculo::query()
            ->where('veiculo_id', $veiculo->id)
            ->where('ocupado_sem_app', true)
            ->orderBy('numero')
            ->get(['numero', 'nome_sem_app']);

        $total = (int) $veiculo->lugares_total;
        $livres = (int) $veiculo->lugares_livres;
        $dentro = $aBordo->count() + $semApp->count();

        $detalhe = fn ($r) => [
            'reserva_id' => (int) $r->id,
            'nome' => (string) $r->nome,
            'destino' => $r->ponto_descida_nome !== null ? (string) $r->ponto_descida_nome : null,
            'assento' => $r->assento !== null ? (int) $r->assento : null,
            'lugares' => max(1, (int) $r->lugares),
        ];

        return [
            'lugares_total' => $total,
            'lugares_livres' => $livres,
            'a_bordo' => $dentro,
            // Lugares presos por reservas que ainda não embarcaram. Nunca
            // negativo: um veículo com dados antigos inconsistentes mostra
            // zero reservados em vez de um número impossível.
            'reservados' => max(0, $total - $livres - $dentro),
            'passageiros' => $aBordo->map(fn ($r) => $detalhe($r) + [
                'passageiro_id' => (int) $r->passageiro_id,
            ])->concat($semApp->map(fn ($a) => [
                'reserva_id' => null,
                'nome' => $a->nome_sem_app ?? 'Passageiro sem app',
                'destino' => null,
                'assento' => (int) $a->numero,
                'lugares' => 1,
                'passageiro_id' => null,
                'sem_app' => true,
            ]))->all(),
            'aguardam' => $aguardam->map(fn ($r) => $detalhe($r) + [
                'estado' => (string) $r->estado,
            ])->all(),
        ];
    }

    /**
     * Gate de aprovação + pagamento (secção 11.6): um condutor só vê o mapa e
     * os passageiros se, tendo pelo menos um veículo aprovado, também tiver
     * uma taxa de operação aprovada e ainda válida.
     *
     * Sem veículo aprovado ainda (candidatura em curso) o mapa fica visível —
     * é preciso para registar o primeiro veículo.
     */
    public function condutorPodeVerMapa(int $condutorId): bool
    {
        $temVeiculoAprovado = Veiculo::query()
            ->where('condutor_id', $condutorId)
            ->where('aprovado', true)
            ->exists();

        if (! $temVeiculoAprovado) {
            return true;
        }

        return PagamentoCondutor::query()
            ->where('condutor_id', $condutorId)
            ->validos()
            ->exists();
    }
}
