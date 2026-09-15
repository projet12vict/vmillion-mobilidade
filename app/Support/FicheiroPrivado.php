<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprovativos e recibos vivem no disco 'local' (storage/app/private), fora
 * do document root — nunca são alcançáveis por URL direto. São servidos
 * apenas por endpoints autenticados que já verificaram quem pode ver o quê.
 */
final class FicheiroPrivado
{
    public const PASTA_COMPROVATIVOS = 'comprovativos';

    public const PASTA_RECIBOS = 'recibos';

    private const TIPOS_MIME = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * @return StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public static function servir(
        string $caminho,
        string $nomeBase,
        string $mensagemErro,
        bool $descarregar = false
    ) {
        $caminho = self::normalizar($caminho);
        $disco = Storage::disk('local');

        if (! $disco->exists($caminho)) {
            return response()->json(['erro' => $mensagemErro], 404);
        }

        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $nomeFicheiro = $nomeBase.'.'.$extensao;
        $cabecalhos = ['Content-Type' => self::TIPOS_MIME[$extensao] ?? 'application/octet-stream'];

        return $descarregar
            ? $disco->download($caminho, $nomeFicheiro, $cabecalhos)
            : $disco->response($caminho, $nomeFicheiro, $cabecalhos, 'inline');
    }

    /**
     * Registos criados antes da migração para Laravel guardaram o caminho com
     * o prefixo 'uploads/' da pasta antiga; o disco privado já é a raiz.
     */
    private static function normalizar(string $caminho): string
    {
        return ltrim(preg_replace('#^uploads/#', '', $caminho), '/');
    }
}
