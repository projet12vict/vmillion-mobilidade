<?php

declare(strict_types=1);

use App\Http\Middleware\BloquearAdminInativo;
use App\Http\Middleware\BloquearUtilizadorSuspenso;
use App\Http\Middleware\ExigirSenhaDefinitiva;
use App\Http\Middleware\GarantirNivelAdmin;
use App\Http\Middleware\GarantirTipoUtilizador;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TimeoutInatividade;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // As rotas de API deste sistema não são stateless: usam a mesma
            // sessão e o mesmo token CSRF das páginas (os painéis chamam-nas
            // por fetch com credentials: 'same-origin'). Por isso entram no
            // grupo 'web' e não no grupo 'api' do Laravel.
            Route::middleware('web')
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            TimeoutInatividade::class,
            BloquearUtilizadorSuspenso::class,
            BloquearAdminInativo::class,
        ]);

        $middleware->alias([
            'tipo' => GarantirTipoUtilizador::class,
            'nivel' => GarantirNivelAdmin::class,
            'senha_definitiva' => ExigirSenhaDefinitiva::class,
        ]);

        // Convidados que batem numa página protegida vão para o login certo:
        // as contas de backoffice têm um login próprio, separado do dos
        // passageiros e condutores.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin*')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Todo o frontend (kg-chamada.js, kg-map.js e os painéis) lê o campo
        // 'erro' das respostas de API; o Laravel devolveria 'message'.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // O HttpResponseException já traz a resposta montada — é assim que
            // o middleware 'throttle' devolve o 429 do limitador (ver
            // AppServiceProvider). Sem esta saída, este render() reescrevia-o
            // como 500 'Pedido inválido.': além de esconder o motivo real, dava
            // ao limite por pedido uma resposta diferente da do bloqueio por
            // conta — e comparar as duas voltava a dizer se a conta existe, que
            // é exatamente o que a mensagem partilhada evita.
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }

            // O frontend mostra uma mensagem de cada vez, por isso o 422 leva
            // o primeiro erro de validação em 'erro' e a lista completa em
            // 'erros' para quem a quiser usar.
            if ($e instanceof ValidationException) {
                return response()->json([
                    'erro' => collect($e->errors())->flatten()->first(),
                    'erros' => $e->errors(),
                ], $e->status);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $mensagem = $status >= 500 && ! config('app.debug')
                ? 'Erro interno. Tente novamente mais tarde.'
                : ($e->getMessage() ?: 'Pedido inválido.');

            return response()->json(['erro' => $mensagem], $status);
        });
    })->create();
