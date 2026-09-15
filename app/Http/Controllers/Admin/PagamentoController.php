<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Models\Notificacao;
use App\Models\PagamentoCondutor;
use App\Services\AprovacaoPagamentoService;
use App\Services\AuditoriaService;
use App\Support\FicheiroPrivado;
use App\Support\IntervaloPeriodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aprovação dos pagamentos da taxa de operação (secção 11.6). Ao aprovar,
 * emite o recibo em PDF e abre o acesso do condutor pela duração do pacote.
 */
class PagamentoController extends Controller
{
    /**
     * O painel nunca desenha mais do que isto de uma vez. Como nas listas de
     * utilizadores e de veículos, o filtro corre no servidor: os pagamentos
     * acumulam-se a cada renovação de pacote e passam depressa deste limite —
     * filtrar no browser só o que já veio esconderia pedidos sem aviso.
     */
    private const LIMITE = 200;

    public function __construct(
        private readonly AprovacaoPagamentoService $aprovacao,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * aprovado_por_nome permite ao Super Admin fiscalizar pagamentos
     * aprovados por outros administradores — a lista não é filtrada por quem
     * os processou.
     */
    public function listar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:pendente,aprovado,recusado'],
            // Um id de pacote, ou "sem" para os pagamentos anteriores aos
            // pacotes (taxa fixa por rota), que não têm pacote nenhum.
            'pacote' => ['nullable', 'string', 'regex:/^(sem|\d+)$/'],
            'tipo_servico' => ['nullable', 'in:urbano,intermunicipal,ambos'],
            'comprovativo' => ['nullable', 'in:com,sem,imagem,pdf'],
            'validade' => ['nullable', 'in:valido,expirado,sem'],
            'valor_min' => ['nullable', 'numeric', 'min:0'],
            'valor_max' => ['nullable', 'numeric', 'min:0'],
            'periodo' => ['nullable', 'in:hoje,ontem,semana,mes,ano,7dias,30dias,personalizado'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'ordem' => ['nullable', 'in:pendentes,recentes,antigos,valor,valor_desc,condutor,validade'],
        ], [
            'periodo.in' => 'Período inválido.',
            'status.in' => 'Estado inválido.',
        ]);

        [$inicio, $fim] = IntervaloPeriodo::paraDatas(
            $dados['periodo'] ?? null,
            $dados['data_inicio'] ?? null,
            $dados['data_fim'] ?? null,
        );

        $texto = trim((string) ($dados['q'] ?? ''));
        $pacote = $dados['pacote'] ?? '';
        $comprovativo = $dados['comprovativo'] ?? null;

        $filtrados = PagamentoCondutor::query()
            ->join('utilizadores as u', 'u.id', '=', 'pagamentos_condutores.condutor_id')
            ->join('veiculos as v', 'v.id', '=', 'pagamentos_condutores.veiculo_id')
            ->leftJoin('precos_rotas as pr', 'pr.id', '=', 'pagamentos_condutores.rota_id')
            ->leftJoin('pontos_partida as po', 'po.id', '=', 'pr.ponto_origem_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'pr.ponto_destino_id')
            ->leftJoin('administradores as a', 'a.id', '=', 'pagamentos_condutores.aprovado_por')
            ->leftJoin('pacotes_pagamento as pac', 'pac.id', '=', 'pagamentos_condutores.pacote_id')
            ->when($dados['status'] ?? null, fn ($q, $status) => $q->where('pagamentos_condutores.status', $status))
            // "sem" são os pagamentos anteriores aos pacotes (taxa fixa por
            // rota), que continuam na lista e não pertencem a pacote nenhum.
            ->when($pacote === 'sem', fn ($q) => $q->whereNull('pagamentos_condutores.pacote_id'))
            ->when($pacote !== '' && $pacote !== 'sem', fn ($q) => $q->where('pagamentos_condutores.pacote_id', (int) $pacote))
            ->when($dados['tipo_servico'] ?? null, fn ($q, $servico) => $q->where('pac.tipo_servico', $servico))
            ->when($comprovativo === 'com', fn ($q) => $q->whereNotNull('pagamentos_condutores.comprovativo_path'))
            ->when($comprovativo === 'sem', fn ($q) => $q->whereNull('pagamentos_condutores.comprovativo_path'))
            ->when(in_array($comprovativo, ['imagem', 'pdf'], true), fn ($q) => $q
                ->whereNotNull('pagamentos_condutores.comprovativo_path')
                ->where('pagamentos_condutores.comprovativo_tipo', $comprovativo))
            // "Válido" é o que abre o mapa ao condutor (scopeValidos): estar
            // aprovado não chega se a validade já passou.
            ->when(($dados['validade'] ?? null) === 'valido', fn ($q) => $q
                ->where('pagamentos_condutores.status', 'aprovado')
                ->where('pagamentos_condutores.data_validade', '>=', now()))
            ->when(($dados['validade'] ?? null) === 'expirado', fn ($q) => $q
                ->whereNotNull('pagamentos_condutores.data_validade')
                ->where('pagamentos_condutores.data_validade', '<', now()))
            ->when(($dados['validade'] ?? null) === 'sem', fn ($q) => $q->whereNull('pagamentos_condutores.data_validade'))
            ->when(isset($dados['valor_min']), fn ($q) => $q->where('pagamentos_condutores.valor_pago', '>=', (float) $dados['valor_min']))
            ->when(isset($dados['valor_max']), fn ($q) => $q->where('pagamentos_condutores.valor_pago', '<=', (float) $dados['valor_max']))
            ->when($texto !== '', fn ($q) => $q->where(function ($q) use ($texto) {
                // O admin chega com o que tem à mão: o nome ou o telefone do
                // condutor, a matrícula do carro, a referência que vem no
                // comprovativo ou o nome do pacote pago.
                $like = '%'.addcslashes($texto, '%_\\').'%';
                $q->where('u.nome', 'like', $like)
                    ->orWhere('u.telefone', 'like', $like)
                    ->orWhere('v.matricula', 'like', $like)
                    ->orWhere('pagamentos_condutores.referencia', 'like', $like)
                    ->orWhere('pac.nome', 'like', $like);
            }))
            ->when($inicio, fn ($q, $inicio) => $q->where('pagamentos_condutores.criado_em', '>=', $inicio))
            ->when($fim, fn ($q, $fim) => $q->where('pagamentos_condutores.criado_em', '<=', $fim));

        // Quantos correspondem ao filtro antes do corte — sem isto o painel
        // diria "200 pagamentos" tanto para 200 como para 4000.
        $total = (clone $filtrados)->count();

        // criado_em tem precisão de segundo: dois pedidos submetidos no mesmo
        // segundo precisam do id para a ordem ser estável entre carregamentos.
        $ordenados = match ($dados['ordem'] ?? null) {
            'recentes' => $filtrados->orderByDesc('pagamentos_condutores.criado_em')->orderByDesc('pagamentos_condutores.id'),
            'antigos' => $filtrados->orderBy('pagamentos_condutores.criado_em')->orderBy('pagamentos_condutores.id'),
            'valor' => $filtrados->orderBy('pagamentos_condutores.valor_pago')->orderBy('pagamentos_condutores.id'),
            'valor_desc' => $filtrados->orderByDesc('pagamentos_condutores.valor_pago')->orderByDesc('pagamentos_condutores.id'),
            'condutor' => $filtrados->orderBy('u.nome')->orderBy('pagamentos_condutores.id'),
            'validade' => $filtrados->orderByDesc('pagamentos_condutores.data_validade')->orderByDesc('pagamentos_condutores.id'),
            // O que espera decisão aparece primeiro, como sempre apareceu.
            default => $filtrados
                ->orderByRaw("(pagamentos_condutores.status = 'pendente') DESC")
                ->orderByDesc('pagamentos_condutores.criado_em')
                ->orderByDesc('pagamentos_condutores.id'),
        };

        $pagamentos = $ordenados
            ->limit(self::LIMITE)
            ->get([
                'pagamentos_condutores.*',
                'u.nome as condutor_nome', 'u.telefone as condutor_telefone', 'u.status as condutor_status',
                'v.matricula',
                'po.nome as origem_nome', 'pd.nome as destino_nome',
                'a.nome as aprovado_por_nome',
                'pac.nome as pacote_nome',
                'pac.duracao_dias as pacote_duracao_dias',
                'pac.tipo_servico as pacote_tipo_servico',
            ]);

        return response()->json([
            'pagamentos' => $pagamentos,
            'total' => $total,
            'limite' => self::LIMITE,
            'pacotes' => $this->pacotesComPagamentos(),
        ]);
    }

    /**
     * Opções do filtro "Pacote". Sai dos pagamentos já feitos, e não da
     * tabela de pacotes: a lista de pacotes só o Super Admin a pode ler, e um
     * pacote que ninguém pagou seria uma opção que nunca devolve nada. Não
     * depende do filtro escolhido, para as opções não irem desaparecendo à
     * medida que o admin restringe a lista.
     *
     * @return Collection<int, object>
     */
    private function pacotesComPagamentos(): Collection
    {
        return DB::table('pagamentos_condutores as p')
            ->join('pacotes_pagamento as pac', 'pac.id', '=', 'p.pacote_id')
            ->distinct()
            ->orderBy('pac.nome')
            ->get(['pac.id', 'pac.nome', 'pac.duracao_dias', 'pac.tipo_servico']);
    }

    public function agir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:aprovar,recusar,reverter'],
            'id' => ['required', 'integer'],
            'observacao' => ['nullable', 'string'],
        ], [
            'acao.required' => 'Ação inválida.',
            'acao.in' => 'Ação inválida.',
            'id.required' => 'ID inválido.',
        ]);

        $admin = $request->user('admin');

        // Reverter uma aprovação (irregularidade encontrada depois) é mais
        // sensível do que decidir sobre um pedido pendente: fica restrito ao
        // Super Admin, tal como suspender um condutor.
        if ($dados['acao'] === 'reverter' && ! $admin->isSuper()) {
            return response()->json(['erro' => 'Apenas o Super Admin pode executar esta ação.'], 403);
        }

        $observacao = trim((string) ($dados['observacao'] ?? '')) ?: null;

        try {
            $resultado = DB::transaction(function () use ($dados, $admin, $observacao) {
                $pagamento = PagamentoCondutor::query()->lockForUpdate()->find($dados['id']);

                if (! $pagamento) {
                    throw new RuntimeException('Pagamento não encontrado.', 404);
                }

                return match ($dados['acao']) {
                    'reverter' => $this->reverter($pagamento, $admin),
                    'recusar' => $this->recusar($pagamento, $admin, $observacao),
                    'aprovar' => $this->aprovar($pagamento, $admin, $observacao),
                };
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json(['sucesso' => true] + $resultado);
    }

    /**
     * Download do recibo PDF (vista do admin). Os ficheiros vivem fora do
     * document root, por isso só são servidos por este endpoint autenticado.
     */
    /**
     * Por omissão o recibo é servido inline: o painel abre-o num separador
     * novo e o admin lê-o no browser, como já acontecia com o comprovativo —
     * antes era sempre "attachment", e o botão "Ver recibo" descarregava um
     * ficheiro em vez de o mostrar. Guardar o PDF continua a ser possível com
     * ?descarregar=1, que é o botão "Descarregar" ao lado.
     */
    public function recibo(Request $request): StreamedResponse|JsonResponse
    {
        $id = $this->idValidado($request);

        $pagamento = PagamentoCondutor::query()
            ->whereKey($id)
            ->where('status', 'aprovado')
            ->first(['id', 'recibo_path', 'referencia']);

        if (! $pagamento?->recibo_path) {
            return response()->json(['erro' => 'Recibo não encontrado.'], 404);
        }

        return FicheiroPrivado::servir(
            $pagamento->recibo_path,
            'recibo_'.preg_replace('/[^A-Za-z0-9_-]/', '', $pagamento->referencia),
            'Ficheiro do recibo não encontrado.',
            descarregar: $request->boolean('descarregar')
        );
    }

    public function comprovativo(Request $request): StreamedResponse|JsonResponse
    {
        $pagamento = PagamentoCondutor::query()
            ->find($this->idValidado($request), ['id', 'comprovativo_path']);

        if (! $pagamento?->comprovativo_path) {
            return response()->json(['erro' => 'Comprovativo não encontrado.'], 404);
        }

        return FicheiroPrivado::servir(
            $pagamento->comprovativo_path,
            'comprovativo_'.$pagamento->id,
            'Ficheiro do comprovativo não encontrado.'
        );
    }

    /**
     * Invalida já a validade (em vez de só mudar o status): o gate do mapa do
     * condutor verifica data_validade >= agora, por isso o efeito é imediato
     * no próximo pedido dele, sem mais nenhuma lógica.
     *
     * @return array<string, mixed>
     */
    private function reverter(PagamentoCondutor $pagamento, Administrador $admin): array
    {
        if ($pagamento->status !== 'aprovado') {
            throw new RuntimeException('Só é possível reverter um pagamento aprovado.', 409);
        }

        $pagamento->forceFill([
            'status' => 'pendente',
            'data_validade' => now()->subSecond(),
        ])->save();

        $this->auditoria->registar(
            $admin->id, 'reverteu_pagamento_condutor', 'pagamentos_condutores',
            $pagamento->id, $pagamento->referencia
        );

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function recusar(PagamentoCondutor $pagamento, Administrador $admin, ?string $observacao): array
    {
        $this->exigirPendente($pagamento);

        $pagamento->forceFill([
            'status' => 'recusado',
            'aprovado_por' => $admin->id,
            'aprovado_em' => now(),
            'observacao_admin' => $observacao,
        ])->save();

        $this->auditoria->registar(
            $admin->id, 'recusou_pagamento_condutor', 'pagamentos_condutores', $pagamento->id
        );

        $this->notificarCondutor(
            $pagamento->condutor_id,
            $admin->id,
            'Pagamento recusado',
            "O seu comprovativo (ref. {$pagamento->referencia}) foi recusado."
                .($observacao ? " Motivo: {$observacao}" : ' Verifique o documento e volte a enviar.'),
            'alerta'
        );

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function aprovar(PagamentoCondutor $pagamento, Administrador $admin, ?string $observacao): array
    {
        $this->exigirPendente($pagamento);

        return ['recibo_path' => $this->aprovacao->aprovar($pagamento, $admin, $observacao)];
    }

    private function exigirPendente(PagamentoCondutor $pagamento): void
    {
        if ($pagamento->status !== 'pendente') {
            throw new RuntimeException('Este pagamento já foi processado.', 409);
        }
    }

    private function notificarCondutor(
        int $condutorId,
        int $adminId,
        string $titulo,
        string $mensagem,
        string $tipo
    ): void {
        Notificacao::query()->create([
            'destinatario_id' => $condutorId,
            'destinatario_tipo' => 'individual',
            'remetente_id' => $adminId,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'tipo' => $tipo,
        ]);
    }

    private function idValidado(Request $request): int
    {
        return (int) $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.'])['id'];
    }
}
