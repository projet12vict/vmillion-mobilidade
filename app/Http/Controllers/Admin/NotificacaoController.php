<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Models\Notificacao;
use App\Models\Utilizador;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notificações do Super Admin para utilizadores e administradores (secção 12).
 *
 * Usa fan-out: uma linha por destinatário, para que o estado lida/não-lida
 * seja individual mesmo num broadcast.
 */
class NotificacaoController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Marcar como lida é para qualquer administrador; enviar é do Super Admin.
     * Como partilham o mesmo endpoint, a distinção é feita aqui.
     */
    public function agir(Request $request): JsonResponse
    {
        $acao = (string) $request->input('acao');

        if ($acao === 'enviar' && ! $request->user('admin')->isSuper()) {
            return response()->json(['erro' => 'Apenas o Super Admin pode enviar notificações.'], 403);
        }

        return match ($acao) {
            'marcar_lida' => $this->marcarLida($request),
            'enviar' => $this->enviar($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    /** Notificações recebidas pelo próprio administrador. */
    public function listar(Request $request): JsonResponse
    {
        $recebidas = Notificacao::query()
            ->where('destinatario_id', $request->user('admin')->id)
            ->where('destinatario_tipo', 'admins')
            ->orderByDesc('criado_em')
            ->limit(30)
            ->get(['id', 'titulo', 'mensagem', 'tipo', 'lida', 'criado_em']);

        return response()->json([
            'recebidas' => $recebidas,
            'nao_lidas' => $recebidas->where('lida', false)->count(),
        ]);
    }

    public function marcarLida(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        Notificacao::query()
            ->whereKey($dados['id'])
            ->where('destinatario_id', $request->user('admin')->id)
            ->where('destinatario_tipo', 'admins')
            ->update(['lida' => true]);

        return response()->json(['sucesso' => true]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'destinatario_tipo' => ['required', 'in:todos,admins,condutores,passageiros,individual'],
            'destinatario_id' => ['nullable', 'integer'],
            'titulo' => ['required', 'string', 'max:255'],
            'mensagem' => ['required', 'string'],
            'tipo' => ['nullable', 'in:alerta,informativo,urgente'],
        ], [
            'destinatario_tipo.required' => 'Preencha o destinatário, o título e a mensagem.',
            'destinatario_tipo.in' => 'Preencha o destinatário, o título e a mensagem.',
            'titulo.required' => 'Preencha o destinatário, o título e a mensagem.',
            'mensagem.required' => 'Preencha o destinatário, o título e a mensagem.',
        ]);

        $admin = $request->user('admin');
        $destinatarioTipo = $dados['destinatario_tipo'];

        if ($destinatarioTipo === 'individual' && empty($dados['destinatario_id'])) {
            return response()->json(['erro' => 'Indique o ID do utilizador (passageiro/condutor).'], 422);
        }

        $alvos = $this->resolverAlvos($destinatarioTipo, $dados['destinatario_id'] ?? null);

        if ($alvos === null) {
            return response()->json(['erro' => 'Utilizador não encontrado.'], 404);
        }

        if ($alvos === []) {
            return response()->json(['erro' => 'Não há destinatários para esta seleção.'], 404);
        }

        $agora = now();
        $tipo = $dados['tipo'] ?? 'informativo';

        DB::transaction(function () use ($alvos, $destinatarioTipo, $admin, $dados, $tipo, $agora) {
            Notificacao::query()->insert(array_map(fn (int $alvoId) => [
                'destinatario_id' => $alvoId,
                'destinatario_tipo' => $destinatarioTipo,
                'remetente_id' => $admin->id,
                'titulo' => trim($dados['titulo']),
                'mensagem' => trim($dados['mensagem']),
                'tipo' => $tipo,
                'lida' => false,
                'criado_em' => $agora,
            ], $alvos));

            $this->auditoria->registar(
                $admin->id, 'enviou_notificacao', 'notificacoes', null,
                "{$destinatarioTipo}: {$dados['titulo']} ({$dados['mensagem']}) para ".count($alvos).' destinatário(s)'
            );
        });

        return response()->json(['sucesso' => true, 'total_destinatarios' => count($alvos)]);
    }

    /**
     * IDs de destino do fan-out. Devolve null quando o destinatário
     * individual indicado não existe.
     *
     * @return array<int, int>|null
     */
    private function resolverAlvos(string $destinatarioTipo, ?int $destinatarioId): ?array
    {
        if ($destinatarioTipo === 'individual') {
            return Utilizador::query()->whereKey($destinatarioId)->exists()
                ? [(int) $destinatarioId]
                : null;
        }

        if ($destinatarioTipo === 'admins') {
            return Administrador::query()->where('ativo', true)->pluck('id')->all();
        }

        return Utilizador::query()
            ->when($destinatarioTipo === 'condutores', fn ($q) => $q->where('tipo', 'condutor'))
            ->when($destinatarioTipo === 'passageiros', fn ($q) => $q->where('tipo', 'passageiro'))
            ->pluck('id')
            ->all();
    }
}
