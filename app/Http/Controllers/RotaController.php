<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GeoService;
use App\Services\OsrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Rota real por estrada entre dois pontos (mapa do passageiro e do condutor).
 *
 * Proxy para a instância própria do OSRM, que só é alcançável a partir do
 * servidor. Se o OSRM falhar devolve sucesso=false — o frontend
 * (KGMap.tracarRota) cai então para a linha reta entre origem e destino, e
 * por isso isto nunca é um erro fatal para o utilizador.
 */
class RotaController extends Controller
{
    public function __construct(
        private readonly OsrmService $osrm,
        private readonly GeoService $geo,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'origem_lat' => ['required', 'numeric'],
            'origem_lng' => ['required', 'numeric'],
            'destino_lat' => ['required', 'numeric'],
            'destino_lng' => ['required', 'numeric'],
        ]);

        $origemLat = (float) $dados['origem_lat'];
        $origemLng = (float) $dados['origem_lng'];
        $destinoLat = (float) $dados['destino_lat'];
        $destinoLng = (float) $dados['destino_lng'];

        if (! $this->geo->coordenadasValidas($origemLat, $origemLng)
            || ! $this->geo->coordenadasValidas($destinoLat, $destinoLng)) {
            return response()->json(
                ['sucesso' => false, 'erro' => 'Coordenadas fora dos limites de Cabo Verde.'],
                422
            );
        }

        try {
            $rota = $this->osrm->calcularRotaEmCache($origemLat, $origemLng, $destinoLat, $destinoLng);
        } catch (Throwable) {
            return response()->json(['sucesso' => false, 'erro' => 'Motor de rotas indisponível.']);
        }

        return response()->json([
            'sucesso' => true,
            'distancia_m' => $rota['distancia_m'],
            'duracao_s' => $rota['duracao_s'],
            'geometria' => $rota['geometria'],
        ]);
    }
}
