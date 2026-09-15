<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Administrador;
use App\Models\Notificacao;
use App\Models\PontoPartida;
use App\Models\Reserva;
use App\Models\Veiculo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos do ponto e da viagem: o veículo entrou no ponto, entrou na fila,
 * saiu do ponto e chegou ao ponto de destino (secções 9.3 e 9.7).
 *
 * São os instantes em que quem está do lado de fora não tem como saber o que
 * se passou: a entrada no ponto e na fila decidem-se dentro do carro, a
 * partida também (o condutor carrega em "Iniciar rota") e a chegada é
 * detetada pelo GPS, sem ninguém carregar em nada. Quem estava à espera no
 * ponto, ou a dormitar no banco de trás, só dava por alguma delas se tivesse
 * o painel aberto à frente dos olhos — daí o push, que chega com a app
 * fechada.
 *
 * Cada aviso vai por dois canais: push para quem tem dispositivo subscrito e
 * uma linha em 'notificacoes' para ficar no sino do painel de quem só abrir a
 * app mais tarde. Os administradores levam apenas a linha — as subscrições de
 * push são de utilizadores (condutores e passageiros), o backoffice não tem
 * dispositivos registados.
 *
 * Nada aqui pode fazer falhar a viagem: o envio corre em defer(), depois de a
 * resposta já ter seguido para o condutor, e engole os próprios erros. O
 * estado real está na base de dados e o painel do passageiro apanha-o na
 * mesma no ciclo seguinte de /api/passageiro/minha_reserva.
 */
class AvisoViagemService
{
    /**
     * Tópico próprio: partilhar o das chamadas fazia o serviço do fabricante
     * substituir um aviso pelo outro (ver WebPushService::enviarParaUtilizador).
     */
    private const TOPICO = 'vm-viagem';

    private const PAINEL_PASSAGEIRO = '/passageiro/painel';

    private const PAINEL_CONDUTOR = '/condutor/painel';

    public function __construct(private readonly WebPushService $push) {}

    /**
     * O veículo entrou no ponto de partida (o condutor guardou o ponto).
     *
     * Interessa a quem espera nesse ponto — é um carro a mais para onde
     * reservar — e é a partir daqui que o veículo aparece na lista de
     * Passageiro\VeiculoController::disponiveis.
     */
    public function entrouNoPonto(Veiculo $veiculo, PontoPartida $ponto): void
    {
        $destino = $this->nomeDoPonto($veiculo->destino_id);

        $this->difundir($veiculo, [
            'evento' => 'entrada_ponto',
            'titulo' => 'Veículo entrou no ponto',
            'corpo' => $veiculo->matricula.' entrou em '.$ponto->nome
                .($destino !== null && $destino !== $ponto->nome ? ', com destino a '.$destino : '').'.',
        ]);
    }

    /**
     * O veículo entrou na fila do ponto. Não é o mesmo que estar no ponto: a
     * fila dá prioridade na recolha, e a posição diz a quem espera quantos
     * carros saem antes deste.
     */
    public function entrouNaFila(Veiculo $veiculo, int $posicao): void
    {
        $ponto = $this->nomeDoPonto($veiculo->ponto_partida_id);

        $this->difundir($veiculo, [
            'evento' => 'entrada_fila',
            'titulo' => 'Veículo entrou na fila',
            'corpo' => $veiculo->matricula.' entrou na fila'.($ponto !== null ? ' de '.$ponto : ' do ponto')
                .', na posição '.$posicao.'.',
        ]);
    }

    /**
     * O veículo saiu do ponto de partida.
     *
     * São três mensagens diferentes porque são três situações diferentes: uns
     * vão dentro do carro (ou estão a ser recolhidos), outros ficaram para
     * trás com a reserva libertada por o veículo ter saído do ponto — a esses
     * o aviso só serve para alguma coisa se disser que o lugar já não existe —
     * e o resto do ponto (quem lá espera por outro carro, o condutor e o
     * backoffice) só precisa de saber que aquele veículo deixou de estar lá.
     *
     * @param  array<int, int>  $emViagem  passageiros que seguem com o veículo
     * @param  array<int, int>  $semLugar  passageiros cuja reserva ficou sem efeito
     */
    public function partiuDoPonto(Veiculo $veiculo, array $emViagem, array $semLugar): void
    {
        $partida = $this->nomeDoPonto($veiculo->ponto_partida_id);
        $destino = $this->nomeDoPonto($veiculo->destino_id);

        // Um veículo urbano circula sem ponto guardado, e há rotas em que
        // partida e destino são o mesmo ponto: em qualquer dos casos a frase
        // fica pelo genérico, em vez de nomear um ponto que não diz nada.
        $saiu = $veiculo->matricula.($partida !== null ? ' saiu de '.$partida : ' saiu do ponto');
        $rumo = $destino !== null && $destino !== $partida ? ', a caminho de '.$destino : '';

        $this->avisar($emViagem, [
            'evento' => 'partida',
            'titulo' => 'O veículo saiu do ponto',
            'corpo' => $saiu.$rumo.'.',
        ], self::PAINEL_PASSAGEIRO);

        $this->avisar($semLugar, [
            'evento' => 'partida_sem_lugar',
            'titulo' => 'O veículo saiu do ponto',
            'corpo' => $saiu.' e a sua reserva foi libertada. Pode reservar outro veículo no ponto.',
        ], self::PAINEL_PASSAGEIRO);

        // Sem os passageiros do próprio veículo: esses acabaram de levar uma
        // das duas mensagens acima, que lhes diz o que isto significa para a
        // reserva deles. Repetir aqui era o mesmo aviso duas vezes.
        $this->difundir($veiculo, [
            'evento' => 'saida_ponto',
            'titulo' => 'Veículo saiu do ponto',
            'corpo' => $saiu.$rumo.'.',
        ], incluirPassageirosDoVeiculo: false);
    }

    /**
     * O veículo chegou ao ponto de destino e as viagens a bordo fecharam-se
     * sozinhas. O ponto vem de fora porque, quando isto é chamado, o veículo
     * já tem partida e destino invertidos para o regresso.
     *
     * @param  array<int, int>  $passageiros  quem vinha na viagem que acabou
     */
    public function chegouAoPonto(Veiculo $veiculo, array $passageiros, ?PontoPartida $ponto): void
    {
        $chegou = $ponto !== null
            ? $veiculo->matricula.' chegou a '.$ponto->nome.'.'
            : $veiculo->matricula.' chegou ao ponto de destino.';

        $this->avisar($passageiros, [
            'evento' => 'chegada',
            'titulo' => 'O veículo chegou ao ponto',
            'corpo' => $chegou.' A sua viagem está concluída.',
        ], self::PAINEL_PASSAGEIRO);
    }

    /**
     * O condutor tirou o passageiro da viagem: "Não apareceu" antes de
     * embarcar ou "Remover" já a bordo (Condutor\PassageiroController). Do
     * lado do passageiro a reserva ficou sem efeito sem ele ter feito nada —
     * sem este aviso só dava por isso ao abrir a app.
     */
    public function retiradoPeloCondutor(Veiculo $veiculo, int $passageiroId, bool $iaABordo): void
    {
        $this->avisar([$passageiroId], $iaABordo ? [
            'evento' => 'removido_a_bordo',
            'titulo' => 'A sua viagem terminou',
            'corpo' => 'O condutor de '.$veiculo->matricula.' terminou a sua viagem antes do destino.',
        ] : [
            'evento' => 'nao_apareceu',
            'titulo' => 'Reserva libertada',
            'corpo' => 'O condutor de '.$veiculo->matricula.' não o(a) encontrou e libertou a reserva. Pode reservar outro veículo.',
        ], self::PAINEL_PASSAGEIRO);
    }

    /**
     * O mesmo acontecimento do ponto contado a toda a gente a quem ele diz
     * respeito: os passageiros do veículo, quem espera nesse ponto por outro
     * carro, o próprio condutor e os administradores.
     *
     * @param  array<string, mixed>  $dados
     */
    private function difundir(Veiculo $veiculo, array $dados, bool $incluirPassageirosDoVeiculo = true): void
    {
        $passageiros = $incluirPassageirosDoVeiculo ? $this->passageirosDoVeiculo($veiculo) : [];

        $this->avisar(
            array_merge($passageiros, $this->passageirosNoPonto($veiculo)),
            $dados,
            self::PAINEL_PASSAGEIRO,
        );

        $this->avisar([$veiculo->condutor_id], $dados, self::PAINEL_CONDUTOR);

        $this->registarParaAdmins($veiculo, $dados);
    }

    /**
     * Quem já tem lugar preso neste veículo.
     *
     * @return array<int, int>
     */
    private function passageirosDoVeiculo(Veiculo $veiculo): array
    {
        return Reserva::query()
            ->where('veiculo_id', $veiculo->id)
            ->ativas()
            ->pluck('passageiro_id')
            ->all();
    }

    /**
     * Quem ainda espera neste ponto — a razão de ser destes avisos: é a essas
     * pessoas que um carro a entrar ou a sair do ponto muda alguma coisa.
     *
     * Só até à recolha: quem já está 'a_caminho' ou 'a_bordo' de outro
     * veículo saiu do mercado e não ganha nada em saber que chegou mais um
     * carro ao ponto onde já não está. Os passageiros do próprio veículo
     * ficam de fora porque são tratados à parte, com a mensagem que fala da
     * reserva deles.
     *
     * Um veículo ainda por aprovar também não conta: não aparece na lista de
     * veículos disponíveis, portanto avisar era oferecer um carro que ninguém
     * pode reservar.
     *
     * @return array<int, int>
     */
    private function passageirosNoPonto(Veiculo $veiculo): array
    {
        if (! $veiculo->aprovado || $veiculo->ponto_partida_id === null) {
            return [];
        }

        return Reserva::query()
            ->where('ponto_partida_id', $veiculo->ponto_partida_id)
            ->where('veiculo_id', '!=', $veiculo->id)
            ->whereIn('estado', Reserva::ESTADOS_ANTES_DA_RECOLHA)
            ->pluck('passageiro_id')
            ->all();
    }

    private function nomeDoPonto(?int $pontoId): ?string
    {
        if ($pontoId === null) {
            return null;
        }

        return PontoPartida::query()->find($pontoId)?->nome;
    }

    /**
     * Push agora e linha no sino para depois — o push só chega a quem tem
     * dispositivo subscrito e não deixa rasto nenhum na app.
     *
     * @param  array<int, int>  $utilizadores
     * @param  array<string, mixed>  $dados
     */
    private function avisar(array $utilizadores, array $dados, string $painel): void
    {
        // intval(): consoante o driver, o pluck() dos controllers traz os ids
        // como string — e daqui vão para um parâmetro int declarado sob
        // strict_types, que rebentaria com TypeError.
        $utilizadores = array_values(array_unique(array_map('intval', $utilizadores)));

        if ($utilizadores === []) {
            return;
        }

        $conteudo = $dados + ['tipo' => 'viagem', 'url' => $painel];
        $tipoDestinatario = $painel === self::PAINEL_CONDUTOR ? 'condutores' : 'passageiros';

        defer(function () use ($utilizadores, $conteudo, $dados, $tipoDestinatario) {
            foreach ($utilizadores as $utilizadorId) {
                try {
                    $this->push->enviarParaUtilizador(
                        $utilizadorId,
                        $conteudo,
                        (int) config('vmillion.push.ttl_viagem'),
                        self::TOPICO,
                    );
                } catch (Throwable $e) {
                    Log::warning('Falha ao avisar o utilizador '.$utilizadorId.' do ponto: '.$e->getMessage());
                }
            }

            $this->registar($utilizadores, $tipoDestinatario, $dados['titulo'], $dados['corpo']);
        });
    }

    /**
     * Os administradores acompanham a movimentação dos pontos pelo sino do
     * painel de administração — não há push para eles: as subscrições são de
     * utilizadores, e o backoffice não regista dispositivos.
     *
     * @param  array<string, mixed>  $dados
     */
    private function registarParaAdmins(Veiculo $veiculo, array $dados): void
    {
        $condutor = $veiculo->condutor?->nome;
        $mensagem = $dados['corpo'].($condutor !== null ? ' Condutor: '.$condutor.'.' : '');

        defer(function () use ($mensagem, $dados) {
            $admins = Administrador::query()->where('ativo', true)->pluck('id')->all();

            $this->registar($admins, 'admins', $dados['titulo'], $mensagem);
        });
    }

    /**
     * Uma linha por destinatário, como no fan-out do Super Admin: o estado
     * lida/não-lida é individual mesmo quando a mensagem é a mesma.
     *
     * 'remetente_id' fica a null — isto não é ninguém a escrever a ninguém, é
     * o sistema a relatar o que o veículo fez.
     *
     * @param  array<int, int>  $destinatarios
     */
    private function registar(array $destinatarios, string $tipo, string $titulo, string $mensagem): void
    {
        if ($destinatarios === []) {
            return;
        }

        $agora = now();

        try {
            Notificacao::query()->insert(array_map(fn (int $id): array => [
                'destinatario_id' => $id,
                'destinatario_tipo' => $tipo,
                'remetente_id' => null,
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'tipo' => 'informativo',
                'lida' => false,
                'criado_em' => $agora,
            ], array_map('intval', $destinatarios)));
        } catch (Throwable $e) {
            Log::warning('Falha ao registar o aviso do ponto no sino: '.$e->getMessage());
        }
    }
}
