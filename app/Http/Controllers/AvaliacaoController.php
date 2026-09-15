<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AvaliacaoCondutor;
use App\Models\Reserva;
use App\Models\Sugestao;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avaliação do condutor pelo passageiro depois da viagem (secção 9).
 * Uma avaliação por reserva — garantido pelo UNIQUE uq_avaliacao_reserva.
 */
class AvaliacaoController extends Controller
{
    public function consultar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'condutor_id' => ['required', 'integer'],
        ], ['condutor_id.required' => 'Indique o condutor.']);

        $agregado = AvaliacaoCondutor::query()
            ->where('condutor_id', $dados['condutor_id'])
            ->selectRaw('COUNT(*) AS total, COALESCE(AVG(avaliacao), 0) AS media')
            ->first();

        return response()->json([
            'condutor_id' => (int) $dados['condutor_id'],
            'total' => (int) $agregado->total,
            'media' => round((float) $agregado->media, 2),
        ]);
    }

    /**
     * O passageiro fechou o modal do fim da viagem sem classificar. Guardar a
     * decisão na reserva é o que impede o painel de voltar a propor a mesma
     * avaliação a cada ciclo de estado (e noutro dispositivo).
     */
    public function dispensar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
        ], ['reserva_id.required' => 'Reserva inválida.']);

        $reserva = Reserva::query()
            ->whereKey($dados['reserva_id'])
            ->where('passageiro_id', $request->user('utilizador')->id)
            ->where('estado', 'concluido')
            ->first();

        if (! $reserva) {
            return response()->json(['erro' => 'Reserva não encontrada ou ainda não concluída.'], 404);
        }

        // Dispensar duas vezes não é erro: fica a primeira data.
        if ($reserva->avaliacao_dispensada_em === null) {
            $reserva->forceFill(['avaliacao_dispensada_em' => now()])->save();
        }

        return response()->json(['sucesso' => true]);
    }

    public function criar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
            'avaliacao' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ], [
            'reserva_id.required' => 'Avaliação inválida (1 a 5).',
            'avaliacao.required' => 'Avaliação inválida (1 a 5).',
            'avaliacao.integer' => 'Avaliação inválida (1 a 5).',
            'avaliacao.between' => 'Avaliação inválida (1 a 5).',
        ]);

        $passageiro = $request->user('utilizador');
        $comentario = trim((string) ($dados['comentario'] ?? '')) ?: null;

        $reserva = Reserva::query()
            ->with('veiculo:id,condutor_id')
            ->whereKey($dados['reserva_id'])
            ->where('passageiro_id', $passageiro->id)
            ->where('estado', 'concluido')
            ->first();

        if (! $reserva || ! $reserva->veiculo) {
            return response()->json(['erro' => 'Reserva não encontrada ou ainda não concluída.'], 404);
        }

        $condutorId = $reserva->veiculo->condutor_id;

        try {
            AvaliacaoCondutor::query()->create([
                'condutor_id' => $condutorId,
                'passageiro_id' => $passageiro->id,
                'reserva_id' => $reserva->id,
                'avaliacao' => $dados['avaliacao'],
                'comentario' => $comentario,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['erro' => 'Esta viagem já foi avaliada.'], 409);
            }

            throw $e;
        }

        // Avaliação baixa com comentário conta como reclamação: reaproveita a
        // secção "Sugestões e reclamações" do admin em vez de criar uma
        // segunda caixa de entrada só para isto.
        if ($dados['avaliacao'] <= 2 && $comentario !== null) {
            try {
                Sugestao::query()->create([
                    'utilizador_id' => $passageiro->id,
                    'tipo' => 'reclamacao',
                    'titulo' => "Avaliação {$dados['avaliacao']}/5 numa viagem",
                    'descricao' => $comentario,
                    'condutor_id' => $condutorId,
                ]);
            } catch (Throwable $e) {
                Log::warning('Falha ao gerar reclamação a partir de avaliação: '.$e->getMessage());
            }
        }

        return response()->json(['sucesso' => true]);
    }
}
