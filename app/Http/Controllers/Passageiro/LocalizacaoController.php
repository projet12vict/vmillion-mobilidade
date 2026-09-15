<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Rules\CoordenadaCaboVerde;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posição GPS ao vivo do passageiro, para o condutor o ver no mapa em vez de
 * ver apenas o ponto de partida (secção 5.3/9.4).
 *
 * Só é guardada se houver reserva ativa — sem reserva não há veículo nenhum
 * a quem mostrar isto.
 */
class LocalizacaoController extends Controller
{
    public function atualizar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
            // accuracy é opcional (nem todos os clientes a enviam), mas acima
            // de 100 m a leitura já não é fiável: é o mesmo limite usado no
            // frontend (kg-geolocation.js) para não deixar o passageiro
            // "aparecer no mar" com uma posição obtida por WiFi/IP.
            'accuracy' => ['nullable', 'numeric', 'max:100'],
        ], [
            'lat.required' => 'Coordenadas inválidas.',
            'lng.required' => 'Coordenadas inválidas.',
            'accuracy.max' => 'Precisão do GPS insuficiente.',
        ]);

        $atualizadas = Reserva::query()
            ->where('passageiro_id', $request->user('utilizador')->id)
            ->ativas()
            ->update([
                'passageiro_lat' => (float) $dados['lat'],
                'passageiro_lng' => (float) $dados['lng'],
                'passageiro_localizacao_em' => now(),
            ]);

        return response()->json(['sucesso' => true, 'atualizado' => $atualizadas > 0]);
    }
}
