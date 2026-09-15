<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Models\AlarmeSos;
use App\Models\Fatura;
use App\Models\LogAuditoria;
use App\Models\PagamentoCondutor;
use App\Models\Parque;
use App\Models\PontoPartida;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Http\JsonResponse;

/**
 * Estatísticas do painel de administração (secção 11.2).
 */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'passageiros' => Utilizador::query()->where('tipo', 'passageiro')->count(),
                'condutores' => Utilizador::query()->where('tipo', 'condutor')->where('status', 'ativo')->count(),
                'condutores_pendentes' => Utilizador::query()->where('tipo', 'condutor')->where('status', 'pendente')->count(),
                'veiculos' => Veiculo::query()->where('aprovado', true)->count(),
                'veiculos_pendentes' => Veiculo::query()->where('aprovado', false)->count(),
                'pontos' => PontoPartida::query()->where('status', 'aprovado')->count(),
                'pontos_pendentes' => PontoPartida::query()->where('status', 'pendente')->count(),
                'parques' => Parque::query()->count(),
                'pagamentos_pendentes' => PagamentoCondutor::query()->where('status', 'pendente')->count(),
                'faturas_pendentes' => Fatura::query()->where('estado', 'pendente')->count(),
                'faturas_vencidas' => Fatura::query()->where('estado', 'vencida')->count(),
                'administradores_ativos' => Administrador::query()->where('ativo', true)->count(),
                'sos_pendentes' => AlarmeSos::query()->where('estado', 'pendente')->count(),
            ],
            'ultimas_acoes' => LogAuditoria::query()
                ->leftJoin('administradores as a', 'a.id', '=', 'logs_auditoria.admin_id')
                ->orderByDesc('logs_auditoria.criado_em')
                ->limit(15)
                ->get([
                    'logs_auditoria.acao', 'logs_auditoria.entidade',
                    'logs_auditoria.entidade_id', 'logs_auditoria.criado_em',
                    'a.nome as admin_nome',
                ]),
        ]);
    }
}
