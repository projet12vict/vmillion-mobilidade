<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Administrador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rate limiting de login por conta (secção 14): ao fim de N tentativas
 * falhadas a conta fica bloqueada durante alguns minutos, e cada bloqueio
 * consecutivo dura o dobro do anterior até ao teto configurado.
 *
 * Ao contrário do RateLimiter do Laravel (que conta por IP/sessão), o
 * bloqueio vive na própria linha da conta — tentativas_login e bloqueado_ate
 * — porque é o que permite a um admin ver e limpar bloqueios no painel. As
 * duas camadas coexistem: o limitador 'login' (ver AppServiceProvider) trava
 * o volume por IP, esta trava a insistência contra uma conta concreta.
 *
 * tentativas_login é cumulativo e só volta a zero num login com sucesso: é
 * assim que um bloqueio já expirado devolve à conta um novo lote de
 * max_tentativas. Enquanto o contador era comparado com >= max_tentativas,
 * a primeira falha depois de um bloqueio expirado voltava a bloquear de
 * imediato — quem soubesse o telefone de alguém podia manter essa pessoa
 * fora da própria conta indefinidamente, com um pedido a cada quinze
 * minutos.
 *
 * O Super Admin nunca é bloqueado: seria a única forma de ficar sem acesso
 * ao backoffice sem ninguém que o pudesse desbloquear.
 */
class LoginThrottleService
{
    /**
     * Teto do expoente do backoff, só para o cálculo nunca transbordar se uma
     * conta acumular um número absurdo de tentativas.
     */
    private const MAX_EXPOENTE = 20;

    /**
     * Minutos que faltam até a conta desbloquear, ou null se não estiver
     * bloqueada.
     */
    public function minutosBloqueadaRestantes(Model $conta): ?int
    {
        $bloqueadoAte = $conta->getAttribute('bloqueado_ate');

        if (! $bloqueadoAte instanceof Carbon || $bloqueadoAte->isPast()) {
            return null;
        }

        return (int) ceil(now()->diffInSeconds($bloqueadoAte) / 60);
    }

    public function estaBloqueada(Model $conta): bool
    {
        return $this->minutosBloqueadaRestantes($conta) !== null;
    }

    public function registarFalha(Model $conta): void
    {
        if ($conta instanceof Administrador && $conta->isSuper()) {
            return;
        }

        $max = max(1, (int) config('vmillion.login.max_tentativas'));
        $tentativas = (int) $conta->getAttribute('tentativas_login') + 1;
        $atributos = ['tentativas_login' => $tentativas];

        // Um bloqueio que já passou deixa de constar na conta: o que fica lá é
        // só o bloqueio em vigor, para quem olhar para a linha (ou para o
        // painel) não confundir um bloqueio antigo com um ativo.
        $bloqueadoAte = $conta->getAttribute('bloqueado_ate');

        if ($bloqueadoAte instanceof Carbon && $bloqueadoAte->isPast()) {
            $atributos['bloqueado_ate'] = null;
        }

        // A conta bloqueia a cada lote completo de max_tentativas falhas: às 5,
        // às 10, às 15. As falhas entre lotes não prolongam o bloqueio anterior.
        if ($tentativas % $max === 0) {
            $atributos['bloqueado_ate'] = now()->addMinutes(
                $this->minutosDeBloqueio(intdiv($tentativas, $max))
            );
        }

        $conta->forceFill($atributos)->save();
    }

    public function limpar(Model $conta): void
    {
        if ((int) $conta->getAttribute('tentativas_login') === 0 && $conta->getAttribute('bloqueado_ate') === null) {
            return;
        }

        $conta->forceFill(['tentativas_login' => 0, 'bloqueado_ate' => null])->save();
    }

    /**
     * Duração do n-ésimo bloqueio consecutivo: base, o dobro, o quádruplo, até
     * bloqueio_max_minutos. O teto existe para o bloqueio não se tornar ele
     * próprio uma forma de negar o acesso a uma conta para sempre.
     */
    private function minutosDeBloqueio(int $bloqueiosConsecutivos): int
    {
        $base = (int) config('vmillion.login.bloqueio_minutos');
        $teto = (int) config('vmillion.login.bloqueio_max_minutos');
        $expoente = min(max($bloqueiosConsecutivos - 1, 0), self::MAX_EXPOENTE);

        return max(1, min($base * (2 ** $expoente), $teto));
    }
}
