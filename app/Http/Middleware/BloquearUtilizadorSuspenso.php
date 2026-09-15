<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Utilizador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reconfirma o estado da conta a cada pedido.
 *
 * O 'status' pode mudar fora da sessão (um admin suspende o condutor a meio
 * de uma sessão já aberta) e sem isto a sessão antiga continuava a funcionar
 * até expirar sozinha.
 *
 * Só bloqueia 'suspenso': 'pendente' continua a passar — um condutor por
 * aprovar ainda precisa de aceder ao painel para registar o primeiro veículo,
 * e essa parte é controlada à parte pelo gate de aprovação/pagamento.
 */
class BloquearUtilizadorSuspenso
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Utilizador|null $utilizador */
        $utilizador = $request->user('utilizador');

        if ($utilizador && $utilizador->isSuspenso()) {
            Auth::guard('utilizador')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(
                    ['erro' => 'A sua conta está suspensa. Contacte o administrador.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            return redirect()->route('login')->with('suspenso', true);
        }

        return $next($request);
    }
}
