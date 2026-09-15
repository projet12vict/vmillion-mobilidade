<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AlarmeSos;
use App\Rules\CoordenadaCaboVerde;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alarme SOS (secção 10.5), disponível a passageiros e condutores.
 */
class SosController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
        ], [
            'lat.required' => 'Localização em falta.',
            'lng.required' => 'Localização em falta.',
        ]);

        $utilizador = $request->user('utilizador');

        $alarme = AlarmeSos::query()->create([
            'utilizador_id' => $utilizador->id,
            'tipo_utilizador' => $utilizador->tipo,
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
            'estado' => 'pendente',
        ]);

        return response()->json(['sucesso' => true, 'alarme_id' => $alarme->id]);
    }
}
