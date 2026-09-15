<?php

declare(strict_types=1);

namespace App\Http\Controllers\Comunicacao;

use App\Http\Controllers\Controller;
use App\Models\ComunicacaoVeiculo;
use App\Models\Reserva;
use App\Models\Utilizador;
use App\Models\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autofalante entre os passageiros e o condutor de um veículo (secção 6).
 *
 * O condutor é o administrador do canal: pode falar para todos (broadcast,
 * destinatario_id a NULL) ou responder a um passageiro específico. Cada
 * passageiro só pode falar com o condutor, e só vê o broadcast e a sua
 * própria conversa.
 */
class ComunicacaoController extends Controller
{
    public function listar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Indique o veículo.']);

        $utilizador = $request->user('utilizador');
        $veiculo = Veiculo::query()->find($dados['veiculo_id'], ['id', 'condutor_id']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        $ehCondutorDoVeiculo = $this->ehCondutorDoVeiculo($utilizador, $veiculo);

        if (! $ehCondutorDoVeiculo && ! $this->temReservaAtiva($veiculo->id, $utilizador->id)) {
            return response()->json(['erro' => 'Não tem uma reserva ativa neste veículo.'], 403);
        }

        $mensagens = ComunicacaoVeiculo::query()
            ->join('utilizadores as u', 'u.id', '=', 'comunicacoes_veiculo.remetente_id')
            ->where('comunicacoes_veiculo.veiculo_id', $veiculo->id)
            ->when(! $ehCondutorDoVeiculo, fn ($q) => $q->where(fn ($sub) => $sub
                ->whereNull('comunicacoes_veiculo.destinatario_id')
                ->orWhere('comunicacoes_veiculo.destinatario_id', $utilizador->id)
                ->orWhere('comunicacoes_veiculo.remetente_id', $utilizador->id)
            ))
            ->orderBy('comunicacoes_veiculo.criado_em')
            ->limit(200)
            ->get([
                'comunicacoes_veiculo.id', 'comunicacoes_veiculo.remetente_id',
                'comunicacoes_veiculo.destinatario_id', 'comunicacoes_veiculo.mensagem',
                'comunicacoes_veiculo.tipo', 'comunicacoes_veiculo.lida',
                'comunicacoes_veiculo.criado_em',
                'u.nome as remetente_nome',
            ]);

        return response()->json([
            'mensagens' => $mensagens,
            'nao_lidas' => $mensagens
                ->where('lida', false)
                ->where('destinatario_id', $utilizador->id)
                ->count(),
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'mensagem' => ['required', 'string', 'max:500'],
            'destinatario_id' => ['nullable', 'integer'],
        ], [
            'veiculo_id.required' => 'Indique o veículo e a mensagem.',
            'mensagem.required' => 'Indique o veículo e a mensagem.',
            'mensagem.max' => 'Mensagem demasiado longa (máx. 500 caracteres).',
        ]);

        $utilizador = $request->user('utilizador');
        $veiculo = Veiculo::query()->find($dados['veiculo_id'], ['id', 'condutor_id']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        $destinatarioId = $dados['destinatario_id'] ?? null;

        if ($this->ehCondutorDoVeiculo($utilizador, $veiculo)) {
            if ($destinatarioId && ! $this->temReservaAtiva($veiculo->id, (int) $destinatarioId)) {
                return response()->json(['erro' => 'Este passageiro não está associado a este veículo.'], 403);
            }
        } elseif ($utilizador->isPassageiro()) {
            if (! $this->temReservaAtiva($veiculo->id, $utilizador->id)) {
                return response()->json(['erro' => 'Não tem uma reserva ativa neste veículo.'], 403);
            }

            // Um passageiro fala sempre com o condutor, nunca com outro passageiro.
            $destinatarioId = $veiculo->condutor_id;
        } else {
            return response()->json(['erro' => 'Sem permissão para comunicar neste canal.'], 403);
        }

        $mensagem = ComunicacaoVeiculo::query()->create([
            'veiculo_id' => $veiculo->id,
            'remetente_id' => $utilizador->id,
            'destinatario_id' => $destinatarioId,
            'mensagem' => trim($dados['mensagem']),
            'tipo' => 'texto',
        ]);

        return response()->json(['sucesso' => true, 'mensagem_id' => $mensagem->id]);
    }

    public function marcarLida(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        ComunicacaoVeiculo::query()
            ->whereKey($dados['id'])
            ->where('destinatario_id', $request->user('utilizador')->id)
            ->update(['lida' => true]);

        return response()->json(['sucesso' => true]);
    }

    private function ehCondutorDoVeiculo(Utilizador $utilizador, Veiculo $veiculo): bool
    {
        return $utilizador->isCondutor() && $veiculo->condutor_id === $utilizador->id;
    }

    private function temReservaAtiva(int $veiculoId, int $passageiroId): bool
    {
        return Reserva::query()
            ->where('veiculo_id', $veiculoId)
            ->where('passageiro_id', $passageiroId)
            ->ativas()
            ->exists();
    }
}
