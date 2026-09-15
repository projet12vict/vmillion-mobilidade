@php
    // Secções da página, na ordem em que aparecem. Alimentam a navegação de
    // topo, o menu móvel e o rodapé — mudar aqui chega para os três.
    $navegacao = [
        'como-funciona' => 'Como funciona',
        'funcionalidades' => 'Funcionalidades',
        'condutores' => 'Condutores',
        'seguranca' => 'Segurança',
        'perguntas' => 'FAQ',
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-CV" class="scroll-smooth">
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
<title>V-MILLION — Chega na hora. Vai com vaga. Vive a Morabeza.</title>
<meta name="description" content="V-MILLION liga passageiros a condutores de Hiace e táxi em Cabo Verde, com localização em tempo real, reservas com escolha de assento e botão SOS.">
<script nonce="{{ $cspNonce }}">document.documentElement.classList.add('js');</script>
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="bg-white font-sans text-slate-700 antialiased">

<a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-brand-800 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
  Saltar para o conteúdo
</a>

{{-- =========================== NAVEGAÇÃO =========================== --}}
<header id="cabecalho" class="fixed inset-x-0 top-0 z-50 pt-seguro transition-all duration-300">
  <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem] sm:h-20" aria-label="Principal">
    <a href="/" class="flex items-center gap-2.5 text-white transition-opacity hover:opacity-90">
      <x-logo class="shadow-lg shadow-brand-950/25" />
      <span class="text-lg font-bold tracking-tight sm:text-xl">V-MILLION</span>
    </a>

    <ul class="hidden items-center gap-1 lg:flex">
      @foreach ($navegacao as $ancora => $rotulo)
        <li>
          <a href="#{{ $ancora }}" class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white">{{ $rotulo }}</a>
        </li>
      @endforeach
    </ul>

    <div class="hidden items-center gap-3 lg:flex">
      <a href="/login" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white/90 transition hover:bg-white/10 hover:text-white">Entrar</a>
      <a href="/registo" class="rounded-xl bg-gold-500 px-5 py-2.5 text-sm font-bold text-brand-900 shadow-lg shadow-brand-950/20 transition hover:-translate-y-0.5 hover:bg-gold-400 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300">
        Criar conta
      </a>
    </div>

    <button type="button" id="botao-menu" aria-controls="menu-movel" aria-expanded="false"
            class="grid h-11 w-11 place-items-center rounded-xl text-white transition hover:bg-white/10 lg:hidden">
      <span class="sr-only">Abrir menu de navegação</span>
      <svg id="icone-abrir" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      <svg id="icone-fechar" class="hidden h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </nav>

  {{-- Painel móvel --}}
  <div id="menu-movel" class="hidden max-h-[calc(100dvh-4rem-env(safe-area-inset-top))] overflow-y-auto overscroll-contain border-t border-white/10 bg-brand-950/95 pb-seguro backdrop-blur-xl lg:hidden">
    <ul class="space-y-1 px-4 py-4 sm:px-6">
      @foreach ($navegacao as $ancora => $rotulo)
        <li><a href="#{{ $ancora }}" class="block rounded-xl px-4 py-3 font-medium text-white/85 transition hover:bg-white/10 hover:text-white">{{ $rotulo }}</a></li>
      @endforeach
    </ul>
    <div class="grid gap-3 border-t border-white/10 px-4 py-4 sm:px-6">
      <a href="/login" class="rounded-xl border border-white/25 px-4 py-3 text-center font-semibold text-white transition hover:bg-white/10">Entrar</a>
      <a href="/registo" class="rounded-xl bg-gold-500 px-4 py-3 text-center font-bold text-brand-900 transition hover:bg-gold-400">Criar conta</a>
    </div>
  </div>
</header>

<main id="conteudo">

{{-- =============================== HERÓI =============================== --}}
<section class="relative isolate overflow-hidden bg-brand-900 pb-20 pt-seguro [--pt-seguro:7rem] sm:pb-28 sm:[--pt-seguro:9rem] lg:pb-32 lg:[--pt-seguro:10rem]">
  {{-- Fundo: gradiente institucional + halos + grelha --}}
  <div class="absolute inset-0 -z-10 bg-linear-135 from-brand-950 via-brand-800 to-brand-700" aria-hidden="true"></div>
  <div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
  <div class="absolute -left-40 top-10 -z-10 h-[28rem] w-[28rem] rounded-full bg-accent-500/20 blur-3xl" aria-hidden="true"></div>
  <div class="absolute -right-32 bottom-0 -z-10 h-[32rem] w-[32rem] rounded-full bg-gold-500/10 blur-3xl" aria-hidden="true"></div>

  <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-14 px-seguro sm:[--px-seguro:1.5rem] lg:grid-cols-12 lg:gap-8 lg:[--px-seguro:2rem]">
    <div class="lg:col-span-6 xl:col-span-6">
      <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-white backdrop-blur-sm sm:text-sm">
        <span class="relative flex h-2 w-2">
          <span class="absolute inline-flex h-full w-full animate-pulse-ring rounded-full bg-gold-400"></span>
          <span class="relative inline-flex h-2 w-2 rounded-full bg-gold-400"></span>
        </span>
        Mobilidade inteligente para Cabo Verde
      </span>

      <h1 class="mt-6 text-4xl font-bold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl xl:text-[4.25rem]">
        Chega na hora.<br>
        Vai com vaga.<br>
        <span class="bg-linear-to-r from-gold-300 via-gold-400 to-gold-500 bg-clip-text text-transparent">Vive a Morabeza.</span>
      </h1>

      <p class="mt-6 max-w-xl text-base leading-relaxed text-blue-100/90 sm:text-lg">
        O V-MILLION liga-te a condutores de Hiace e táxi com localização em tempo real,
        reserva do teu assento e segurança em cada viagem — a começar em Santiago.
      </p>

      <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
        <a href="/login?tipo=passageiro"
           class="group inline-flex items-center justify-center gap-2 rounded-2xl bg-gold-500 px-7 py-4 text-base font-bold text-brand-900 shadow-xl shadow-brand-950/30 transition hover:-translate-y-0.5 hover:bg-gold-400 hover:shadow-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300">
          Sou Passageiro
          <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
        <a href="/login?tipo=condutor"
           class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/30 bg-white/5 px-7 py-4 text-base font-semibold text-white backdrop-blur-sm transition hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60">
          Sou Condutor
        </a>
      </div>

      <dl class="mt-12 grid max-w-lg grid-cols-3 gap-4 border-t border-white/15 pt-8 sm:gap-8">
        @foreach ([
          ['0 CVE', 'Custo para o passageiro'],
          ['14', 'Lugares mapeados por Hiace'],
          ['< 1s', 'Atualização da posição'],
        ] as [$valor, $rotulo])
          <div>
            <dt class="text-2xl font-bold text-white sm:text-3xl">{{ $valor }}</dt>
            <dd class="mt-1 text-xs leading-snug text-blue-200/80 sm:text-sm">{{ $rotulo }}</dd>
          </div>
        @endforeach
      </dl>
    </div>

    {{-- Mockup: viagem ao vivo --}}
    <div class="relative lg:col-span-6 xl:col-span-6" data-revelar>
      <div class="relative mx-auto w-full max-w-sm animate-float sm:max-w-md">
        {{-- Sem backdrop-blur aqui: este bloco está em animação contínua
             (animate-float) e o desfoque de fundo obrigaria o browser a
             recompor o herói inteiro a cada frame. --}}
        <div class="rounded-[2.5rem] border border-white/15 bg-brand-950/70 p-3 shadow-2xl shadow-brand-950/60">
          <div class="overflow-hidden rounded-[2rem] bg-slate-900">
            {{-- Mapa estilizado --}}
            <div class="relative h-56 bg-linear-160 from-slate-800 via-brand-950 to-slate-900 sm:h-64">
              <svg class="absolute inset-0 h-full w-full" viewBox="0 0 400 260" fill="none" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <path d="M-20 60 L140 90 L260 40 L420 80" stroke="rgb(255 255 255 / 0.08)" stroke-width="14"/>
                <path d="M-20 190 L120 165 L300 210 L420 180" stroke="rgb(255 255 255 / 0.08)" stroke-width="10"/>
                <path d="M70 -20 L110 120 L80 280" stroke="rgb(255 255 255 / 0.06)" stroke-width="10"/>
                <path d="M320 -20 L290 130 L330 280" stroke="rgb(255 255 255 / 0.06)" stroke-width="8"/>
                <path d="M40 220 C 120 200, 150 120, 230 96 S 330 70, 372 44"
                      stroke="#f7d116" stroke-width="4" stroke-linecap="round" stroke-dasharray="10 12"/>
              </svg>

              {{-- Marcador do veículo --}}
              <div class="absolute left-[54%] top-[36%] -translate-x-1/2 -translate-y-1/2">
                <span class="absolute inset-0 -z-10 m-auto block h-10 w-10 animate-pulse-ring rounded-full bg-accent-400/50"></span>
                <span class="grid h-10 w-10 place-items-center rounded-full bg-accent-500 shadow-lg shadow-accent-500/40 ring-4 ring-white/20">
                  <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 17h2m14 0h2M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0m6 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0M5 17V9.5L7 6h9l3 3.5V17"/>
                  </svg>
                </span>
              </div>

              {{-- Destino --}}
              <div class="absolute left-[12%] top-[80%] -translate-x-1/2 -translate-y-1/2">
                <span class="block h-4 w-4 rounded-full border-4 border-gold-400 bg-brand-950"></span>
              </div>

              <div class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-slate-950/70 px-3 py-1.5 text-[11px] font-semibold text-white">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                Em viagem — Praia &rarr; Assomada
              </div>
            </div>

            {{-- Cartão do condutor --}}
            <div class="space-y-4 bg-white p-5">
              <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-800 text-sm font-bold text-white">HC</span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-bold text-slate-900">Hiace &middot; ST-42-AB</p>
                  <p class="truncate text-xs text-slate-500">Chega ao teu ponto em 4 min</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">A caminho</span>
              </div>

              <div>
                <div class="mb-2 flex items-center justify-between">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Lugares</p>
                  <p class="text-xs font-semibold text-brand-800">5 disponíveis</p>
                </div>
                <div class="grid grid-cols-7 gap-1.5">
                  @foreach ([1,0,0,1,1,0,1,0,1,1,0,0,1,0] as $ocupado)
                    <span class="h-6 rounded-md {{ $ocupado ? 'bg-slate-200' : 'bg-brand-100 ring-1 ring-brand-300' }}"></span>
                  @endforeach
                </div>
              </div>

              <div class="flex gap-2">
                <span class="flex-1 rounded-xl bg-brand-800 py-2.5 text-center text-xs font-bold text-white">Reservar assento</span>
                <span class="grid w-11 place-items-center rounded-xl bg-red-50 text-[11px] font-black text-red-600 ring-1 ring-red-200">SOS</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Cartão flutuante --}}
        <div class="absolute -bottom-6 -left-4 hidden rounded-2xl border border-white/15 bg-white px-4 py-3 shadow-2xl shadow-brand-950/40 sm:block">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Poupança do passageiro</p>
          <p class="text-lg font-bold text-brand-800">Sem taxas de plataforma</p>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ========================= FAIXA DE CONFIANÇA ========================= --}}
<section class="border-b border-slate-200 bg-brand-950 py-4" aria-label="Destaques">
  <div class="flex overflow-hidden" role="presentation">
    <div class="flex shrink-0 animate-marquee items-center gap-10 whitespace-nowrap pr-10 sm:gap-14 sm:pr-14">
      @for ($i = 0; $i < 2; $i++)
        @foreach ([
          'Localização GPS em tempo real',
          'Escolha de assento no mapa do veículo',
          'Botão SOS ligado à central',
          'Contacto direto com o condutor',
          'Piloto na Ilha de Santiago',
          'Gratuito para passageiros',
          'Funciona como app no telemóvel',
        ] as $item)
          <span class="flex items-center gap-3 text-sm font-medium text-blue-100/70 sm:text-base">
            <svg class="h-4 w-4 shrink-0 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
            {{ $item }}
          </span>
        @endforeach
      @endfor
    </div>
  </div>
</section>

{{-- ========================== COMO FUNCIONA ========================== --}}
<section id="como-funciona" class="bg-white py-20 sm:py-28">
  <div class="mx-auto max-w-7xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="mx-auto max-w-2xl text-center" data-revelar>
      <p class="text-sm font-bold uppercase tracking-widest text-brand-700">Como funciona</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">Três passos até ao teu lugar</h2>
      <p class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
        Sem filas na paragem e sem incerteza sobre a hora de partida. Tudo acontece no telemóvel, antes de saíres de casa.
      </p>
    </div>

    <ol class="mt-16 grid grid-cols-1 gap-6 md:grid-cols-3 lg:gap-8">
      @foreach ([
        ['01', 'Escolhe ponto e destino', 'Indica de onde sais e para onde vais. O mapa mostra, em tempo real, os veículos disponíveis nessa rota.', 'M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
        ['02', 'Reserva o teu assento', 'Escolhe o veículo e o lugar exato no mapa visual de 14 assentos. O condutor recebe a reserva no momento.', 'M4 19V9.5C4 8.7 4.7 8 5.5 8h13c.8 0 1.5.7 1.5 1.5V19M4 19h16M4 19v2m16-2v2M7 8V6.5C7 5.7 7.7 5 8.5 5h7c.8 0 1.5.7 1.5 1.5V8M9 12h6'],
        ['03', 'Segue a viagem ao vivo', 'Acompanha o condutor no mapa, fala com ele quando precisares e usa o SOS a qualquer momento da viagem.', 'M12 22s8-4.5 8-10V5l-8-3-8 3v7c0 5.5 8 10 8 10Zm0-13v4m0 3h.01'],
      ] as $i => [$numero, $titulo, $texto, $icone])
        <li class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5"
            data-revelar style="transition-delay: {{ $i * 110 }}ms">
          <span class="absolute -right-4 -top-6 text-8xl font-black text-slate-50 transition-colors group-hover:text-brand-50" aria-hidden="true">{{ $numero }}</span>
          <span class="relative grid h-14 w-14 place-items-center rounded-2xl bg-linear-135 from-brand-800 to-brand-600 text-white shadow-lg shadow-brand-800/25">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
          </span>
          <h3 class="relative mt-6 text-xl font-bold text-slate-900">{{ $titulo }}</h3>
          <p class="relative mt-3 leading-relaxed text-slate-600">{{ $texto }}</p>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- ========================= FUNCIONALIDADES ========================= --}}
<section id="funcionalidades" class="bg-slate-50 py-20 sm:py-28">
  <div class="mx-auto max-w-7xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="mx-auto max-w-2xl text-center" data-revelar>
      <p class="text-sm font-bold uppercase tracking-widest text-brand-700">Porquê o V-MILLION</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">Feito para o transporte cabo-verdiano</h2>
      <p class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
        Hiaces, táxis e as rotas que já existem — com a tecnologia que faltava para as tornar previsíveis.
      </p>
    </div>

    <div class="mt-16 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
      {{-- Destaque largo --}}
      <article class="relative overflow-hidden rounded-3xl bg-linear-135 from-brand-900 to-brand-700 p-8 text-white shadow-xl shadow-brand-900/20 md:col-span-2 md:p-10" data-revelar>
        <div class="grelha-mapa absolute inset-0" aria-hidden="true"></div>
        <div class="relative max-w-lg">
          <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-gold-300 ring-1 ring-white/15">Tempo real</span>
          <h3 class="mt-5 text-2xl font-bold sm:text-3xl">Vês o veículo a chegar, metro a metro</h3>
          <p class="mt-4 leading-relaxed text-blue-100/85">
            Posição GPS de alta precisão transmitida de forma contínua, com o trajeto desenhado no mapa e a estimativa de chegada sempre atualizada. Nunca mais esperas sem saber quanto falta.
          </p>
          <div class="mt-8 flex flex-wrap gap-2">
            @foreach (['Rota desenhada no mapa', 'Estimativa de chegada', 'Histórico da viagem'] as $tag)
              <span class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white ring-1 ring-white/15">{{ $tag }}</span>
            @endforeach
          </div>
        </div>
      </article>

      @foreach ([
        ['Gratuito para passageiros', 'O passageiro nunca paga nada ao sistema — apenas o preço justo da viagem, diretamente ao condutor.', 'M12 3v18m5-14.5C17 5 14.8 4 12 4S7 5.2 7 7.2 9 10 12 10.5s5 1.4 5 3.4-2.2 3.1-5 3.1-5-1-5-2.6'],
        ['Assento à tua escolha', 'Mapa visual dos 14 lugares do Hiace: escolhes onde te sentas antes de entrar, com a lotação sempre atualizada.', 'M4 19V9.5C4 8.7 4.7 8 5.5 8h13c.8 0 1.5.7 1.5 1.5V19M4 19h16M8 8V5.5C8 4.7 8.7 4 9.5 4h5c.8 0 1.5.7 1.5 1.5V8'],
        ['Contacto direto', 'Fala com o condutor por chamada ou mensagem quando o ponto de encontro precisa de um ajuste.', 'M15.05 5A5 5 0 0 1 19 8.95M13 3a9 9 0 0 1 8 8m-1.5 5.9v2.5a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 3.6 5.7 2 2 0 0 1 5.6 3.5h2.5a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.7a2 2 0 0 1-.5 2.1l-1 1a16 16 0 0 0 6 6l1-1a2 2 0 0 1 2.1-.5c.9.4 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z'],
        ['Instala como app', 'Funciona no navegador e instala-se no ecrã inicial do telemóvel, com notificações e arranque rápido.', 'M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm4 15h2'],
        ['Painel do condutor', 'Vagas, reservas recebidas e estado da viagem num painel pensado para ser usado com o veículo parado.', 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6ZM13 9h8V3h-8v6Z'],
      ] as $i => [$titulo, $texto, $icone])
        <article class="group rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5"
                 data-revelar style="transition-delay: {{ $i * 80 }}ms">
          <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-800 transition-colors group-hover:bg-brand-800 group-hover:text-white">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
          </span>
          <h3 class="mt-5 text-lg font-bold text-slate-900">{{ $titulo }}</h3>
          <p class="mt-2.5 text-sm leading-relaxed text-slate-600">{{ $texto }}</p>
        </article>
      @endforeach
    </div>
  </div>
</section>

{{-- ============================ CONDUTORES ============================ --}}
<section id="condutores" class="overflow-hidden bg-white py-20 sm:py-28">
  <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:gap-20 lg:px-8">
    <div data-revelar>
      <p class="text-sm font-bold uppercase tracking-widest text-brand-700">Para condutores</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Enche o veículo antes de arrancar</h2>
      <p class="mt-5 text-base leading-relaxed text-slate-600 sm:text-lg">
        Publica a tua rota, recebe reservas confirmadas e sai da paragem com os lugares já ocupados.
        Menos tempo à espera, mais viagens por dia.
      </p>

      <ul class="mt-10 space-y-5">
        @foreach ([
          ['Reservas confirmadas antes da partida', 'Sabes quantos passageiros tens e onde os apanhas.'],
          ['Gestão de lugares em tempo real', 'Cada reserva atualiza a lotação automaticamente.'],
          ['Passageiros identificados', 'Contas verificadas e contacto direto para combinar o ponto.'],
          ['Apoio da central', 'Alarmes SOS e ocorrências monitorizados pela administração.'],
        ] as [$titulo, $texto])
          <li class="flex gap-4">
            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-800 text-white">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
            </span>
            <span>
              <strong class="block font-semibold text-slate-900">{{ $titulo }}</strong>
              <span class="text-sm leading-relaxed text-slate-600">{{ $texto }}</span>
            </span>
          </li>
        @endforeach
      </ul>

      <a href="/registo?tipo=condutor"
         class="group mt-10 inline-flex items-center gap-2 rounded-2xl bg-brand-800 px-7 py-4 text-base font-bold text-white shadow-lg shadow-brand-800/25 transition hover:-translate-y-0.5 hover:bg-brand-900 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
        Registar como condutor
        <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
      </a>
    </div>

    <div class="relative" data-revelar>
      <div class="absolute -inset-6 -z-10 rounded-[3rem] bg-linear-135 from-brand-50 to-slate-100" aria-hidden="true"></div>
      <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl shadow-brand-900/10 sm:p-8">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Viagem ativa</p>
            <p class="mt-1 text-lg font-bold text-slate-900">Praia &rarr; Assomada</p>
          </div>
          <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">A decorrer</span>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3">
          @foreach ([['9', 'Reservados'], ['5', 'Livres'], ['14', 'Total']] as [$valor, $rotulo])
            <div class="rounded-2xl bg-slate-50 p-4 text-center">
              <p class="text-2xl font-bold text-brand-800">{{ $valor }}</p>
              <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $rotulo }}</p>
            </div>
          @endforeach
        </div>

        <div class="mt-6 space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reservas recebidas</p>
          @foreach ([['Ana M.', 'Lugar 3', 'Sucupira'], ['Jorge T.', 'Lugar 7', 'Palmarejo'], ['Dina F.', 'Lugar 11', 'Achada']] as [$nome, $lugar, $ponto])
            <div class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-800">{{ mb_substr($nome, 0, 1) }}</span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $nome }}</p>
                <p class="truncate text-xs text-slate-500">Embarque em {{ $ponto }}</p>
              </div>
              <span class="shrink-0 rounded-lg bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-800">{{ $lugar }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ============================ SEGURANÇA ============================ --}}
<section id="seguranca" class="relative isolate overflow-hidden bg-brand-950 py-20 sm:py-28">
  <div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
  <div class="absolute left-1/2 top-0 -z-10 h-96 w-[40rem] -translate-x-1/2 rounded-full bg-brand-700/30 blur-3xl" aria-hidden="true"></div>

  <div class="mx-auto max-w-7xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="mx-auto max-w-2xl text-center" data-revelar>
      <p class="text-sm font-bold uppercase tracking-widest text-gold-400">Segurança</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">Ninguém viaja sozinho</h2>
      <p class="mt-4 text-base leading-relaxed text-blue-100/80 sm:text-lg">
        Cada viagem fica registada e cada alarme chega a uma pessoa. A segurança não é uma funcionalidade extra — é a base do sistema.
      </p>
    </div>

    <div class="mt-16 grid grid-cols-1 gap-6 md:grid-cols-3">
      @foreach ([
        ['Botão SOS', 'Um toque envia a tua localização exata e os dados da viagem para a central de alarmes.', 'M12 2 3 7v6c0 5 3.8 8.4 9 9 5.2-.6 9-4 9-9V7l-9-5Zm0 6v5m0 3h.01'],
        ['Central monitorizada', 'Os alarmes entram num painel de administração, com histórico e acompanhamento de cada ocorrência.', 'M2 12h4l2-7 4 14 3-9 2 2h5'],
        ['Contas verificadas', 'Passageiros e condutores registam-se com identificação, e contas em incumprimento podem ser suspensas.', 'M12 2 4 5v6.5c0 4.7 3.4 8.6 8 9.5 4.6-.9 8-4.8 8-9.5V5l-8-3Zm-2.5 9.5 2 2 4-4'],
      ] as $i => [$titulo, $texto, $icone])
        <div class="rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/10"
             data-revelar style="transition-delay: {{ $i * 110 }}ms">
          <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gold-500/15 text-gold-400 ring-1 ring-gold-500/25">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
          </span>
          <h3 class="mt-5 text-lg font-bold text-white">{{ $titulo }}</h3>
          <p class="mt-2.5 text-sm leading-relaxed text-blue-100/75">{{ $texto }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ============================== FAQ ============================== --}}
<section id="perguntas" class="bg-white py-20 sm:py-28">
  <div class="mx-auto max-w-3xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="text-center" data-revelar>
      <p class="text-sm font-bold uppercase tracking-widest text-brand-700">Perguntas frequentes</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Ainda tens dúvidas?</h2>
    </div>

    <div class="mt-12 divide-y divide-slate-200 border-y border-slate-200" data-revelar>
      @foreach ([
        ['Quanto custa usar o V-MILLION?', 'Para o passageiro é totalmente gratuito: não há taxa de plataforma nem comissão. Pagas apenas o preço da viagem, diretamente ao condutor, como sempre fizeste.'],
        ['Preciso de instalar alguma aplicação?', 'Não é obrigatório. O V-MILLION funciona no navegador do telemóvel e pode ser adicionado ao ecrã inicial, ficando com o aspeto e o arranque de uma aplicação normal.'],
        ['Em que zonas está disponível?', 'O piloto arranca na Ilha de Santiago, nas rotas de Hiace e táxi já existentes. À medida que mais condutores aderem, a cobertura vai crescendo para as outras ilhas.'],
        ['Como escolho o meu lugar no Hiace?', 'Depois de escolheres o veículo, aparece o mapa dos 14 assentos com os lugares livres e ocupados. Escolhes o teu e a reserva chega imediatamente ao condutor.'],
        ['Sou condutor — o que preciso para começar?', 'Cria uma conta de condutor com os teus dados e os do veículo. Depois de validada, podes publicar rotas, receber reservas e partilhar a tua posição durante a viagem.'],
        ['O que acontece quando carrego no SOS?', 'A tua localização exata e os dados da viagem são enviados de imediato para a central de alarmes da administração, que regista e acompanha a ocorrência.'],
      ] as $i => [$pergunta, $resposta])
        <details class="group py-5" @if ($i === 0) open @endif>
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left text-base font-semibold text-slate-900 transition hover:text-brand-800 sm:text-lg">
            {{ $pergunta }}
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600 transition-all duration-300 group-open:rotate-45 group-open:bg-brand-800 group-open:text-white">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </span>
          </summary>
          <p class="mt-3 max-w-2xl pr-12 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $resposta }}</p>
        </details>
      @endforeach
    </div>
  </div>
</section>

{{-- ============================ CTA FINAL ============================ --}}
<section class="bg-slate-50 py-20 sm:py-24">
  <div class="mx-auto max-w-7xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="relative isolate overflow-hidden rounded-[2rem] bg-linear-135 from-brand-900 via-brand-800 to-brand-700 px-6 py-16 text-center shadow-2xl shadow-brand-900/25 sm:rounded-[2.5rem] sm:px-12 sm:py-20" data-revelar>
      <div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
      <div class="absolute -right-24 -top-24 -z-10 h-72 w-72 rounded-full bg-gold-500/15 blur-3xl" aria-hidden="true"></div>
      <div class="absolute -bottom-24 -left-24 -z-10 h-72 w-72 rounded-full bg-accent-500/15 blur-3xl" aria-hidden="true"></div>

      <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
        A tua próxima viagem começa aqui
      </h2>
      <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-blue-100/85 sm:text-lg">
        Cria a tua conta em menos de um minuto e reserva o teu lugar na próxima Hiace que passar.
      </p>
      <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
        <a href="/registo?tipo=passageiro"
           class="inline-flex items-center justify-center rounded-2xl bg-gold-500 px-8 py-4 text-base font-bold text-brand-900 shadow-xl shadow-brand-950/25 transition hover:-translate-y-0.5 hover:bg-gold-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300">
          Criar conta de passageiro
        </a>
        <a href="/registo?tipo=condutor"
           class="inline-flex items-center justify-center rounded-2xl border border-white/30 bg-white/5 px-8 py-4 text-base font-semibold text-white backdrop-blur-sm transition hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60">
          Sou condutor
        </a>
      </div>
    </div>
  </div>
</section>

</main>

{{-- ============================== RODAPÉ ============================== --}}
<footer class="bg-brand-950 pb-seguro [--pb-seguro:2.5rem] pt-16 text-blue-100/70">
  <div class="mx-auto max-w-7xl px-seguro sm:[--px-seguro:1.5rem] lg:[--px-seguro:2rem]">
    <div class="grid grid-cols-2 gap-10 md:grid-cols-4">
      <div class="col-span-2 md:col-span-2">
        <div class="flex items-center gap-2.5 text-white">
          <x-logo />
          <span class="text-lg font-bold tracking-tight">V-MILLION</span>
        </div>
        <p class="mt-5 max-w-sm text-sm leading-relaxed">
          Mobilidade inteligente para Cabo Verde. Ligamos passageiros a condutores de Hiace e táxi com
          localização em tempo real, reserva de assento e segurança em cada viagem.
        </p>
        <p class="mt-5 text-sm font-medium text-white/90">Piloto — Ilha de Santiago, Cabo Verde</p>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-white">Navegar</h3>
        <ul class="mt-5 space-y-3 text-sm">
          @foreach ($navegacao as $ancora => $rotulo)
            <li><a href="#{{ $ancora }}" class="transition hover:text-gold-400">{{ $rotulo }}</a></li>
          @endforeach
        </ul>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-white">Conta</h3>
        <ul class="mt-5 space-y-3 text-sm">
          <li><a href="/login?tipo=passageiro" class="transition hover:text-gold-400">Entrar como passageiro</a></li>
          <li><a href="/login?tipo=condutor" class="transition hover:text-gold-400">Entrar como condutor</a></li>
          <li><a href="/registo" class="transition hover:text-gold-400">Criar conta</a></li>
          <li><a href="/admin/login" class="transition hover:text-gold-400">Acesso administrativo</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-14 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 text-sm sm:flex-row">
      <p>V-MILLION &copy; {{ date('Y') }} — Todos os direitos reservados.</p>
      <p>Feito em Cabo Verde, com Morabeza.</p>
    </div>
  </div>
</footer>

<script nonce="{{ $cspNonce }}">
(() => {
  // Menu móvel
  const botao = document.getElementById('botao-menu');
  const menu = document.getElementById('menu-movel');
  const iconeAbrir = document.getElementById('icone-abrir');
  const iconeFechar = document.getElementById('icone-fechar');

  const alternarMenu = (aberto) => {
    menu.classList.toggle('hidden', !aberto);
    iconeAbrir.classList.toggle('hidden', aberto);
    iconeFechar.classList.toggle('hidden', !aberto);
    botao.setAttribute('aria-expanded', String(aberto));
  };

  botao.addEventListener('click', () => {
    alternarMenu(botao.getAttribute('aria-expanded') !== 'true');
  });

  // Fecha ao navegar para uma âncora ou ao voltar ao ecrã grande.
  menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => alternarMenu(false)));
  window.matchMedia('(min-width: 1024px)').addEventListener('change', (ev) => {
    if (ev.matches) alternarMenu(false);
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && botao.getAttribute('aria-expanded') === 'true') {
      alternarMenu(false);
      botao.focus();
    }
  });

  // O cabeçalho arranca transparente sobre o herói e ganha fundo ao descer.
  const cabecalho = document.getElementById('cabecalho');
  const classesFixas = ['bg-brand-950/85', 'backdrop-blur-xl', 'shadow-lg', 'shadow-brand-950/20'];
  const atualizarCabecalho = () => {
    const descido = window.scrollY > 24;
    classesFixas.forEach((c) => cabecalho.classList.toggle(c, descido));
  };
  atualizarCabecalho();
  window.addEventListener('scroll', atualizarCabecalho, { passive: true });

  // Revelação progressiva das secções.
  const alvos = document.querySelectorAll('[data-revelar]');
  if (!('IntersectionObserver' in window)) {
    alvos.forEach((el) => el.classList.add('e-visivel'));
    return;
  }

  const observador = new IntersectionObserver((entradas) => {
    entradas.forEach((entrada) => {
      if (entrada.isIntersecting) {
        entrada.target.classList.add('e-visivel');
        observador.unobserve(entrada.target);
      }
    });
  }, { rootMargin: '0px 0px -80px 0px', threshold: 0.1 });

  alvos.forEach((el) => observador.observe(el));
})();
</script>
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
