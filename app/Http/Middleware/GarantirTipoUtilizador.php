<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Utilizador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe uma rota a passageiros ou a condutores.
 *
 * Um condutor autenticado a bater numa rota de passageiro é tratado como não
 * autenticado (401 em API, redirecionamento para o login em páginas) — é o
 * mesmo comportamento de kg_exigir_utilizador($tipo).
 *
 * Uso: ->middleware('tipo:condutor')
 */
class GarantirTipoUtilizador
{
    public function handle(Request $request, Closure $next, string $tipo): Response
    {
        /** @var Utilizador|null $utilizador */
        $utilizador = $request->user('utilizador');

        if (! $utilizador || $utilizador->tipo !== $tipo) {
            if ($request->expectsJson()) {
                return response()->json(['erro' => 'Não autenticado.'], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
