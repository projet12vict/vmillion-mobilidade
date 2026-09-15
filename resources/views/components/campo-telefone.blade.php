@props([
    'nome' => 'telefone',
    'id' => 'telefone',
    'etiqueta' => 'Telefone',
    'pais' => 'CV',
    'valor' => '',
    'obrigatorio' => true,
    'autofocus' => false,
])

@php
    $paises = config('paises');
    $selecionado = collect($paises)->firstWhere('iso', $pais) ?? $paises[0];
@endphp

{{--
    Campo de telefone com seletor de país (indicativo E.164).

    O que é submetido é o <input type="hidden" name="{{ $nome }}">: o JS
    (public/assets/js/vm-telefone.js) junta lá o indicativo do país escolhido
    com o número nacional, no formato "+238 9912345". O input visível não tem
    'name' de propósito — se tivesse, o número nacional seguia sozinho no
    pedido e o servidor via um telefone sem indicativo.

    Atenção: o seletor mostra os 246 países, mas o registo continua a aceitar
    apenas Cabo Verde (config('vmillion.validacao.regex_telefone')). Escolher
    outro país dá erro de validação — do lado do servidor, e também aqui pelo
    pré-teste do formulário.

    As bandeiras não vêm do HTML: são compostas no browser a partir do código
    ISO (dois indicadores regionais). Onde o sistema não tem tipo de letra com
    bandeiras (Windows), fica o par de letras, que continua a identificar o
    país.
--}}
<div class="campo">
  <label class="campo-etiqueta" for="{{ $id }}">{{ $etiqueta }}</label>

  <div class="campo-tel" data-tel data-valor-inicial="{{ $valor }}">
    <div class="campo-tel-grupo">
      <button type="button"
              class="campo-tel-botao"
              data-tel-botao
              aria-haspopup="listbox"
              aria-expanded="false"
              aria-label="País do indicativo: {{ $selecionado['nome'] }} ({{ $selecionado['indicativo'] }})">
        <span class="campo-tel-bandeira" data-tel-bandeira aria-hidden="true">{{ $selecionado['iso'] }}</span>
        <span class="campo-tel-indicativo" data-tel-indicativo>{{ $selecionado['indicativo'] }}</span>
        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </button>

      <input class="campo-tel-numero"
             type="tel"
             id="{{ $id }}"
             data-tel-numero
             inputmode="tel"
             autocomplete="tel-national"
             placeholder="{{ $selecionado['exemplo'] ?? '' }}"
             @if ($obrigatorio) required @endif
             @if ($autofocus) autofocus @endif>
    </div>

    <input type="hidden" name="{{ $nome }}" data-tel-valor value="{{ $valor }}">

    <div class="campo-tel-painel" data-tel-painel hidden>
      <input type="text"
             class="campo-tel-pesquisa"
             data-tel-pesquisa
             autocomplete="off"
             role="combobox"
             aria-controls="{{ $id }}-lista"
             aria-expanded="true"
             aria-autocomplete="list"
             placeholder="Procurar país ou indicativo"
             aria-label="Procurar país ou indicativo">

      <ul class="campo-tel-lista" id="{{ $id }}-lista" data-tel-lista role="listbox" aria-label="País">
        @foreach ($paises as $p)
          <li class="campo-tel-opcao"
              id="{{ $id }}-pais-{{ $p['iso'] }}"
              role="option"
              tabindex="-1"
              aria-selected="{{ $p['iso'] === $selecionado['iso'] ? 'true' : 'false' }}"
              data-tel-opcao
              data-iso="{{ $p['iso'] }}"
              data-nome="{{ $p['nome'] }}"
              data-indicativo="{{ $p['indicativo'] }}"
              data-exemplo="{{ $p['exemplo'] ?? '' }}">
            <span class="campo-tel-bandeira" data-tel-bandeira aria-hidden="true">{{ $p['iso'] }}</span>
            <span class="campo-tel-nome">{{ $p['nome'] }}</span>
            <span class="campo-tel-dial">{{ $p['indicativo'] }}</span>
          </li>
        @endforeach
      </ul>

      <p class="campo-tel-vazio" data-tel-vazio hidden>Nenhum país corresponde à pesquisa.</p>
    </div>
  </div>

  <span class="campo-erro" data-erro="{{ $nome }}"></span>
</div>
