<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\PacotePagamento;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Models\Veiculo;
use App\Services\AprovacaoPagamentoService;
use App\Support\FicheiroPrivado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagamentos do condutor por pacote (diário/semanal/mensal/anual), com preço
 * e duração definidos pelo Super Admin.
 *
 * O condutor escolhe o pacote e anexa o comprovativo ao solicitar; o admin
 * aprova ou recusa e, ao aprovar, emite o recibo (ver Admin\PagamentoController).
 *
 * A exceção é o pacote de teste — qualquer pacote com preço 0: não há nada a
 * provar, por isso dispensa comprovativo e é aprovado logo, uma única vez por
 * condutor.
 */
class PagamentoController extends Controller
{
    public function __construct(
        private readonly AprovacaoPagamentoService $aprovacao,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $condutorId = $request->user('utilizador')->id;

        $pagamentos = PagamentoCondutor::query()
            ->join('veiculos as v', 'v.id', '=', 'pagamentos_condutores.veiculo_id')
            ->leftJoin('pacotes_pagamento as pac', 'pac.id', '=', 'pagamentos_condutores.pacote_id')
            ->where('pagamentos_condutores.condutor_id', $condutorId)
            ->orderByDesc('pagamentos_condutores.criado_em')
            ->limit(50)
            ->get([
                'pagamentos_condutores.id', 'pagamentos_condutores.veiculo_id',
                'pagamentos_condutores.rota_id', 'pagamentos_condutores.pacote_id',
                'pagamentos_condutores.valor_pago', 'pagamentos_condutores.referencia',
                'pagamentos_condutores.data_pagamento', 'pagamentos_condutores.data_validade',
                'pagamentos_condutores.status', 'pagamentos_condutores.recibo_path',
                'pagamentos_condutores.comprovativo_path', 'pagamentos_condutores.observacao_admin',
                'pagamentos_condutores.criado_em',
                'v.matricula',
                'pac.nome as pacote_nome',
            ]);

        return response()->json([
            'pagamentos' => $pagamentos,
            'pagamento_valido' => PagamentoCondutor::query()
                ->where('condutor_id', $condutorId)
                ->validos()
                ->exists(),
            // O painel mostra o estado do pagamento do veículo escolhido no
            // seletor, e não o do condutor: quem tem dois veículos pode ter um
            // em dia e o outro por pagar. A regra de "válido" fica aqui, com o
            // scope validos(), em vez de o painel a redescobrir por datas.
            'veiculos_validos' => PagamentoCondutor::query()
                ->where('condutor_id', $condutorId)
                ->validos()
                ->distinct()
                ->pluck('veiculo_id'),
            // O painel esconde o comprovativo nos pacotes de teste e desativa-os
            // para quem já usou o seu, com a mesma regra que solicitar() aplica.
            'pacote_teste_usado' => $this->jaUsouPacoteTeste($condutorId),
            'pacotes' => PacotePagamento::query()
                ->where('ativo', true)
                ->orderBy('tipo_servico')
                ->orderBy('duracao_dias')
                ->get(['id', 'nome', 'tipo_servico', 'descricao', 'preco', 'duracao_dias']),
        ]);
    }

    /**
     * Pedido de aprovação de pagamento.
     *
     * Num pacote pago o comprovativo é obrigatório: é o condutor a provar que
     * pagou antes de o pedido poder ser avaliado. Esta é a única via para pedir
     * aprovação, quer se trate da renovação de um condutor já ativo, quer do
     * primeiro pagamento de um condutor novo (que ativa a conta ao ser
     * aprovado — ver AprovacaoPagamentoService).
     *
     * Num pacote de teste (preço 0) o comprovativo é dispensado e o pedido é
     * aprovado logo, mas só uma vez por condutor, seja qual for o veículo.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:solicitar'],
            'veiculo_id' => ['required', 'integer'],
            'pacote_id' => ['required', 'integer'],
            'rota_id' => ['nullable', 'integer'],
            // A obrigatoriedade depende do preço do pacote, que só se conhece
            // depois de o carregar — é verificada mais abaixo.
            'comprovativo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'acao.required' => 'Ação inválida.',
            'acao.in' => 'Ação inválida.',
            'veiculo_id.required' => 'Indique o veículo.',
            'pacote_id.required' => 'Escolha um pacote (diário, semanal, mensal ou anual).',
            'comprovativo.mimes' => 'Ficheiro inválido. Use PDF/JPG/PNG até 5MB.',
            'comprovativo.max' => 'Ficheiro inválido. Use PDF/JPG/PNG até 5MB.',
        ]);

        $condutor = $request->user('utilizador');

        // O condutor só pode pagar por um veículo que lhe pertence e que já
        // foi aprovado: pagar por um veículo ainda em análise não faz sentido.
        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $condutor->id)
            ->first(['id', 'aprovado', 'tipo_servico']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não pertence ao condutor autenticado.'], 403);
        }

        if (! $veiculo->aprovado) {
            return response()->json(['erro' => 'Este veículo ainda não foi aprovado pelo administrador.'], 403);
        }

        $pacote = PacotePagamento::query()
            ->whereKey($dados['pacote_id'])
            ->where('ativo', true)
            ->first(['id', 'preco', 'tipo_servico']);

        if (! $pacote) {
            return response()->json(['erro' => 'Pacote inválido ou já não está disponível.'], 404);
        }

        // O pacote tem de ser do mesmo tipo de serviço do veículo: evita que
        // um condutor urbano pague (e fique com acesso associado a) um pacote
        // pensado para intermunicipal.
        if ($pacote->tipo_servico !== $veiculo->tipo_servico) {
            return response()->json(
                ['erro' => 'O pacote escolhido não corresponde ao tipo de serviço deste veículo.'],
                400
            );
        }

        $jaPendente = PagamentoCondutor::query()
            ->where('condutor_id', $condutor->id)
            ->where('veiculo_id', $veiculo->id)
            ->where('status', 'pendente')
            ->exists();

        if ($jaPendente) {
            return response()->json(['erro' => 'Já existe um pedido de pagamento pendente para este veículo.'], 409);
        }

        if ((float) $pacote->preco <= 0) {
            return $this->ativarPacoteTeste($condutor, $veiculo, $pacote, $dados['rota_id'] ?? null);
        }

        $ficheiro = $request->file('comprovativo');

        if (! $ficheiro) {
            return response()->json(['erro' => 'Anexe o comprovativo de pagamento (imagem ou PDF).'], 422);
        }

        $extensao = strtolower($ficheiro->getClientOriginalExtension());

        $caminho = $ficheiro->storeAs(
            FicheiroPrivado::PASTA_COMPROVATIVOS,
            'pag_'.$condutor->id.'_'.time().'.'.$extensao
        );

        $referencia = $this->novaReferencia();

        PagamentoCondutor::query()->create([
            'condutor_id' => $condutor->id,
            'veiculo_id' => $veiculo->id,
            'rota_id' => $dados['rota_id'] ?? null,
            'pacote_id' => $pacote->id,
            'valor_pago' => $pacote->preco,
            'referencia' => $referencia,
            'comprovativo_path' => $caminho,
            'comprovativo_tipo' => $extensao === 'pdf' ? 'pdf' : 'imagem',
            'status' => 'pendente',
        ]);

        return response()->json([
            'sucesso' => true,
            'referencia' => $referencia,
            'valor' => (float) $pacote->preco,
        ]);
    }

    /**
     * O pacote de teste não tem comprovativo nem espera pelo admin: fica
     * aprovado no mesmo pedido, com recibo, ativação da conta e notificação
     * iguais aos de uma aprovação normal.
     */
    private function ativarPacoteTeste(
        Utilizador $condutor,
        Veiculo $veiculo,
        PacotePagamento $pacote,
        ?int $rotaId
    ): JsonResponse {
        $resultado = DB::transaction(function () use ($condutor, $veiculo, $pacote, $rotaId) {
            // Bloquear a linha do condutor serializa dois pedidos em paralelo
            // (duplo clique, dois separadores): sem isto ambos passavam a
            // verificação abaixo e o condutor ficava com dois testes.
            Utilizador::query()->whereKey($condutor->id)->lockForUpdate()->first(['id']);

            if ($this->jaUsouPacoteTeste($condutor->id)) {
                return null;
            }

            $pagamento = PagamentoCondutor::query()->create([
                'condutor_id' => $condutor->id,
                'veiculo_id' => $veiculo->id,
                'rota_id' => $rotaId,
                'pacote_id' => $pacote->id,
                'valor_pago' => 0,
                'referencia' => $this->novaReferencia(),
                'status' => 'pendente',
            ]);

            $this->aprovacao->aprovar($pagamento, null);

            return $pagamento;
        });

        if (! $resultado) {
            return response()->json(['erro' => 'Já utilizou o seu pacote de teste. Escolha um pacote pago.'], 409);
        }

        return response()->json([
            'sucesso' => true,
            'referencia' => $resultado->referencia,
            'valor' => 0.0,
            'aprovado' => true,
            'data_validade' => $resultado->data_validade,
        ]);
    }

    /**
     * Qualquer pagamento de valor 0 conta, mesmo que um Super Admin o tenha
     * revertido depois: o teste é um só, e revertê-lo não o devolve.
     */
    private function jaUsouPacoteTeste(int $condutorId): bool
    {
        return PagamentoCondutor::query()
            ->where('condutor_id', $condutorId)
            ->where('valor_pago', '<=', 0)
            ->exists();
    }

    private function novaReferencia(): string
    {
        return 'KG'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Download do próprio comprovativo. Os ficheiros vivem fora do document
     * root, por isso só são servidos através deste endpoint autenticado.
     */
    public function comprovativo(Request $request): StreamedResponse|JsonResponse
    {
        $id = $this->idValidado($request);

        $pagamento = PagamentoCondutor::query()
            ->whereKey($id)
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first(['id', 'comprovativo_path']);

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
     * Download do recibo PDF de um pagamento aprovado (secção 11.6).
     */
    public function recibo(Request $request): StreamedResponse|JsonResponse
    {
        $id = $this->idValidado($request);

        $pagamento = PagamentoCondutor::query()
            ->whereKey($id)
            ->where('condutor_id', $request->user('utilizador')->id)
            ->where('status', 'aprovado')
            ->first(['id', 'recibo_path', 'referencia']);

        if (! $pagamento?->recibo_path) {
            return response()->json(['erro' => 'Recibo não encontrado.'], 404);
        }

        return FicheiroPrivado::servir(
            $pagamento->recibo_path,
            'recibo_'.preg_replace('/[^A-Za-z0-9_-]/', '', $pagamento->referencia),
            'Ficheiro do recibo não encontrado.',
            descarregar: true
        );
    }

    private function idValidado(Request $request): int
    {
        return (int) $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.'])['id'];
    }
}
