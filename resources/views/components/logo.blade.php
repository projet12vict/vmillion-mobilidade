@props([
    'caixa' => 'h-9 w-9',
    'marca' => 'w-[72%]',
])

{{--
    Logótipo V-MILLION.

    A marca é azul-escura sobre branco e tem recortes brancos (as barras
    diagonais e os entalhes triangulares), por isso a chapa tem de ser sempre
    branca — sobre fundo azul os recortes fechavam e o "M" perdia a forma.

    O ficheiro é /assets/img/logo.svg, o mesmo desenho de que saem o favicon e
    os ícones da PWA. Fica em public/ e não em resources/ porque é servido tal
    e qual, sem passar pelo Vite, como os restantes ícones.
--}}
<span {{ $attributes->merge(['class' => 'grid shrink-0 place-items-center overflow-hidden rounded-xl bg-white ' . $caixa]) }}>
  <img src="/assets/img/logo.svg" alt="" width="512" height="306" class="{{ $marca }} h-auto" aria-hidden="true">
</span>
