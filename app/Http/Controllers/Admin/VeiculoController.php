<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Services\AuditoriaService;
use App\Support\IntervaloPeriodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aprovação e gestão de veículos (secções 7.2 e 11.3).
 */
class VeiculoController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * O painel nunca desenha mais do que isto de uma vez. Como na lista de
     * utilizadores, o filtro corre no servidor: a frota passa deste limite e
     * filtrar no browser só o que já veio escondia veículos sem aviso.
     */
    private const LIMITE = 200;

    public function listar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'tipo' => ['nullable', 'in:hiace,taxi,autocarro'],
            'tipo_servico' => ['nullable', 'in:urbano,intermunicipal,ambos'],
            'estado' => ['nullable', 'in:no_ponto,na_fila,em_movimento,partiu_da_fila,chegou_destino'],
            'aprovado' => ['nullable', 'in:0,1'],
            'ponto' => ['nullable', 'in:bloqueado,livre'],
            'periodo' => ['nullable', 'in:hoje,ontem,semana,mes,ano,7dias,30dias,personalizado'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'ordem' => ['nullable', 'in:recentes,antigos,matricula,matricula_desc,condutor'],
        ]);

        [$inicio, $fim] = IntervaloPeriodo::paraDatas(
            $dados['periodo'] ?? null,
            $dados['data_inicio'] ?? null,
            $dados['data_fim'] ?? null,
        );

        $texto = trim((string) ($dados['q'] ?? ''));

        $filtrados = Veiculo::query()
            ->join('utilizadores as u', 'u.id', '=', 'veiculos.condutor_id')
            ->when($dados['tipo'] ?? null, fn ($q, $tipo) => $q->where('veiculos.tipo', $tipo))
            ->when($dados['tipo_servico'] ?? null, fn ($q, $servico) => $q->where('veiculos.tipo_servico', $servico))
            ->when($dados['estado'] ?? null, fn ($q, $estado) => $q->where('veiculos.estado', $estado))
            ->when(isset($dados['aprovado']), fn ($q) => $q->where('veiculos.aprovado', (bool) $dados['aprovado']))
            // "Ponto trancado" é o prazo ainda por cumprir, não a coluna
            // preenchida: um bloqueio que já passou deixou de trancar nada e
            // o painel também já não mostra o botão "Libertar ponto".
            ->when(($dados['ponto'] ?? null) === 'bloqueado', fn ($q) => $q->where('veiculos.ponto_bloqueado_ate', '>', now()))
            ->when(($dados['ponto'] ?? null) === 'livre', fn ($q) => $q->where(function ($q) {
                $q->whereNull('veiculos.ponto_bloqueado_ate')->orWhere('veiculos.ponto_bloqueado_ate', '<=', now());
            }))
            ->when($texto !== '', fn ($q) => $q->where(function ($q) use ($texto) {
                // O admin chega aqui com o que tem à mão: a matrícula de uma
                // queixa, o modelo e a cor de quem descreveu o carro, ou o
                // nome/telefone do condutor.
                $like = '%'.addcslashes($texto, '%_\\').'%';
                $q->where('veiculos.matricula', 'like', $like)
                    ->orWhere('veiculos.modelo', 'like', $like)
                    ->orWhere('veiculos.cor', 'like', $like)
                    ->orWhere('u.nome', 'like', $like)
                    ->orWhere('u.telefone', 'like', $like);
            }))
            ->when($inicio, fn ($q, $inicio) => $q->where('veiculos.criado_em', '>=', $inicio))
            ->when($fim, fn ($q, $fim) => $q->where('veiculos.criado_em', '<=', $fim));

        // Quantos correspondem ao filtro antes do corte — sem isto o painel
        // diria "200 veículos" tanto para 200 como para 4000.
        $total = (clone $filtrados)->count();

        // criado_em tem precisão de segundo: dois veículos registados no mesmo
        // segundo precisam do id para a ordem ser estável entre carregamentos.
        $ordenados = match ($dados['ordem'] ?? null) {
            'antigos' => $filtrados->orderBy('veiculos.criado_em')->orderBy('veiculos.id'),
            'matricula' => $filtrados->orderBy('veiculos.matricula')->orderBy('veiculos.id'),
            'matricula_desc' => $filtrados->orderByDesc('veiculos.matricula')->orderByDesc('veiculos.id'),
            'condutor' => $filtrados->orderBy('u.nome')->orderBy('veiculos.id'),
            default => $filtrados->orderByDesc('veiculos.criado_em')->orderByDesc('veiculos.id'),
        };

        $veiculos = $ordenados
            ->limit(self::LIMITE)
            ->get([
                'veiculos.id', 'veiculos.matricula', 'veiculos.tipo', 'veiculos.tipo_servico', 'veiculos.cor',
                'veiculos.modelo', 'veiculos.aprovado', 'veiculos.estado', 'veiculos.criado_em',
                // Diz ao painel que este veículo tem o ponto trancado e até
                // quando — é o que decide se aparece o botão "Libertar ponto".
                'veiculos.ponto_bloqueado_ate',
                'u.nome as condutor_nome', 'u.telefone as condutor_telefone',
            ]);

        return response()->json([
            'veiculos' => $veiculos,
            'total' => $total,
            'limite' => self::LIMITE,
        ]);
    }

    public function agir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:aprovar,rejeitar,eliminar,libertar_ponto'],
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Veículo inválido.',
            'acao.required' => 'Ação inválida.',
            'acao.in' => 'Ação inválida.',
        ]);

        $veiculo = Veiculo::query()->find($dados['id']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        $adminId = $request->user('admin')->id;

        if ($dados['acao'] === 'eliminar') {
            return $this->eliminar($adminId, $veiculo);
        }

        if ($dados['acao'] === 'libertar_ponto') {
            return $this->libertarPonto($adminId, $veiculo);
        }

        $aprovar = $dados['acao'] === 'aprovar';

        $veiculo->forceFill(['aprovado' => $aprovar])->save();

        $this->auditoria->registar(
            $adminId,
            $aprovar ? 'aprovou_veiculo' : 'rejeitou_veiculo',
            'veiculos',
            $veiculo->id
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Levanta o bloqueio de 24 h do ponto de partida/destino (ver
     * Condutor\VeiculoController::definirPonto): o botão "Guardar" volta a
     * aparecer no painel do condutor no refrescamento seguinte.
     *
     * Fica sempre em auditoria, com a hora até quando o bloqueio ia durar —
     * é uma exceção concedida a um condutor concreto e tem de ter dono.
     */
    private function libertarPonto(int $adminId, Veiculo $veiculo): JsonResponse
    {
        if ($veiculo->ponto_bloqueado_ate === null) {
            return response()->json(['erro' => 'Este veículo já pode alterar o ponto.'], 422);
        }

        $ate = $veiculo->ponto_bloqueado_ate->format('Y-m-d H:i');

        $veiculo->forceFill(['ponto_bloqueado_ate' => null])->save();

        $this->auditoria->registar(
            $adminId,
            'libertou_ponto_veiculo',
            'veiculos',
            $veiculo->id,
            "bloqueio_ate={$ate}"
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * Apagar o veículo arrasta em cascata os assentos, as reservas, as
     * comunicações e os pagamentos ligados a ele (FKs onDelete cascade) — por
     * isso recusa enquanto houver passageiros com reserva viva, que ficariam
     * sem viagem e sem registo dela.
     */
    private function eliminar(int $adminId, Veiculo $veiculo): JsonResponse
    {
        $temReservasAtivas = Reserva::query()
            ->where('veiculo_id', $veiculo->id)
            ->whereIn('estado', Reserva::ESTADOS_ATIVOS)
            ->exists();

        if ($temReservasAtivas) {
            return response()->json(
                ['erro' => 'O veículo tem reservas ativas. Conclua ou cancele essas viagens antes de o eliminar.'],
                422
            );
        }

        $id = $veiculo->id;
        $matricula = $veiculo->matricula;

        $veiculo->delete();

        $this->auditoria->registar($adminId, 'eliminou_veiculo', 'veiculos', $id, $matricula);

        return response()->json(['sucesso' => true]);
    }
}
