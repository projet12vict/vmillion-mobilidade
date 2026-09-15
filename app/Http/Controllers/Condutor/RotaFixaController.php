<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\PrecoRota;
use Illuminate\Http\JsonResponse;

/**
 * Rotas fixas disponíveis para o condutor associar ao veículo (secção 3).
 * Apenas leitura: a definição de rotas é do Super Admin.
 */
class RotaFixaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rotas = PrecoRota::query()
            ->join('pontos_partida as po', 'po.id', '=', 'precos_rotas.ponto_origem_id')
            ->join('pontos_partida as pd', 'pd.id', '=', 'precos_rotas.ponto_destino_id')
            ->orderBy('po.nome')
            ->get([
                'precos_rotas.id',
                'precos_rotas.preco_fixo_cve',
                'precos_rotas.distancia_km',
                'po.nome as origem_nome',
                'pd.nome as destino_nome',
            ]);

        return response()->json(['rotas' => $rotas]);
    }
}
