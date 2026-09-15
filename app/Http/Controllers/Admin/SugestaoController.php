<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sugestao;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sugestões e reclamações (secção 10). As sugestões só são visíveis ao Super
 * Admin; as reclamações são visíveis a qualquer administrador.
 */
class SugestaoController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function agir(Request $request): JsonResponse
    {
        return match ((string) $request->input('acao')) {
            'atualizar_status' => $this->atualizarStatus($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    public function listar(Request $request): JsonResponse
    {
        $tiposPermitidos = $this->tiposPermitidos($request);

        $sugestoes = Sugestao::query()
            ->join('utilizadores as u', 'u.id', '=', 'sugestoes.utilizador_id')
            ->leftJoin('utilizadores as c', 'c.id', '=', 'sugestoes.condutor_id')
            ->whereIn('sugestoes.tipo', $tiposPermitidos)
            ->orderByRaw("(sugestoes.status = 'pendente') DESC")
            ->orderByDesc('sugestoes.criado_em')
            ->limit(200)
            ->get([
                'sugestoes.id', 'sugestoes.tipo', 'sugestoes.titulo', 'sugestoes.descricao',
                'sugestoes.status', 'sugestoes.criado_em',
                'u.nome as utilizador_nome', 'u.tipo as utilizador_tipo', 'u.telefone as utilizador_telefone',
                'c.nome as condutor_nome', 'c.telefone as condutor_telefone',
            ]);

        return response()->json(['sugestoes' => $sugestoes]);
    }

    public function atualizarStatus(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['required', 'in:pendente,visto,implementado,resolvido'],
        ], [
            'id.required' => 'Dados inválidos.',
            'status.required' => 'Dados inválidos.',
            'status.in' => 'Dados inválidos.',
        ]);

        $sugestao = Sugestao::query()->find($dados['id'], ['id', 'tipo']);

        if (! $sugestao) {
            return response()->json(['erro' => 'Registo não encontrado.'], 404);
        }

        // Um admin normal só gere reclamações — nem sequer vê as sugestões.
        if ($sugestao->tipo === 'sugestao' && ! $request->user('admin')->isSuper()) {
            return response()->json(['erro' => 'Apenas o Super Admin gere sugestões.'], 403);
        }

        $sugestao->forceFill(['status' => $dados['status']])->save();

        $this->auditoria->registar(
            $request->user('admin')->id, 'atualizou_sugestao', 'sugestoes', $sugestao->id, $dados['status']
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * @return array<int, string>
     */
    private function tiposPermitidos(Request $request): array
    {
        return $request->user('admin')->isSuper()
            ? ['sugestao', 'reclamacao']
            : ['reclamacao'];
    }
}
