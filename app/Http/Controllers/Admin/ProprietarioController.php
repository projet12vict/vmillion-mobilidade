<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proprietario;
use App\Models\Utilizador;
use App\Support\Telefone;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proprietários de frota (secção 8). Um proprietário pode também conduzir o
 * seu próprio veículo — nesse caso fica associado a uma conta de condutor
 * existente através de utilizador_condutor_id.
 */
class ProprietarioController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function agir(Request $request): JsonResponse
    {
        return match ((string) $request->input('acao')) {
            'criar' => $this->criar($request),
            'editar' => $this->editar($request),
            'associar_condutor' => $this->associarCondutor($request),
            'eliminar' => $this->eliminar($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    public function listar(): JsonResponse
    {
        $proprietarios = Proprietario::query()
            ->leftJoin('utilizadores as u', 'u.id', '=', 'proprietarios.utilizador_condutor_id')
            ->withCount('condutoresDaFrota as total_condutores')
            ->orderBy('proprietarios.nome')
            ->get([
                'proprietarios.id', 'proprietarios.nome', 'proprietarios.telefone',
                'proprietarios.nif', 'proprietarios.utilizador_condutor_id',
                'u.nome as condutor_nome',
            ]);

        return response()->json(['proprietarios' => $proprietarios]);
    }

    public function criar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request);
        $telefone = Telefone::normalizar($dados['telefone']);

        $duplicado = Proprietario::query()
            ->where(fn ($q) => $q->where('telefone', $telefone)->orWhere('nif', $dados['nif']))
            ->exists();

        if ($duplicado) {
            return response()->json(['erro' => 'Já existe um proprietário com este telefone ou NIF.'], 409);
        }

        $admin = $request->user('admin');

        $proprietario = Proprietario::query()->create([
            'nome' => $dados['nome'],
            'telefone' => $telefone,
            'nif' => $dados['nif'],
            'utilizador_condutor_id' => $dados['utilizador_condutor_id'] ?? null,
        ]);

        $this->auditoria->registar(
            $admin->id, 'criou_proprietario', 'proprietarios', $proprietario->id, $proprietario->nome
        );

        return response()->json(['sucesso' => true, 'id' => $proprietario->id]);
    }

    public function editar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request, comId: true);

        $proprietario = Proprietario::query()->find($dados['id']);
        if (! $proprietario) {
            return response()->json(['erro' => 'Proprietário não encontrado.'], 404);
        }

        $proprietario->forceFill([
            'nome' => $dados['nome'],
            'telefone' => Telefone::normalizar($dados['telefone']),
            'nif' => $dados['nif'],
            'utilizador_condutor_id' => $dados['utilizador_condutor_id'] ?? null,
        ])->save();

        $this->auditoria->registar(
            $request->user('admin')->id, 'editou_proprietario', 'proprietarios',
            $proprietario->id, $proprietario->nome
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Liga um condutor existente a um proprietário (frota de terceiros).
     * Sem proprietario_id, desassocia.
     */
    public function associarCondutor(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'condutor_id' => ['required', 'integer'],
            'proprietario_id' => ['nullable', 'integer', 'exists:proprietarios,id'],
        ], ['condutor_id.required' => 'Indique o condutor.']);

        $proprietarioId = $dados['proprietario_id'] ?? null;

        Utilizador::query()
            ->whereKey($dados['condutor_id'])
            ->where('tipo', 'condutor')
            ->update(['proprietario_id' => $proprietarioId]);

        $this->auditoria->registar(
            $request->user('admin')->id, 'associou_condutor_proprietario', 'utilizadores',
            (int) $dados['condutor_id'], (string) $proprietarioId
        );

        return response()->json(['sucesso' => true]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        Proprietario::query()->whereKey($dados['id'])->delete();

        $this->auditoria->registar(
            $request->user('admin')->id, 'eliminou_proprietario', 'proprietarios', (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDados(Request $request, bool $comId = false): array
    {
        $regras = [
            'nome' => ['required', 'string', 'min:3', 'max:150'],
            'telefone' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_telefone')],
            'nif' => ['required', 'string', 'regex:'.config('vmillion.validacao.regex_nif')],
            'utilizador_condutor_id' => ['nullable', 'integer'],
        ];

        if ($comId) {
            $regras['id'] = ['required', 'integer'];
        }

        $dados = $request->validate($regras, [
            'nome.required' => 'Indique o nome completo.',
            'nome.min' => 'Indique o nome completo.',
            'telefone.required' => 'Telefone inválido.',
            'telefone.regex' => 'Telefone inválido.',
            'nif.required' => 'NIF inválido.',
            'nif.regex' => 'NIF inválido.',
            'id.required' => 'ID inválido.',
        ]);

        if (! empty($dados['utilizador_condutor_id'])) {
            $existe = Utilizador::query()
                ->whereKey($dados['utilizador_condutor_id'])
                ->where('tipo', 'condutor')
                ->exists();

            abort_unless($existe, 422, 'Condutor não encontrado.');
        }

        return $dados;
    }
}
