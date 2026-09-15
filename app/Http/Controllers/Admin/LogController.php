<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Logs de auditoria com filtros (secção 11.8).
 */
class LogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'admin_id' => ['nullable', 'integer'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $logs = LogAuditoria::query()
            ->leftJoin('administradores as a', 'a.id', '=', 'logs_auditoria.admin_id')
            ->when($dados['admin_id'] ?? null, fn ($q, $adminId) => $q->where('logs_auditoria.admin_id', $adminId))
            ->when($dados['data_inicio'] ?? null,
                fn ($q, $inicio) => $q->where('logs_auditoria.criado_em', '>=', $inicio.' 00:00:00'))
            ->when($dados['data_fim'] ?? null,
                fn ($q, $fim) => $q->where('logs_auditoria.criado_em', '<=', $fim.' 23:59:59'))
            ->orderByDesc('logs_auditoria.criado_em')
            ->limit(300)
            ->get([
                'logs_auditoria.id', 'logs_auditoria.acao', 'logs_auditoria.entidade',
                'logs_auditoria.entidade_id', 'logs_auditoria.detalhes', 'logs_auditoria.ip',
                'logs_auditoria.criado_em',
                'a.nome as admin_nome',
            ]);

        return response()->json(['logs' => $logs]);
    }
}
