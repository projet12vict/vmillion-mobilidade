<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Todo o URL /api/... escrito no frontend tem de bater certo com uma rota
 * registada.
 *
 * Isto não é zelo teórico: os ficheiros de public/assets/js vieram do projeto
 * antigo, onde cada endpoint era um ficheiro .php no document root, e ficaram
 * a chamar /api/rota.php e /api/chamada/*.php depois de as rotas passarem a
 * ser /api/rota e /api/chamada/*. Como o frontend trata a falha em silêncio
 * (a rota no mapa caía para linha reta, a chamada simplesmente não tocava),
 * o 404 nunca chegava ao utilizador — só se via pelo comportamento errado.
 */
class UrlsApiDoFrontendTest extends TestCase
{
    public function test_todos_os_urls_de_api_do_frontend_existem(): void
    {
        $rotas = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($rota) => '#^/'.preg_replace('#\\\{[^}]+\\\}#', '[^/]+', preg_quote($rota->uri(), '#')).'$#')
            ->all();

        $urls = [];

        foreach ($this->ficheirosDoFrontend() as $ficheiro) {
            foreach ($this->urlsDeApi((string) file_get_contents($ficheiro)) as $url) {
                $urls[$url][] = basename($ficheiro);
            }
        }

        $this->assertNotEmpty($urls, 'Nenhum URL /api/ encontrado — o varrimento deixou de funcionar.');

        foreach ($urls as $url => $ficheiros) {
            $existe = collect($rotas)->contains(fn ($padrao) => preg_match($padrao, $url) === 1);

            $this->assertTrue($existe, sprintf(
                '%s (em %s) não corresponde a nenhuma rota registada.',
                $url,
                implode(', ', array_unique($ficheiros))
            ));
        }
    }

    /** @return list<string> */
    private function ficheirosDoFrontend(): array
    {
        return array_merge(
            glob(base_path('public/assets/js/*.js')) ?: [],
            glob(base_path('resources/views/*/*.blade.php')) ?: [],
        );
    }

    /**
     * URLs literais de /api/ no código, ignorando comentários (onde aparecem
     * formas genéricas como /api/chamada/* que não são chamadas reais).
     *
     * @return list<string>
     */
    private function urlsDeApi(string $codigo): array
    {
        $codigo = (string) preg_replace('#/\*.*?\*/#s', '', $codigo);
        $codigo = (string) preg_replace('#^\s*//.*$#m', '', $codigo);

        preg_match_all('#[\'"`](/api/[A-Za-z0-9_/.\-]+)#', $codigo, $encontrados);

        return array_values(array_unique($encontrados[1]));
    }
}
