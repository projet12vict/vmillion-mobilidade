<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notificações do utilizador autenticado, incluindo alertas do Super Admin
 * (secção 12). As notificações dirigidas a 'admins' nunca aparecem aqui: a
 * coluna destinatario_id refere administradores nesse caso, não utilizadores.
 */
class NotificacaoController extends Controller
{
    public function listar(Request $request): JsonResponse
    {
        $notificacoes = Notificacao::query()
            ->where('destinatario_id', $request->user('utilizador')->id)
            ->where('destinatario_tipo', '!=', 'admins')
            ->orderByDesc('criado_em')
            ->limit(30)
            ->get(['id', 'titulo', 'mensagem', 'tipo', 'lida', 'criado_em']);

        return response()->json([
            'notificacoes' => $notificacoes,
            'nao_lidas' => $notificacoes->where('lida', false)->count(),
        ]);
    }

    public function marcarLida(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        Notificacao::query()
            ->whereKey($dados['id'])
            ->where('destinatario_id', $request->user('utilizador')->id)
            ->where('destinatario_tipo', '!=', 'admins')
            ->update(['lida' => true]);

        return response()->json(['sucesso' => true]);
    }
}
