<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URL de um ficheiro estático de public/ com a data de modificação no fim
 * (/assets/js/kg-chamada.js?v=1755806940).
 *
 * Estes ficheiros não passam pelo Vite — o nome nunca muda — e eram servidos
 * sem versão nenhuma. O browser e a cache da PWA ficavam agarrados à cópia
 * antiga muito depois de o servidor já ter a nova: foi assim que os painéis
 * continuaram a pedir /api/chamada/iniciar.php meses depois de a extensão
 * .php ter desaparecido das rotas, com o Laravel a responder "The route
 * api/chamada/iniciar.php could not be found".
 *
 * Com a data de modificação no URL, publicar o ficheiro muda o URL e o
 * browser é obrigado a ir buscá-lo de novo. Um ficheiro que não exista sai
 * sem versão em vez de rebentar a página — o 404 do próprio ficheiro é mais
 * fácil de perceber do que um erro de template.
 */
final class Asset
{
    public static function url(string $caminho): string
    {
        $caminho = '/'.ltrim($caminho, '/');
        $ficheiro = public_path($caminho);

        if (! is_file($ficheiro)) {
            return $caminho;
        }

        return $caminho.'?v='.filemtime($ficheiro);
    }
}
