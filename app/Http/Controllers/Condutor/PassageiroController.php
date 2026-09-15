<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\AssentoVeiculo;
use App\Models\ComunicacaoVeiculo;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Veiculo;
use App\Services\AuditoriaService;
use App\Services\AvisoViagemService;
use App\Services\GeoService;
use App\Services\MunicipioService;
use App\Services\PricingService;
use App\Services\VeiculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Passageiros do condutor: lista do veículo, procura urbana em aberto e
 * transições de estado da reserva (secção 9.4).
 */
class PassageiroController extends Controller
{
    /**
     * Recusa e aviso de um veículo intermunicipal que ainda não gravou o
     * ponto: a mesma frase no botão bloqueado e na resposta do servidor.
     */
    private const PONTO_POR_GUARDAR = 'Escolha o ponto de partida e o destino em "Veículo ativo" e carregue em "Guardar" antes de embarcar ou entregar passageiros.';

    public function __construct(
        private readonly GeoService $geo,
        private readonly VeiculoService $veiculos,
        private readonly AuditoriaService $auditoria,
        private readonly PricingService $precos,
        private readonly MunicipioService $municipios,
        private readonly AvisoViagemService $avisos,
    ) {}

    /**
     * Passageiros que escolheram um veículo do condutor.
     *
     * O contacto do passageiro já é visível aqui: a reserva existe, portanto
     * o passageiro já escolheu este veículo (secção 13).
     */
    public function doVeiculo(Request $request): JsonResponse
    {
        if ($erro = $this->bloqueadoPorGate($request)) {
            return $erro;
        }

        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first(['id', 'lat', 'lng', 'lugares_total', 'lugares_livres', 'ponto_partida_id', 'destino_id']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não pertence ao condutor autenticado.'], 403);
        }

        $passageiros = Reserva::query()
            ->join('utilizadores as u', 'u.id', '=', 'reservas.passageiro_id')
            ->join('pontos_partida as pp', 'pp.id', '=', 'reservas.ponto_partida_id')
            ->where('reservas.veiculo_id', $veiculo->id)
            ->whereIn('reservas.estado', Reserva::ESTADOS_ATIVOS)
            ->orderBy('reservas.criado_em')
            ->get([
                'reservas.id', 'reservas.estado', 'reservas.preco_final', 'reservas.tipo_viagem',
                'reservas.ponto_descida_nome', 'reservas.ponto_descida_lat', 'reservas.ponto_descida_lng',
                'reservas.lugares', 'reservas.passageiro_lat', 'reservas.passageiro_lng',
                'reservas.passageiro_localizacao_em',
                'u.id as passageiro_id', 'u.nome as passageiro_nome', 'u.telefone as passageiro_telefone',
                'pp.nome as ponto_partida_nome', 'pp.lat as ponto_partida_lat', 'pp.lng as ponto_partida_lng',
            ]);

        $temPosicaoVeiculo = $veiculo->lat !== null && $veiculo->lng !== null;
        $ocupacao = $this->veiculos->ocupacao($veiculo);
        $lotado = $ocupacao['a_bordo'] >= $ocupacao['lugares_total'];

        $passageiros->each(function ($p) use ($veiculo, $temPosicaoVeiculo, $lotado) {
            // Posição a mostrar no mapa: a localização GPS ao vivo do
            // passageiro quando existe, com recurso ao ponto de partida
            // enquanto o telemóvel dele ainda não enviou a primeira posição.
            $p->mapa_lat = $p->passageiro_lat ?? $p->ponto_partida_lat;
            $p->mapa_lng = $p->passageiro_lng ?? $p->ponto_partida_lng;

            // Quem tem marcador próprio no mapa e quem já pode embarcar. Vem
            // decidido do servidor (e não do estado do cliente) para o mapa
            // ficar igual depois de um refresh, e para o painel nunca mostrar
            // um botão que o servidor vai recusar.
            $p->no_mapa = $p->estado === 'a_caminho';
            $p->pode_ir_buscar = in_array($p->estado, Reserva::ESTADOS_ANTES_DA_RECOLHA, true);

            $semPonto = $this->faltaGuardarPonto($p, $veiculo);

            $p->pode_embarcar = $p->estado === 'a_caminho' && ! $lotado && ! $semPonto;
            $p->pode_entregar = $p->estado === 'a_bordo' && ! $semPonto;
            $p->motivo_bloqueio = match (true) {
                $semPonto && in_array($p->estado, ['a_caminho', 'a_bordo'], true) => self::PONTO_POR_GUARDAR,
                $p->estado === 'a_caminho' && $lotado => 'Veículo cheio — nenhum lugar livre para embarcar.',
                default => null,
            };

            // Distância até ao ponto de partida do passageiro, para o
            // condutor saber a quem vai buscar primeiro.
            $p->distancia_m = $temPosicaoVeiculo
                ? (int) round($this->geo->distanciaMetros(
                    (float) $veiculo->lat, (float) $veiculo->lng,
                    (float) $p->ponto_partida_lat, (float) $p->ponto_partida_lng
                ))
                : null;
        });

        if ($temPosicaoVeiculo) {
            $passageiros = $passageiros->sortBy('distancia_m')->values();
        }

        return response()->json(['passageiros' => $passageiros, 'ocupacao' => $ocupacao]);
    }

    /**
     * Pedidos de viagem urbana em aberto no concelho onde o condutor está
     * agora, visíveis a qualquer condutor aprovado e em dia, mesmo fora de um
     * ponto de partida.
     *
     * A delimitação por município é o alcance da procura urbana: um táxi na
     * Praia não é chamado para quem espera no Tarrafal, a 60 km, e o pedido
     * só é mostrado a quem lhe pode mesmo chegar. A fronteira é a mesma que o
     * mapa do painel desenha (MunicipioService lê o mesmo traçado) e a mesma
     * que o passageiro tem do outro lado — ver
     * Passageiro\VeiculoController::disponiveis.
     *
     * O contacto do passageiro só é revelado depois de o pedido ser
     * reclamado (ver recolher()).
     */
    public function urbanos(Request $request): JsonResponse
    {
        if ($erro = $this->bloqueadoPorGate($request)) {
            return $erro;
        }

        $dados = $request->validate([
            'veiculo_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $veiculo = null;

        if (isset($dados['veiculo_id'])) {
            $veiculo = Veiculo::query()
                ->whereKey($dados['veiculo_id'])
                ->where('condutor_id', $request->user('utilizador')->id)
                ->first(['tipo_servico', 'ponto_partida_id', 'lat', 'lng']);

            if (! $veiculo) {
                return response()->json(['erro' => 'Veículo não encontrado.'], 404);
            }

            // Um veículo 'intermunicipal' nunca vê pedidos urbanos; um
            // 'ambos' só os vê enquanto não tiver ponto de partida definido
            // (com ponto definido está a operar como intermunicipal e vê
            // antes os passageiros desse ponto).
            $elegivel = $veiculo->tipo_servico === 'urbano'
                || ($veiculo->tipo_servico === 'ambos' && $veiculo->ponto_partida_id === null);

            if (! $elegivel) {
                return response()->json([
                    'passageiros' => [],
                    'elegivel' => false,
                    'motivo' => $veiculo->tipo_servico === 'intermunicipal'
                        ? 'Este veículo é só intermunicipal — não recebe pedidos urbanos.'
                        : 'Este veículo tem um ponto de partida definido — a operar como intermunicipal. Remova o ponto para voltar a ver pedidos urbanos.',
                ]);
            }
        }

        // Onde o condutor está: a leitura de GPS que o painel manda a cada
        // posição nova e, enquanto ela não chega, a última posição gravada do
        // veículo (que é a do ponto quando ele ainda lá está parado).
        $condutorLat = $dados['lat'] ?? $veiculo?->lat;
        $condutorLng = $dados['lng'] ?? $veiculo?->lng;
        $condutorLat = $condutorLat !== null ? (float) $condutorLat : null;
        $condutorLng = $condutorLng !== null ? (float) $condutorLng : null;

        $municipio = null;

        // Sem traçado carregado não há como aplicar a regra do concelho: a
        // lista sai como saía antes, em vez de o condutor ficar sem procura
        // nenhuma por causa de um ficheiro em falta (ver MunicipioService).
        if ($this->municipios->disponivel()) {
            if ($condutorLat === null || $condutorLng === null) {
                return response()->json([
                    'passageiros' => [],
                    'elegivel' => false,
                    'motivo' => 'A procura urbana é do seu município — ligue o GPS para o painel saber onde está.',
                ]);
            }

            $municipio = $this->municipios->nomeEm($condutorLat, $condutorLng);

            if ($municipio === null) {
                return response()->json([
                    'passageiros' => [],
                    'elegivel' => false,
                    'motivo' => 'A sua posição está fora dos municípios de Cabo Verde — sem município não há pedidos urbanos para mostrar.',
                ]);
            }
        }

        $caixa = $municipio !== null ? $this->municipios->caixaDe($municipio) : null;

        $passageiros = Reserva::query()
            ->join('utilizadores as u', 'u.id', '=', 'reservas.passageiro_id')
            ->urbanasEmAberto()
            // A caixa envolvente do concelho corta em SQL tudo o que está
            // longe, antes do LIMIT: sem isto, cem pedidos de outra ilha
            // podiam encher a lista e não sobrar nenhum depois do teste exato.
            ->when($caixa, fn ($q, $caixa) => $q
                ->whereBetween('reservas.passageiro_lat', [$caixa['lat_min'], $caixa['lat_max']])
                ->whereBetween('reservas.passageiro_lng', [$caixa['lng_min'], $caixa['lng_max']])
            )
            ->orderBy('reservas.criado_em')
            ->limit(100)
            ->get([
                'reservas.id', 'reservas.ponto_descida_nome',
                'reservas.ponto_descida_lat', 'reservas.ponto_descida_lng',
                'reservas.passageiro_lat', 'reservas.passageiro_lng',
                'reservas.lugares', 'reservas.preco_final', 'reservas.criado_em',
                'u.nome as passageiro_nome',
            ]);

        if ($municipio !== null) {
            // A caixa é um retângulo, a fronteira não: quem ficou dentro da
            // caixa mas fora do concelho sai aqui. É este o teste que manda.
            $passageiros = $passageiros
                ->filter(fn ($p) => $this->municipios->contem(
                    $municipio, (float) $p->passageiro_lat, (float) $p->passageiro_lng
                ))
                ->values();
        }

        if ($condutorLat !== null && $condutorLng !== null) {
            $passageiros->each(function ($p) use ($condutorLat, $condutorLng) {
                $p->distancia_m = (int) round($this->geo->distanciaMetros(
                    $condutorLat, $condutorLng, (float) $p->passageiro_lat, (float) $p->passageiro_lng
                ));
            });

            $passageiros = $passageiros->sortBy('distancia_m')->values();
        } else {
            $passageiros->each(fn ($p) => $p->distancia_m = null);
        }

        return response()->json([
            'passageiros' => $passageiros,
            'elegivel' => true,
            'municipio' => $municipio,
        ]);
    }

    /**
     * Pedidos intermunicipais em aberto no ponto do veículo: o passageiro
     * escolheu o ponto e o destino sem escolher carro, por isso o pedido é de
     * todos os condutores ancorados nesse ponto — o primeiro a carregar em
     * "Ir buscar" fica com ele (ver recolher()).
     *
     * Quem escolhe um lugar concreto no mapa de assentos nunca aparece aqui:
     * essa reserva nasce com veiculo_id e vai direta para doVeiculo(), só ao
     * condutor escolhido.
     */
    public function doPonto(Request $request): JsonResponse
    {
        if ($erro = $this->bloqueadoPorGate($request)) {
            return $erro;
        }

        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first(['id', 'lat', 'lng', 'tipo_servico', 'ponto_partida_id', 'destino_id', 'aprovado']);

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        if ($motivo = $this->motivoParaNaoVerPedidosDoPonto($veiculo)) {
            return response()->json(['passageiros' => [], 'elegivel' => false, 'motivo' => $motivo]);
        }

        $passageiros = Reserva::query()
            ->join('utilizadores as u', 'u.id', '=', 'reservas.passageiro_id')
            ->join('pontos_partida as pp', 'pp.id', '=', 'reservas.ponto_partida_id')
            ->join('pontos_partida as pd', 'pd.id', '=', 'reservas.destino_id')
            ->intermunicipaisEmAbertoNoPonto((int) $veiculo->ponto_partida_id)
            ->orderBy('reservas.criado_em')
            ->limit(100)
            ->get([
                'reservas.id', 'reservas.lugares', 'reservas.preco_final', 'reservas.criado_em',
                'reservas.ponto_descida_nome', 'reservas.ponto_descida_lat', 'reservas.ponto_descida_lng',
                'reservas.destino_id',
                'u.nome as passageiro_nome',
                'pp.nome as ponto_partida_nome', 'pp.lat as ponto_partida_lat', 'pp.lng as ponto_partida_lng',
                'pd.nome as destino_nome',
            ]);

        $temPosicaoVeiculo = $veiculo->lat !== null && $veiculo->lng !== null;

        $passageiros->each(function ($p) use ($veiculo, $temPosicaoVeiculo) {
            // O passageiro à espera está no ponto: é essa a posição no mapa
            // (ainda não há GPS dele — sem veículo não há viagem a seguir).
            $p->mapa_lat = $p->ponto_partida_lat;
            $p->mapa_lng = $p->ponto_partida_lng;

            // Um pedido do ponto não é filtrado pelo destino do condutor: o
            // ponto é que o torna visível. Este sinalizador só ordena a lista
            // e deixa o condutor ver de relance quem vai mesmo com ele.
            $p->mesmo_destino = (int) $p->destino_id === (int) $veiculo->destino_id;

            $p->distancia_m = $temPosicaoVeiculo
                ? (int) round($this->geo->distanciaMetros(
                    (float) $veiculo->lat, (float) $veiculo->lng,
                    (float) $p->ponto_partida_lat, (float) $p->ponto_partida_lng
                ))
                : null;
        });

        $passageiros = $passageiros
            ->sortByDesc(fn ($p) => $p->mesmo_destino ? 1 : 0)
            ->values();

        return response()->json(['passageiros' => $passageiros, 'elegivel' => true]);
    }

    /**
     * Porque é que este veículo não recebe os pedidos em aberto do ponto —
     * null quando os recebe. Um veículo só urbano não faz carreiras, e sem o
     * "Guardar" do cartão "Veículo ativo" não há ponto onde esperar (nem
     * destino, e sem ele nem "Embarcar" funciona — ver faltaGuardarPonto).
     */
    private function motivoParaNaoVerPedidosDoPonto(Veiculo $veiculo): ?string
    {
        return match (true) {
            ! $veiculo->aprovado => 'Este veículo ainda não está aprovado.',
            $veiculo->tipo_servico === 'urbano' => 'Este veículo é só urbano — os pedidos dele aparecem em "Passageiros urbanos".',
            $veiculo->ponto_partida_id === null || $veiculo->destino_id === null => self::PONTO_POR_GUARDAR,
            default => null,
        };
    }

    /**
     * O condutor reclama um pedido em aberto — urbano (de qualquer condutor
     * elegível) ou intermunicipal do seu ponto. A partir daqui a reserva segue
     * exatamente o mesmo fluxo de sempre (embarcar/entregar/chat).
     *
     * O bloqueio das linhas dentro da transação é o que impede dois
     * condutores de reclamarem o mesmo pedido ao mesmo tempo.
     */
    public function recolher(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
            'veiculo_id' => ['required', 'integer'],
        ], [
            'reserva_id.required' => 'Dados inválidos.',
            'veiculo_id.required' => 'Dados inválidos.',
        ]);

        $condutor = $request->user('utilizador');

        try {
            $reserva = DB::transaction(function () use ($dados, $condutor) {
                $veiculo = Veiculo::query()
                    ->lockForUpdate()
                    ->whereKey($dados['veiculo_id'])
                    ->where('condutor_id', $condutor->id)
                    ->first();

                if (! $veiculo || ! $veiculo->aprovado) {
                    throw new RuntimeException('Veículo não encontrado ou não aprovado.', 404);
                }
                if ($veiculo->lugares_livres < 1) {
                    throw new RuntimeException('Este veículo já não tem lugares disponíveis.', 409);
                }

                $reserva = Reserva::query()
                    ->with('passageiro:id,nome')
                    ->lockForUpdate()
                    ->find($dados['reserva_id']);

                if (! $reserva) {
                    throw new RuntimeException('Pedido não encontrado.', 404);
                }
                if ($reserva->veiculo_id !== null || $reserva->estado !== 'pendente') {
                    throw new RuntimeException('Este pedido já foi reclamado por outro condutor.', 409);
                }

                // Um pedido do ponto é de todos os condutores desse ponto — e
                // só desses: sem esta verificação, um condutor de outro ponto
                // (ou fora de qualquer ponto) apanhava pelo id um passageiro
                // que nunca o veria na sua lista.
                if ($reserva->tipo_viagem === 'intermunicipal') {
                    if ($motivo = $this->motivoParaNaoVerPedidosDoPonto($veiculo)) {
                        throw new RuntimeException($motivo, 409);
                    }
                    if ((int) $reserva->ponto_partida_id !== (int) $veiculo->ponto_partida_id) {
                        throw new RuntimeException('Este pedido é de outro ponto de partida.', 409);
                    }
                }

                // Nunca um lugar que o condutor retirou (VeiculoService::retirarLugar).
                $assento = AssentoVeiculo::query()
                    ->lockForUpdate()
                    ->where('veiculo_id', $veiculo->id)
                    ->where('ativo', true)
                    ->where('ocupado', false)
                    ->orderBy('numero')
                    ->first();

                if (! $assento) {
                    throw new RuntimeException('Este veículo já não tem lugares disponíveis.', 409);
                }

                // 'a_caminho' e não 'confirmado': o botão que despoleta isto é
                // o "Ir buscar" da lista de pedidos urbanos, e o efeito tem de
                // ser o mesmo do "Ir buscar" da lista de passageiros — o
                // condutor fica a caminho e o passageiro aparece no mapa, sem
                // um passo intermédio que ninguém carrega.
                // A tarifa da rota fixa do condutor prevalece sobre a
                // estimativa do pedido: um pedido do ponto nasce sem carro e é
                // orçado por km, mas quem o reclama pode ter uma rota fixa com
                // preço próprio para o mesmo troço. Sem rota fixa o preço
                // combinado com o passageiro fica como está.
                $precoRotaFixa = $this->precoDaRotaFixa($reserva, $veiculo);

                $reserva->forceFill(array_filter([
                    'veiculo_id' => $veiculo->id,
                    'assento_id' => $assento->id,
                    'estado' => 'a_caminho',
                    'preco_final' => $precoRotaFixa,
                ], fn ($valor) => $valor !== null))->save();

                $assento->forceFill(['ocupado' => true, 'reserva_id' => $reserva->id])->save();
                $veiculo->decrement('lugares_livres');

                // Avisa o passageiro por mensagem de sistema no autofalante:
                // já existe polling/realtime para isso, não é preciso um
                // canal novo só para este aviso.
                $aviso = "{$condutor->nome} está a caminho para o(a) buscar.";

                if ($precoRotaFixa !== null) {
                    $aviso .= ' Este veículo faz uma rota fixa: o preço da viagem passa a '
                        .number_format($precoRotaFixa, 2, ',', ' ').' CVE.';
                }

                ComunicacaoVeiculo::query()->create([
                    'veiculo_id' => $veiculo->id,
                    'remetente_id' => $condutor->id,
                    'destinatario_id' => $reserva->passageiro_id,
                    'mensagem' => $aviso,
                    'tipo' => 'sistema',
                ]);

                $this->auditoria->registar(
                    null,
                    $reserva->tipo_viagem === 'urbano' ? 'condutor_recolheu_urbano' : 'condutor_recolheu_ponto',
                    'reservas',
                    $reserva->id,
                    "veiculo #{$veiculo->id}"
                );

                return $reserva;
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json([
            'sucesso' => true,
            'tipo_viagem' => $reserva->tipo_viagem,
            'preco_final' => $reserva->preco_final,
            'passageiro_id' => $reserva->passageiro_id,
            'passageiro_nome' => $reserva->passageiro?->nome,
            'passageiro_lat' => $reserva->passageiro_lat,
            'passageiro_lng' => $reserva->passageiro_lng,
        ]);
    }

    /**
     * Preço da rota fixa deste veículo para o troço da reserva — null quando
     * o veículo não faz rota fixa, quando ela não tem distância registada
     * (não dá para fracionar) ou quando a viagem não é intermunicipal.
     *
     * Não faz nenhuma chamada ao OSRM: o preço fracionado só depende das
     * coordenadas dos pontos, e isto corre dentro da transação de recolher().
     */
    private function precoDaRotaFixa(Reserva $reserva, Veiculo $veiculo): ?float
    {
        if ($reserva->tipo_viagem !== 'intermunicipal' || ! $veiculo->rota_fixa_id) {
            return null;
        }

        $pontoPartida = PontoPartida::query()->find($reserva->ponto_partida_id);
        $destino = PontoPartida::query()->find($reserva->destino_id);

        if (! $pontoPartida || ! $destino) {
            return null;
        }

        $descida = $reserva->ponto_descida_lat !== null && $reserva->ponto_descida_lng !== null
            ? ['lat' => $reserva->ponto_descida_lat, 'lng' => $reserva->ponto_descida_lng]
            : null;

        return $this->precos->precoRotaFixaDoVeiculo($veiculo, $pontoPartida, $destino, $descida);
    }

    /**
     * Confirmar, recusar, ir buscar, embarcar ou marcar a chegada de uma
     * reserva.
     *
     * O ciclo de recolha tem dois passos deliberadamente separados: 'ir_buscar'
     * põe o passageiro no mapa (estado 'a_caminho') e 'embarcar' mete-o dentro
     * do carro (estado 'a_bordo'). Só o condutor do veículo da reserva os pode
     * executar — a linha do veículo é lida a partir da reserva e comparada com
     * o utilizador autenticado, nunca vinda do pedido.
     */
    public function mudarEstado(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'reserva_id' => ['required', 'integer'],
            'acao' => ['required', 'in:confirmar,recusar,ir_buscar,embarcar,chegou,nao_apareceu,remover'],
        ], [
            'reserva_id.required' => 'Dados inválidos.',
            'acao.required' => 'Dados inválidos.',
            'acao.in' => 'Dados inválidos.',
        ]);

        // Veículo de quem foi tirado da viagem ("Não apareceu"/"Remover"),
        // para o aviso ao passageiro sair só depois de a transação gravar.
        $retiradoDe = null;

        try {
            $resultado = DB::transaction(function () use ($dados, $request, &$retiradoDe) {
                $reserva = Reserva::query()->lockForUpdate()->find($dados['reserva_id']);

                if (! $reserva || ! $reserva->veiculo_id) {
                    throw new RuntimeException('Reserva não encontrada.', 404);
                }

                // lockForUpdate no veículo também nas ações que não mexem em
                // 'lugares_livres': é este bloqueio que serializa dois cliques
                // em "Embarcar" (duplo clique, ou dois separadores abertos) e
                // impede que ambos passem pela contagem de lotação.
                $veiculo = Veiculo::query()->lockForUpdate()->find($reserva->veiculo_id);

                if (! $veiculo || $veiculo->condutor_id !== $request->user('utilizador')->id) {
                    throw new RuntimeException('Reserva não encontrada.', 404);
                }

                $lugaresLivres = match ($dados['acao']) {
                    'confirmar' => $this->confirmar($reserva),
                    'recusar' => $this->recusar($reserva, $veiculo),
                    'ir_buscar' => $this->irBuscar($reserva, $veiculo),
                    'embarcar' => $this->embarcar($reserva, $veiculo),
                    'chegou' => $this->marcarChegada($reserva, $veiculo),
                    'nao_apareceu' => $this->naoApareceu($reserva, $veiculo),
                    'remover' => $this->removerABordo($reserva, $veiculo),
                };

                if (in_array($dados['acao'], ['nao_apareceu', 'remover'], true)) {
                    $retiradoDe = $veiculo;
                }

                return [
                    'passageiro_id' => $reserva->passageiro_id,
                    'veiculo_id' => $veiculo->id,
                    'lugares_livres' => $lugaresLivres,
                    'estado' => $reserva->estado,
                    'ocupacao' => $this->veiculos->ocupacao($veiculo->refresh()),
                ];
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        if ($retiradoDe !== null) {
            $this->avisos->retiradoPeloCondutor($retiradoDe, (int) $resultado['passageiro_id'], $dados['acao'] === 'remover');
        }

        return response()->json(['sucesso' => true] + $resultado);
    }

    /**
     * "Não apareceu": o passageiro confirmado (ou que o condutor já ia
     * buscar) não está no local. A reserva fica sem efeito — 'recusado', o
     * mesmo estado de quando é o passageiro a desistir
     * (Passageiro\ReservaController::cancelar) — e o lugar volta a ficar
     * livre. Um pedido ainda pendente recusa-se com "Recusar".
     */
    private function naoApareceu(Reserva $reserva, Veiculo $veiculo): int
    {
        if (! in_array($reserva->estado, ['confirmado', 'a_caminho'], true)) {
            throw new RuntimeException(match ($reserva->estado) {
                'pendente' => 'Este pedido ainda está pendente — use "Recusar".',
                'a_bordo' => 'Este passageiro já está a bordo — use "Remover".',
                default => 'Esta reserva já foi processada.',
            }, 409);
        }

        $reserva->forceFill(['estado' => 'recusado'])->save();

        $this->auditoria->registar(null, 'condutor_marcou_nao_apareceu', 'reservas', $reserva->id, "veiculo={$veiculo->id}");

        return $this->libertarLugar($reserva, $veiculo);
    }

    /**
     * "Remover": tira da viagem quem já está a bordo sem a dar como concluída
     * (saiu antes do destino, teve de sair). Fica 'recusado' e não
     * 'concluido', para não contar como viagem feita nem pedir avaliação ao
     * passageiro, e fica sempre em auditoria — é o condutor a decidir sozinho
     * sobre uma viagem que já estava a decorrer.
     */
    private function removerABordo(Reserva $reserva, Veiculo $veiculo): int
    {
        if ($reserva->estado !== 'a_bordo') {
            throw new RuntimeException('Só se remove assim quem já está a bordo.', 409);
        }

        $reserva->forceFill(['estado' => 'recusado'])->save();

        $this->auditoria->registar(null, 'condutor_removeu_passageiro_a_bordo', 'reservas', $reserva->id, "veiculo={$veiculo->id}");

        return $this->libertarLugar($reserva, $veiculo);
    }

    private function confirmar(Reserva $reserva): ?int
    {
        if ($reserva->estado !== 'pendente') {
            throw new RuntimeException('Esta reserva já foi processada.', 409);
        }

        $reserva->forceFill(['estado' => 'confirmado'])->save();

        return null;
    }

    private function recusar(Reserva $reserva, Veiculo $veiculo): int
    {
        if ($reserva->estado !== 'pendente') {
            throw new RuntimeException('Esta reserva já foi processada.', 409);
        }

        $reserva->forceFill(['estado' => 'recusado'])->save();

        return $this->libertarLugar($reserva, $veiculo);
    }

    /**
     * "Ir buscar": o condutor assume a recolha e o passageiro passa a ter
     * marcador próprio no mapa. É idempotente de propósito — um segundo clique
     * (ou um pedido repetido pela rede) devolve sucesso em vez de um erro, que
     * ao condutor apareceria como uma falha de uma ação que afinal correu bem.
     */
    private function irBuscar(Reserva $reserva, Veiculo $veiculo): ?int
    {
        if ($reserva->estado === 'a_caminho') {
            return null;
        }

        if (! in_array($reserva->estado, Reserva::ESTADOS_ANTES_DA_RECOLHA, true)) {
            throw new RuntimeException(
                $reserva->estado === 'a_bordo'
                    ? 'Este passageiro já está a bordo.'
                    : 'Esta reserva já foi processada.',
                409
            );
        }

        $this->recusarSeJaEmbarcadoNoutroVeiculo($reserva, $veiculo);

        $reserva->forceFill(['estado' => 'a_caminho'])->save();

        return null;
    }

    /**
     * "Embarcar": o passageiro entra no carro. Deixa de ter marcador autónomo
     * no mapa e passa a contar dentro do marcador do condutor.
     */
    private function embarcar(Reserva $reserva, Veiculo $veiculo): ?int
    {
        if ($reserva->estado === 'a_bordo') {
            throw new RuntimeException('Este passageiro já está a bordo.', 409);
        }

        if ($reserva->estado !== 'a_caminho') {
            throw new RuntimeException('Carregue primeiro em "Ir buscar" para pôr o passageiro no mapa.', 409);
        }

        // A regra vive aqui e não só no painel: um botão desativado não trava
        // um POST feito à mão.
        if ($this->faltaGuardarPonto($reserva, $veiculo)) {
            throw new RuntimeException(self::PONTO_POR_GUARDAR, 409);
        }

        $this->recusarSeJaEmbarcadoNoutroVeiculo($reserva, $veiculo);

        // Lotação contada por pessoas dentro do carro, e não por lugares
        // reservados: o assento desta reserva já está preso desde que o
        // passageiro reservou, portanto 'lugares_livres' não responde a "cabe
        // mais alguém agora?". Quem entrou sem app não tem reserva, mas
        // também está lá dentro.
        $aBordo = Reserva::query()->where('veiculo_id', $veiculo->id)->aBordo()->count()
            + $this->veiculos->passageirosSemApp($veiculo->id);

        if ($aBordo >= (int) $veiculo->lugares_total) {
            throw new RuntimeException('Veículo cheio — nenhum lugar livre para embarcar.', 409);
        }

        $reserva->forceFill(['estado' => 'a_bordo'])->save();

        return null;
    }

    /**
     * Um passageiro não pode estar dentro de dois carros ao mesmo tempo. Pode
     * ter mais do que uma reserva ativa (o fluxo de reserva não o impede), por
     * isso a verificação é feita aqui, no momento em que passaria a estar a
     * bordo ou a ser recolhido por este condutor.
     */
    private function recusarSeJaEmbarcadoNoutroVeiculo(Reserva $reserva, Veiculo $veiculo): void
    {
        $noutroVeiculo = Reserva::query()
            ->where('passageiro_id', $reserva->passageiro_id)
            ->whereKeyNot($reserva->id)
            ->where('veiculo_id', '!=', $veiculo->id)
            ->aBordo()
            ->exists();

        if ($noutroVeiculo) {
            throw new RuntimeException('Este passageiro já embarcou noutro veículo.', 409);
        }
    }

    /** O passageiro desce, no destino final ou a meio da rota. */
    private function marcarChegada(Reserva $reserva, Veiculo $veiculo): int
    {
        if ($reserva->estado !== 'a_bordo') {
            throw new RuntimeException('O passageiro precisa de estar a bordo para marcar a chegada.', 409);
        }

        if ($this->faltaGuardarPonto($reserva, $veiculo)) {
            throw new RuntimeException(self::PONTO_POR_GUARDAR, 409);
        }

        $reserva->forceFill(['estado' => 'concluido'])->save();

        return $this->libertarLugar($reserva, $veiculo);
    }

    private function libertarLugar(Reserva $reserva, Veiculo $veiculo): int
    {
        AssentoVeiculo::query()
            ->where('reserva_id', $reserva->id)
            ->update(['ocupado' => false, 'reserva_id' => null]);

        DB::table('veiculos')
            ->where('id', $veiculo->id)
            ->update(['lugares_livres' => DB::raw('LEAST(lugares_total, lugares_livres + 1)')]);

        return (int) Veiculo::query()->whereKey($veiculo->id)->value('lugares_livres');
    }

    /**
     * Uma viagem intermunicipal só anda com o veículo ancorado a um ponto, e
     * quem o ancora é o "Guardar" do cartão "Veículo ativo" (grava partida e
     * destino de uma só vez, ver Condutor\VeiculoController::definirPonto).
     * Sem isso não há rota, nem fila, nem destino para onde levar o
     * passageiro — daí "Embarcar" e "Entregue" ficarem travados.
     *
     * As viagens urbanas ficam de fora de propósito: o táxi (e o 'ambos'
     * enquanto não tem ponto) circula livremente e recolhe pedidos urbanos
     * sem nunca passar por aqui (ver urbanos()).
     */
    private function faltaGuardarPonto(Reserva $reserva, Veiculo $veiculo): bool
    {
        return $reserva->tipo_viagem === 'intermunicipal'
            && ($veiculo->ponto_partida_id === null || $veiculo->destino_id === null);
    }

    /**
     * Gate de aprovação e pagamento (secção 11.6): sem taxa de operação
     * aprovada e válida, o condutor não vê o mapa nem os passageiros.
     */
    private function bloqueadoPorGate(Request $request): ?JsonResponse
    {
        if ($this->veiculos->condutorPodeVerMapa($request->user('utilizador')->id)) {
            return null;
        }

        return response()->json(
            ['erro' => 'A sua conta ainda não tem acesso ao mapa (aprovação ou pagamento pendente).'],
            403
        );
    }
}
