<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Models\DestinoUrbano;
use App\Models\PontoPartida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PontoController extends Controller
{
    /**
     * Pontos de partida aprovados, para as listas de origem e destino.
     * Pendentes e recusados só são visíveis no painel de administração.
     */
    public function listar(): JsonResponse
    {
        return response()->json([
            'pontos' => PontoPartida::query()
                ->where('status', 'aprovado')
                ->orderBy('nome')
                ->get(['id', 'nome', 'cidade', 'lat', 'lng', 'zona']),
        ]);
    }

    /**
     * Destinos urbanos já gravados por outros passageiros, sugeridos por
     * autocomplete antes de ir à pesquisa externa (Nominatim).
     *
     * Não são pontos públicos como pontos_partida: aparecem só como sugestão
     * de texto, nunca como marcador visível a toda a gente no mapa.
     */
    public function destinosUrbanos(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json(['destinos' => []]);
        }

        return response()->json([
            'destinos' => DestinoUrbano::query()
                ->where('nome', 'like', '%'.$termo.'%')
                ->orderByDesc('usos')
                ->orderBy('nome')
                ->limit(8)
                ->get(['id', 'nome', 'lat', 'lng']),
        ]);
    }
}
