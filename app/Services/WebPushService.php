<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscricaoPush;
use App\Support\CifraWebPush;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envio de notificações push aos dispositivos subscritos.
 *
 * É a única forma de avisar alguém com a app fechada ou o telemóvel
 * bloqueado: o polling de kg-chamada.js e o socket.io só existem enquanto o
 * painel está aberto num separador vivo. Sem isto, uma chamada para quem
 * tivesse acabado de arrumar o telemóvel tocava só do lado de quem ligou.
 *
 * Nada aqui pode fazer falhar a operação que o chamou. Um push é um extra
 * em cima do estado que já está na base de dados — se o serviço do
 * fabricante estiver em baixo, a chamada continua a existir e o destinatário
 * apanha-a na mesma pelo polling assim que abrir a app.
 */
class WebPushService
{
    /**
     * Sem chaves VAPID configuradas não há push nenhum — é o estado normal
     * de um ambiente que ainda não correu `php artisan push:chaves`.
     */
    public function ativo(): bool
    {
        return config('vmillion.push.chave_publica') !== ''
            && config('vmillion.push.chave_privada') !== '';
    }

    /**
     * Manda o mesmo conteúdo para todos os dispositivos do utilizador e
     * devolve quantos o aceitaram.
     *
     * @param  array<string, mixed>  $dados  Conteúdo lido pelo 'push' do sw.js.
     * @param  string  $topico  Avisos do mesmo tópico substituem-se uns aos
     *                          outros no serviço do fabricante; avisos de
     *                          assuntos diferentes têm de ter tópicos
     *                          diferentes, senão o aviso do ponto apagava uma
     *                          chamada ainda por entregar (e vice-versa).
     */
    public function enviarParaUtilizador(int $utilizadorId, array $dados, int $ttl = 60, string $topico = 'vm-chamada'): int
    {
        if (! $this->ativo()) {
            return 0;
        }

        $conteudo = (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $entregues = 0;

        foreach (SubscricaoPush::query()->where('utilizador_id', $utilizadorId)->get() as $subscricao) {
            if ($this->enviar($subscricao, $conteudo, $ttl, $topico)) {
                $entregues++;
            }
        }

        return $entregues;
    }

    private function enviar(SubscricaoPush $subscricao, string $conteudo, int $ttl, string $topico): bool
    {
        try {
            $corpo = CifraWebPush::cifrar($conteudo, $subscricao->chave_p256dh, $subscricao->chave_auth);

            $jwt = CifraWebPush::jwtVapid(
                $subscricao->endpoint,
                (string) config('vmillion.push.assunto'),
                (string) config('vmillion.push.chave_privada'),
                (string) config('vmillion.push.chave_publica'),
            );

            $resposta = Http::withHeaders([
                'Authorization' => 'vapid t='.$jwt.', k='.config('vmillion.push.chave_publica'),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                // O TTL é de quem manda: uma chamada a tocar não tem
                // interesse nenhum entregue dez minutos depois de o telefone
                // ter desligado, mas "o veículo saiu do ponto" ainda diz
                // alguma coisa a quem só liga a rede um bocado depois.
                'TTL' => (string) $ttl,
                // 'high' é o que faz um Android acordar o dispositivo em vez
                // de guardar o push para a próxima vez que o ecrã acender.
                'Urgency' => 'high',
                // Mesmo tópico = o serviço substitui um push ainda por
                // entregar em vez de acumular avisos de uma chamada que já
                // terminou entretanto.
                'Topic' => $topico,
            ])
                ->withBody($corpo, 'application/octet-stream')
                ->timeout((int) config('vmillion.push.timeout'))
                ->post($subscricao->endpoint);
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar push para a subscrição '.$subscricao->id.': '.$e->getMessage());

            return false;
        }

        // 404/410: a subscrição deixou de existir (app desinstalada, permissão
        // revogada, dados do site limpos). É a única notificação que o
        // fabricante dá disso, por isso é aqui que a linha morta desaparece —
        // sem isto, a tabela só crescia e cada envio pagava um pedido HTTP
        // por dispositivo que já não existe.
        if (in_array($resposta->status(), [404, 410], true)) {
            $subscricao->delete();

            return false;
        }

        if ($resposta->failed()) {
            Log::warning('Serviço de push devolveu '.$resposta->status().' para a subscrição '.$subscricao->id.'.');

            return false;
        }

        $subscricao->forceFill(['usado_em' => now()])->save();

        return true;
    }
}
