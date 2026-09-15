<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Administrador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe uma rota a um nível mínimo de administrador.
 * Hoje só 'super' é exigido explicitamente (criar administradores, definir
 * preços, aprovar veículos), por isso a comparação é direta.
 *
 * Uso: ->middleware('nivel:super')
 */
class GarantirNivelAdmin
{
    public function handle(Request $request, Closure $next, string $nivelMinimo): Response
    {
        /** @var Administrador|null $admin */
        $admin = $request->user('admin');

        if ($nivelMinimo === 'super' && (! $admin || ! $admin->isSuper())) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['erro' => 'Apenas o Super Admin pode executar esta ação.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            return redirect()->route('admin.painel');
        }

        return $next($request);
    }
}
