<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parque;
use App\Rules\CoordenadaCaboVerde;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parques de estacionamento (secções 6.2 e 11.4).
 */
class ParqueController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function agir(Request $request): JsonResponse
    {
        return match ((string) $request->input('acao')) {
            'criar' => $this->criar($request),
            'editar' => $this->editar($request),
            'eliminar' => $this->eliminar($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    public function listar(): JsonResponse
    {
        return response()->json(['parques' => Parque::query()->orderBy('nome')->get()]);
    }

    public function criar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request);
        $admin = $request->user('admin');

        $parque = Parque::query()->create($this->atributos($dados) + ['criado_por' => $admin->id]);

        $this->auditoria->registar($admin->id, 'criou_parque', 'parques', $parque->id, $parque->nome);

        return response()->json(['sucesso' => true, 'id' => $parque->id]);
    }

    public function editar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request, comId: true);

        $parque = Parque::query()->find($dados['id']);
        if (! $parque) {
            return response()->json(['erro' => 'Parque não encontrado.'], 404);
        }

        $parque->forceFill($this->atributos($dados))->save();

        $this->auditoria->registar($request->user('admin')->id, 'editou_parque', 'parques', $parque->id, $parque->nome);

        return response()->json(['sucesso' => true]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        Parque::query()->whereKey($dados['id'])->delete();

        $this->auditoria->registar($request->user('admin')->id, 'eliminou_parque', 'parques', (int) $dados['id']);

        return response()->json(['sucesso' => true]);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function atributos(array $dados): array
    {
        return [
            'nome' => $dados['nome'],
            'morada' => $dados['morada'],
            'cidade' => $dados['cidade'],
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
            'capacidade_total' => (int) $dados['capacidade_total'],
            'vagas_ocupadas' => (int) ($dados['vagas_ocupadas'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDados(Request $request, bool $comId = false): array
    {
        $regras = [
            'nome' => ['required', 'string', 'max:150'],
            'morada' => ['required', 'string', 'max:200'],
            'cidade' => ['required', 'string', 'max:100'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
            'capacidade_total' => ['required', 'integer', 'min:1'],
            'vagas_ocupadas' => ['nullable', 'integer', 'min:0', 'lte:capacidade_total'],
        ];

        if ($comId) {
            $regras['id'] = ['required', 'integer'];
        }

        return $request->validate($regras, [
            'nome.required' => 'Preencha todos os campos obrigatórios.',
            'morada.required' => 'Preencha todos os campos obrigatórios.',
            'cidade.required' => 'Preencha todos os campos obrigatórios.',
            'lat.required' => 'Preencha todos os campos obrigatórios.',
            'lng.required' => 'Preencha todos os campos obrigatórios.',
            'capacidade_total.required' => 'Preencha todos os campos obrigatórios.',
            'vagas_ocupadas.lte' => 'Vagas ocupadas não pode exceder a capacidade total.',
            'id.required' => 'ID inválido.',
        ]);
    }
}
