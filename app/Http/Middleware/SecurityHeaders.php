<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de segurança de nível governamental (secção 14).
 * Porta de kg_send_security_headers().
 *
 * O nonce da CSP é gerado por pedido e partilhado com as views (variável
 * $cspNonce), para os blocos <script> inline dos painéis.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);
        View::share('cspNonce', $nonce);
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // microphone=(self): a chamada com voz (WebRTC, ver kg-chamada.js) usa
        // getUserMedia para o áudio — com microphone=() o pedido do microfone
        // falhava sempre, mesmo na própria origem, e a chamada ficava muda sem
        // nenhum erro visível.
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(self)');

        $response->headers->set('Content-Security-Policy', $this->politica($nonce));

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }

    /**
     * O Leaflet e o cliente socket.io são servidos de /assets/vendor, e não de
     * uma CDN. Não é preferência: enquanto 'unpkg.com' esteve em script-src, a
     * política autorizava qualquer pacote publicado no npm — a uma injeção de
     * HTML num painel bastava escrever um <script src="https://unpkg.com/...">
     * para a CSP a deixar passar. Uma allowlist de CDN generalista não é uma
     * barreira; só o é uma origem que nós controlamos.
     *
     * form-action fecha a outra saída da mesma injeção: sem esta diretiva
     * (default-src não a cobre), um formulário injetado podia enviar o que o
     * utilizador lá escrevesse para um servidor qualquer.
     */
    private function politica(string $nonce): string
    {
        return implode(' ', [
            "default-src 'self';",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://tile.openstreetmap.org;",
            "style-src 'self' 'unsafe-inline';",
            "script-src 'self' 'nonce-{$nonce}';",
            "worker-src 'self' blob:;",
            // O motor de rotas (OSRM) é sempre chamado do lado do servidor
            // (OsrmService), por isso router.project-osrm.org não entra aqui;
            // nominatim mantém-se (geocodificação de destino no mapa).
            "connect-src 'self' https://nominatim.openstreetmap.org ws: wss:;",
            "font-src 'self';",
            "object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self';",
        ]);
    }
}
