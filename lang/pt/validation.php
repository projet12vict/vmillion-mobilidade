<?php

declare(strict_types=1);

/**
 * As mensagens da regra Password (ver AppServiceProvider::regraDeSenha) não
 * passam pelo messages() dos FormRequest: a regra falha com a chave de
 * tradução directamente, e sem esta entrada o utilizador via
 * 'validation.password.uncompromised' escrito no formulário.
 *
 * O ficheiro é intencionalmente mínimo. O FileLoader do Laravel junta este
 * caminho ao do próprio framework com array_replace_recursive, por isso só é
 * preciso declarar o que se quer mudar — todas as outras mensagens continuam
 * a vir do framework.
 *
 * Fica em lang/pt porque é esse o locale da aplicação (APP_LOCALE no .env),
 * e não o 'en' que config/app.php traz como valor por omissão.
 */
return [
    'password' => [
        'letters' => 'A senha deve conter pelo menos uma letra.',
        'mixed' => 'A senha deve conter pelo menos uma maiúscula e uma minúscula.',
        'numbers' => 'A senha deve conter pelo menos um número.',
        'symbols' => 'A senha deve conter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha aparece em fugas de dados conhecidas. Escolha outra.',
    ],
];
