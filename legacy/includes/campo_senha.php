<?php
/**
 * V-MILLION — campo de senha com botão de mostrar/esconder (o "olho").
 *
 * Equivalente do componente <x-campo-senha> da app Laravel: gera o mesmo DOM
 * (data-senha / data-senha-input / data-senha-olho) para que o comportamento
 * seja o mesmo ficheiro de sempre — /assets/js/vm-senha.js. A página que usa
 * este helper tem de o carregar; o CSP do legacy é 'self' + nonce, por isso
 * nada de onclick em linha.
 *
 * Sem JS o campo continua a funcionar: o botão nasce com 'hidden' e só é
 * revelado pelo script.
 */

declare(strict_types=1);

/**
 * @param string $nome     Nome submetido no formulário.
 * @param array  $opcoes   id, etiqueta, autocomplete, minlength (int|null),
 *                         obrigatorio (bool), erro (bool — <span data-erro>).
 */
function kg_campo_senha(string $nome, array $opcoes = []): string
{
    $id = (string) ($opcoes['id'] ?? $nome);
    $etiqueta = (string) ($opcoes['etiqueta'] ?? 'Senha');
    $autocomplete = (string) ($opcoes['autocomplete'] ?? 'current-password');
    $minlength = $opcoes['minlength'] ?? null;
    $obrigatorio = (bool) ($opcoes['obrigatorio'] ?? true);
    $erro = (bool) ($opcoes['erro'] ?? false);

    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);

    $atributos = '';
    if ($minlength !== null) {
        $atributos .= ' minlength="' . (int) $minlength . '"';
    }
    if ($obrigatorio) {
        $atributos .= ' required';
    }

    $spanErro = $erro
        ? '<span class="kg-erro-msg" data-erro="' . $e($nome) . '"></span>'
        : '';

    return <<<HTML
    <div class="kg-field">
      <label class="kg-label" for="{$e($id)}">{$e($etiqueta)}</label>
      <div class="kg-campo-senha" data-senha>
        <input class="kg-input" type="password" id="{$e($id)}" name="{$e($nome)}" data-senha-input autocomplete="{$e($autocomplete)}"{$atributos}>
        <button type="button" class="kg-olho" data-senha-olho hidden aria-controls="{$e($id)}" aria-pressed="false" aria-label="Mostrar senha">
          <svg width="20" height="20" data-senha-icone="mostrar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
          </svg>
          <svg width="20" height="20" data-senha-icone="esconder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
            <path d="M10.7 6.1A10.5 10.5 0 0 1 12 6c6.4 0 10 6 10 6a17.4 17.4 0 0 1-3.1 3.8M6.5 6.6A17.1 17.1 0 0 0 2 12s3.6 6 10 6a10 10 0 0 0 4.1-.9"/>
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m3 3 18 18"/>
          </svg>
        </button>
      </div>
      {$spanErro}
    </div>
    HTML;
}
