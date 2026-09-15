<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Telefones de Cabo Verde: indicativo +238 e sete dígitos.
 *
 * O formulário aceita o número com ou sem espaço a seguir ao indicativo
 * ("+238 9912345" e "+2389912345"), mas a base de dados guarda sempre a
 * forma normalizada — é isso que torna o UNIQUE de 'telefone' fiável e o que
 * permite fazer login com qualquer das duas escritas.
 */
final class Telefone
{
    public static function valido(string $telefone): bool
    {
        return (bool) preg_match(config('vmillion.validacao.regex_telefone'), trim($telefone));
    }

    public static function normalizar(string $telefone): string
    {
        return '+238'.preg_replace('/\D/', '', substr(trim($telefone), 4));
    }
}
