<?php

declare(strict_types=1);

namespace App\Http\Controllers\Condutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Condutor\RegistarVeiculoRequest;
use App\Models\AssentoVeiculo;
use App\Models\PontoPartida;
use App\Models\Veiculo;
use App\Services\AuditoriaService;
use App\Services\AvisoViagemService;
use App\Services\VeiculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Frota do condutor autenticado (secções 7.1 e 9.1/9.2).
 */
class VeiculoController extends Controller
{
    public function __construct(
        private readonly VeiculoService $veiculos,
        private readonly AuditoriaService $auditoria,
        private readonly AvisoViagemService $avisos,
    ) {}

    /**
     * Veículos do condutor, com a contagem de passageiros pendentes de cada um.
     *
     * A contagem existe porque um condutor com mais do que um veículo
     * aprovado só vê os passageiros do veículo selecionado: sem ela, um
     * pedido preso noutro veículo seu ficava invisível sem nenhuma pista de
     * que existia.
     */
    public function listar(Request $request): JsonResponse
    {
        $veiculos = Veiculo::query()
            ->where('condutor_id', $request->user('utilizador')->id)
            ->withCount(['reservas as passageiros_pendentes' => fn ($q) => $q->where('estado', 'pendente')])
            ->orderByDesc('criado_em')
            ->get([
                'id', 'matricula', 'tipo', 'tipo_servico', 'cor', 'modelo',
                'lugares_total', 'lugares_livres', 'estado', 'aprovado',
                'ponto_partida_id', 'destino_id', 'rota_fixa_id', 'posicao_fila', 'lat', 'lng',
                // O painel esconde o botão "Guardar" enquanto esta data não
                // passar (ver definirPonto) — sem ela na listagem, o botão
                // reaparecia a cada refrescamento da lista.
                'ponto_bloqueado_ate',
            ]);

        return response()->json(['veiculos' => $veiculos]);
    }

    public function registar(RegistarVeiculoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $matricula = $this->veiculos->normalizarMatricula($dados['matricula']);

        if (Veiculo::query()->where('matricula', $matricula)->exists()) {
            return response()->json(['erro' => 'Já existe um veículo com esta matrícula.'], 409);
        }

        // O condutor diz quantos lugares o carro leva; sem o campo, vale a
        // capacidade do tipo (14 no Hiace, 30 no autocarro, 4 no táxi — ver
        // VeiculoService::lugaresTotal). O teto é o mesmo do botão "Lugares":
        // registar com mais do que isso era contornar esse limite.
        $lugares = isset($dados['lugares'])
            ? (int) $dados['lugares']
            : $this->veiculos->lugaresTotal($dados['tipo']);
        $maximo = $this->veiculos->lugaresMax($dados['tipo']);

        if ($lugares > $maximo) {
            return response()->json(['erro' => 'Este tipo de veículo leva no máximo '.$maximo.' lugares.'], 422);
        }

        $veiculo = DB::transaction(function () use ($dados, $request, $matricula, $lugares) {
            $veiculo = Veiculo::query()->create([
                'condutor_id' => $request->user('utilizador')->id,
                'matricula' => $matricula,
                'tipo' => $dados['tipo'],
                'tipo_servico' => $dados['tipo_servico'] ?? 'ambos',
                'cor' => $dados['cor'],
                'modelo' => $dados['modelo'],
                'lugares_total' => $lugares,
                'lugares_livres' => $lugares,
                'estado' => 'no_ponto',
                'aprovado' => false,
                'rota_fixa_id' => $dados['rota_fixa_id'] ?? null,
            ]);

            $this->veiculos->criarAssentos($veiculo, $lugares);

            return $veiculo;
        });

        return response()->json([
            'sucesso' => true,
            'veiculo_id' => $veiculo->id,
            'mensagem' => 'Veículo registado. Fica ativo após aprovação do administrador (mediante comprovativo de pagamento).',
        ]);
    }

    /**
     * Ponto de partida e destino do veículo (secção 9.2). A escolha persiste
     * na base de dados, não apenas na sessão.
     *
     * Guardar tranca o ponto durante 'vmillion.veiculos.bloqueio_ponto_horas'
     * (24 h): a escolha do ponto decide a fila em que o veículo entra e quais
     * os pedidos que lhe chegam, e trocá-la a toda a hora era a forma simples
     * de passar à frente de quem já esperava no ponto novo. Quem se enganou
     * pede ao administrador, que liberta pela ação 'libertar_ponto' do painel
     * de administração.
     *
     * A regra vive aqui e não só no painel: esconder o botão não trava um
     * POST feito à mão.
     */
    public function definirPonto(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'ponto_partida_id' => ['required', 'integer'],
            'destino_id' => ['required', 'integer', 'different:ponto_partida_id'],
        ], [
            'veiculo_id.required' => 'Selecione o ponto de partida e o destino.',
            'ponto_partida_id.required' => 'Selecione o ponto de partida e o destino.',
            'destino_id.required' => 'Selecione o ponto de partida e o destino.',
            'destino_id.different' => 'O destino tem de ser diferente do ponto de partida.',
        ]);

        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first();

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        if ($veiculo->pontoBloqueado()) {
            return response()->json([
                'erro' => 'O ponto deste veículo foi guardado há pouco e só pode ser alterado a partir de '
                    .$veiculo->ponto_bloqueado_ate->format('d/m/Y H:i')
                    .'. Para o mudar antes disso, peça autorização ao administrador.',
                'ponto_bloqueado_ate' => $veiculo->ponto_bloqueado_ate,
            ], 422);
        }

        $ponto = PontoPartida::query()
            ->whereKey($dados['ponto_partida_id'])
            ->where('status', 'aprovado')
            ->first();

        if (! $ponto) {
            return response()->json(['erro' => 'Ponto de partida inválido ou ainda não aprovado.'], 422);
        }

        $bloqueadoAte = now()->addHours((int) config('vmillion.veiculos.bloqueio_ponto_horas'));

        // Ancora o veículo nas coordenadas reais do ponto escolhido: nunca
        // fica "a flutuar" no mapa sem lat/lng.
        $veiculo->forceFill([
            'ponto_partida_id' => $ponto->id,
            'destino_id' => $dados['destino_id'],
            'estado' => 'no_ponto',
            'posicao_fila' => null,
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'ultima_posicao_em' => now(),
            'ponto_bloqueado_ate' => $bloqueadoAte,
        ])->save();

        $this->auditoria->registar(
            null,
            'condutor_definiu_ponto',
            'veiculos',
            $veiculo->id,
            "ponto={$ponto->id} destino={$dados['destino_id']}"
        );

        // Guardar o ponto é o instante em que o veículo passa a estar lá para
        // quem espera: é a partir daqui que aparece em
        // Passageiro\VeiculoController::disponiveis. Ninguém do lado de fora
        // dá por isso sem ter o painel aberto — daí o aviso.
        $this->avisos->entrouNoPonto($veiculo, $ponto);

        // A data volta na resposta para o painel esconder o botão de imediato,
        // sem esperar pelo refrescamento da lista de veículos.
        return response()->json([
            'sucesso' => true,
            'ponto_bloqueado_ate' => $bloqueadoAte,
        ]);
    }

    /**
     * Mapa de assentos do veículo tal como o condutor o edita no botão
     * "Lugares" do painel. Ao contrário do do passageiro, traz também os
     * lugares retirados, para poderem ser repostos com um toque.
     */
    public function assentos(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        $veiculo = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->first();

        if (! $veiculo) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        return response()->json($this->lugaresDoVeiculo($veiculo));
    }

    /**
     * O que o condutor faz no mapa de "Lugares":
     *
     * - 'remover' retira o lugar indicado (ou o livre de número mais alto) e
     *   'adicionar' repõe o indicado (ou o retirado de número mais baixo) ou,
     *   não havendo nenhum retirado, acrescenta um lugar novo até ao teto do
     *   tipo — VeiculoService::retirarLugar e ::reporLugar;
     * - 'ocupar' marca o lugar de um passageiro sem app (quem entrou sem
     *   reservar), com um nome opcional, e 'libertar' devolve-o —
     *   VeiculoService::ocuparSemApp e ::libertarSemApp.
     *
     * Não passa pelo administrador: a capacidade nunca sai do intervalo de 1
     * ao teto do tipo, e tudo fica em auditoria com o antes e o depois.
     */
    public function alterarLugares(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'acao' => ['required', 'in:adicionar,remover,ocupar,libertar'],
            // Sem assento_id, o servidor escolhe o lugar (ver
            // VeiculoService::ocuparSemApp e ::libertarSemApp).
            'assento_id' => ['nullable', 'integer'],
            'nome' => ['nullable', 'string', 'max:60'],
        ], [
            'veiculo_id.required' => 'Dados inválidos.',
            'acao.required' => 'Dados inválidos.',
            'acao.in' => 'Dados inválidos.',
            'nome.max' => 'O nome pode ter no máximo 60 caracteres.',
        ]);

        $assentoId = isset($dados['assento_id']) ? (int) $dados['assento_id'] : null;

        try {
            $veiculo = DB::transaction(function () use ($dados, $assentoId, $request) {
                $veiculo = Veiculo::query()
                    ->lockForUpdate()
                    ->whereKey($dados['veiculo_id'])
                    ->where('condutor_id', $request->user('utilizador')->id)
                    ->first();

                if (! $veiculo) {
                    throw new RuntimeException('Veículo não encontrado.', 404);
                }

                $totalAntes = $veiculo->lugares_total;
                $livresAntes = $veiculo->lugares_livres;

                $assento = match ($dados['acao']) {
                    'adicionar' => $this->veiculos->reporLugar($veiculo, $assentoId),
                    'remover' => $this->veiculos->retirarLugar($veiculo, $assentoId),
                    'ocupar' => $this->veiculos->ocuparSemApp($veiculo, $assentoId, $dados['nome'] ?? null),
                    'libertar' => $this->veiculos->libertarSemApp($veiculo, $assentoId),
                };

                [$acao, $contagem] = match ($dados['acao']) {
                    'adicionar' => ['condutor_adicionou_lugar', "lugares={$totalAntes}->{$veiculo->lugares_total}"],
                    'remover' => ['condutor_retirou_lugar', "lugares={$totalAntes}->{$veiculo->lugares_total}"],
                    'ocupar' => ['condutor_ocupou_lugar_sem_app', "livres={$livresAntes}->{$veiculo->lugares_livres}"],
                    'libertar' => ['condutor_libertou_lugar_sem_app', "livres={$livresAntes}->{$veiculo->lugares_livres}"],
                };

                $this->auditoria->registar(null, $acao, 'veiculos', $veiculo->id, "lugar={$assento->numero} {$contagem}");

                return $veiculo;
            });
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json(['sucesso' => true, 'veiculo_id' => $veiculo->id] + $this->lugaresDoVeiculo($veiculo));
    }

    /**
     * @return array{lugares_total: int, lugares_livres: int, lugares_max: int, assentos: mixed}
     */
    private function lugaresDoVeiculo(Veiculo $veiculo): array
    {
        return [
            'lugares_total' => (int) $veiculo->lugares_total,
            'lugares_livres' => (int) $veiculo->lugares_livres,
            'lugares_max' => $this->veiculos->lugaresMax((string) $veiculo->tipo),
            'assentos' => AssentoVeiculo::query()
                ->where('veiculo_id', $veiculo->id)
                ->orderBy('numero')
                ->get(['id', 'numero', 'fila', 'coluna', 'ocupado', 'ativo', 'ocupado_sem_app', 'nome_sem_app']),
        ];
    }

    public function eliminar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
        ], ['veiculo_id.required' => 'Veículo inválido.']);

        $eliminados = Veiculo::query()
            ->whereKey($dados['veiculo_id'])
            ->where('condutor_id', $request->user('utilizador')->id)
            ->delete();

        if ($eliminados === 0) {
            return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        }

        return response()->json(['sucesso' => true]);
    }
}
