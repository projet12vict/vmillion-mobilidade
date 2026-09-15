<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chamada;

use App\Http\Controllers\Controller;
use App\Models\SinalizacaoChamada;
use App\Services\ChamadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canal de sinalização WebRTC das chamadas.
 *
 * O servidor nunca vê áudio: só este texto de negociação (offer/answer/ICE),
 * que os dois participantes trocam entre si.
 */
class SinalizacaoController extends Controller
{
    public function __construct(private readonly ChamadaService $chamadas) {}

    public function guardar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chamada_id' => ['required', 'integer'],
            'tipo' => ['required', 'in:offer,answer,ice'],
            'payload' => ['required', 'string', 'max:4000', 'json'],
        ], [
            'chamada_id.required' => 'Dados inválidos.',
            'tipo.required' => 'Dados inválidos.',
            'tipo.in' => 'Dados inválidos.',
            'payload.required' => 'Dados inválidos.',
            'payload.max' => 'Sinal demasiado grande.',
            'payload.json' => 'Sinal mal formado.',
        ]);

        $utilizadorId = $request->user('utilizador')->id;
        $chamada = $this->chamadas->chamadaDoParticipante((int) $dados['chamada_id'], $utilizadorId);

        if (! $chamada) {
            return response()->json(['erro' => 'Chamada não encontrada.'], 404);
        }

        if (! in_array($chamada->estado, ['iniciada', 'atendida'], true)) {
            return response()->json(['erro' => 'Esta chamada já terminou.'], 409);
        }

        SinalizacaoChamada::query()->create([
            'chamada_id' => $chamada->id,
            'remetente_id' => $utilizadorId,
            'tipo' => $dados['tipo'],
            'payload' => $dados['payload'],
        ]);

        return response()->json(['sucesso' => true]);
    }

    /**
     * Polling rápido dos sinais novos do OUTRO participante — nunca devolve
     * os sinais do próprio.
     */
    public function novos(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chamada_id' => ['required', 'integer'],
            'desde_id' => ['nullable', 'integer'],
        ], ['chamada_id.required' => 'Indique a chamada.']);

        $utilizadorId = $request->user('utilizador')->id;
        $chamada = $this->chamadas->chamadaDoParticipante((int) $dados['chamada_id'], $utilizadorId);

        if (! $chamada) {
            return response()->json(['erro' => 'Chamada não encontrada.'], 404);
        }

        $sinais = SinalizacaoChamada::query()
            ->where('chamada_id', $chamada->id)
            ->where('remetente_id', '!=', $utilizadorId)
            ->where('id', '>', (int) ($dados['desde_id'] ?? 0))
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'tipo', 'payload']);

        return response()->json(['sinais' => $sinais]);
    }
}
