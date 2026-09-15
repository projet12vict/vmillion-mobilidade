@props([
    'nome' => 'senha',
    'id' => null,
    'etiqueta' => 'Senha',
    'variante' => 'campo',
    'autocomplete' => 'current-password',
    'minlength' => null,
    'obrigatorio' => true,
    'autofocus' => false,
    'erro' => null,
])

@php
    $id = $id ?? $nome;

    // Duas famílias de classes convivem no projeto: .campo-* nos ecrãs de
    // autenticação e .vm-* dentro dos painéis. O olho é o mesmo nos dois; só
    // a moldura à volta muda.
    $variantes = [
        'campo' => ['grupo' => 'campo', 'etiqueta' => 'campo-etiqueta', 'input' => 'campo-input'],
        'vm' => ['grupo' => 'vm-field', 'etiqueta' => 'vm-label', 'input' => 'vm-input'],
    ];
    $classes = $variantes[$variante] ?? $variantes['campo'];

    // Por omissão só a variante 'campo' traz <span data-erro>: é a que os
    // formulários de autenticação preenchem campo a campo. Nos painéis o erro
    // é um só, partilhado pelo formulário inteiro.
    $mostrarErro = $erro ?? ($variante === 'campo');
@endphp

{{--
    Campo de senha com botão de mostrar/esconder (o "olho").

    O botão troca o type do input entre 'password' e 'text'. O comportamento
    está em public/assets/js/vm-senha.js — a página que usa este componente
    tem de carregar esse ficheiro (o CSP não permite scripts em linha sem
    nonce, por isso nada de onclick aqui).

    Sem JS o campo continua a funcionar como sempre: o botão é escondido pelo
    próprio script ao arrancar (data-senha-olho começa hidden e só aparece
    quando há JS), portanto ninguém fica com um botão morto no formulário.
--}}
<div class="{{ $classes['grupo'] }}">
  <label class="{{ $classes['etiqueta'] }}" for="{{ $id }}">{{ $etiqueta }}</label>

  <div class="campo-senha" data-senha>
    <input class="{{ $classes['input'] }} campo-senha-input"
           type="password"
           id="{{ $id }}"
           name="{{ $nome }}"
           data-senha-input
           autocomplete="{{ $autocomplete }}"
           @if ($minlength) minlength="{{ $minlength }}" @endif
           @if ($obrigatorio) required @endif
           @if ($autofocus) autofocus @endif>

    <button type="button"
            class="campo-senha-olho"
            data-senha-olho
            hidden
            aria-controls="{{ $id }}"
            aria-pressed="false"
            aria-label="Mostrar senha">
      <svg class="h-5 w-5" data-senha-icone="mostrar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>
      <svg class="h-5 w-5" data-senha-icone="esconder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
        <path d="M10.7 6.1A10.5 10.5 0 0 1 12 6c6.4 0 10 6 10 6a17.4 17.4 0 0 1-3.1 3.8M6.5 6.6A17.1 17.1 0 0 0 2 12s3.6 6 10 6a10 10 0 0 0 4.1-.9"/>
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
        <path d="m3 3 18 18"/>
      </svg>
    </button>
  </div>

  @if ($mostrarErro)
    <span class="campo-erro" data-erro="{{ $nome }}"></span>
  @endif
</div>
