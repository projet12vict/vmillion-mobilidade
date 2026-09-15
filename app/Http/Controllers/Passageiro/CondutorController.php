<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Models\Utilizador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Condutores com quem o passageiro já viajou — é esta lista que limita o
 * alvo de uma reclamação, evitando queixas sobre condutores com quem nunca
 * houve contacto (secção 10).
 */
class CondutorController extends Controller
{
    public function meusCondutores(Request $request): JsonResponse
    {
        $condutores = Utilizador::query()
            ->join('veiculos as v', 'v.condutor_id', '=', 'utilizadores.id')
            ->join('reservas as r', 'r.veiculo_id', '=', 'v.id')
            ->where('r.passageiro_id', $request->user('utilizador')->id)
            ->whereIn('r.estado', ['confirmado', 'a_caminho', 'a_bordo', 'concluido'])
            ->distinct()
            ->orderBy('utilizadores.nome')
            ->get(['utilizadores.id', 'utilizadores.nome']);

        return response()->json(['condutores' => $condutores]);
    }
}
