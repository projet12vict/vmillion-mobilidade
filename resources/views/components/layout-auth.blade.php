@props([
    'titulo',
    'subtitulo' => null,
    'tituloPagina' => null,
    'largura' => 'max-w-md',
])

{{--
    Shell dos ecrãs de autenticação (login e registo).

    Em ecrãs largos divide-se em dois: painel institucional à esquerda e
    formulário à direita. Abaixo de lg o painel desaparece e fica só o
    formulário, com uma barra compacta com o logótipo — assim o teclado do
    telemóvel não disputa espaço com decoração.
--}}
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003893">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="V-MILLION">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<title>{{ $tituloPagina ?? $titulo }} — V-MILLION</title>
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-700 antialiased">

<div class="grid min-h-dvh lg:grid-cols-2">

  {{-- Painel institucional (apenas em ecrãs largos) --}}
  <aside class="relative isolate hidden overflow-hidden bg-brand-900 p-12 lg:flex lg:flex-col lg:justify-between">
    <div class="absolute inset-0 -z-10 bg-linear-135 from-brand-950 via-brand-800 to-brand-700" aria-hidden="true"></div>
    <div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="absolute -left-32 top-1/4 -z-10 h-96 w-96 rounded-full bg-accent-500/20 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -bottom-24 -right-24 -z-10 h-96 w-96 rounded-full bg-gold-500/10 blur-3xl" aria-hidden="true"></div>

    <a href="/" class="flex items-center gap-2.5 text-white transition-opacity hover:opacity-90">
      <x-logo class="shadow-lg shadow-brand-950/25" />
      <span class="text-xl font-bold tracking-tight">V-MILLION</span>
    </a>

    <div class="max-w-md">
      <h2 class="text-4xl font-bold leading-tight tracking-tight text-white">
        Chega na hora.<br>
        Vai com vaga.<br>
        <span class="bg-linear-to-r from-gold-300 to-gold-500 bg-clip-text text-transparent">Vive a Morabeza.</span>
      </h2>

      <ul class="mt-10 space-y-4">
        @foreach ([
          'Localização dos veículos em tempo real',
          'Escolhes o teu assento antes de entrar',
          'Botão SOS ligado à central em cada viagem',
        ] as $vantagem)
          <li class="flex items-center gap-3 text-blue-100/85">
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/10 text-gold-400 ring-1 ring-white/15">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
            </span>
            {{ $vantagem }}
          </li>
        @endforeach
      </ul>
    </div>

    <p class="text-sm text-blue-200/70">Piloto — Ilha de Santiago, Cabo Verde</p>
  </aside>

  {{-- Coluna do formulário --}}
  <div class="flex flex-col">
    <header class="flex items-center justify-between px-seguro [--px-seguro:1.25rem] py-4 pt-seguro [--pt-seguro:1rem] lg:hidden">
      <a href="/" class="flex items-center gap-2.5 text-brand-900">
        <x-logo class="ring-1 ring-slate-200" />
        <span class="text-lg font-bold tracking-tight">V-MILLION</span>
      </a>
      <a href="/" class="grid h-10 w-10 place-items-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Fechar e voltar à página inicial">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </a>
    </header>

    <main class="flex flex-1 items-center justify-center px-seguro sm:[--px-seguro:1.5rem] pb-seguro [--pb-seguro:3rem] pt-4 lg:p-12">
      <div class="w-full {{ $largura }}">
        <a href="/" class="mb-8 hidden items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-brand-800 lg:inline-flex">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
          Voltar à página inicial
        </a>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $titulo }}</h1>
        @if ($subtitulo)
          <p class="mt-2 text-slate-600">{{ $subtitulo }}</p>
        @endif

        <div class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 sm:p-8">
          {{ $slot }}
        </div>

        @isset($rodape)
          <div class="mt-6 text-center text-sm text-slate-600">{{ $rodape }}</div>
        @endisset
      </div>
    </main>
  </div>
</div>

{{ $scripts ?? '' }}
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
