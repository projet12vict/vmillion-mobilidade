<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passageiro\PedirPontoRequest;
use App\Http\Requests\Passageiro\PedirUrbanoRequest;
use App\Http\Requests\Passageiro\ReservarRequest;
use App\Models\AssentoVeiculo;
use App\Models\AvaliacaoCondutor;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Rules\CoordenadaCaboVerde;
use App\Services\AvisoPedidoService;
use App\Services\GeoService;
use App\Services\OsrmService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ciclo de vida da reserva do lado do passageiro (secção 8 e 13).
 */
class ReservaController extends Controller
{
    public function __construct(
        private readonly PricingService $precos,
        private readonly OsrmService $osrm,
        private readonly GeoService $geo,
        private readonly AvisoPedidoService $avisos,
    ) {}

    /**
     * Cria a reserva: escolha de veículo, assento e ponto de descida, com o
     * preço calculado no momento (secção 8, passos 6-8).
     *
     * A transação bloqueia a linha do veículo e a do assento para evitar que
     * duas reservas concorrentes fiquem com o mesmo lugar.
     */
    public function reservar(ReservarRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $passageiro = $request->user('utilizador');

        $tipoViagem = $dados['tipo_viagem'] ?? 'intermunicipal';
        $descidaNome = trim((string) ($dados['ponto_descida_nome'] ?? ''));
        $descidaLat = isset($dados['ponto_descida_lat']) ? (float) $dados['ponto_descida_lat'] : null;
        $descidaLng = isset($dados['ponto_descida_lng']) ? (float) $dados['ponto_descida_lng'] : null;

        // Numa viagem urbana o destino não vem de uma lista: é sempre o ponto
        // de descida escrito/geocodificado pelo passageiro. destino_id fica
        // igual a ponto_partida_id só para satisfazer a FK — não influencia o
        // preço (ver PricingService::calcularPrecoUrbano).
        $destinoId = $tipoViagem === 'urbano'
            ? (int) $dados['ponto_partida_id']
            : (int) $dados['destino_id'];

        try {
            $resultado = DB::transaction(function () use (
                $dados, $passageiro, $tipoViagem, $destinoId, $descidaNome, $descidaLat, $descidaLng
            ) {
                $veiculo = Veiculo::query()->lockForUpdate()->find($dados['veiculo_id']);

                if (! $veiculo || ! $veiculo->aprovado || $veiculo->lugares_livres < 1) {
                    throw new RuntimeException('Este veículo já não tem lugares disponíveis.');
                }

                $assento = AssentoVeiculo::query()
                    ->lockForUpdate()
                    ->where('id', $dados['assento_id'])
                    ->where('veiculo_id', $veiculo->id)
                    ->first();

                // Um lugar retirado pelo condutor (VeiculoService::retirarLugar)
                // continua na tabela por causa do histórico, mas já não existe
                // no carro — e um mapa de assentos aberto antes de ele o
                // retirar ainda o mostra.
                if (! $assento || ! $assento->ativo) {
                    throw new RuntimeException('Este lugar já não está disponível neste veículo.');
                }

                if ($assento->ocupado) {
                    throw new RuntimeException('Este lugar já foi reservado por outro passageiro.');
                }

                $pontoPartida = PontoPartida::query()->find($dados['ponto_partida_id']);
                $destino = PontoPartida::query()->find($destinoId);

                if (! $pontoPartida || ! $destino) {
                    throw new RuntimeException('Ponto de partida ou destino inválido.');
                }

                $preco = $tipoViagem === 'urbano'
                    ? $this->precoUrbano($pontoPartida, $descidaLat, $descidaLng)
                    : $this->precoIntermunicipal($veiculo, $pontoPartida, $destino, $descidaLat, $descidaLng);

                $reserva = Reserva::query()->create([
                    'passageiro_id' => $passageiro->id,
                    'veiculo_id' => $veiculo->id,
                    'assento_id' => $assento->id,
                    'ponto_partida_id' => $pontoPartida->id,
                    'destino_id' => $destino->id,
                    'tipo_viagem' => $tipoViagem,
                    'ponto_descida_nome' => $descidaNome ?: null,
                    'ponto_descida_lat' => $descidaLat,
                    'ponto_descida_lng' => $descidaLng,
                    'lugares' => $dados['lugares'] ?? 1,
                    'motivo' => $dados['motivo'] ?? 'normal',
                    'preco_final' => $preco,
                    'estado' => 'pendente',
                ]);

                $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
                $veiculo->decrement('lugares_livres');

                return ['reserva' => $reserva, 'veiculo' => $veiculo, 'preco' => $preco];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 409);
        }

        if ($tipoViagem === 'urbano' && $descidaNome !== '' && $descidaLat !== null && $descidaLng !== null) {
            $this->precos->guardarDestinoUrbano($descidaNome, $descidaLat, $descidaLng, $passageiro->id);
        }

        $this->avisos->reservaNoVeiculo($resultado['reserva'], $resultado['veiculo']);

        return response()->json([
            'sucesso' => true,
            'reserva_id' => $resultado['reserva']->id,
            'preco_final' => $resultado['preco'],
            'veiculo_id' => $resultado['veiculo']->id,
            'condutor_id' => $resultado['veiculo']->condutor_id,
        ]);
    }

    /**
     * Pedido de viagem urbana em aberto: não exige veículo nem assento e fica
     * visível a qualquer condutor aprovado e em dia até ser reclamado, altura
     * em que segue exatamente o mesmo fluxo de sempre.
     */
    public function pedirUrbano(PedirUrbanoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $passageiro = $request->user('utilizador');

        // Mesma regra do fluxo normal: um pedido/reserva ativo de cada vez.
        if ($erro = $this->recusarSeJaTemReservaAtiva($passageiro->id)) {
            return $erro;
        }

        $pontoPartida = PontoPartida::query()
            ->whereKey($dados['ponto_partida_id'])
            ->where('status', 'aprovado')
            ->first();

        if (! $pontoPartida) {
            return response()->json(['erro' => 'Ponto de referência inválido.'], 422);
        }

        $origemLat = (float) $dados['origem_lat'];
        $origemLng = (float) $dados['origem_lng'];
        $descidaLat = (float) $dados['ponto_descida_lat'];
        $descidaLng = (float) $dados['ponto_descida_lng'];
        $descidaNome = trim($dados['ponto_descida_nome']);

        $distanciaKm = $this->osrm->distanciaKmOuLinhaReta(
            $this->geo, $origemLat, $origemLng, $descidaLat, $descidaLng
        );
        $preco = $this->precos->calcularPrecoUrbano($distanciaKm)['preco'];

        $reserva = Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => null,
            'assento_id' => null,
            'ponto_partida_id' => $pontoPartida->id,
            'destino_id' => $pontoPartida->id,
            'tipo_viagem' => 'urbano',
            'ponto_descida_nome' => $descidaNome,
            'ponto_descida_lat' => $descidaLat,
            'ponto_descida_lng' => $descidaLng,
            'passageiro_lat' => $origemLat,
            'passageiro_lng' => $origemLng,
            'passageiro_localizacao_em' => now(),
            'lugares' => $dados['lugares'] ?? 1,
            'motivo' => $dados['motivo'] ?? 'normal',
            'preco_final' => $preco,
            'estado' => 'pendente',
        ]);

        $this->precos->guardarDestinoUrbano($descidaNome, $descidaLat, $descidaLng, $passageiro->id);

        $this->avisos->pedidoUrbano($reserva);

        return response()->json([
            'sucesso' => true,
            'reserva_id' => $reserva->id,
            'preco_estimado' => $preco,
        ]);
    }

    /**
     * Pedido intermunicipal em aberto: o passageiro escolhe só o ponto de
     * partida e o destino da carreira, sem escolher veículo. O pedido fica
     * visível a todos os condutores ancorados nesse ponto e o primeiro a
     * carregar em "Ir buscar" fica com ele (Condutor\PassageiroController).
     *
     * É o contraponto de reservar(): escolher um lugar concreto no mapa de
     * assentos prende a reserva a um único condutor logo à partida.
     */
    public function pedirPonto(PedirPontoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $passageiro = $request->user('utilizador');

        if ($erro = $this->recusarSeJaTemReservaAtiva($passageiro->id)) {
            return $erro;
        }

        $pontoPartida = PontoPartida::query()
            ->whereKey($dados['ponto_partida_id'])
            ->where('status', 'aprovado')
            ->first();

        $destino = PontoPartida::query()
            ->whereKey($dados['destino_id'])
            ->where('status', 'aprovado')
            ->first();

        if (! $pontoPartida || ! $destino) {
            return response()->json(['erro' => 'Ponto de partida ou destino inválido.'], 422);
        }

        if ($pontoPartida->id === $destino->id) {
            return response()->json(['erro' => 'O destino tem de ser diferente do ponto de partida.'], 422);
        }

        $descidaNome = trim((string) ($dados['ponto_descida_nome'] ?? ''));
        $descidaLat = isset($dados['ponto_descida_lat']) ? (float) $dados['ponto_descida_lat'] : null;
        $descidaLng = isset($dados['ponto_descida_lng']) ? (float) $dados['ponto_descida_lng'] : null;

        // Preço sem veículo: por km, o que é uma estimativa — só ao ser
        // reclamado é que se sabe que carro faz a viagem. Um condutor com
        // rota fixa traz a tarifa dela, e é essa que passa a valer (ver
        // Condutor\PassageiroController::recolher); sem rota fixa, o valor
        // aqui calculado é o que o passageiro paga.
        $preco = $this->precoIntermunicipal(null, $pontoPartida, $destino, $descidaLat, $descidaLng);

        $reserva = Reserva::query()->create([
            'passageiro_id' => $passageiro->id,
            'veiculo_id' => null,
            'assento_id' => null,
            'ponto_partida_id' => $pontoPartida->id,
            'destino_id' => $destino->id,
            'tipo_viagem' => 'intermunicipal',
            'ponto_descida_nome' => $descidaNome ?: null,
            'ponto_descida_lat' => $descidaLat,
            'ponto_descida_lng' => $descidaLng,
            'lugares' => $dados['lugares'] ?? 1,
            'motivo' => $dados['motivo'] ?? 'normal',
            'preco_final' => $preco,
            'estado' => 'pendente',
        ]);

        $this->avisos->pedidoNoPonto($reserva);

        return response()->json([
            'sucesso' => true,
            'reserva_id' => $reserva->id,
            'ponto_partida_id' => $pontoPartida->id,
            'preco_estimado' => $preco,
        ]);
    }

    /**
     * Reserva ativa do passageiro e contacto do condutor — o telefone só é
     * revelado depois de a reserva estar confirmada (secção 13).
     *
     * Um pedido urbano ainda por reclamar tem veiculo_id a NULL: continua a
     * ser "a reserva ativa", só sem esses dados disponíveis ainda.
     */
    public function minhaReserva(Request $request): JsonResponse
    {
        $passageiroId = $request->user('utilizador')->id;

        $reserva = Reserva::query()
            ->leftJoin('veiculos as v', 'v.id', '=', 'reservas.veiculo_id')
            ->leftJoin('utilizadores as u', 'u.id', '=', 'v.condutor_id')
            ->join('pontos_partida as pp', 'pp.id', '=', 'reservas.ponto_partida_id')
            ->join('pontos_partida as pd', 'pd.id', '=', 'reservas.destino_id')
            ->where('reservas.passageiro_id', $passageiroId)
            ->whereNotIn('reservas.estado', ['concluido', 'recusado'])
            ->orderByDesc('reservas.criado_em')
            ->select([
                'reservas.id', 'reservas.estado', 'reservas.preco_final', 'reservas.tipo_viagem',
                'reservas.ponto_descida_nome', 'reservas.ponto_descida_lat', 'reservas.ponto_descida_lng',
                'reservas.criado_em',
                'v.id as veiculo_id', 'v.matricula', 'v.tipo', 'v.cor',
                'v.lat as veiculo_lat', 'v.lng as veiculo_lng', 'v.estado as veiculo_estado',
                'pp.nome as ponto_partida_nome', 'pp.lat as ponto_partida_lat', 'pp.lng as ponto_partida_lng',
                // Coordenadas do destino da carreira: numa viagem intermunicipal o
                // "ponto de descida" é opcional, por isso é este ponto — e só ele —
                // que diz para onde o veículo vai (usado a bordo, no mapa do
                // passageiro, para manter o destino e a rota à vista).
                'pd.id as destino_id', 'pd.lat as destino_lat', 'pd.lng as destino_lng',
                'pd.nome as destino_nome',
                'u.id as condutor_id', 'u.nome as condutor_nome',
                DB::raw("CASE WHEN reservas.estado IN ('confirmado', 'a_caminho', 'a_bordo') THEN u.telefone ELSE NULL END AS condutor_telefone"),
            ])
            ->first();

        return response()->json([
            'reserva' => $reserva,
            'avaliar_pendente' => $reserva ? null : $this->avaliacaoPendente($passageiroId),
        ]);
    }

    /**
     * O passageiro sai da reserva atual. Ninguém fica refém de um condutor:
     * enquanto não embarcou, pode sair e escolher outro veículo.
     */
    public function cancelar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
        ], ['reserva_id.required' => 'Reserva inválida.']);

        try {
            $resultado = DB::transaction(function () use ($dados, $request) {
                $reserva = Reserva::query()
                    ->lockForUpdate()
                    ->whereKey($dados['reserva_id'])
                    ->where('passageiro_id', $request->user('utilizador')->id)
                    ->first();

                if (! $reserva) {
                    throw new RuntimeException('Reserva não encontrada.', 404);
                }

                // Depois de embarcar (a_bordo) sair já não faz sentido no mapa.
                // Com o condutor a caminho ainda faz: o passageiro continua
                // fora do carro e não fica refém de uma recolha que já não quer.
                if (! in_array($reserva->estado, ['pendente', 'confirmado', 'a_caminho'], true)) {
                    throw new RuntimeException('Já embarcou nesta viagem — não é possível sair agora.', 409);
                }

                $reserva->forceFill(['estado' => 'recusado'])->save();

                return [
                    'veiculo_id' => $reserva->veiculo_id,
                    'lugares_livres' => $this->libertarLugar($reserva),
                ];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json(['sucesso' => true] + $resultado);
    }

    /**
     * O passageiro fecha a própria viagem quando o condutor não clicou em
     * "Entregue". O primeiro dos dois lados a agir é que vale: depois de
     * concluída, a reserva já não está 'a_bordo' e não pode ser processada
     * outra vez por nenhum deles.
     */
    public function finalizar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
        ], ['reserva_id.required' => 'Reserva inválida.']);

        try {
            $resultado = DB::transaction(function () use ($dados, $request) {
                $reserva = Reserva::query()
                    ->lockForUpdate()
                    ->whereKey($dados['reserva_id'])
                    ->where('passageiro_id', $request->user('utilizador')->id)
                    ->whereNotNull('veiculo_id')
                    ->first();

                if (! $reserva) {
                    throw new RuntimeException('Reserva não encontrada.', 404);
                }

                if ($reserva->estado !== 'a_bordo') {
                    throw new RuntimeException('Só pode finalizar depois de embarcar.', 409);
                }

                $reserva->forceFill(['estado' => 'concluido'])->save();

                return [
                    'veiculo_id' => $reserva->veiculo_id,
                    'lugares_livres' => $this->libertarLugar($reserva),
                ];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json(['sucesso' => true] + $resultado);
    }

    /**
     * Ajuste fino do destino, arrastando o marcador no mapa.
     *
     * Não recalcula o preço — fica fixo ao valor combinado na reserva; o
     * ajuste serve só para a navegação e a exibição ficarem corretas. Também
     * não toca em destinos_urbanos: um ajuste pessoal não deve mudar a
     * sugestão que os outros passageiros veem.
     */
    public function atualizarDestino(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
            'lat' => ['required', 'numeric', new CoordenadaCaboVerde('lng')],
            'lng' => ['required', 'numeric'],
        ], [
            'reserva_id.required' => 'Dados inválidos.',
            'lat.required' => 'Dados inválidos.',
            'lng.required' => 'Dados inválidos.',
        ]);

        $atualizadas = Reserva::query()
            ->whereKey($dados['reserva_id'])
            ->where('passageiro_id', $request->user('utilizador')->id)
            ->whereNotIn('estado', ['concluido', 'recusado'])
            ->update([
                'ponto_descida_lat' => (float) $dados['lat'],
                'ponto_descida_lng' => (float) $dados['lng'],
            ]);

        if ($atualizadas === 0) {
            return response()->json(['erro' => 'Reserva não encontrada.'], 404);
        }

        return response()->json([
            'sucesso' => true,
            'lat' => (float) $dados['lat'],
            'lng' => (float) $dados['lng'],
        ]);
    }

    /**
     * Liberta o assento e devolve o lugar ao veículo. Devolve o novo número
     * de lugares livres, ou null se a reserva ainda não tinha veículo (pedido
     * urbano por reclamar).
     */
    private function libertarLugar(Reserva $reserva): ?int
    {
        if (! $reserva->veiculo_id) {
            return null;
        }

        AssentoVeiculo::query()
            ->where('reserva_id', $reserva->id)
            ->update(['ocupado' => false, 'reserva_id' => null]);

        DB::table('veiculos')
            ->where('id', $reserva->veiculo_id)
            ->update(['lugares_livres' => DB::raw('LEAST(lugares_total, lugares_livres + 1)')]);

        return (int) Veiculo::query()->whereKey($reserva->veiculo_id)->value('lugares_livres');
    }

    /**
     * Viagem concluída e ainda por avaliar (secção 9).
     */
    private function avaliacaoPendente(int $passageiroId): ?object
    {
        return Reserva::query()
            ->join('veiculos as v', 'v.id', '=', 'reservas.veiculo_id')
            ->join('utilizadores as u', 'u.id', '=', 'v.condutor_id')
            ->where('reservas.passageiro_id', $passageiroId)
            ->where('reservas.estado', 'concluido')
            ->whereNull('reservas.avaliacao_dispensada_em')
            ->whereNotExists(fn ($q) => $q->from((new AvaliacaoCondutor)->getTable().' as a')
                ->whereColumn('a.reserva_id', 'reservas.id')
            )
            ->orderByDesc('reservas.atualizado_em')
            ->select([
                'reservas.id as reserva_id',
                'u.id as condutor_id',
                'u.nome as condutor_nome',
                'v.matricula',
            ])
            ->first();
    }

    private function precoUrbano(PontoPartida $pontoPartida, float $descidaLat, float $descidaLng): float
    {
        // Sem segmentação de zonas nem rota fixa a considerar: é sempre
        // tarifa urbana sobre a distância até ao ponto de descida escrito.
        $distanciaKm = $this->osrm->distanciaKmOuLinhaReta(
            $this->geo, $pontoPartida->lat, $pontoPartida->lng, $descidaLat, $descidaLng
        );

        return $this->precos->calcularPrecoUrbano($distanciaKm)['preco'];
    }

    /**
     * Um pedido/reserva ativo de cada vez: sem isto o mesmo passageiro podia
     * ficar à espera em vários pontos ao mesmo tempo e prender um lugar em
     * cada carro que o reclamasse.
     */
    private function recusarSeJaTemReservaAtiva(int $passageiroId): ?JsonResponse
    {
        $temReservaAtiva = Reserva::query()
            ->where('passageiro_id', $passageiroId)
            ->whereNotIn('estado', ['concluido', 'recusado'])
            ->exists();

        if (! $temReservaAtiva) {
            return null;
        }

        return response()->json(
            ['erro' => 'Já tem uma reserva ativa. Saia dela antes de pedir uma nova viagem.'],
            409
        );
    }

    private function precoIntermunicipal(
        ?Veiculo $veiculo,
        PontoPartida $pontoPartida,
        PontoPartida $destino,
        ?float $descidaLat,
        ?float $descidaLng
    ): float {
        $temDescida = $descidaLat !== null && $descidaLng !== null;

        $distanciaTotalKm = $this->geo->distanciaKm(
            $pontoPartida->lat, $pontoPartida->lng, $destino->lat, $destino->lng
        );
        $distanciaAteDescidaKm = $temDescida
            ? $this->geo->distanciaKm($pontoPartida->lat, $pontoPartida->lng, $descidaLat, $descidaLng)
            : $distanciaTotalKm;

        // A rota real por estrada é mais longa do que a linha reta; o fator
        // preserva a proporção do troço do passageiro dentro da rota total.
        $rotaKm = $this->osrm->distanciaKmOuLinhaReta(
            $this->geo, $pontoPartida->lat, $pontoPartida->lng, $destino->lat, $destino->lng
        );

        if ($rotaKm > 0) {
            $fator = $distanciaAteDescidaKm / max($distanciaTotalKm, 0.001);
            $distanciaTotalKm = $rotaKm;
            $distanciaAteDescidaKm = $temDescida ? $rotaKm * $fator : $rotaKm;
        }

        $descidaCoord = $temDescida ? ['lat' => $descidaLat, 'lng' => $descidaLng] : null;

        return $this->precos->calcularPrecoViagem(
            $veiculo, $pontoPartida, $destino, $distanciaTotalKm, $distanciaAteDescidaKm, $descidaCoord
        )['preco'];
    }
}
