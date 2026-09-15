<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\AssentoVeiculo;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Rules\CoordenadaCaboVerde;
use App\Services\AvisoViagemService;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Posição GPS do condutor (secções 9.5 e 9.7).
 *
 * Deteta a proximidade dos pontos de descida (<500 m) e a chegada ao destino
 * (<100 m); nesse caso conclui as viagens a bordo, avisa os passageiros de
 * que o veículo chegou ao ponto e inverte automaticamente ponto de partida e
 * destino para o regresso.
 */
class PosicaoController extends Controller
{
    private const RAIO_CHEGADA_M = 100;

    private const RAIO_AVISO_DESCIDA_M = 500;

    public function __construct(
        private readonly GeoService $geo,
        private readonly AvisoViagemService $avisos,
    ) {}

    public function atualizar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
            // Uma leitura de baixa precisão aqui não é só um marcador feio no
            // mapa: a deteção de chegada e os avisos de proximidade confiam
            // nesta coordenada, e uma leitura má podia concluir por engano a
            // viagem de todos os passageiros.
            'accuracy' => ['nullable', 'numeric', 'max:100'],
        ], [
            'veiculo_id.required' => 'Dados de posição inválidos.',
            'lat.required' => 'Dados de posição inválidos.',
            'lng.required' => 'Dados de posição inválidos.',
            'accuracy.max' => 'Precisão do GPS insuficiente.',
        ]);

        $lat = (float) $dados['lat'];
        $lng = (float) $dados['lng'];

        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first();

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        // Com ponto de partida definido e rota ainda por iniciar, o veículo
        // fica ancorado nas coordenadas do ponto: o GPS do telemóvel não pode
        // arrastar o marcador para fora do ponto antes de "Iniciar rota".
        // Veículos sem ponto (modo urbano, a circular livremente) não são
        // afetados por esta regra.
        if ($veiculo->ponto_partida_id !== null
            && ! in_array($veiculo->estado, ['partiu_da_fila', 'em_movimento'], true)) {
            return response()->json(
                ['erro' => 'Veículo ainda não iniciou a rota — clique em "Iniciar rota" para partir do ponto.'],
                403
            );
        }

        // Ao sair da fila o veículo fica 'partiu_da_fila'; a primeira posição
        // recebida depois disso transita para 'em_movimento'.
        $novoEstado = $veiculo->estado === 'partiu_da_fila' ? 'em_movimento' : $veiculo->estado;

        $veiculo->forceFill([
            'lat' => $lat,
            'lng' => $lng,
            'ultima_posicao_em' => now(),
            'estado' => $novoEstado,
        ])->save();

        $resposta = [
            'sucesso' => true,
            'estado' => $novoEstado,
            'chegou_destino' => false,
            'passageiros_proximos' => $this->passageirosProximos($veiculo, $lat, $lng),
        ];

        $pontoDeChegada = $this->pontoDeChegada($veiculo, $lat, $lng);

        if ($pontoDeChegada !== null) {
            $resposta = array_merge($resposta, $this->concluirChegada($veiculo, $pontoDeChegada));
        }

        return response()->json($resposta);
    }

    /**
     * Avisos de proximidade aos pontos de descida das reservas ativas.
     *
     * @return array<int, array{reserva_id: int, ponto_descida_nome: ?string, distancia_m: int}>
     */
    private function passageirosProximos(Veiculo $veiculo, float $lat, float $lng): array
    {
        $reservas = Reserva::query()
            ->where('veiculo_id', $veiculo->id)
            ->whereIn('estado', Reserva::ESTADOS_ACEITES)
            ->whereNotNull('ponto_descida_lat')
            ->whereNotNull('ponto_descida_lng')
            ->get(['id', 'ponto_descida_nome', 'ponto_descida_lat', 'ponto_descida_lng']);

        $proximos = [];

        foreach ($reservas as $reserva) {
            $distancia = $this->geo->distanciaMetros(
                $lat, $lng, $reserva->ponto_descida_lat, $reserva->ponto_descida_lng
            );

            if ($distancia < self::RAIO_AVISO_DESCIDA_M) {
                $proximos[] = [
                    'reserva_id' => $reserva->id,
                    'ponto_descida_nome' => $reserva->ponto_descida_nome,
                    'distancia_m' => (int) round($distancia),
                ];
            }
        }

        return $proximos;
    }

    /**
     * O ponto de destino, quando o veículo já lá está (<100 m); null enquanto
     * ainda vai a caminho. Devolve o ponto, e não um booleano, porque o nome
     * dele vai no aviso ao passageiro — e a seguir a esta chegada o veículo
     * já tem partida e destino trocados, deixando de o saber dizer.
     */
    private function pontoDeChegada(Veiculo $veiculo, float $lat, float $lng): ?PontoPartida
    {
        if (! $veiculo->destino_id) {
            return null;
        }

        $destino = PontoPartida::query()->find($veiculo->destino_id);

        if ($destino === null
            || $this->geo->distanciaMetros($lat, $lng, $destino->lat, $destino->lng) >= self::RAIO_CHEGADA_M) {
            return null;
        }

        return $destino;
    }

    /**
     * Conclui as viagens a bordo, liberta todos os lugares e inverte ponto de
     * partida e destino, ficando à espera da decisão do condutor (entrar na
     * fila ou seguir livre).
     *
     * Ninguém carrega em nada para isto acontecer — é o GPS do condutor que a
     * deteta — por isso o passageiro é avisado por push de que o veículo
     * chegou ao ponto; sem isso, a viagem dele fechava-se em silêncio.
     *
     * @return array<string, mixed>
     */
    private function concluirChegada(Veiculo $veiculo, PontoPartida $ponto): array
    {
        $novoPontoPartida = $veiculo->destino_id;
        $novoDestino = $veiculo->ponto_partida_id;

        // Lida antes da transação lhes mudar o estado: depois de concluídas já
        // não há como saber quem vinha nesta viagem.
        $passageiros = Reserva::query()
            ->where('veiculo_id', $veiculo->id)
            ->whereIn('estado', Reserva::ESTADOS_ACEITES)
            ->pluck('passageiro_id')
            ->all();

        try {
            DB::transaction(function () use ($veiculo, $novoPontoPartida, $novoDestino) {
                Reserva::query()
                    ->where('veiculo_id', $veiculo->id)
                    ->whereIn('estado', Reserva::ESTADOS_ACEITES)
                    ->update(['estado' => 'concluido']);

                // Os passageiros sem app descem aqui como os outros.
                AssentoVeiculo::query()
                    ->where('veiculo_id', $veiculo->id)
                    ->update(['ocupado' => false, 'reserva_id' => null, 'ocupado_sem_app' => false, 'nome_sem_app' => null]);

                $veiculo->forceFill([
                    'estado' => 'chegou_destino',
                    'ponto_partida_id' => $novoPontoPartida,
                    'destino_id' => $novoDestino,
                    'lugares_livres' => $veiculo->lugares_total,
                    'posicao_fila' => null,
                ])->save();
            });
        } catch (Throwable) {
            // A chegada é detetada outra vez na próxima posição recebida; não
            // vale a pena falhar o pedido de posição por causa disto.
            return [];
        }

        $this->avisos->chegouAoPonto($veiculo, $passageiros, $ponto);

        return [
            'chegou_destino' => true,
            'estado' => 'chegou_destino',
            'novo_ponto_partida_id' => $novoPontoPartida,
            'novo_destino_id' => $novoDestino,
        ];
    }
}
