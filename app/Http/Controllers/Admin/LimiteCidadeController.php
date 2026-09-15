<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimiteCidade;
use App\Rules\CoordenadaCaboVerde;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Limites de cidade (centro + raio), usados para segmentar os troços urbanos
 * e intermunicipais no motor de preços (secção 12.1). Exclusivo do Super Admin.
 */
class LimiteCidadeController extends Controller
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
        return response()->json([
            'limites' => LimiteCidade::query()
                ->orderBy('cidade')
                ->get(['id', 'cidade', 'lat', 'lng', 'raio_km']),
        ]);
    }

    /**
     * Criar usa updateOrCreate porque 'cidade' é única: repetir uma cidade
     * já registada atualiza o centro e o raio em vez de rebentar com um erro
     * de chave duplicada.
     */
    public function criar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request);
        $admin = $request->user('admin');

        LimiteCidade::query()->updateOrCreate(
            ['cidade' => $dados['cidade']],
            [
                'lat' => (float) $dados['lat'],
                'lng' => (float) $dados['lng'],
                'raio_km' => (float) $dados['raio_km'],
                'atualizado_por' => $admin->id,
            ]
        );

        $this->auditoria->registar($admin->id, 'criou_limite_cidade', 'limites_cidades', null, $dados['cidade']);

        return response()->json(['sucesso' => true]);
    }

    public function editar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request, comId: true);
        $admin = $request->user('admin');

        $limite = LimiteCidade::query()->find($dados['id']);
        if (! $limite) {
            return response()->json(['erro' => 'Limite de cidade não encontrado.'], 404);
        }

        $limite->forceFill([
            'cidade' => $dados['cidade'],
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
            'raio_km' => (float) $dados['raio_km'],
            'atualizado_por' => $admin->id,
        ])->save();

        $this->auditoria->registar($admin->id, 'editou_limite_cidade', 'limites_cidades', $limite->id, $limite->cidade);

        return response()->json(['sucesso' => true]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        LimiteCidade::query()->whereKey($dados['id'])->delete();

        $this->auditoria->registar(
            $request->user('admin')->id, 'eliminou_limite_cidade', 'limites_cidades', (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDados(Request $request, bool $comId = false): array
    {
        $regras = [
            'cidade' => ['required', 'string', 'max:100'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
            'raio_km' => ['required', 'numeric', 'gt:0'],
        ];

        if ($comId) {
            $regras['id'] = ['required', 'integer'];
        }

        return $request->validate($regras, [
            'cidade.required' => 'Dados de limite de cidade inválidos.',
            'lat.required' => 'Dados de limite de cidade inválidos.',
            'lng.required' => 'Dados de limite de cidade inválidos.',
            'raio_km.required' => 'Dados de limite de cidade inválidos.',
            'raio_km.gt' => 'Dados de limite de cidade inválidos.',
            'id.required' => 'ID inválido.',
        ]);
    }
}
