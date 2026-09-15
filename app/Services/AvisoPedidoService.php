<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reserva;
use App\Models\Veiculo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso aos condutores de que chegou um pedido de passageiro.
 *
 * O painel do condutor já apanha os pedidos no polling de 10 s e toca um som
 * — mas só enquanto está aberto, à frente dos olhos e com o ecrã ligado. Um
 * condutor distraído, com o telemóvel bloqueado no suporte ou a app fechada,
 * não dava por nada e o passageiro ficava à espera de alguém que nunca soube
 * dele. O push é a única coisa que chega nessa situação.
 *
 * Os destinatários são exatamente quem vê o pedido na sua lista (as mesmas
 * regras de Condutor\PassageiroController): avisar alguém que depois abre o
 * painel e não encontra pedido nenhum é pior do que não avisar.
 *
 * Só push, sem linha no sino: um pedido é reclamado em minutos, e no sino
 * ficava a apontar para um passageiro que já seguiu com outro carro.
 *
 * Nada aqui pode fazer falhar o pedido do passageiro: corre em defer(), depois
 * de a resposta já ter seguido, e engole os próprios erros.
 */
class AvisoPedidoService
{
    /**
     * Tópico próprio: com o das chamadas ou o da viagem, o serviço do
     * fabricante substituía um aviso pelo outro ainda por entregar.
     */
    private const TOPICO = 'vm-pedido';

    private const PAINEL_CONDUTOR = '/condutor/painel';

    public function __construct(
        private readonly WebPushService $push,
        private readonly VeiculoService $veiculos,
        private readonly MunicipioService $municipios,
    ) {}

    /**
     * Pedido urbano em aberto: vai para os condutores com um veículo a receber
     * procura urbana no mesmo concelho do passageiro (ver
     * PassageiroController::urbanos).
     */
    public function pedidoUrbano(Reserva $reserva): void
    {
        $this->emSegundoPlano(function () use ($reserva) {
            $condutores = $this->condutoresUrbanosPara((float) $reserva->passageiro_lat, (float) $reserva->passageiro_lng);

            $this->avisar($condutores, [
                'evento' => 'pedido_urbano',
                'reserva_id' => $reserva->id,
                'titulo' => 'Novo pedido urbano',
                'corpo' => 'Passageiro à espera'.$this->paraOnde($reserva).'. Abra o painel para ir buscar.',
            ]);
        });
    }

    /**
     * Pedido intermunicipal em aberto no ponto: vai para os condutores com um
     * veículo ancorado nesse ponto (ver PassageiroController::doPonto).
     */
    public function pedidoNoPonto(Reserva $reserva): void
    {
        $this->emSegundoPlano(function () use ($reserva) {
            $condutores = Veiculo::query()
                ->where('aprovado', true)
                ->where('tipo_servico', '!=', 'urbano')
                ->where('ponto_partida_id', $reserva->ponto_partida_id)
                ->whereNotNull('destino_id')
                ->pluck('condutor_id')
                ->all();

            $ponto = $reserva->pontoPartida?->nome;

            $this->avisar($condutores, [
                'evento' => 'pedido_ponto',
                'reserva_id' => $reserva->id,
                'titulo' => 'Novo pedido no ponto',
                'corpo' => 'Passageiro à espera'.($ponto !== null ? ' em '.$ponto : ' no seu ponto')
                    .$this->paraOnde($reserva, $reserva->destino?->nome).'. O primeiro a ir buscar fica com ele.',
            ]);
        });
    }

    /**
     * Reserva feita num lugar concreto: é só do condutor desse veículo, e é
     * ele que tem de confirmar.
     */
    public function reservaNoVeiculo(Reserva $reserva, Veiculo $veiculo): void
    {
        $this->emSegundoPlano(function () use ($reserva, $veiculo) {
            $this->avisar([(int) $veiculo->condutor_id], [
                'evento' => 'reserva_veiculo',
                'reserva_id' => $reserva->id,
                'titulo' => 'Nova reserva em '.$veiculo->matricula,
                'corpo' => 'Um passageiro reservou lugar'
                    .$this->paraOnde($reserva, $reserva->tipo_viagem === 'intermunicipal' ? $reserva->destino?->nome : null)
                    .'. Abra o painel para confirmar.',
            ]);
        });
    }

    /**
     * Condutores com um veículo a receber procura urbana (só urbano, ou
     * 'ambos' sem ponto guardado) cuja última posição conhecida cai no
     * concelho do passageiro.
     *
     * A janela de atividade corta quem não manda posição há muito: um táxi
     * estacionado desde ontem não está a trabalhar, e acordá-lo a meio da
     * noite por cada pedido da cidade não serve a ninguém.
     *
     * @return array<int, int>
     */
    private function condutoresUrbanosPara(float $lat, float $lng): array
    {
        $veiculos = Veiculo::query()
            ->where('aprovado', true)
            ->where(fn ($q) => $q
                ->where('tipo_servico', 'urbano')
                ->orWhere(fn ($q) => $q->where('tipo_servico', 'ambos')->whereNull('ponto_partida_id'))
            )
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('ultima_posicao_em', '>=', now()->subMinutes((int) config('vmillion.push.pedido_urbano_janela_min')))
            ->get(['condutor_id', 'lat', 'lng']);

        // Sem traçado não há regra do concelho: os mesmos que veem a lista,
        // que nesse caso sai sem filtro (ver PassageiroController::urbanos).
        if ($this->municipios->disponivel()) {
            $municipio = $this->municipios->nomeEm($lat, $lng);

            if ($municipio === null) {
                return [];
            }

            $veiculos = $veiculos->filter(
                fn (Veiculo $v) => $this->municipios->contem($municipio, (float) $v->lat, (float) $v->lng)
            );
        }

        return $veiculos->pluck('condutor_id')->all();
    }

    /**
     * @param  array<int, int|string>  $condutores
     * @param  array<string, mixed>  $dados
     */
    private function avisar(array $condutores, array $dados): void
    {
        $condutores = array_values(array_unique(array_map('intval', $condutores)));
        $conteudo = $dados + ['tipo' => 'pedido', 'url' => self::PAINEL_CONDUTOR];

        foreach ($condutores as $condutorId) {
            // Quem não tem taxa em dia não vê as listas (dão 403): um aviso
            // levava-o a um painel sem pedido nenhum para mostrar.
            if (! $this->veiculos->condutorPodeVerMapa($condutorId)) {
                continue;
            }

            try {
                $this->push->enviarParaUtilizador(
                    $condutorId,
                    $conteudo,
                    (int) config('vmillion.push.ttl_pedido'),
                    self::TOPICO,
                );
            } catch (Throwable $e) {
                Log::warning('Falha ao avisar o condutor '.$condutorId.' de um pedido: '.$e->getMessage());
            }
        }
    }

    private function emSegundoPlano(callable $tarefa): void
    {
        defer(function () use ($tarefa) {
            try {
                $tarefa();
            } catch (Throwable $e) {
                Log::warning('Falha ao preparar o aviso de pedido aos condutores: '.$e->getMessage());
            }
        });
    }

    private function paraOnde(Reserva $reserva, ?string $alternativa = null): string
    {
        $destino = $reserva->ponto_descida_nome ?: $alternativa;

        return $destino ? ', com destino a '.$destino : '';
    }
}
