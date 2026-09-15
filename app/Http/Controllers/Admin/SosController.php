<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlarmeSos;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central de alarmes SOS (secção 11.6).
 */
class SosController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function listar(): JsonResponse
    {
        $alarmes = AlarmeSos::query()
            ->join('utilizadores as u', 'u.id', '=', 'alarmes_sos.utilizador_id')
            ->orderByRaw("(alarmes_sos.estado = 'pendente') DESC")
            ->orderByDesc('alarmes_sos.criado_em')
            ->limit(100)
            ->get([
                'alarmes_sos.id', 'alarmes_sos.tipo_utilizador', 'alarmes_sos.lat',
                'alarmes_sos.lng', 'alarmes_sos.estado', 'alarmes_sos.criado_em',
                'u.nome as utilizador_nome', 'u.telefone',
            ]);

        return response()->json(['alarmes' => $alarmes]);
    }

    public function atualizar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
            'estado' => ['required', 'in:em_curso,resolvido'],
        ], [
            'id.required' => 'Dados inválidos.',
            'estado.required' => 'Dados inválidos.',
            'estado.in' => 'Dados inválidos.',
        ]);

        $admin = $request->user('admin');

        AlarmeSos::query()->whereKey($dados['id'])->update([
            'estado' => $dados['estado'],
            'resolvido_por' => $admin->id,
        ]);

        $this->auditoria->registar(
            $admin->id, 'atualizou_sos', 'alarmes_sos', (int) $dados['id'], $dados['estado']
        );

        return response()->json(['sucesso' => true]);
    }
}
