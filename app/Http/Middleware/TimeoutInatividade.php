<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Utilizador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Timeout de inatividade (2 horas por omissão), independente do lifetime do
 * cookie de sessão: uma sessão parada é destruída mesmo que o cookie ainda
 * seja válido. Porta do bloco de inatividade de kg_session_start().
 *
 * Os utilizadores do guard 'utilizador' (passageiros e condutores) estão de
 * fora: entram com "manter sessão iniciada" (ver LoginController::autenticar)
 * e só saem quando carregam em "Sair". O painel do condutor fica horas aberto
 * sem um clique enquanto ele conduz ou espera na fila, e o do passageiro fica
 * aberto entre viagens — mandá-los para o /login tirava o condutor do mapa e
 * da fila e obrigava o passageiro a reautenticar-se a cada pedido de viagem.
 * O timeout continua a valer para o guard 'admin'. As contas continuam a ser
 * reconfirmadas a cada pedido — quem for suspenso cai na mesma (ver
 * BloquearUtilizadorSuspenso).
 */
class TimeoutInatividade
{
    private const CHAVE = 'vmillion_ultimo_acesso';

    public function handle(Request $request, Closure $next): Response
    {
        $limite = (int) config('vmillion.sessao.inactivity_timeout') * 60;
        $ultimoAcesso = $request->session()->get(self::CHAVE);

        // Resolve o guard 'utilizador' antes da verificação: é aqui que uma
        // sessão já expirada volta a ser autenticada a partir do cookie de
        // recordação.
        $sessaoPersistente = $request->user('utilizador') instanceof Utilizador;

        if (! $sessaoPersistente
            && $ultimoAcesso !== null
            && (time() - (int) $ultimoAcesso) > $limite) {
            foreach (array_keys(config('auth.guards')) as $guard) {
                if (Auth::guard($guard)->check()) {
                    Auth::guard($guard)->logout();
                }
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->put(self::CHAVE, time());

        return $next($request);
    }
}
