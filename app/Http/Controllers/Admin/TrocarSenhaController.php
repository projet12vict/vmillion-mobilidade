<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Troca da senha do administrador autenticado. É o que fecha a flag
 * senha_temporaria com que uma conta nova é criada.
 */
class TrocarSenhaController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nova_senha' => ['required', 'string', 'min:'.config('vmillion.validacao.senha_min_len'), 'confirmed'],
        ], [
            'nova_senha.required' => 'Senha inválida ou as senhas não coincidem.',
            'nova_senha.min' => 'Senha inválida ou as senhas não coincidem.',
            'nova_senha.confirmed' => 'Senha inválida ou as senhas não coincidem.',
        ]);

        $admin = $request->user('admin');

        $admin->forceFill([
            'senha_hash' => $dados['nova_senha'],
            'senha_temporaria' => false,
        ])->save();

        $this->auditoria->registar($admin->id, 'trocou_senha', 'administradores', $admin->id);

        return response()->json(['sucesso' => true]);
    }
}
