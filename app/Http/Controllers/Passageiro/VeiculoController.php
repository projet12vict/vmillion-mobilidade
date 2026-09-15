<?php

declare(strict_types=1);

namespace App\Http\Controllers\Passageiro;

use App\Http\Controllers\Controller;
use App\Models\AssentoVeiculo;
use App\Models\AvaliacaoCondutor;
use App\Models\PontoPartida;
use App\Models\Veiculo;
use App\Services\MunicipioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VeiculoController extends Controller
{
    public function __construct(
        private readonly MunicipioService $municipios,
    ) {}

    /**
     * Veículos disponíveis num ponto de partida (secção 8, passo 4).
     *
     * Numa viagem urbana o passageiro vê não só os veículos parados no ponto,
     * mas também os que já circulam na mesma cidade (em_movimento /
     * partiu_da_fila); fora disso continua limitado ao ponto escolhido.
     *
     * Em qualquer dos casos a lista fica dentro de um só concelho: só aparece
     * o veículo cuja posição atual cai dentro da delimitação do município da
     * procura (MunicipioService, o mesmo traçado que o mapa desenha). Sem
     * isto, um táxi que saiu do ponto e já vai a caminho de outro concelho
     * continuava a ser mostrado a quem ficou para trás. É a mesma fronteira
     * do outro lado — ver Condutor\PassageiroController::urbanos.
     *
     * Um condutor sem taxa de operação aprovada e em dia não pode operar, por
     * isso o veículo dele nunca aparece aqui.
     */
    public function disponiveis(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ponto_id' => ['required', 'integer'],
            'destino_id' => ['nullable', 'integer'],
            'viagem_tipo' => ['nullable', 'in:urbana,intermunicipal'],
            // A posição do passageiro é opcional: o painel manda-a quando o
            // GPS já respondeu, e é ela que define o município da procura
            // urbana. Sem ela vale o ponto escolhido.
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ], ['ponto_id.required' => 'Indique o ponto de partida.']);

        $pontoId = (int) $dados['ponto_id'];
        $viagemTipo = $dados['viagem_tipo'] ?? null;

        $ponto = PontoPartida::query()->whereKey($pontoId)->first(['cidade', 'lat', 'lng']);
        $cidadePonto = $viagemTipo === 'urbana' ? $ponto?->cidade : null;
        $municipio = $this->municipioDaProcura($ponto, $viagemTipo, $dados);

        $veiculos = Veiculo::query()
            ->join('utilizadores as u', 'u.id', '=', 'veiculos.condutor_id')
            ->join('pontos_partida as pp', 'pp.id', '=', 'veiculos.ponto_partida_id')
            ->leftJoin('pontos_partida as pd', 'pd.id', '=', 'veiculos.destino_id')
            ->where('veiculos.aprovado', true)
            ->where('u.status', 'ativo')
            ->where('veiculos.lugares_livres', '>', 0)
            ->whereExists(fn ($q) => $q->from('pagamentos_condutores as pc')
                ->whereColumn('pc.condutor_id', 'veiculos.condutor_id')
                ->where('pc.status', 'aprovado')
                ->where('pc.data_validade', '>=', now())
            )
            ->where(function ($q) use ($pontoId, $cidadePonto) {
                $q->where(fn ($sub) => $sub
                    ->where('veiculos.ponto_partida_id', $pontoId)
                    ->whereIn('veiculos.estado', ['no_ponto', 'na_fila'])
                );

                if ($cidadePonto !== null) {
                    $q->orWhere(fn ($sub) => $sub
                        ->where('pp.cidade', $cidadePonto)
                        ->whereIn('veiculos.estado', ['em_movimento', 'partiu_da_fila'])
                    );
                }
            })
            ->when($dados['destino_id'] ?? null, fn ($q, $destinoId) => $q->where('veiculos.destino_id', $destinoId))
            ->when($viagemTipo === 'urbana', fn ($q) => $q->whereIn('veiculos.tipo_servico', ['urbano', 'ambos']))
            ->when($viagemTipo === 'intermunicipal', fn ($q) => $q->whereIn('veiculos.tipo_servico', ['intermunicipal', 'ambos']))
            ->orderByRaw("(veiculos.estado = 'na_fila') DESC")
            ->orderBy('veiculos.posicao_fila')
            ->orderByDesc('veiculos.lugares_livres')
            ->get([
                'veiculos.id', 'veiculos.condutor_id', 'veiculos.matricula', 'veiculos.tipo',
                'veiculos.tipo_servico', 'veiculos.cor', 'veiculos.modelo', 'veiculos.lugares_livres',
                'veiculos.estado', 'veiculos.posicao_fila', 'veiculos.lat', 'veiculos.lng',
                'veiculos.destino_id',
                'pd.nome as destino_nome',
                'pp.lat as ponto_lat', 'pp.lng as ponto_lng',
                'u.nome as condutor_nome',
            ]);

        if ($municipio !== null) {
            $veiculos = $veiculos->filter(function (Veiculo $veiculo) use ($municipio) {
                // A posição que conta é a atual; o ponto onde o veículo está
                // ancorado só entra enquanto ele ainda não mandou GPS nenhum.
                $lat = $veiculo->lat ?? $veiculo->getAttribute('ponto_lat');
                $lng = $veiculo->lng ?? $veiculo->getAttribute('ponto_lng');

                return $lat !== null && $lng !== null
                    && $this->municipios->contem($municipio, (float) $lat, (float) $lng);
            })->values();
        }

        // O ponto de ancoragem serviu só para o filtro acima; o painel não o
        // usa e não tem de o receber.
        $veiculos->each(fn (Veiculo $veiculo) => $veiculo->makeHidden(['ponto_lat', 'ponto_lng']));

        return response()->json([
            'veiculos' => $this->comAvaliacoes($veiculos),
            'municipio' => $municipio,
        ]);
    }

    /**
     * O concelho a que esta procura está presa, ou null quando não há
     * delimitação carregada (nesse caso não se filtra nada — mais vale a
     * lista de sempre do que uma lista vazia por um ficheiro em falta).
     *
     * Numa viagem urbana o concelho é o de onde o passageiro está: é para
     * lá que o táxi tem de vir. Numa intermunicipal é o do ponto de partida
     * que ele escolheu — a carreira sai desse ponto, e o passageiro pode
     * muito bem estar a comprar lugar de véspera a partir de outro concelho.
     *
     * @param  array<string, mixed>  $dados
     */
    private function municipioDaProcura(?PontoPartida $ponto, ?string $viagemTipo, array $dados): ?string
    {
        if ($ponto === null || ! $this->municipios->disponivel()) {
            return null;
        }

        if ($viagemTipo === 'urbana' && isset($dados['lat'], $dados['lng'])) {
            $municipio = $this->municipios->nomeEm((float) $dados['lat'], (float) $dados['lng']);

            if ($municipio !== null) {
                return $municipio;
            }
            // GPS ainda a assentar, ou uma leitura que caiu no mar: vale o
            // ponto escolhido, que é um local aprovado e está sempre dentro
            // de um concelho.
        }

        return $this->municipios->nomeEm((float) $ponto->lat, (float) $ponto->lng);
    }

    /**
     * Layout de assentos de um veículo (secção 8). Os lugares que o condutor
     * retirou (VeiculoService::retirarLugar) não se vendem e não aparecem.
     */
    public function assentos(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        $assentos = AssentoVeiculo::query()
            ->where('veiculo_id', $dados['veiculo_id'])
            ->where('ativo', true)
            ->orderBy('numero')
            ->get(['id', 'numero', 'fila', 'coluna', 'ocupado']);

        if ($assentos->isEmpty()) {
            return response()->json(['erro' => 'Veículo sem assentos configurados.'], 404);
        }

        return response()->json(['assentos' => $assentos]);
    }

    /**
     * Anexa a média e o total de avaliações do condutor de cada veículo.
     *
     * Uma única query agregada para toda a lista, em vez das duas
     * subconsultas correlacionadas por linha que a versão original fazia.
     *
     * @param  Collection<int, Veiculo>  $veiculos
     * @return Collection<int, Veiculo>
     */
    private function comAvaliacoes(Collection $veiculos): Collection
    {
        if ($veiculos->isEmpty()) {
            return $veiculos;
        }

        $avaliacoes = AvaliacaoCondutor::query()
            ->whereIn('condutor_id', $veiculos->pluck('condutor_id')->unique())
            ->groupBy('condutor_id')
            ->selectRaw('condutor_id, ROUND(AVG(avaliacao), 1) AS media, COUNT(*) AS total')
            ->get()
            ->keyBy('condutor_id');

        return $veiculos->each(function (Veiculo $veiculo) use ($avaliacoes) {
            $resumo = $avaliacoes->get($veiculo->condutor_id);

            $veiculo->setAttribute('condutor_avaliacao_media', $resumo ? (float) $resumo->media : null);
            $veiculo->setAttribute('condutor_avaliacao_total', $resumo ? (int) $resumo->total : 0);
        });
    }
}
