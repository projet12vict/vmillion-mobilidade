<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Models\Veiculo;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contactos de outros condutores na mesma rota (secção 9.6).
 *
 * Inclui duas situações:
 *
 * 1. Parados no mesmo ponto de partida ('no_ponto'/'na_fila') — o caso
 *    original desta secção.
 * 2. Já a caminho na mesma rota ('partiu_da_fila'/'em_movimento') — enquanto
 *    o veículo não chega ao destino, ponto de partida e destino continuam a
 *    ser os da viagem em curso (só invertem na chegada, ver
 *    PosicaoController::concluirChegada), portanto o mesmo par ponto/destino
 *    identifica quem está mesmo à frente na estrada. Sem isto, um condutor
 *    desaparecia do painel de todos os colegas no instante em que saía do
 *    ponto.
 *
 * Só devolve veículos intermunicipais ou 'ambos' — um condutor urbano não
 * opera por ponto/fila nem por rota fixa. E quando se sabe qual é o veículo
 * do próprio condutor (veiculo_id), só devolve os colegas que vão para o
 * mesmo destino e que conduzem o mesmo tipo de veículo: um táxi negoceia a
 * vez com táxis, um Hiace com Hiaces e um autocarro com autocarros — filas,
 * capacidade e tarifa não se misturam entre tipos, por isso um colega de
 * outro tipo só enchia a lista e o mapa com ruído.
 */
class OutrosCondutoresController extends Controller
{
    /** Parados no ponto: a posição no mapa é a do próprio ponto. */
    private const ESTADOS_PARADOS = ['no_ponto', 'na_fila'];

    /** A caminho: só entram com uma posição GPS recente (ver abaixo). */
    private const ESTADOS_EM_ROTA = ['partiu_da_fila', 'em_movimento'];

    /**
     * O estado 'em_movimento' só sai quando o veículo chega ao destino: um
     * condutor que feche a aplicação a meio fica indefinidamente "a caminho"
     * na base de dados. Mostrar essa posição velha seria pior do que não
     * mostrar nada, por isso um veículo em rota só aparece enquanto o GPS
     * dele continuar a chegar.
     */
    private const FRESCURA_POSICAO_MINUTOS = 15;

    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ponto_id' => ['required', 'integer'],
            'veiculo_id' => ['nullable', 'integer'],
        ], ['ponto_id.required' => 'Ponto inválido.']);

        $condutorId = $request->user('utilizador')->id;

        $meuVeiculo = isset($dados['veiculo_id'])
            ? Veiculo::query()
                ->whereKey($dados['veiculo_id'])
                ->where('condutor_id', $condutorId)
                ->first(['tipo', 'destino_id'])
            : null;

        $destinoId = $meuVeiculo?->destino_id;
        $tipo = $meuVeiculo?->tipo;

        $limiteFrescura = now()->subMinutes(self::FRESCURA_POSICAO_MINUTOS);

        $condutores = Veiculo::query()
            ->join('utilizadores as u', 'u.id', '=', 'veiculos.condutor_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'veiculos.destino_id')
            ->where('veiculos.ponto_partida_id', $dados['ponto_id'])
            ->where('veiculos.aprovado', true)
            ->where('u.status', 'ativo')
            ->where('veiculos.condutor_id', '!=', $condutorId)
            ->whereIn('veiculos.tipo_servico', ['intermunicipal', 'ambos'])
            ->where(fn (QueryBuilder $q) => $q
                ->whereIn('veiculos.estado', self::ESTADOS_PARADOS)
                ->orWhere(fn (QueryBuilder $emRota) => $emRota
                    ->whereIn('veiculos.estado', self::ESTADOS_EM_ROTA)
                    ->whereNotNull('veiculos.lat')
                    ->whereNotNull('veiculos.lng')
                    ->where('veiculos.ultima_posicao_em', '>=', $limiteFrescura)))
            ->whereExists(fn ($q) => $q->from('pagamentos_condutores as pc')
                ->whereColumn('pc.condutor_id', 'veiculos.condutor_id')
                ->where('pc.status', 'aprovado')
                ->where('pc.data_validade', '>=', now())
            )
            ->when($destinoId, fn ($q) => $q->where('veiculos.destino_id', $destinoId))
            // Cada tipo vê só o seu: táxi com táxi, Hiace com Hiace,
            // autocarro com autocarro.
            ->when($tipo, fn ($q) => $q->where('veiculos.tipo', $tipo))
            // Quem está parado no ponto vem primeiro (é com esses que o
            // condutor negoceia a vez); os que já vão a caminho ficam no fim.
            ->orderByRaw('CASE WHEN veiculos.estado IN (?, ?) THEN 0 ELSE 1 END', self::ESTADOS_PARADOS)
            ->orderBy('veiculos.posicao_fila')
            ->orderBy('veiculos.id')
            ->get([
                'veiculos.id', 'veiculos.matricula', 'veiculos.tipo', 'veiculos.cor',
                'veiculos.estado', 'veiculos.posicao_fila', 'veiculos.lat', 'veiculos.lng',
                'veiculos.lugares_livres', 'veiculos.ultima_posicao_em',
                'veiculos.destino_id',
                'pd.nome as destino_nome',
                'u.nome as condutor_nome',
                'u.telefone as condutor_telefone',
            ]);

        // Bandeira explícita para o painel não ter de repetir a lista de
        // estados que contam como "a caminho".
        $condutores->each(fn ($c) => $c->em_rota = in_array($c->estado, self::ESTADOS_EM_ROTA, true));

        return response()->json(['condutores' => $condutores]);
    }
}
