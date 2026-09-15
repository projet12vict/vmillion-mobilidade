<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestão de contas de backoffice (secções 4.3 e 11.7). Exclusivo do Super Admin.
 */
class AdministradorController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function listar(): JsonResponse
    {
        return response()->json([
            'administradores' => Administrador::query()
                ->orderByDesc('criado_em')
                ->get(['id', 'nome', 'email', 'nivel', 'ativo', 'senha_temporaria', 'criado_em']),
        ]);
    }

    /**
     * A conta nasce com uma senha temporária gerada aqui, que é devolvida uma
     * única vez para o Super Admin a entregar ao novo administrador; a flag
     * senha_temporaria obriga à troca no primeiro login.
     */
    public function criar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'nivel' => ['nullable', 'in:gestor,admin,super'],
        ], [
            'nome.required' => 'Nome ou email inválido.',
            'email.required' => 'Nome ou email inválido.',
            'email.email' => 'Nome ou email inválido.',
        ]);

        $nivel = $dados['nivel'] ?? 'admin';

        // O Super Admin é único e exclusivo (secção 3): nunca se cria um
        // segundo, mesmo que outro Super Admin peça explicitamente.
        if ($nivel === 'super' && Administrador::query()->where('nivel', 'super')->exists()) {
            return response()->json(
                ['erro' => 'Já existe um Super Admin. Apenas um pode existir no sistema.'],
                409
            );
        }

        if (Administrador::query()->where('email', $dados['email'])->exists()) {
            return response()->json(['erro' => 'Já existe um administrador com este email.'], 409);
        }

        $superAdmin = $request->user('admin');
        $senhaTemporaria = Str::random(12);

        $novo = Administrador::query()->create([
            'nome' => trim($dados['nome']),
            'email' => trim($dados['email']),
            'senha_hash' => $senhaTemporaria,
            'nivel' => $nivel,
            'senha_temporaria' => true,
            'ativo' => true,
            'criado_por' => $superAdmin->id,
        ]);

        $this->auditoria->registar($superAdmin->id, 'criou_admin', 'administradores', $novo->id, $novo->email);

        return response()->json([
            'sucesso' => true,
            'id' => $novo->id,
            'senha_temporaria' => $senhaTemporaria,
        ]);
    }

    public function agir(Request $request): JsonResponse
    {
        if ($request->input('acao') === 'criar') {
            return $this->criar($request);
        }

        $dados = $request->validate([
            'acao' => ['required', 'in:desativar,reativar,eliminar'],
            'id' => ['required', 'integer'],
        ], [
            'acao.required' => 'Ação inválida.',
            'acao.in' => 'Ação inválida.',
            'id.required' => 'ID inválido.',
        ]);

        $superAdmin = $request->user('admin');
        $id = (int) $dados['id'];

        if ($id === $superAdmin->id && in_array($dados['acao'], ['desativar', 'eliminar'], true)) {
            return response()->json(
                ['erro' => 'Não é possível desativar ou eliminar o próprio Super Admin.'],
                403
            );
        }

        match ($dados['acao']) {
            'desativar' => Administrador::query()->whereKey($id)->update(['ativo' => false]),
            'reativar' => Administrador::query()->whereKey($id)->update(['ativo' => true]),
            'eliminar' => Administrador::query()->whereKey($id)->delete(),
        };

        $this->auditoria->registar(
            $superAdmin->id,
            match ($dados['acao']) {
                'desativar' => 'desativou_admin',
                'reativar' => 'reativou_admin',
                'eliminar' => 'eliminou_admin',
            },
            'administradores',
            $id
        );

        return response()->json(['sucesso' => true]);
    }
}
