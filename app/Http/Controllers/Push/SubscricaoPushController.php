<?php

declare(strict_types=1);

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\SubscricaoPush;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registo e remoção dos dispositivos que recebem notificações push.
 *
 * O browser é quem gera a subscrição (kg-push.js → pushManager.subscribe);
 * aqui só se guarda o que ele devolveu, colado ao utilizador da sessão.
 */
class SubscricaoPushController extends Controller
{
    public function __construct(private readonly WebPushService $push) {}

    /**
     * A chave pública VAPID que o browser precisa de dar ao pushManager.
     * É pública por definição — o que a torna útil é o servidor ser o único
     * a ter a privada com que assina cada envio.
     */
    public function chave(): JsonResponse
    {
        return response()->json([
            'ativo' => $this->push->ativo(),
            'chave_publica' => (string) config('vmillion.push.chave_publica'),
        ]);
    }

    /**
     * Guardar é sempre updateOrCreate pelo endpoint: o mesmo browser
     * reenvia a subscrição em cada arranque do painel, e o serviço de push
     * pode renovar-lhe as chaves sem mudar o endpoint. Sem isto, cada abertura
     * da app deixava uma linha nova e o utilizador passava a receber a mesma
     * chamada várias vezes no mesmo telemóvel.
     */
    public function subscrever(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https,http'],
            'p256dh' => ['required', 'string', 'max:120'],
            'auth' => ['required', 'string', 'max:40'],
        ], [
            'endpoint.required' => 'Subscrição inválida.',
            'endpoint.url' => 'Subscrição inválida.',
            'endpoint.max' => 'Subscrição inválida.',
            'p256dh.required' => 'Subscrição inválida.',
            'auth.required' => 'Subscrição inválida.',
        ]);

        $utilizadorId = $request->user('utilizador')->id;

        // Um endpoint pertence a um dispositivo, não a uma conta: se duas
        // pessoas usarem o mesmo telemóvel, a subscrição passa para quem
        // entrou por último em vez de continuar a tocar para quem saiu.
        SubscricaoPush::query()->updateOrCreate(
            ['endpoint' => $dados['endpoint']],
            [
                'utilizador_id' => $utilizadorId,
                'chave_p256dh' => $dados['p256dh'],
                'chave_auth' => $dados['auth'],
            ],
        );

        return response()->json(['sucesso' => true]);
    }

    /**
     * O browser cancelou a subscrição (o utilizador desligou as notificações
     * no painel). Só apaga a própria: um endpoint alheio não é tocado.
     */
    public function cancelar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ], ['endpoint.required' => 'Subscrição inválida.']);

        SubscricaoPush::query()
            ->where('endpoint', $dados['endpoint'])
            ->where('utilizador_id', $request->user('utilizador')->id)
            ->delete();

        return response()->json(['sucesso' => true]);
    }
}
