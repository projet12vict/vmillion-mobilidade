@props([
    'titulo',
    'icone' => null,
    'aberta' => false,
])

{{--
    Secção do menu do painel do condutor: o título é um botão grande que abre
    e fecha o conteúdo (<details> nativo — funciona sem JS e com teclado).

    Não leva a classe .vm-card de propósito: os itens das listas lá dentro são
    .vm-card e o JS remove-os com closest('.vm-card'); se a secção também o
    fosse, um item já removido deixava o closest() subir até à secção inteira.

    Quando o ícone é dinâmico (Veículo ativo), passa-se no slot "iconeSlot".
--}}
<details {{ $attributes->merge(['class' => 'vm-menu-secao group']) }} @if ($aberta) open @endif>
  <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 rounded-2xl bg-brand-800 px-4 py-3 text-left text-base font-bold text-white shadow-md shadow-brand-950/20 transition select-none hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-400 group-open:rounded-b-none [&::-webkit-details-marker]:hidden">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-800">
      @if (isset($iconeSlot))
        {{ $iconeSlot }}
      @elseif ($icone)
        <x-icone :nome="$icone" tamanho="h-5 w-5" />
      @endif
    </span>
    <span class="min-w-0 flex-1">{{ $titulo }}</span>
    <x-icone nome="seta-baixo" tamanho="h-6 w-6" class="text-gold-400 transition-transform duration-200 group-open:rotate-180" />
  </summary>
  <div class="rounded-b-2xl border border-t-0 border-slate-200 bg-white p-4 shadow-sm">
    {{ $slot }}
  </div>
</details>
