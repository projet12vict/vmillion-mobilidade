<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use App\Models\Notificacao;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Rules\CoordenadaCaboVerde;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pontos de partida: CRUD e fluxo de aprovação (secções 6.1, 6.3 e 11.4).
 *
 * Um ponto novo entra sempre 'pendente' e só fica visível ao público depois
 * de um admin o aprovar.
 */
class PontoController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * O painel envia todas as operações de pontos para o mesmo endpoint, com
     * a operação em 'acao'. A eliminação definitiva é a única restrita ao
     * Super Admin, por isso a verificação vive aqui e não no middleware da
     * rota (que serve também as ações abertas a qualquer administrador).
     */
    public function agir(Request $request): JsonResponse
    {
        $acao = (string) $request->input('acao');

        if ($acao === 'eliminar_definitivo' && ! $request->user('admin')->isSuper()) {
            return response()->json(['erro' => 'Apenas o Super Admin pode executar esta ação.'], 403);
        }

        return match ($acao) {
            'criar' => $this->criar($request),
            'editar' => $this->editar($request),
            'mover' => $this->mover($request),
            'aprovar', 'recusar' => $this->decidir($request),
            'eliminar' => $this->desativar($request),
            'eliminar_definitivo' => $this->eliminar($request),
            default => response()->json(['erro' => 'Ação inválida.'], 422),
        };
    }

    public function listar(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $pontos = PontoPartida::query()
            ->leftJoin('administradores as c', 'c.id', '=', 'pontos_partida.criado_por')
            ->leftJoin('administradores as a', 'a.id', '=', 'pontos_partida.aprovado_por')
            ->when(in_array($status, ['pendente', 'aprovado', 'recusado'], true),
                fn ($q) => $q->where('pontos_partida.status', $status))
            ->orderByRaw("(pontos_partida.status = 'pendente') DESC")
            ->orderBy('pontos_partida.nome')
            ->get([
                'pontos_partida.*',
                'c.nome as criado_por_nome',
                'a.nome as aprovado_por_nome',
            ]);

        return response()->json(['pontos' => $pontos]);
    }

    public function criar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request);
        $admin = $request->user('admin');

        $ponto = PontoPartida::query()->create([
            'nome' => $dados['nome'],
            'cidade' => $dados['cidade'],
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
            'zona' => $dados['zona'] ?? 'urbana',
            'status' => 'pendente',
            'criado_por' => $admin->id,
        ]);

        $this->auditoria->registar($admin->id, 'criou_ponto', 'pontos_partida', $ponto->id, $ponto->nome);
        $this->notificarAdminsPontoPendente($admin, $ponto);

        return response()->json(['sucesso' => true, 'id' => $ponto->id]);
    }

    public function editar(Request $request): JsonResponse
    {
        $dados = $this->validarDados($request, comId: true);

        $ponto = PontoPartida::query()->find($dados['id']);
        if (! $ponto) {
            return response()->json(['erro' => 'Ponto não encontrado.'], 404);
        }

        $ponto->forceFill([
            'nome' => $dados['nome'],
            'cidade' => $dados['cidade'],
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
            'zona' => $dados['zona'] ?? 'urbana',
        ])->save();

        $this->auditoria->registar($request->user('admin')->id, 'editou_ponto', 'pontos_partida', $ponto->id, $ponto->nome);

        return response()->json(['sucesso' => true]);
    }

    /**
     * Arrastar o marcador no editor de mapa: guarda só a nova posição, sem
     * exigir os restantes campos.
     */
    public function mover(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
        ], [
            'id.required' => 'Dados de posição inválidos.',
            'lat.required' => 'Dados de posição inválidos.',
            'lng.required' => 'Dados de posição inválidos.',
        ]);

        $lat = (float) $dados['lat'];
        $lng = (float) $dados['lng'];

        PontoPartida::query()->whereKey($dados['id'])->update(['lat' => $lat, 'lng' => $lng]);

        $this->auditoria->registar(
            $request->user('admin')->id, 'moveu_ponto', 'pontos_partida', (int) $dados['id'], "{$lat}, {$lng}"
        );

        return response()->json(['sucesso' => true, 'lat' => $lat, 'lng' => $lng]);
    }

    public function decidir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:aprovar,recusar'],
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        $admin = $request->user('admin');
        $aprovar = $dados['acao'] === 'aprovar';

        $atributos = [
            'status' => $aprovar ? 'aprovado' : 'recusado',
            'aprovado_por' => $admin->id,
            'aprovado_em' => now(),
        ];

        // Aprovar também reativa o ponto (caso tivesse sido desativado antes
        // deste fluxo existir): o status passa a ser a única fonte de verdade
        // sobre visibilidade pública.
        if ($aprovar) {
            $atributos['ativo'] = true;
        }

        PontoPartida::query()->whereKey($dados['id'])->update($atributos);

        $this->auditoria->registar(
            $admin->id,
            $aprovar ? 'aprovou_ponto' : 'recusou_ponto',
            'pontos_partida',
            (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    /** Desativação reversível: esconde o ponto sem apagar nada. */
    public function desativar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        PontoPartida::query()->whereKey($dados['id'])->update(['ativo' => false]);

        $this->auditoria->registar(
            $request->user('admin')->id, 'desativou_ponto', 'pontos_partida', (int) $dados['id']
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Eliminação definitiva e irreversível, restrita ao Super Admin — é a
     * única ação desta API que apaga uma linha em vez de mudar um estado.
     *
     * Só é permitida se o ponto não tiver reservas associadas (a FK é
     * RESTRICT e rebentaria com uma exceção não tratada). Veículos ainda
     * ligados ao ponto também bloqueiam, para o admin usar "Recusar" nesses
     * casos em vez de perder referências silenciosamente.
     */
    public function eliminar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
        ], ['id.required' => 'ID inválido.']);

        $id = (int) $dados['id'];
        $ponto = PontoPartida::query()->find($id, ['id', 'nome']);

        if (! $ponto) {
            return response()->json(['erro' => 'Ponto não encontrado.'], 404);
        }

        $temReservas = Reserva::query()
            ->where(fn ($q) => $q->where('ponto_partida_id', $id)->orWhere('destino_id', $id))
            ->exists();

        if ($temReservas) {
            return response()->json(
                ['erro' => 'Este ponto tem reservas associadas e não pode ser eliminado — use "Recusar" para o esconder do público.'],
                409
            );
        }

        $temVeiculos = Veiculo::query()
            ->where(fn ($q) => $q->where('ponto_partida_id', $id)->orWhere('destino_id', $id))
            ->exists();

        if ($temVeiculos) {
            return response()->json(
                ['erro' => 'Há veículos atualmente associados a este ponto — mude-os de ponto ou use "Recusar" em vez de eliminar.'],
                409
            );
        }

        $ponto->delete();
        $this->auditoria->registar($request->user('admin')->id, 'eliminou_ponto', 'pontos_partida', $id, $ponto->nome);

        return response()->json(['sucesso' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDados(Request $request, bool $comId = false): array
    {
        $regras = [
            'nome' => ['required', 'string', 'max:150'],
            'cidade' => ['required', 'string', 'max:100'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
            'zona' => ['nullable', 'in:urbana,intermunicipal'],
        ];

        if ($comId) {
            $regras['id'] = ['required', 'integer'];
        }

        return $request->validate($regras, [
            'nome.required' => 'Preencha nome, cidade e coordenadas válidas.',
            'cidade.required' => 'Preencha nome, cidade e coordenadas válidas.',
            'lat.required' => 'Preencha nome, cidade e coordenadas válidas.',
            'lng.required' => 'Preencha nome, cidade e coordenadas válidas.',
            'id.required' => 'ID inválido.',
        ]);
    }

    /**
     * Avisa os restantes admins ativos de que há um ponto novo por rever.
     * Uma notificação gorada nunca pode bloquear a criação do ponto.
     */
    private function notificarAdminsPontoPendente(Administrador $criador, PontoPartida $ponto): void
    {
        try {
            $alvos = Administrador::query()
                ->where('ativo', true)
                ->whereKeyNot($criador->id)
                ->pluck('id');

            foreach ($alvos as $alvoId) {
                Notificacao::query()->create([
                    'destinatario_id' => $alvoId,
                    'destinatario_tipo' => 'admins',
                    'remetente_id' => $criador->id,
                    'titulo' => 'Novo ponto pendente',
                    'mensagem' => "{$ponto->nome} ({$ponto->cidade}) aguarda aprovação — ponto #{$ponto->id}.",
                    'tipo' => 'informativo',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Falha ao notificar admins sobre ponto pendente: '.$e->getMessage());
        }
    }
}
