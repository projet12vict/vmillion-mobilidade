@props([
    'nome',
    'tamanho' => 'h-5 w-5',
])

@php
    $desenho = config('icones.' . $nome);
@endphp

{{--
    Ícone de traço da interface (config/icones.php).

    Herda a cor do texto (currentColor) e não leva rótulo: é sempre
    decorativo, ao lado de texto que já diz o mesmo. Se algum dia precisar de
    ser o único conteúdo de um botão, o rótulo vai no aria-label do botão.
--}}
@if ($desenho)
  <svg {{ $attributes->merge(['class' => $tamanho . ' shrink-0']) }}
       viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $desenho !!}
  </svg>
@endif
