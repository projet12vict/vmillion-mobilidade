<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sugestao;
use App\Models\Utilizador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sugestões (qualquer utilizador, visíveis ao Super Admin) e reclamações
 * sobre condutores (só passageiros, visíveis aos administradores) — secção 10.
 */
class SugestaoController extends Controller
{
    public function enviar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:sugestao,reclamacao'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'condutor_id' => ['nullable', 'integer'],
        ], [
            'tipo.required' => 'Preencha o título e a descrição.',
            'tipo.in' => 'Preencha o título e a descrição.',
            'titulo.required' => 'Preencha o título e a descrição.',
            'descricao.required' => 'Preencha o título e a descrição.',
        ]);

        $utilizador = $request->user('utilizador');
        $condutorId = null;

        if ($dados['tipo'] === 'reclamacao') {
            if (! $utilizador->isPassageiro()) {
                return response()->json(
                    ['erro' => 'Só passageiros podem submeter reclamações sobre condutores.'],
                    403
                );
            }

            $condutorId = $dados['condutor_id'] ?? null;
            if (! $condutorId) {
                return response()->json(['erro' => 'Indique o condutor a que se refere a reclamação.'], 422);
            }

            $existe = Utilizador::query()
                ->whereKey($condutorId)
                ->where('tipo', 'condutor')
                ->exists();

            if (! $existe) {
                return response()->json(['erro' => 'Condutor não encontrado.'], 404);
            }
        }

        Sugestao::query()->create([
            'utilizador_id' => $utilizador->id,
            'tipo' => $dados['tipo'],
            'titulo' => trim($dados['titulo']),
            'descricao' => trim($dados['descricao']),
            'condutor_id' => $condutorId,
        ]);

        return response()->json(['sucesso' => true]);
    }
}
