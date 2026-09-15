<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chamada;

use App\Http\Controllers\Controller;
use App\Models\Chamada;
use App\Services\ChamadaService;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chamada de voz entre passageiro e condutor (WebRTC).
 *
 * O estado vive na base de dados para o destinatário a apanhar por polling
 * mesmo que o servidor de tempo real não esteja a correr — é a camada de
 * confiança independente do socket.io.
 */
class ChamadaController extends Controller
{
    public function __construct(
        private readonly ChamadaService $chamadas,
        private readonly WebPushService $push,
    ) {}

    public function iniciar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'destinatario_id' => ['required', 'integer'],
        ], ['destinatario_id.required' => 'Indique o destinatário.']);

        $utilizador = $request->user('utilizador');
        $destinatarioId = (int) $dados['destinatario_id'];

        $this->chamadas->expirarChamadasParadas();

        if (! $this->chamadas->podeChamar($utilizador, $destinatarioId)) {
            return response()->json(['erro' => 'Sem relação ativa com este utilizador.'], 403);
        }

        // A app comporta-se como uma central telefónica: não deixa duas
        // chamadas em curso envolvendo qualquer um dos dois participantes.
        $ocupado = Chamada::query()
            ->whereIn('estado', ['iniciada', 'atendida'])
            ->where(fn ($q) => $q
                ->whereIn('remetente_id', [$utilizador->id, $destinatarioId])
                ->orWhereIn('destinatario_id', [$utilizador->id, $destinatarioId])
            )
            ->exists();

        if ($ocupado) {
            return response()->json(['erro' => 'ocupado'], 409);
        }

        $chamada = Chamada::query()->create([
            'remetente_id' => $utilizador->id,
            'destinatario_id' => $destinatarioId,
            'estado' => 'iniciada',
        ]);

        // podeChamar() já garantiu que isto é sempre condutor <-> passageiro,
        // por isso o painel do destinatário é o oposto do de quem liga — não
        // é preciso ir buscar o tipo dele à base de dados.
        $this->avisarPorPush($destinatarioId, [
            'tipo' => 'chamada',
            'chamada_id' => $chamada->id,
            'de' => $utilizador->nome,
            'url' => $utilizador->tipo === 'condutor' ? '/passageiro/painel' : '/condutor/painel',
        ]);

        return response()->json(['sucesso' => true, 'chamada_id' => $chamada->id]);
    }

    /**
     * O destinatário atende ou recusa. Nunca automático: só a partir de um
     * clique real em "Atender"/"Recusar".
     */
    public function responder(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chamada_id' => ['required', 'integer'],
            'acao' => ['required', 'in:atender,recusar'],
        ], [
            'chamada_id.required' => 'Dados inválidos.',
            'acao.required' => 'Dados inválidos.',
            'acao.in' => 'Dados inválidos.',
        ]);

        $chamada = Chamada::query()
            ->whereKey($dados['chamada_id'])
            ->where('destinatario_id', $request->user('utilizador')->id)
            ->first();

        if (! $chamada) {
            return response()->json(['erro' => 'Chamada não encontrada.'], 404);
        }

        if ($chamada->estado !== 'iniciada') {
            return response()->json(['erro' => 'Esta chamada já não está a tocar.'], 409);
        }

        $chamada->forceFill($dados['acao'] === 'atender'
            ? ['estado' => 'atendida', 'atendida_em' => now()]
            : ['estado' => 'recusada', 'terminada_em' => now()]
        )->save();

        // Quem respondeu pode ter a notificação a tocar noutro dispositivo
        // seu (atendeu no computador, o telemóvel continua a chamar). O
        // browser que trata deste push fecha-a — ver sw.js.
        $this->avisarPorPush($chamada->destinatario_id, [
            'tipo' => 'chamada_fim',
            'chamada_id' => $chamada->id,
        ]);

        return response()->json(['sucesso' => true, 'remetente_id' => $chamada->remetente_id]);
    }

    /**
     * Qualquer um dos dois lados pode desligar, em qualquer estado ainda não
     * terminado. Nunca é automático.
     */
    public function desligar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chamada_id' => ['required', 'integer'],
        ], ['chamada_id.required' => 'Indique a chamada.']);

        $utilizador = $request->user('utilizador');
        $chamada = $this->chamadas->chamadaDoParticipante((int) $dados['chamada_id'], $utilizador->id);

        if (! $chamada) {
            return response()->json(['erro' => 'Chamada não encontrada.'], 404);
        }

        // Já tinha terminado: nada a fazer, e não é um erro.
        if (! in_array($chamada->estado, ['iniciada', 'atendida'], true)) {
            return response()->json(['sucesso' => true]);
        }

        $estadoAnterior = $chamada->estado;
        $chamada->forceFill(['estado' => 'terminada', 'terminada_em' => now()])->save();

        // Desistiu antes de o outro atender: sem isto ficava uma notificação
        // a tocar num telemóvel para uma chamada que já não existe, e quem lá
        // tocasse abria a app para nada.
        if ($estadoAnterior === 'iniciada') {
            $this->avisarPorPush($chamada->destinatario_id, [
                'tipo' => 'chamada_fim',
                'chamada_id' => $chamada->id,
            ]);
        }

        return response()->json([
            'sucesso' => true,
            'outro_id' => $chamada->remetente_id === $utilizador->id
                ? $chamada->destinatario_id
                : $chamada->remetente_id,
        ]);
    }

    /**
     * Prova de vida da chamada em curso, mandada pelo painel a cada 15s
     * enquanto está atendida. É o que distingue uma chamada viva de uma que
     * ficou 'atendida' porque o browser fechou sem clicar em Desligar —
     * ChamadaService::expirarChamadasParadas() fecha as que deixam de pulsar.
     */
    public function pulso(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'chamada_id' => ['required', 'integer'],
        ], ['chamada_id.required' => 'Indique a chamada.']);

        $utilizador = $request->user('utilizador');
        $chamada = $this->chamadas->chamadaDoParticipante((int) $dados['chamada_id'], $utilizador->id);

        if (! $chamada) {
            return response()->json(['erro' => 'Chamada não encontrada.'], 404);
        }

        if ($chamada->estado !== 'atendida') {
            return response()->json(['erro' => 'Esta chamada não está em curso.'], 409);
        }

        $chamada->touch();

        return response()->json(['sucesso' => true]);
    }

    /**
     * Chamada mais recente em que este utilizador participa.
     *
     * O cliente compara (id, estado) com o que já viu para decidir se há uma
     * transição nova a mostrar — não existe "marcar como visto" no servidor.
     */
    public function verificar(Request $request): JsonResponse
    {
        $this->chamadas->expirarChamadasParadas();

        $utilizadorId = $request->user('utilizador')->id;

        $chamada = Chamada::query()
            ->join('utilizadores as ur', 'ur.id', '=', 'chamadas.remetente_id')
            ->join('utilizadores as ud', 'ud.id', '=', 'chamadas.destinatario_id')
            ->where(fn ($q) => $q
                ->where('chamadas.remetente_id', $utilizadorId)
                ->orWhere('chamadas.destinatario_id', $utilizadorId)
            )
            ->orderByDesc('chamadas.atualizada_em')
            ->first([
                'chamadas.id', 'chamadas.remetente_id', 'chamadas.destinatario_id', 'chamadas.estado',
                'ur.nome as remetente_nome',
                'ud.nome as destinatario_nome',
            ]);

        return response()->json(['chamada' => $chamada]);
    }

    /**
     * Notificação push do estado da chamada (ver WebPushService).
     *
     * defer(): corre depois da resposta já ter seguido para o browser, por
     * isso quem liga não espera pelo pedido HTTP ao serviço de push do
     * fabricante, que é feito a um servidor de terceiros e pode demorar (o
     * botão "Ligar" ficava parado até lá). E nunca propaga um erro —
     * o push é um extra por cima do estado que já está na base de dados; se
     * falhar, o destinatário apanha a chamada na mesma pelo polling de
     * /api/chamada/verificar assim que abrir a app.
     *
     * @param  array<string, mixed>  $dados
     */
    private function avisarPorPush(int $utilizadorId, array $dados): void
    {
        defer(function () use ($utilizadorId, $dados) {
            try {
                $this->push->enviarParaUtilizador(
                    $utilizadorId,
                    $dados,
                    (int) config('vmillion.push.ttl_chamada'),
                );
            } catch (Throwable $e) {
                Log::warning('Falha ao notificar chamada por push: '.$e->getMessage());
            }
        });
    }
}
