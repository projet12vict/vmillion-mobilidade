<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verificação descartável de senha, para igualar o tempo de resposta dos
 * caminhos de login em que não existe conta nenhuma para comparar.
 *
 * Sem isto, um identificador desconhecido respondia de imediato e um
 * identificador registado pagava o custo do bcrypt (uma ordem de grandeza
 * mais): cronometrar as duas respostas dizia a quem estivesse a tentar quais
 * os telefones e emails que existem no sistema.
 *
 * O hash é gerado uma vez por processo e a partir da configuração em vigor,
 * para o custo acompanhar sempre o das senhas reais (em testes, BCRYPT_ROUNDS
 * é bem mais baixo do que em produção).
 */
final class HashReferencia
{
    private static ?string $hash = null;

    public static function consumirTempo(string $senha): void
    {
        self::$hash ??= Hash::make(Str::random(40));

        Hash::check($senha, self::$hash);
    }
}
