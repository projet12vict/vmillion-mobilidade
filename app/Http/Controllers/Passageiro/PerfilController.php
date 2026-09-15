<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passageiro\AtualizarPerfilRequest;
use App\Models\Utilizador;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Perfil do utilizador autenticado (secção 10.4).
 * A senha só muda mediante confirmação da senha atual.
 */
class PerfilController extends Controller
{
    public function mostrar(Request $request): JsonResponse
    {
        $utilizador = $request->user('utilizador');

        return response()->json([
            'perfil' => $utilizador->only(['nome', 'telefone', 'nif', 'email']),
        ]);
    }

    public function atualizar(AtualizarPerfilRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $utilizador = $request->user('utilizador');
        $telefone = Telefone::normalizar($dados['telefone']);

        $duplicado = Utilizador::query()
            ->whereKeyNot($utilizador->id)
            ->where(fn ($q) => $q->where('telefone', $telefone)->orWhere('nif', $dados['nif']))
            ->exists();

        if ($duplicado) {
            return response()->json(['erro' => 'Telefone ou NIF já em uso por outra conta.'], 409);
        }

        $atributos = [
            'nome' => $dados['nome'],
            'telefone' => $telefone,
            'nif' => $dados['nif'],
        ];

        $novaSenha = (string) ($dados['nova_senha'] ?? '');
        if ($novaSenha !== '') {
            if (! Hash::check((string) ($dados['senha_atual'] ?? ''), $utilizador->senha_hash)) {
                return response()->json(['erro' => 'Senha atual incorreta.'], 403);
            }

            $atributos['senha_hash'] = $novaSenha;
        }

        $utilizador->fill($atributos)->save();

        return response()->json(['sucesso' => true, 'mensagem' => 'Perfil atualizado com sucesso.']);
    }
}
