<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\AssentoVeiculo;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Services\AuditoriaService;
use App\Services\AvisoViagemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fila do ponto de partida (secção 9.3). Estar na fila dá prioridade na
 * recolha de passageiros.
 *
 * As duas pontas são avisadas por AvisoViagemService: entrar na fila é um
 * carro a mais para quem espera naquele ponto, sair dela é um carro a menos —
 * e nenhuma das duas coisas se vê de fora sem o painel aberto.
 */
class FilaController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly AvisoViagemService $avisos,
    ) {}

    public function entrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        try {
            $entrada = DB::transaction(function () use ($dados, $request) {
                $veiculo = Veiculo::query()
                    ->lockForUpdate()
                    ->whereKey($dados['veiculo_id'])
                    ->where('condutor_id', $request->user('utilizador')->id)
                    ->first();

                if (! $veiculo) {
                    throw new RuntimeException('Veículo não encontrado.', 404);
                }
                if (! $veiculo->aprovado) {
                    throw new RuntimeException('O veículo ainda não foi aprovado.', 403);
                }
                if (! $veiculo->ponto_partida_id) {
                    throw new RuntimeException('Escolha primeiro o ponto de partida.', 422);
                }

                $novaPosicao = (int) Veiculo::query()
                    ->where('ponto_partida_id', $veiculo->ponto_partida_id)
                    ->where('estado', 'na_fila')
                    ->max('posicao_fila') + 1;

                $veiculo->forceFill(['estado' => 'na_fila', 'posicao_fila' => $novaPosicao])->save();

                return ['veiculo' => $veiculo, 'posicao' => $novaPosicao];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        // Fora da transação: um aviso não pode segurar a linha do veículo
        // trancada nem fazer reverter a entrada na fila se falhar.
        $this->avisos->entrouNaFila($entrada['veiculo'], $entrada['posicao']);

        return response()->json([
            'sucesso' => true,
            'estado' => 'na_fila',
            'posicao_fila' => $entrada['posicao'],
        ]);
    }

    /**
     * Sair da fila, com justificação obrigatória. É também o "Iniciar rota
     * (sair do ponto)" do painel: em qualquer dos casos o veículo deixa o
     * ponto de partida.
     *
     * Os passageiros com reserva pendente ou confirmada nesse veículo são
     * libertados (lugar devolvido, reserva marcada como recusada) e avisados
     * de imediato, para poderem reservar outro veículo no mesmo ponto. É a
     * forma mais transparente de lhes dar prioridade face a novos pedidos:
     * uma reatribuição automática entre veículos exigiria um motor de
     * emparelhamento próprio, fora do âmbito desta fase.
     *
     * Tanto esses como os que seguem viagem levam com o veículo a partir do
     * ponto — a saída é avisada por push aos dois grupos (AvisoViagemService),
     * com mensagens diferentes, porque quem ficou no ponto sem lugar precisa
     * de saber que tem de reservar outra vez.
     */
    public function sair(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'motivo' => ['required', 'string'],
        ], [
            'veiculo_id.required' => 'Indique o motivo para sair da fila.',
            'motivo.required' => 'Indique o motivo para sair da fila.',
        ]);

        try {
            $saida = DB::transaction(function () use ($dados, $request) {
                $veiculo = Veiculo::query()
                    ->lockForUpdate()
                    ->whereKey($dados['veiculo_id'])
                    ->where('condutor_id', $request->user('utilizador')->id)
                    ->first();

                if (! $veiculo) {
                    throw new RuntimeException('Veículo não encontrado.', 404);
                }

                $reservasAfetadas = Reserva::query()
                    ->where('veiculo_id', $veiculo->id)
                    ->whereIn('estado', ['pendente', 'confirmado'])
                    ->get(['id', 'passageiro_id']);

                if ($reservasAfetadas->isNotEmpty()) {
                    Reserva::query()->whereIn('id', $reservasAfetadas->pluck('id'))
                        ->update(['estado' => 'recusado']);

                    AssentoVeiculo::query()->whereIn('reserva_id', $reservasAfetadas->pluck('id'))
                        ->update(['ocupado' => false, 'reserva_id' => null]);
                }

                // A lotação não volta a cheio só por o veículo sair do ponto:
                // as reservas libertadas acima são as pendentes/confirmadas, e
                // quem já vai a bordo (ou está a ser recolhido) continua a
                // ocupar o lugar. Repor 'lugares_total' aqui punha o carro a
                // anunciar-se vazio com passageiros lá dentro — e a aceitar
                // mais reservas do que os lugares que lhe sobram.
                //
                // São exatamente os mesmos que partem com o veículo, por isso
                // esta lista serve as duas coisas: contar os lugares que ficam
                // ocupados e avisar quem vai na viagem.
                $emViagem = Reserva::query()
                    ->where('veiculo_id', $veiculo->id)
                    ->whereIn('estado', Reserva::ESTADOS_ATIVOS)
                    ->pluck('passageiro_id');

                // Os passageiros sem app (VeiculoService::ocuparSemApp) também
                // seguem no carro, mas não têm reserva — contam-se à parte.
                $semApp = AssentoVeiculo::query()
                    ->where('veiculo_id', $veiculo->id)
                    ->where('ocupado_sem_app', true)
                    ->count();

                $veiculo->forceFill([
                    'estado' => 'partiu_da_fila',
                    'posicao_fila' => null,
                    'lugares_livres' => max(0, (int) $veiculo->lugares_total - $emViagem->count() - $semApp),
                ])->save();

                $this->auditoria->registar(null, 'condutor_saiu_fila', 'veiculos', $veiculo->id, trim($dados['motivo']));

                return [
                    'veiculo' => $veiculo,
                    'em_viagem' => $emViagem->all(),
                    'sem_lugar' => $reservasAfetadas->pluck('passageiro_id')->all(),
                ];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        $this->avisos->partiuDoPonto($saida['veiculo'], $saida['em_viagem'], $saida['sem_lugar']);

        return response()->json([
            'sucesso' => true,
            'estado' => 'partiu_da_fila',
            'passageiros_notificar' => $saida['sem_lugar'],
        ]);
    }
}
