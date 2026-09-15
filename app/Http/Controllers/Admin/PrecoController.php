<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigPreco;
use App\Models\PacotePagamento;
use App\Models\PagamentoCondutor;
use App\Models\PontoPartida;
use App\Models\PrecoKm;
use App\Models\PrecoRota;
use App\Services\AuditoriaService;
use App\Services\GeoService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tarifários e pacotes (secções 11.5 e 12.1). Todas estas rotas são
 * exclusivas do Super Admin.
 */
class PrecoController extends Controller
{
    public function __construct(
        private readonly PricingService $precos,
        private readonly GeoService $geo,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function agir(Request $request): JsonResponse
    {
        return match ((string) $request->input('acao')) {
            'atualizar_km' => $this->atualizarKm($request),
            'definir_rota' => $this->definirRota($request),
            'eliminar_rota' => $this->eliminarRota($request),
            'atualizar_config' => $this->atualizarConfig($request),
            'guardar_pacote' => $this->guardarPacote($request),
            'remover_pacote' => $this->removerPacote($request),
            'eliminar_pacote' => $this->eliminarPacote($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    public function listar(): JsonResponse
    {
        return response()->json([
            'precos_km' => PrecoKm::query()->get(),
            'precos_rotas' => PrecoRota::query()
                ->join('pontos_partida as po', 'po.id', '=', 'precos_rotas.ponto_origem_id')
                ->join('pontos_partida as pd', 'pd.id', '=', 'precos_rotas.ponto_destino_id')
                ->orderBy('po.nome')
                ->get([
                    'precos_rotas.id', 'precos_rotas.preco_fixo_cve', 'precos_rotas.distancia_km',
                    'precos_rotas.ponto_origem_id', 'precos_rotas.ponto_destino_id',
                    'po.nome as origem_nome', 'pd.nome as destino_nome',
                ]),
            'config' => $this->precos->configPrecos(),
            'pacotes' => PacotePagamento::query()
                ->orderBy('tipo_servico')
                ->orderBy('duracao_dias')
                ->get(),
        ]);
    }

    public function atualizarKm(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'zona' => ['required', 'in:urbana,intermunicipal'],
            'preco_por_km_cve' => ['required', 'numeric', 'min:0'],
        ], [
            'zona.required' => 'Zona ou preço inválido.',
            'zona.in' => 'Zona ou preço inválido.',
            'preco_por_km_cve.required' => 'Zona ou preço inválido.',
            'preco_por_km_cve.min' => 'Zona ou preço inválido.',
        ]);

        $admin = $request->user('admin');

        PrecoKm::query()->updateOrCreate(
            ['zona' => $dados['zona']],
            ['preco_por_km_cve' => (float) $dados['preco_por_km_cve'], 'atualizado_por' => $admin->id]
        );

        $this->auditoria->registar(
            $admin->id, 'atualizou_preco_km', 'precos_km', null,
            "{$dados['zona']}: {$dados['preco_por_km_cve']} CVE/km"
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Define (ou atualiza) o preço fixo de uma rota. A distância é calculada
     * a partir das coordenadas dos dois pontos: é ela que permite fracionar
     * o preço em paragens intermédias.
     */
    public function definirRota(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ponto_origem_id' => ['required', 'integer'],
            'ponto_destino_id' => ['required', 'integer', 'different:ponto_origem_id'],
            'preco_fixo_cve' => ['required', 'numeric', 'min:0'],
        ], [
            'ponto_origem_id.required' => 'Dados de rota inválidos.',
            'ponto_destino_id.required' => 'Dados de rota inválidos.',
            'preco_fixo_cve.required' => 'Dados de rota inválidos.',
            'preco_fixo_cve.min' => 'Dados de rota inválidos.',
            'ponto_destino_id.different' => 'Origem e destino não podem ser o mesmo ponto.',
        ]);

        $pontos = PontoPartida::query()
            ->whereIn('id', [$dados['ponto_origem_id'], $dados['ponto_destino_id']])
            ->get(['id', 'lat', 'lng'])
            ->keyBy('id');

        $origem = $pontos->get((int) $dados['ponto_origem_id']);
        $destino = $pontos->get((int) $dados['ponto_destino_id']);

        if (! $origem || ! $destino) {
            return response()->json(['erro' => 'Ponto de origem ou destino não encontrado.'], 404);
        }

        $distanciaKm = round($this->geo->distanciaKm($origem->lat, $origem->lng, $destino->lat, $destino->lng), 2);
        $admin = $request->user('admin');

        PrecoRota::query()->updateOrCreate(
            [
                'ponto_origem_id' => $origem->id,
                'ponto_destino_id' => $destino->id,
            ],
            [
                'preco_fixo_cve' => (float) $dados['preco_fixo_cve'],
                'distancia_km' => $distanciaKm,
                'definido_por' => $admin->id,
            ]
        );

        $this->auditoria->registar(
            $admin->id, 'definiu_preco_rota', 'precos_rotas', null,
            "{$origem->id}->{$destino->id}: {$dados['preco_fixo_cve']} CVE ({$distanciaKm} km)"
        );

        return response()->json(['sucesso' => true, 'distancia_km' => $distanciaKm]);
    }

    public function eliminarRota(Request $request): JsonResponse
    {
        $dados = $this->validarId($request);

        PrecoRota::query()->whereKey($dados['id'])->delete();

        $this->auditoria->registar(
            $request->user('admin')->id, 'eliminou_preco_rota', 'precos_rotas', (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    public function atualizarConfig(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chave' => ['required', 'in:valor_minimo,valor_maximo,taxa_operacao_rota'],
            'valor' => ['required', 'numeric', 'min:0'],
        ], [
            'chave.required' => 'Configuração ou valor inválido.',
            'chave.in' => 'Configuração ou valor inválido.',
            'valor.required' => 'Configuração ou valor inválido.',
            'valor.min' => 'Configuração ou valor inválido.',
        ]);

        $admin = $request->user('admin');

        ConfigPreco::query()->updateOrCreate(
            ['chave' => $dados['chave']],
            ['valor' => (float) $dados['valor'], 'atualizado_por' => $admin->id]
        );

        $this->auditoria->registar(
            $admin->id, 'atualizou_config_precos', 'config_precos', null,
            "{$dados['chave']}: {$dados['valor']}"
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Cria ou atualiza um pacote de pagamento.
     *
     * Cada pacote pertence a um tipo de serviço, e o condutor só pode pagar
     * pacotes do mesmo tipo do seu veículo — por isso tipo_servico é sempre
     * obrigatório. O nome, junto com o tipo de serviço, identifica o pacote:
     * ao editar sem o enviar (guardar rápido na tabela) mantém-se o atual.
     */
    public function guardarPacote(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'nome' => ['nullable', 'string', 'max:20'],
            'tipo_servico' => ['required', 'in:urbano,intermunicipal,ambos'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'preco' => ['required', 'numeric', 'min:0'],
            'duracao_dias' => ['required', 'integer', 'min:1'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'tipo_servico.required' => 'Preço, duração ou tipo de serviço inválidos.',
            'tipo_servico.in' => 'Preço, duração ou tipo de serviço inválidos.',
            'preco.required' => 'Preço, duração ou tipo de serviço inválidos.',
            'preco.min' => 'Preço, duração ou tipo de serviço inválidos.',
            'duracao_dias.required' => 'Preço, duração ou tipo de serviço inválidos.',
            'duracao_dias.min' => 'Preço, duração ou tipo de serviço inválidos.',
        ]);

        $admin = $request->user('admin');
        $id = $dados['id'] ?? null;
        $descricao = trim((string) ($dados['descricao'] ?? '')) ?: null;
        $ativo = ! array_key_exists('ativo', $dados) || (bool) $dados['ativo'];

        if ($id) {
            $pacote = PacotePagamento::query()->find($id);
            if (! $pacote) {
                return response()->json(['erro' => 'Pacote não encontrado.'], 404);
            }

            $nome = trim((string) ($dados['nome'] ?? '')) ?: $pacote->nome;

            if ($this->pacoteDuplicado($dados['tipo_servico'], $nome, $pacote->id)) {
                return response()->json(
                    ['erro' => 'Já existe um pacote com este nome para este tipo de serviço.'],
                    409
                );
            }

            $pacote->forceFill([
                'nome' => $nome,
                'tipo_servico' => $dados['tipo_servico'],
                'preco' => (float) $dados['preco'],
                'duracao_dias' => (int) $dados['duracao_dias'],
                'descricao' => $descricao,
                'ativo' => $ativo,
            ])->save();

            $this->auditoria->registar(
                $admin->id, 'atualizou_pacote_pagamento', 'pacotes_pagamento', $pacote->id,
                "{$nome} ({$dados['tipo_servico']}): {$dados['preco']} CVE / {$dados['duracao_dias']}d"
            );

            return response()->json(['sucesso' => true, 'id' => $pacote->id]);
        }

        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            return response()->json(['erro' => 'Indique um nome para o pacote (até 20 caracteres).'], 422);
        }

        if ($this->pacoteDuplicado($dados['tipo_servico'], $nome)) {
            return response()->json(['erro' => 'Já existe um pacote com este nome para este tipo de serviço.'], 409);
        }

        $pacote = PacotePagamento::query()->create([
            'nome' => $nome,
            'tipo_servico' => $dados['tipo_servico'],
            'descricao' => $descricao,
            'preco' => (float) $dados['preco'],
            'duracao_dias' => (int) $dados['duracao_dias'],
            'ativo' => $ativo,
            'criado_por' => $admin->id,
        ]);

        $this->auditoria->registar(
            $admin->id, 'criou_pacote_pagamento', 'pacotes_pagamento', $pacote->id,
            "{$nome} ({$dados['tipo_servico']}): {$dados['preco']} CVE / {$dados['duracao_dias']}d"
        );

        return response()->json(['sucesso' => true, 'id' => $pacote->id]);
    }

    /**
     * Desativa: nunca apaga a linha, porque pagamentos já feitos referenciam
     * pacote_id. O pacote apenas deixa de aparecer como opção em novos
     * pedidos. Para apagar mesmo, ver eliminarPacote().
     */
    public function removerPacote(Request $request): JsonResponse
    {
        $dados = $this->validarId($request);

        PacotePagamento::query()->whereKey($dados['id'])->update(['ativo' => false]);

        $this->auditoria->registar(
            $request->user('admin')->id, 'desativou_pacote_pagamento', 'pacotes_pagamento', (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Apaga o pacote de vez — só enquanto nenhum pagamento o referenciar. A
     * chave estrangeira é nullOnDelete, por isso eliminar um pacote já usado
     * deixava os recibos antigos sem pacote nenhum; nesse caso o caminho é
     * desativá-lo, que o tira das opções sem mexer no histórico.
     */
    public function eliminarPacote(Request $request): JsonResponse
    {
        $dados = $this->validarId($request);

        $pacote = PacotePagamento::query()->find($dados['id']);

        if (! $pacote) {
            return response()->json(['erro' => 'Pacote não encontrado.'], 404);
        }

        if (PagamentoCondutor::query()->where('pacote_id', $pacote->id)->exists()) {
            return response()->json(
                ['erro' => 'Este pacote já foi usado em pagamentos — desative-o em vez de o eliminar.'],
                409
            );
        }

        $pacote->delete();

        $this->auditoria->registar(
            $request->user('admin')->id, 'eliminou_pacote_pagamento', 'pacotes_pagamento', $pacote->id,
            "{$pacote->nome} ({$pacote->tipo_servico})"
        );

        return response()->json(['sucesso' => true]);
    }

    private function pacoteDuplicado(string $tipoServico, string $nome, ?int $excetoId = null): bool
    {
        return PacotePagamento::query()
            ->where('tipo_servico', $tipoServico)
            ->where('nome', $nome)
            ->when($excetoId, fn ($q) => $q->whereKeyNot($excetoId))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validarId(Request $request): array
    {
        return $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);
    }
}
