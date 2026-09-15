<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Administrador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uma conta de backoffice criada pelo Super Admin nasce com senha temporária
 * e não pode fazer mais nada senão trocá-la.
 *
 * A regra existia só no painelAdmin(), que redirecionava a página — e uma
 * página redirecionada não é um gate: os painéis falam com /api/admin/* por
 * fetch, e essas rotas respondiam na mesma a quem ainda tivesse a senha que
 * o Super Admin entregou. Quem soubesse essa senha (foi transmitida por fora
 * do sistema, é essa a natureza dela) operava o backoffice inteiro sem nunca
 * abrir /admin/painel.
 *
 * Uso: ->middleware('senha_definitiva'), aplicado ao grupo inteiro. A troca
 * de senha em si sai com ->withoutMiddleware('senha_definitiva') — só essa,
 * e assim uma rota nova nasce protegida em vez de nascer aberta.
 */
class ExigirSenhaDefinitiva
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Administrador|null $admin */
        $admin = $request->user('admin');

        if ($admin && $admin->senha_temporaria) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['erro' => 'Defina uma senha definitiva antes de continuar.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            return redirect()->route('admin.trocar-senha');
        }

        return $next($request);
    }
}
