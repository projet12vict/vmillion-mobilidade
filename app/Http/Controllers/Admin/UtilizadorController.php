<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PagamentoCondutor;
use App\Models\Utilizador;
use App\Services\AuditoriaService;
use App\Support\IntervaloPeriodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestão de passageiros e condutores (secção 11.3).
 */
class UtilizadorController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * O painel nunca desenha mais do que isto de uma vez. O filtro corre no
     * servidor (e não no browser, como nos pacotes) precisamente porque a
     * lista de utilizadores cresce para lá deste limite: filtrar só o que já
     * veio daria um resultado errado sem o admin dar por isso.
     */
    private const LIMITE = 200;

    public function listar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['nullable', 'in:passageiro,condutor'],
            'status' => ['nullable', 'in:ativo,pendente,suspenso'],
            'q' => ['nullable', 'string', 'max:150'],
            'periodo' => ['nullable', 'in:hoje,ontem,semana,mes,ano,7dias,30dias,personalizado'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'ordem' => ['nullable', 'in:recentes,antigos,nome,nome_desc'],
        ]);

        [$inicio, $fim] = IntervaloPeriodo::paraDatas(
            $dados['periodo'] ?? null,
            $dados['data_inicio'] ?? null,
            $dados['data_fim'] ?? null,
        );

        $texto = trim((string) ($dados['q'] ?? ''));

        $filtrados = Utilizador::query()
            ->when($dados['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($dados['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($texto !== '', fn ($q) => $q->where(function ($q) use ($texto) {
                // O admin procura pelo que tem à mão: um nome mal soletrado ao
                // telefone, o NIF de um formulário ou o email de um recibo.
                $like = '%'.addcslashes($texto, '%_\\').'%';
                $q->where('nome', 'like', $like)
                    ->orWhere('telefone', 'like', $like)
                    ->orWhere('nif', 'like', $like)
                    ->orWhere('email', 'like', $like);
            }))
            ->when($inicio, fn ($q, $inicio) => $q->where('criado_em', '>=', $inicio))
            ->when($fim, fn ($q, $fim) => $q->where('criado_em', '<=', $fim));

        // Quantos correspondem ao filtro antes do corte — sem isto o painel
        // diria "200 utilizadores" tanto para 200 como para 4000.
        $total = (clone $filtrados)->count();

        // criado_em tem precisão de segundo: dois registos feitos no mesmo
        // segundo precisam do id para a "ordem de registo" ser estável entre
        // dois carregamentos da mesma lista.
        $ordenados = match ($dados['ordem'] ?? null) {
            'antigos' => $filtrados->orderBy('criado_em')->orderBy('id'),
            'nome' => $filtrados->orderBy('nome')->orderBy('id'),
            'nome_desc' => $filtrados->orderByDesc('nome')->orderByDesc('id'),
            default => $filtrados->orderByDesc('criado_em')->orderByDesc('id'),
        };

        $utilizadores = $ordenados
            ->limit(self::LIMITE)
            ->get(['id', 'tipo', 'nome', 'telefone', 'nif', 'email', 'status', 'criado_em']);

        return response()->json([
            'utilizadores' => $utilizadores,
            'total' => $total,
            'limite' => self::LIMITE,
        ]);
    }

    public function agir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:aprovar_condutor,suspender,reativar,eliminar'],
            'id' => ['required', 'integer'],
            'motivo' => ['nullable', 'string'],
        ], [
            'id.required' => 'Utilizador inválido.',
            'acao.required' => 'Ação inválida.',
            'acao.in' => 'Ação inválida.',
        ]);

        $admin = $request->user('admin');
        $id = (int) $dados['id'];
        $utilizador = Utilizador::query()->find($id);

        if (! $utilizador) {
            return response()->json(['erro' => 'Utilizador inválido.'], 422);
        }

        // Suspender ou reativar um condutor é a sentença final sobre o seu
        // sustento: só o Super Admin decide. Passageiros mantêm a regra
        // anterior (qualquer admin), por ser uma ação menos grave.
        if (in_array($dados['acao'], ['suspender', 'reativar'], true)
            && $utilizador->isCondutor()
            && ! $admin->isSuper()) {
            return response()->json(['erro' => 'Apenas o Super Admin pode executar esta ação.'], 403);
        }

        return match ($dados['acao']) {
            'aprovar_condutor' => $this->aprovarCondutor($admin->id, $utilizador),
            'suspender' => $this->mudarStatus($admin->id, $utilizador, 'suspenso', 'suspendeu_utilizador', $dados['motivo'] ?? null),
            'reativar' => $this->mudarStatus($admin->id, $utilizador, 'ativo', 'reativou_utilizador'),
            'eliminar' => $this->eliminar($admin->id, $utilizador),
        };
    }

    /**
     * A fonte única de verdade para "o condutor pagou" é
     * pagamentos_condutores — a mesma tabela que o condutor alimenta e que o
     * admin já aprova. Normalmente a ativação acontece automaticamente ao
     * aprovar o pagamento; esta ação serve para os casos manuais.
     */
    private function aprovarCondutor(int $adminId, Utilizador $condutor): JsonResponse
    {
        $temPagamentoValido = PagamentoCondutor::query()
            ->where('condutor_id', $condutor->id)
            ->validos()
            ->exists();

        if (! $temPagamentoValido) {
            return response()->json(
                ['erro' => 'É necessário um pagamento aprovado e válido antes de aprovar o condutor.'],
                422
            );
        }

        if (! $condutor->isCondutor()) {
            return response()->json(['erro' => 'Este utilizador não é um condutor.'], 422);
        }

        $condutor->forceFill(['status' => 'ativo'])->save();
        $this->auditoria->registar($adminId, 'aprovou_condutor', 'utilizadores', $condutor->id);

        return response()->json(['sucesso' => true]);
    }

    private function mudarStatus(
        int $adminId,
        Utilizador $utilizador,
        string $status,
        string $acao,
        ?string $motivo = null
    ): JsonResponse {
        $utilizador->forceFill(['status' => $status])->save();
        $this->auditoria->registar($adminId, $acao, 'utilizadores', $utilizador->id, $motivo ? trim($motivo) : null);

        return response()->json(['sucesso' => true]);
    }

    private function eliminar(int $adminId, Utilizador $utilizador): JsonResponse
    {
        $utilizador->delete();
        $this->auditoria->registar($adminId, 'eliminou_utilizador', 'utilizadores', $utilizador->id);

        return response()->json(['sucesso' => true]);
    }
}
