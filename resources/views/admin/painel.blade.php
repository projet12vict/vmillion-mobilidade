@php
    // Navegação lateral, na ordem em que aparece. Cada entrada liga-se à
    // secção com o id "secao-{chave}" e ao carregarSecao() do JS — mudar aqui
    // chega para o menu, para o título da barra de topo e para o carregamento.
    // 'super' => true limita a entrada ao Super Admin. O primeiro valor é
    // o nome do ícone em config/icones.php.
    $navegacao = [
        'Operação' => [
            'dashboard' => ['dashboard', 'Dashboard'],
            'utilizadores' => ['utilizadores', 'Utilizadores'],
            'veiculos' => ['veiculo', 'Veículos'],
            'condutores' => ['condutor', 'Condutores'],
            'proprietarios' => ['proprietario', 'Proprietários'],
        ],
        'Infraestrutura' => [
            'pontos' => ['ponto', 'Pontos de partida'],
            'parques' => ['parque', 'Parques de estacionamento'],
            'editor-mapa' => ['mapa', 'Editor de mapa'],
        ],
        'Financeiro' => [
            'pagamentos' => ['pagamento', 'Pagamentos (taxa por rota)'],
            'precos' => ['preco', 'Preços por rota', 'super'],
        ],
        'Sistema' => [
            'sugestoes' => ['sugestao', $ehSuper ? 'Sugestões e reclamações' : 'Reclamações'],
            'notificacoes' => ['comunicar', 'Comunicar', 'super'],
            'administradores' => ['administrador', 'Administradores', 'super'],
            'logs' => ['log', 'Logs de auditoria'],
            'sos' => ['sos', 'Central de alarmes (SOS)'],
        ],
    ];
@endphp
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
<meta name="robots" content="noindex, nofollow">
<title>Painel Admin — V-MILLION</title>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
{{-- Os marcadores do mapa continuam no CSS legado: é folha independente
     (declara as suas próprias variáveis) e nada tem que ver com o tema. --}}
<link rel="stylesheet" href="@kgAsset('assets/css/kg-map-markers.css')">
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-700 antialiased lg:h-dvh lg:overflow-hidden">

{{-- Fundo escurecido do menu em ecrã pequeno (no desktop a barra é fixa). --}}
<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

<div class="lg:grid lg:h-dvh lg:grid-cols-[17rem_minmax(0,1fr)] lg:grid-rows-[auto_minmax(0,1fr)]">

  {{-- =========================== NAVEGAÇÃO =========================== --}}
  <aside id="sidebar"
         class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full overflow-y-auto overscroll-contain bg-brand-900 bg-linear-135 from-brand-950 via-brand-800 to-brand-700 px-seguro [--px-seguro:0.75rem] pt-seguro [--pt-seguro:1.25rem] pb-seguro [--pb-seguro:1.25rem] transition-transform duration-200 lg:static lg:col-start-1 lg:row-span-2 lg:w-auto lg:translate-x-0">
    <a href="/" class="mb-6 flex items-center gap-2.5 px-2 text-white transition-opacity hover:opacity-90">
      <x-logo class="shadow-lg shadow-brand-950/25" />
      <span class="text-lg font-bold tracking-tight">V-MILLION <span class="text-gold-400">Admin</span></span>
    </a>

    <nav aria-label="Secções do painel">
      @foreach ($navegacao as $grupo => $itens)
        <h2 class="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wider text-blue-200/60 first:mt-0">{{ $grupo }}</h2>
        <ul class="space-y-1">
          @foreach ($itens as $chave => $item)
            @continue(($item[2] ?? null) === 'super' && ! $ehSuper)
            <li>
              <a class="adm-nav-item @if ($chave === 'dashboard') ativo @endif" data-secao="{{ $chave }}">
                <x-icone :nome="$item[0]" class="adm-nav-icon" />
                <span class="adm-nav-label">{{ $item[1] }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      @endforeach
    </nav>
  </aside>

  {{-- ============================ BARRA DE TOPO ============================ --}}
  <header class="vm-barra px-seguro sm:[--px-seguro:1.5rem] sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 backdrop-blur-sm lg:static lg:col-start-2 lg:row-start-1">
    <div class="flex min-w-0 items-center gap-2">
      <button type="button" id="btn-toggle-sidebar" aria-controls="sidebar" aria-expanded="false"
              class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden">
        <span class="sr-only">Abrir menu de navegação</span>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <strong id="titulo-secao" class="truncate text-base font-bold tracking-tight text-slate-900 sm:text-lg">Dashboard</strong>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <button type="button" id="btn-notificacoes-admin"
              class="relative grid h-10 w-10 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
        <span class="sr-only">As minhas notificações</span>
        <x-icone nome="sino" />
        <span id="badge-notificacoes-admin" style="display:none;"
              class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-[0.65rem] font-bold leading-none text-white"></span>
      </button>
      <span class="hidden text-sm text-slate-500 sm:inline">
        {{ $admin->nome }} <span class="font-semibold text-brand-800">({{ $admin->nivel }})</span>
      </span>
      <a href="/logout" class="vm-btn vm-btn--sm vm-btn--outline">Sair</a>
    </div>
  </header>

  {{-- ============================== CONTEÚDO ============================== --}}
  <main class="p-4 pb-seguro [--pb-seguro:1rem] sm:p-6 sm:[--pb-seguro:1.5rem] lg:col-start-2 lg:row-start-2 lg:overflow-y-auto">

    <section class="adm-secao ativa" id="secao-dashboard">
      {{-- Faixa de boas-vindas com o mesmo desenho do herói da página
           inicial — gradiente institucional, grelha e halo dourado. --}}
      <div class="relative isolate mb-6 overflow-hidden rounded-3xl bg-brand-900 px-6 py-8 sm:px-8">
        <div class="absolute inset-0 -z-10 bg-linear-135 from-brand-950 via-brand-800 to-brand-700" aria-hidden="true"></div>
        <div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="absolute -right-20 -top-24 -z-10 h-72 w-72 rounded-full bg-gold-500/15 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-28 -left-16 -z-10 h-72 w-72 rounded-full bg-accent-500/20 blur-3xl" aria-hidden="true"></div>

        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1 text-xs font-semibold uppercase tracking-wider text-white backdrop-blur-sm">
          <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-pulse-ring rounded-full bg-gold-400"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-gold-400"></span>
          </span>
          Operação em tempo real
        </span>
        <h1 class="mt-4 text-2xl font-bold leading-tight tracking-tight text-white sm:text-3xl">
          Olá, {{ $admin->nome }}.
        </h1>
        <p class="mt-2 max-w-xl text-sm leading-relaxed text-blue-100/85 sm:text-base">
          O retrato da operação V-MILLION em Santiago — utilizadores, frota, pontos e alarmes, tudo num sítio só.
        </p>
      </div>

      <div class="mb-6 space-y-6" id="dashboard-stats"></div>
      <div class="vm-card">
        <h3 class="vm-h3">Últimas ações</h3>
        <div class="vm-tabela-wrap mt-4"><table class="vm-tabela"><thead><tr><th>Admin</th><th>Ação</th><th>Entidade</th><th>Data</th></tr></thead><tbody id="dashboard-logs"></tbody></table></div>
      </div>
    </section>

    <section class="adm-secao" id="secao-utilizadores">
      <div class="mb-4 flex flex-wrap gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-tipo="">Todos</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-tipo="passageiro">Passageiros</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-tipo="condutor">Condutores</button>
      </div>
      <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <form id="filtro-utilizadores" class="flex flex-wrap items-end gap-2">
          <div class="vm-field mb-0 min-w-44 flex-1">
            <label class="vm-label" for="filtro-utilizador-texto">Pesquisar</label>
            <input class="vm-input" type="search" id="filtro-utilizador-texto" placeholder="Nome, telefone, NIF ou email">
          </div>
          <div class="vm-field mb-0 w-36">
            <label class="vm-label" for="filtro-utilizador-tipo">Tipo</label>
            <select class="vm-select" id="filtro-utilizador-tipo">
              <option value="">Todos</option>
              <option value="passageiro">Passageiros</option>
              <option value="condutor">Condutores</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-32">
            <label class="vm-label" for="filtro-utilizador-status">Estado</label>
            <select class="vm-select" id="filtro-utilizador-status">
              <option value="">Todos</option>
              <option value="ativo">Ativo</option>
              <option value="pendente">Pendente</option>
              <option value="suspenso">Suspenso</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-utilizador-periodo">Registado em</label>
            <select class="vm-select" id="filtro-utilizador-periodo">
              <option value="">Sempre</option>
              <option value="hoje">Hoje</option>
              <option value="ontem">Ontem</option>
              <option value="semana">Esta semana</option>
              <option value="mes">Este mês</option>
              <option value="ano">Este ano</option>
              <option value="7dias">Últimos 7 dias</option>
              <option value="30dias">Últimos 30 dias</option>
              <option value="personalizado">Intervalo personalizado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-personalizado hidden>
            <label class="vm-label" for="filtro-utilizador-inicio">De</label>
            <input class="vm-input" type="date" id="filtro-utilizador-inicio">
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-personalizado hidden>
            <label class="vm-label" for="filtro-utilizador-fim">Até</label>
            <input class="vm-input" type="date" id="filtro-utilizador-fim">
          </div>
          <div class="vm-field mb-0 w-48">
            <label class="vm-label" for="filtro-utilizador-ordem">Ordenar por</label>
            <select class="vm-select" id="filtro-utilizador-ordem">
              <option value="recentes">Registo (mais recente)</option>
              <option value="antigos">Registo (mais antigo)</option>
              <option value="nome">Nome (A–Z)</option>
              <option value="nome_desc">Nome (Z–A)</option>
            </select>
          </div>
          <button class="vm-btn vm-btn--ghost vm-btn--sm" type="reset" id="btn-limpar-filtro-utilizadores">Limpar filtro</button>
        </form>
        <p class="vm-small mt-2 mb-0" id="filtro-utilizadores-resumo"></p>
      </div>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Tipo</th><th>Telefone</th><th>NIF</th><th>Registo</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-utilizadores"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-veiculos">
      <div class="mb-4 flex flex-wrap gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-aprovado="">Todos</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-aprovado="0">Por aprovar</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-aprovado="1">Aprovados</button>
      </div>
      <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <form id="filtro-veiculos" class="flex flex-wrap items-end gap-2">
          <div class="vm-field mb-0 min-w-44 flex-1">
            <label class="vm-label" for="filtro-veiculo-texto">Pesquisar</label>
            <input class="vm-input" type="search" id="filtro-veiculo-texto" placeholder="Matrícula, modelo, cor ou condutor">
          </div>
          <div class="vm-field mb-0 w-36">
            <label class="vm-label" for="filtro-veiculo-tipo">Tipo</label>
            <select class="vm-select" id="filtro-veiculo-tipo">
              <option value="">Todos</option>
              <option value="hiace">Hiace</option>
              <option value="taxi">Táxi</option>
              <option value="autocarro">Autocarro</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-veiculo-servico">Tipo de serviço</label>
            <select class="vm-select" id="filtro-veiculo-servico">
              <option value="">Todos</option>
              <option value="urbano">Urbano</option>
              <option value="intermunicipal">Intermunicipal</option>
              <option value="ambos">Urbano + Intermunicipal</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40">
            <label class="vm-label" for="filtro-veiculo-estado">Estado</label>
            <select class="vm-select" id="filtro-veiculo-estado">
              <option value="">Todos</option>
              <option value="no_ponto">No ponto</option>
              <option value="na_fila">Na fila</option>
              <option value="partiu_da_fila">Em rota</option>
              <option value="em_movimento">Em movimento</option>
              <option value="chegou_destino">Chegou ao destino</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-32">
            <label class="vm-label" for="filtro-veiculo-aprovado">Aprovação</label>
            <select class="vm-select" id="filtro-veiculo-aprovado">
              <option value="">Todos</option>
              <option value="1">Aprovados</option>
              <option value="0">Por aprovar</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40">
            <label class="vm-label" for="filtro-veiculo-ponto">Ponto de partida</label>
            <select class="vm-select" id="filtro-veiculo-ponto">
              <option value="">Todos</option>
              <option value="bloqueado">Ponto trancado</option>
              <option value="livre">Ponto livre</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-veiculo-periodo">Registado em</label>
            <select class="vm-select" id="filtro-veiculo-periodo">
              <option value="">Sempre</option>
              <option value="hoje">Hoje</option>
              <option value="ontem">Ontem</option>
              <option value="semana">Esta semana</option>
              <option value="mes">Este mês</option>
              <option value="ano">Este ano</option>
              <option value="7dias">Últimos 7 dias</option>
              <option value="30dias">Últimos 30 dias</option>
              <option value="personalizado">Intervalo personalizado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-veiculo hidden>
            <label class="vm-label" for="filtro-veiculo-inicio">De</label>
            <input class="vm-input" type="date" id="filtro-veiculo-inicio">
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-veiculo hidden>
            <label class="vm-label" for="filtro-veiculo-fim">Até</label>
            <input class="vm-input" type="date" id="filtro-veiculo-fim">
          </div>
          <div class="vm-field mb-0 w-48">
            <label class="vm-label" for="filtro-veiculo-ordem">Ordenar por</label>
            <select class="vm-select" id="filtro-veiculo-ordem">
              <option value="recentes">Registo (mais recente)</option>
              <option value="antigos">Registo (mais antigo)</option>
              <option value="matricula">Matrícula (A–Z)</option>
              <option value="matricula_desc">Matrícula (Z–A)</option>
              <option value="condutor">Condutor (A–Z)</option>
            </select>
          </div>
          <button class="vm-btn vm-btn--ghost vm-btn--sm" type="reset" id="btn-limpar-filtro-veiculos">Limpar filtro</button>
        </form>
        <p class="vm-small mt-2 mb-0" id="filtro-veiculos-resumo"></p>
      </div>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Matrícula</th><th>Tipo</th><th>Condutor</th><th>Registo</th><th>Estado</th><th>Aprovado</th><th>Ações</th></tr></thead><tbody id="tabela-veiculos"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-condutores">
      <p class="vm-small mb-4">Condutores pendentes de aprovação (necessitam de um pagamento aprovado e válido em "Pagamentos" — aprovar o pagamento de um condutor novo já ativa a conta automaticamente; este botão serve para casos manuais).</p>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Telefone</th><th>NIF</th><th>Ações</th></tr></thead><tbody id="tabela-condutores-pendentes"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-proprietarios">
      <p class="vm-small mb-4">Um proprietário pode também ser condutor — associe-o a uma conta de condutor existente para refletir isso no sistema.</p>
      <button class="vm-btn vm-btn--cta vm-btn--sm mb-4" id="btn-novo-proprietario">+ Novo proprietário</button>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Telefone</th><th>NIF</th><th>Também conduz</th><th>Condutores da frota</th><th>Ações</th></tr></thead><tbody id="tabela-proprietarios"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-pontos">
      <button class="vm-btn vm-btn--cta vm-btn--sm mb-4" id="btn-novo-ponto">+ Novo ponto</button>
      <div class="mb-4 flex flex-wrap gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-ponto-status="">Todos</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-ponto-status="pendente">Pendentes</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-ponto-status="aprovado">Aprovados</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-ponto-status="recusado">Recusados</button>
      </div>
      <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <form id="filtro-pontos" class="flex flex-wrap items-end gap-2">
          <div class="vm-field mb-0 min-w-44 flex-1">
            <label class="vm-label" for="filtro-ponto-texto">Pesquisar</label>
            <input class="vm-input" type="search" id="filtro-ponto-texto" placeholder="Nome, cidade ou administrador">
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-ponto-cidade">Cidade</label>
            <select class="vm-select" id="filtro-ponto-cidade"><option value="">Todas</option></select>
          </div>
          <div class="vm-field mb-0 w-40">
            <label class="vm-label" for="filtro-ponto-zona">Zona</label>
            <select class="vm-select" id="filtro-ponto-zona">
              <option value="">Todas</option>
              <option value="urbana">Urbana</option>
              <option value="intermunicipal">Intermunicipal</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-36">
            <label class="vm-label" for="filtro-ponto-status">Status</label>
            <select class="vm-select" id="filtro-ponto-status">
              <option value="">Todos</option>
              <option value="pendente">Pendente</option>
              <option value="aprovado">Aprovado</option>
              <option value="recusado">Recusado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-36">
            <label class="vm-label" for="filtro-ponto-visibilidade">Visibilidade</label>
            <select class="vm-select" id="filtro-ponto-visibilidade">
              <option value="">Todos</option>
              <option value="1">Ativo</option>
              <option value="0">Desativado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-ponto-periodo">Criado em</label>
            <select class="vm-select" id="filtro-ponto-periodo">
              <option value="">Sempre</option>
              <option value="hoje">Hoje</option>
              <option value="ontem">Ontem</option>
              <option value="semana">Esta semana</option>
              <option value="mes">Este mês</option>
              <option value="ano">Este ano</option>
              <option value="7dias">Últimos 7 dias</option>
              <option value="30dias">Últimos 30 dias</option>
              <option value="personalizado">Intervalo personalizado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-ponto hidden>
            <label class="vm-label" for="filtro-ponto-inicio">De</label>
            <input class="vm-input" type="date" id="filtro-ponto-inicio">
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-ponto hidden>
            <label class="vm-label" for="filtro-ponto-fim">Até</label>
            <input class="vm-input" type="date" id="filtro-ponto-fim">
          </div>
          <div class="vm-field mb-0 w-48">
            <label class="vm-label" for="filtro-ponto-ordem">Ordenar por</label>
            <select class="vm-select" id="filtro-ponto-ordem">
              <option value="pendentes">Pendentes primeiro</option>
              <option value="nome">Nome (A–Z)</option>
              <option value="nome_desc">Nome (Z–A)</option>
              <option value="cidade">Cidade (A–Z)</option>
              <option value="recentes">Criado (mais recente)</option>
              <option value="antigos">Criado (mais antigo)</option>
            </select>
          </div>
          <button class="vm-btn vm-btn--ghost vm-btn--sm" type="reset" id="btn-limpar-filtro-pontos">Limpar filtro</button>
        </form>
        <p class="vm-small mt-2 mb-0" id="filtro-pontos-resumo"></p>
      </div>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Cidade</th><th>Zona</th><th>Coordenadas</th><th>Criado em</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tabela-pontos"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-parques">
      <button class="vm-btn vm-btn--cta vm-btn--sm mb-4" id="btn-novo-parque">+ Novo parque</button>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Cidade</th><th>Vagas</th><th>Ações</th></tr></thead><tbody id="tabela-parques"></tbody></table></div>
    </section>

    {{-- O mesmo mapa do condutor (veículos com emoji/cor/lugares, passageiros,
         pontos, limites dos municípios), mas sem os recortes do painel dele:
         aqui vê-se o sistema inteiro, com os contactos dos dois lados. A
         edição de pontos (clicar para criar, arrastar, aprovar) continua. --}}
    <section class="adm-secao" id="secao-editor-mapa">
      <p class="vm-small mb-4">Vista geral em tempo real de condutores e passageiros. Clique num sítio vazio do mapa para criar um novo ponto de partida; abra um ponto para o aprovar, arrastar ou eliminar.</p>
      <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="relative h-[65dvh] min-h-[420px] overflow-hidden rounded-2xl border border-slate-200 shadow-sm shadow-slate-900/5">
          <div id="admin-map" class="absolute inset-0"></div>
          <div class="kg-map-controls">
            <button type="button" class="kg-map-btn" id="btn-mapa-admin-tudo" title="Ver tudo" aria-label="Ajustar o mapa a todos os marcadores"><x-icone nome="localizar" /></button>
            <button type="button" class="kg-map-btn" id="btn-mapa-admin-municipios" title="Limites dos municípios" aria-label="Mostrar ou esconder os limites dos municípios" aria-pressed="true"><x-icone nome="mapa" /></button>
          </div>
          <div class="kg-map-legend">
            <div class="kg-map-legend__item"><span class="kg-marker kg-marker--veiculo scale-75" data-legenda-veiculo></span> Veículo no ponto</div>
            <div class="kg-map-legend__item"><span class="kg-marker kg-marker--veiculo em-movimento scale-75" data-legenda-veiculo></span> Veículo a caminho</div>
            <div class="kg-map-legend__item"><span class="kg-marker kg-marker--passageiro scale-75"></span> Passageiro</div>
            <div class="kg-map-legend__item"><span class="kg-marker kg-marker--ponto scale-75"></span> Ponto de partida</div>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="vm-card">
            <h3 class="vm-h3 mb-3"><x-icone nome="mapa" class="vm-h3-icone" /> Camadas</h3>
            <div class="grid grid-cols-2 gap-2 text-sm" id="camadas-mapa-admin">
              <label class="flex items-center gap-2"><input type="checkbox" data-camada="veiculos" checked> Veículos <span class="vm-badge" id="conta-camada-veiculos">0</span></label>
              <label class="flex items-center gap-2"><input type="checkbox" data-camada="passageiros" checked> Passageiros <span class="vm-badge" id="conta-camada-passageiros">0</span></label>
              <label class="flex items-center gap-2"><input type="checkbox" data-camada="pontos" checked> Pontos <span class="vm-badge" id="conta-camada-pontos">0</span></label>
              <label class="flex items-center gap-2"><input type="checkbox" data-camada="parques"> Parques <span class="vm-badge" id="conta-camada-parques">0</span></label>
            </div>
            <div class="vm-field mb-0 mt-3">
              <label class="vm-label" for="mapa-admin-pesquisa">Pesquisar</label>
              <input class="vm-input" type="search" id="mapa-admin-pesquisa" placeholder="Nome, telefone, matrícula ou ponto">
            </div>
            <p class="vm-small mb-0 mt-2" id="mapa-admin-atualizado"></p>
          </div>

          <div class="vm-card">
            <h3 class="vm-h3 mb-3"><x-icone nome="veiculo" class="vm-h3-icone" /> Condutores</h3>
            <div id="lista-mapa-veiculos" class="max-h-[40dvh] space-y-2 overflow-y-auto overscroll-contain"></div>
          </div>

          <div class="vm-card">
            <h3 class="vm-h3 mb-3"><x-icone nome="utilizadores" class="vm-h3-icone" /> Passageiros</h3>
            <div id="lista-mapa-passageiros" class="max-h-[40dvh] space-y-2 overflow-y-auto overscroll-contain"></div>
          </div>
        </aside>
      </div>
    </section>

    <section class="adm-secao" id="secao-pagamentos">
      <p class="vm-small mb-4">Pedidos de pagamento dos condutores (pacote diário/semanal/mensal/anual + comprovativo) — inclui os condutores novos a aguardar a primeira aprovação. Ao aprovar, a validade é calculada a partir da duração do pacote, é emitido automaticamente um recibo em PDF e, se for a primeira aprovação do condutor, a conta é ativada de imediato.</p>
      <div class="mb-4 flex flex-wrap gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-pagamento-status="">Todos</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-pagamento-status="pendente">Pendentes</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-pagamento-status="aprovado">Aprovados</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-filtro-pagamento-status="recusado">Recusados</button>
      </div>
      <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <form id="filtro-pagamentos" class="flex flex-wrap items-end gap-2">
          <div class="vm-field mb-0 min-w-44 flex-1">
            <label class="vm-label" for="filtro-pagamento-texto">Pesquisar</label>
            <input class="vm-input" type="search" id="filtro-pagamento-texto" placeholder="Condutor, telefone, matrícula, referência ou pacote">
          </div>
          <div class="vm-field mb-0 w-36">
            <label class="vm-label" for="filtro-pagamento-status">Estado</label>
            <select class="vm-select" id="filtro-pagamento-status">
              <option value="">Todos</option>
              <option value="pendente">Pendente</option>
              <option value="aprovado">Aprovado</option>
              <option value="recusado">Recusado</option>
            </select>
          </div>
          {{-- As opções saem dos pagamentos já feitos (campo "pacotes" da API). --}}
          <div class="vm-field mb-0 w-48">
            <label class="vm-label" for="filtro-pagamento-pacote">Pacote</label>
            <select class="vm-select" id="filtro-pagamento-pacote"><option value="">Todos</option></select>
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-pagamento-servico">Tipo de serviço</label>
            <select class="vm-select" id="filtro-pagamento-servico">
              <option value="">Todos</option>
              <option value="urbano">Urbano</option>
              <option value="intermunicipal">Intermunicipal</option>
              <option value="ambos">Urbano + Intermunicipal</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40">
            <label class="vm-label" for="filtro-pagamento-comprovativo">Comprovativo</label>
            <select class="vm-select" id="filtro-pagamento-comprovativo">
              <option value="">Todos</option>
              <option value="com">Com comprovativo</option>
              <option value="sem">Sem comprovativo</option>
              <option value="imagem">Foto</option>
              <option value="pdf">PDF</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40">
            <label class="vm-label" for="filtro-pagamento-validade">Validade</label>
            <select class="vm-select" id="filtro-pagamento-validade">
              <option value="">Todas</option>
              <option value="valido">Válido (dá acesso)</option>
              <option value="expirado">Expirado</option>
              <option value="sem">Sem validade</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-32">
            <label class="vm-label" for="filtro-pagamento-valor-min">Valor mín.</label>
            <input class="vm-input" type="number" step="1" min="0" id="filtro-pagamento-valor-min">
          </div>
          <div class="vm-field mb-0 w-32">
            <label class="vm-label" for="filtro-pagamento-valor-max">Valor máx.</label>
            <input class="vm-input" type="number" step="1" min="0" id="filtro-pagamento-valor-max">
          </div>
          <div class="vm-field mb-0 w-44">
            <label class="vm-label" for="filtro-pagamento-periodo">Submetido em</label>
            <select class="vm-select" id="filtro-pagamento-periodo">
              <option value="">Sempre</option>
              <option value="hoje">Hoje</option>
              <option value="ontem">Ontem</option>
              <option value="semana">Esta semana</option>
              <option value="mes">Este mês</option>
              <option value="ano">Este ano</option>
              <option value="7dias">Últimos 7 dias</option>
              <option value="30dias">Últimos 30 dias</option>
              <option value="personalizado">Intervalo personalizado</option>
            </select>
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-pagamento hidden>
            <label class="vm-label" for="filtro-pagamento-inicio">De</label>
            <input class="vm-input" type="date" id="filtro-pagamento-inicio">
          </div>
          <div class="vm-field mb-0 w-40" data-intervalo-pagamento hidden>
            <label class="vm-label" for="filtro-pagamento-fim">Até</label>
            <input class="vm-input" type="date" id="filtro-pagamento-fim">
          </div>
          <div class="vm-field mb-0 w-48">
            <label class="vm-label" for="filtro-pagamento-ordem">Ordenar por</label>
            <select class="vm-select" id="filtro-pagamento-ordem">
              <option value="pendentes">Pendentes primeiro</option>
              <option value="recentes">Submetido (mais recente)</option>
              <option value="antigos">Submetido (mais antigo)</option>
              <option value="valor_desc">Valor (maior)</option>
              <option value="valor">Valor (menor)</option>
              <option value="condutor">Condutor (A–Z)</option>
              <option value="validade">Validade (mais longa)</option>
            </select>
          </div>
          <button class="vm-btn vm-btn--ghost vm-btn--sm" type="reset" id="btn-limpar-filtro-pagamentos">Limpar filtro</button>
        </form>
        <p class="vm-small mt-2 mb-0" id="filtro-pagamentos-resumo"></p>
      </div>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Condutor</th><th>Veículo</th><th>Pacote</th><th>Comprovativo</th><th>Valor</th><th>Estado</th><th>Validade</th><th>Aprovado por</th><th>Ações</th></tr></thead><tbody id="tabela-pagamentos"></tbody></table></div>
    </section>

    @if ($ehSuper)
    <section class="adm-secao space-y-4" id="secao-precos">
      <div class="vm-card">
        <h3 class="vm-h3">Pacotes de pagamento (acesso do condutor)</h3>
        <p class="vm-small mb-4">Preço e duração de cada pacote — o condutor escolhe um destes ao submeter o comprovativo de pagamento.</p>
        <form id="form-novo-pacote" class="flex flex-wrap items-end gap-2">
          <div class="vm-field w-36"><label class="vm-label">Nome</label><input class="vm-input" type="text" id="pacote-nome" maxlength="20" required></div>
          <div class="vm-field w-44">
            <label class="vm-label">Tipo de serviço</label>
            <select class="vm-select" id="pacote-tipo-servico" required>
              <option value="urbano">Urbano</option>
              <option value="intermunicipal">Intermunicipal</option>
              <option value="ambos" selected>Urbano + Intermunicipal</option>
            </select>
          </div>
          <div class="vm-field min-w-40 flex-1"><label class="vm-label">Descrição</label><input class="vm-input" type="text" id="pacote-descricao"></div>
          <div class="vm-field w-32"><label class="vm-label">Preço (CVE)</label><input class="vm-input" type="number" step="1" min="0" id="pacote-preco" required></div>
          <div class="vm-field w-32"><label class="vm-label">Duração (dias)</label><input class="vm-input" type="number" step="1" min="1" id="pacote-duracao" required></div>
          <button class="vm-btn vm-btn--primario vm-btn--sm mb-4" type="submit">Criar pacote</button>
        </form>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
          <form id="filtro-pacotes" class="flex flex-wrap items-end gap-2">
            <div class="vm-field mb-0 min-w-44 flex-1"><label class="vm-label" for="filtro-pacote-texto">Pesquisar</label><input class="vm-input" type="search" id="filtro-pacote-texto" placeholder="Nome ou descrição"></div>
            <div class="vm-field mb-0 w-44">
              <label class="vm-label" for="filtro-pacote-tipo">Tipo de serviço</label>
              <select class="vm-select" id="filtro-pacote-tipo">
                <option value="">Todos</option>
                <option value="urbano">Urbano</option>
                <option value="intermunicipal">Intermunicipal</option>
                <option value="ambos">Urbano + Intermunicipal</option>
              </select>
            </div>
            <div class="vm-field mb-0 w-32">
              <label class="vm-label" for="filtro-pacote-estado">Estado</label>
              <select class="vm-select" id="filtro-pacote-estado">
                <option value="">Todos</option>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
            <div class="vm-field mb-0 w-28"><label class="vm-label" for="filtro-pacote-preco-min">Preço mín.</label><input class="vm-input" type="number" step="1" min="0" id="filtro-pacote-preco-min"></div>
            <div class="vm-field mb-0 w-28"><label class="vm-label" for="filtro-pacote-preco-max">Preço máx.</label><input class="vm-input" type="number" step="1" min="0" id="filtro-pacote-preco-max"></div>
            <div class="vm-field mb-0 w-28"><label class="vm-label" for="filtro-pacote-dias-min">Dias mín.</label><input class="vm-input" type="number" step="1" min="1" id="filtro-pacote-dias-min"></div>
            <div class="vm-field mb-0 w-28"><label class="vm-label" for="filtro-pacote-dias-max">Dias máx.</label><input class="vm-input" type="number" step="1" min="1" id="filtro-pacote-dias-max"></div>
            <button class="vm-btn vm-btn--ghost vm-btn--sm" type="reset" id="btn-limpar-filtro-pacotes">Limpar filtro</button>
          </form>
          <p class="vm-small mt-2 mb-0" id="filtro-pacotes-resumo"></p>
        </div>
        <div class="vm-tabela-wrap mt-4"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Tipo de serviço</th><th>Descrição</th><th>Preço (CVE)</th><th>Duração (dias)</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-pacotes"></tbody></table></div>
      </div>
      <div class="vm-card">
        <h3 class="vm-h3">Preço por km</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2" id="precos-km"></div>
      </div>
      <div class="vm-card">
        <h3 class="vm-h3">Configurações gerais</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" id="precos-config"></div>
      </div>
      <div class="vm-card">
        <h3 class="vm-h3">Preço fixo por rota</h3>
        <p class="vm-small mb-4">A distância é calculada automaticamente e usada para fracionar o preço quando um passageiro embarca/desce a meio da rota.</p>
        <form id="form-nova-rota" class="flex flex-wrap items-end gap-2">
          <div class="vm-field min-w-40 flex-1"><label class="vm-label">Origem</label><select class="vm-select" id="rota-origem"></select></div>
          <div class="vm-field min-w-40 flex-1"><label class="vm-label">Destino</label><select class="vm-select" id="rota-destino"></select></div>
          <div class="vm-field w-36"><label class="vm-label">Preço (CVE)</label><input class="vm-input" type="number" step="1" min="0" id="rota-preco" required></div>
          <button class="vm-btn vm-btn--primario vm-btn--sm mb-4" type="submit">Definir</button>
        </form>
        <div class="vm-tabela-wrap mt-4"><table class="vm-tabela"><thead><tr><th>Origem</th><th>Destino</th><th>Distância</th><th>Preço fixo</th><th></th></tr></thead><tbody id="tabela-rotas"></tbody></table></div>
      </div>
      <div class="vm-card">
        <h3 class="vm-h3">Limites de cidade</h3>
        <p class="vm-small mb-4">Centro (lat/lng) e raio de cada cidade — define onde termina a tarifa urbana e começa a intermunicipal.</p>
        <button class="vm-btn vm-btn--cta vm-btn--sm mb-4" id="btn-novo-limite">+ Novo limite de cidade</button>
        <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Cidade</th><th>Coordenadas</th><th>Raio (km)</th><th>Ações</th></tr></thead><tbody id="tabela-limites-cidades"></tbody></table></div>
      </div>
    </section>
    @endif

    @if ($ehSuper)
    <section class="adm-secao" id="secao-administradores">
      <form id="form-novo-admin" class="mb-4 flex flex-wrap items-end gap-2">
        <div class="vm-field min-w-40 flex-1"><label class="vm-label">Nome</label><input class="vm-input" name="nome" required></div>
        <div class="vm-field min-w-40 flex-1"><label class="vm-label">Email</label><input class="vm-input" type="email" name="email" required></div>
        <div class="vm-field w-40">
          <label class="vm-label">Nível</label>
          <select class="vm-select" name="nivel"><option value="gestor">Gestor</option><option value="admin">Admin</option><option value="super">Super</option></select>
        </div>
        <button class="vm-btn vm-btn--primario vm-btn--sm mb-4" type="submit">Criar admin</button>
      </form>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Nome</th><th>Email</th><th>Nível</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-admins"></tbody></table></div>
    </section>
    @endif

    @if ($ehSuper)
    <section class="adm-secao" id="secao-notificacoes">
      <div class="vm-card max-w-2xl">
        <h3 class="vm-h3">Enviar notificação</h3>
        <form id="form-notificacao" class="mt-4">
          <div class="vm-field">
            <label class="vm-label">Destinatário</label>
            <select class="vm-select" id="notif-destinatario" name="destinatario_tipo">
              <option value="todos">Todos os utilizadores</option>
              <option value="admins">Administradores</option>
              <option value="condutores">Condutores</option>
              <option value="passageiros">Passageiros</option>
              <option value="individual">Utilizador específico (ID)</option>
            </select>
          </div>
          <div class="vm-field" id="campo-notif-id" style="display:none;">
            <label class="vm-label">ID do utilizador</label>
            <input class="vm-input" type="number" name="destinatario_id" id="notif-destinatario-id">
          </div>
          <div class="vm-field">
            <label class="vm-label">Tipo</label>
            <select class="vm-select" name="tipo">
              <option value="informativo">Informativo</option>
              <option value="alerta">Alerta</option>
              <option value="urgente">Urgente (aparece como modal ao entrar)</option>
            </select>
          </div>
          <div class="vm-field"><label class="vm-label">Título</label><input class="vm-input" name="titulo" required></div>
          <div class="vm-field"><label class="vm-label">Mensagem</label><textarea class="vm-input" name="mensagem" rows="4" required></textarea></div>
          <div id="notificacao-msg" class="vm-erro"></div>
          <button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Enviar</button>
        </form>
      </div>
    </section>
    @endif

    <section class="adm-secao" id="secao-sugestoes">
      <p class="vm-small mb-4">Sugestões são visíveis apenas ao Super Admin. Reclamações sobre condutores são visíveis a todos os administradores.</p>
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Tipo</th><th>De</th><th>Sobre condutor</th><th>Título</th><th>Estado</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tabela-sugestoes"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-logs">
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Admin</th><th>Ação</th><th>Entidade</th><th>Data</th></tr></thead><tbody id="tabela-logs"></tbody></table></div>
    </section>

    <section class="adm-secao" id="secao-sos">
      <div class="vm-tabela-wrap"><table class="vm-tabela"><thead><tr><th>Utilizador</th><th>Tipo</th><th>Localização</th><th>Estado</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tabela-sos"></tbody></table></div>
    </section>

  </main>
</div>

<!-- Modal proprietário -->
<div class="vm-modal-overlay" id="modal-proprietario" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Proprietário</h3>
    <form id="form-proprietario">
      <input type="hidden" name="id" id="proprietario-id">
      <input type="hidden" name="acao" id="proprietario-acao" value="criar">
      <div class="vm-field"><label class="vm-label">Nome</label><input class="vm-input" name="nome" id="proprietario-nome" required></div>
      <div class="vm-field"><label class="vm-label">Telefone</label><input class="vm-input" name="telefone" id="proprietario-telefone" placeholder="+238 9912345" required></div>
      <div class="vm-field"><label class="vm-label">NIF</label><input class="vm-input" name="nif" id="proprietario-nif" maxlength="9" required></div>
      <div class="vm-field">
        <label class="vm-label">Também é condutor (opcional)</label>
        <select class="vm-select" name="utilizador_condutor_id" id="proprietario-condutor"><option value="">Não conduz</option></select>
      </div>
      <div id="proprietario-msg" class="vm-erro"></div>
      <div class="flex flex-wrap gap-2"><button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-proprietario">Cancelar</button><button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal ponto -->
<div class="vm-modal-overlay" id="modal-ponto" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Ponto de partida</h3>
    <form id="form-ponto">
      <input type="hidden" name="id" id="ponto-id">
      <input type="hidden" name="acao" id="ponto-acao" value="criar">
      <div class="vm-field"><label class="vm-label">Nome</label><input class="vm-input" name="nome" id="ponto-nome" required></div>
      <div class="vm-field"><label class="vm-label">Cidade</label><input class="vm-input" name="cidade" id="ponto-cidade" required></div>
      <div class="vm-field"><label class="vm-label">Zona</label><select class="vm-select" name="zona" id="ponto-zona"><option value="urbana">Urbana</option><option value="intermunicipal">Intermunicipal</option></select></div>
      <div class="flex flex-wrap gap-2">
        <div class="vm-field flex-1"><label class="vm-label">Latitude</label><input class="vm-input" type="number" step="0.000001" name="lat" id="ponto-lat" required></div>
        <div class="vm-field flex-1"><label class="vm-label">Longitude</label><input class="vm-input" type="number" step="0.000001" name="lng" id="ponto-lng" required></div>
      </div>
      <div id="ponto-msg" class="vm-erro"></div>
      <div class="flex flex-wrap gap-2"><button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-ponto">Cancelar</button><button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal parque -->
<div class="vm-modal-overlay" id="modal-parque" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Parque de estacionamento</h3>
    <form id="form-parque">
      <input type="hidden" name="id" id="parque-id">
      <input type="hidden" name="acao" id="parque-acao" value="criar">
      <div class="vm-field"><label class="vm-label">Nome</label><input class="vm-input" name="nome" id="parque-nome" required></div>
      <div class="vm-field"><label class="vm-label">Morada</label><input class="vm-input" name="morada" id="parque-morada" required></div>
      <div class="vm-field"><label class="vm-label">Cidade</label><input class="vm-input" name="cidade" id="parque-cidade" required></div>
      <div class="flex flex-wrap gap-2">
        <div class="vm-field flex-1"><label class="vm-label">Latitude</label><input class="vm-input" type="number" step="0.000001" name="lat" id="parque-lat" required></div>
        <div class="vm-field flex-1"><label class="vm-label">Longitude</label><input class="vm-input" type="number" step="0.000001" name="lng" id="parque-lng" required></div>
      </div>
      <div class="flex flex-wrap gap-2">
        <div class="vm-field flex-1"><label class="vm-label">Capacidade total</label><input class="vm-input" type="number" min="1" name="capacidade_total" id="parque-capacidade" required></div>
        <div class="vm-field flex-1"><label class="vm-label">Vagas ocupadas</label><input class="vm-input" type="number" min="0" name="vagas_ocupadas" id="parque-ocupadas" value="0" required></div>
      </div>
      <div id="parque-msg" class="vm-erro"></div>
      <div class="flex flex-wrap gap-2"><button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-parque">Cancelar</button><button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal pacote de pagamento -->
<div class="vm-modal-overlay" id="modal-pacote" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Editar pacote</h3>
    <form id="form-pacote">
      <input type="hidden" id="pacote-editar-id">
      <div class="vm-field"><label class="vm-label" for="pacote-editar-nome">Nome</label><input class="vm-input" type="text" id="pacote-editar-nome" maxlength="20" required></div>
      <div class="vm-field">
        <label class="vm-label" for="pacote-editar-tipo">Tipo de serviço</label>
        <select class="vm-select" id="pacote-editar-tipo" required>
          <option value="urbano">Urbano</option>
          <option value="intermunicipal">Intermunicipal</option>
          <option value="ambos">Urbano + Intermunicipal</option>
        </select>
      </div>
      <div class="vm-field"><label class="vm-label" for="pacote-editar-descricao">Descrição</label><input class="vm-input" type="text" id="pacote-editar-descricao" maxlength="255"></div>
      <div class="flex flex-wrap gap-2">
        <div class="vm-field flex-1"><label class="vm-label" for="pacote-editar-preco">Preço (CVE)</label><input class="vm-input" type="number" step="1" min="0" id="pacote-editar-preco" required></div>
        <div class="vm-field flex-1"><label class="vm-label" for="pacote-editar-duracao">Duração (dias)</label><input class="vm-input" type="number" step="1" min="1" id="pacote-editar-duracao" required></div>
      </div>
      <div class="vm-field">
        <label class="vm-label" for="pacote-editar-estado">Estado</label>
        <select class="vm-select" id="pacote-editar-estado">
          <option value="1">Ativo</option>
          <option value="0">Inativo</option>
        </select>
      </div>
      <div id="pacote-editar-msg" class="vm-erro"></div>
      <div class="flex flex-wrap gap-2"><button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-pacote">Cancelar</button><button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal limite de cidade -->
<div class="vm-modal-overlay" id="modal-limite-cidade" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Limite de cidade</h3>
    <form id="form-limite-cidade">
      <input type="hidden" name="id" id="limite-id">
      <input type="hidden" name="acao" id="limite-acao" value="criar">
      <div class="vm-field"><label class="vm-label">Cidade</label><input class="vm-input" name="cidade" id="limite-cidade" required></div>
      <div class="flex flex-wrap gap-2">
        <div class="vm-field flex-1"><label class="vm-label">Latitude (centro)</label><input class="vm-input" type="number" step="0.000001" name="lat" id="limite-lat" required></div>
        <div class="vm-field flex-1"><label class="vm-label">Longitude (centro)</label><input class="vm-input" type="number" step="0.000001" name="lng" id="limite-lng" required></div>
      </div>
      <div class="vm-field"><label class="vm-label">Raio (km)</label><input class="vm-input" type="number" step="0.1" min="0.1" name="raio_km" id="limite-raio" required></div>
      <div id="limite-msg" class="vm-erro"></div>
      <div class="flex flex-wrap gap-2"><button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-limite">Cancelar</button><button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal senha temporária -->
<div class="vm-modal-overlay" id="modal-senha-admin" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3">Admin criado com sucesso</h3>
    <p class="vm-small mb-4">Entregue a senha temporária abaixo ao novo administrador por um canal seguro. Só é mostrada uma vez.</p>
    <div class="vm-field">
      <label class="vm-label">Senha temporária</label>
      <input class="vm-input font-bold tracking-widest" id="senha-admin-valor" readonly>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="vm-btn vm-btn--outline vm-btn--full" id="btn-copiar-senha-admin">Copiar</button>
      <button type="button" class="vm-btn vm-btn--primario vm-btn--full" id="btn-fechar-senha-admin">Concluído</button>
    </div>
  </div>
</div>

<!-- Modal notificações do admin -->
<div class="vm-modal-overlay" id="modal-notificacoes-admin" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">As minhas notificações</h3>
    <div id="lista-notificacoes-admin" class="max-h-[60dvh] space-y-2 overflow-y-auto overscroll-contain"></div>
    <button type="button" class="vm-btn vm-btn--ghost vm-btn--full mt-4" id="btn-fechar-notificacoes-admin">Fechar</button>
  </div>
</div>

<!-- Toasts -->
<div id="toast-container" class="fixed right-4 top-seguro [--top-seguro:1rem] z-1100 flex flex-col gap-2 sm:right-5 sm:[--top-seguro:1.25rem]"></div>

<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script src="@kgAsset('assets/js/kg-esc.js')"></script>
<script src="@kgAsset('assets/js/kg-datas.js')"></script>
<script src="@kgAsset('assets/js/kg-map.js')"></script>
<script nonce="{{ $cspNonce }}">
const CSRF_TOKEN = @json(csrf_token());
const EH_SUPER = @json($ehSuper);
const TIPO_SERVICO_LABEL = { urbano: 'Urbano', intermunicipal: 'Intermunicipal', ambos: 'Urbano + Intermunicipal' };
let mapaEditor = null;
let pontosCache = [];

// Ícones (config/icones.php) — a mesma lista que o componente x-icone usa
// do lado do servidor, para os cartões desenhados aqui não terem um conjunto
// à parte. (Sem os sinais de menor/maior à volta do nome: o Blade compila
// isso como componente mesmo dentro de um comentário de JavaScript.)
const ICONES = @json(config('icones'));

function svgIcone(nome, classe) {
  const desenho = ICONES[nome];
  if (!desenho) return '';
  return `<svg class="${classe || 'h-5 w-5'}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${desenho}</svg>`;
}

// 'login_falhado' -> 'Login falhado'
function rotularAcao(acao) {
  if (!acao) return '—';
  const texto = String(acao).replace(/_/g, ' ');
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

// 'administradores' + 1 -> 'administradores #1'
function rotularEntidade(entidade, id) {
  if (!entidade) return '—';
  return id ? `${entidade} #${id}` : String(entidade);
}

function fd(extra) {
  const d = new FormData();
  d.set('_token', CSRF_TOKEN);
  Object.entries(extra || {}).forEach(([k, v]) => d.set(k, v));
  return d;
}

// Toasts
function toast(mensagem, tipo) {
  const el = document.createElement('div');
  el.className = 'vm-toast' + (tipo === 'erro' ? ' vm-toast--erro' : tipo === 'sucesso' ? ' vm-toast--sucesso' : '');
  el.textContent = mensagem;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

// Sidebar mobile (hamburger)
const sidebarEl = document.getElementById('sidebar');
const sidebarOverlayEl = document.getElementById('sidebar-overlay');
const btnToggleSidebar = document.getElementById('btn-toggle-sidebar');
function abrirSidebar() {
  sidebarEl.classList.remove('-translate-x-full');
  sidebarOverlayEl.classList.remove('hidden');
  btnToggleSidebar.setAttribute('aria-expanded', 'true');
}
function fecharSidebar() {
  sidebarEl.classList.add('-translate-x-full');
  sidebarOverlayEl.classList.add('hidden');
  btnToggleSidebar.setAttribute('aria-expanded', 'false');
}
btnToggleSidebar.addEventListener('click', () => {
  if (sidebarEl.classList.contains('-translate-x-full')) abrirSidebar(); else fecharSidebar();
});
sidebarOverlayEl.addEventListener('click', fecharSidebar);

// Navegação
document.querySelectorAll('.adm-nav-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.adm-nav-item').forEach(i => i.classList.remove('ativo'));
    item.classList.add('ativo');
    document.querySelectorAll('.adm-secao').forEach(s => s.classList.remove('ativa'));
    const secao = document.getElementById('secao-' + item.dataset.secao);
    if (secao) secao.classList.add('ativa');
    document.getElementById('titulo-secao').textContent = item.querySelector('.adm-nav-label').textContent;
    fecharSidebar();
    carregarSecao(item.dataset.secao);
  });
});

// Pedido GET/POST com tratamento uniforme de sessão expirada (401): em vez
// de deixar o .map()/Object.entries() seguinte rebentar com um JSON de erro,
// redireciona para o login. Lança para o chamador não continuar a executar.
async function kgApiJSON(url, opts) {
  const resp = await fetch(url, opts);
  if (resp.status === 401) {
    window.location.href = '/admin/login';
    throw new Error('Sessão expirada, a redirecionar para o login.');
  }
  const json = await resp.json();
  if (!resp.ok) {
    throw new Error(json.erro || `Erro ${resp.status} em ${url}`);
  }
  return json;
}

async function carregarSecao(nome) {
  if (nome === 'dashboard') return carregarDashboard();
  if (nome === 'utilizadores') return carregarUtilizadores();
  if (nome === 'veiculos') return carregarVeiculos();
  if (nome === 'condutores') return carregarCondutoresPendentes();
  if (nome === 'proprietarios') return carregarProprietarios();
  if (nome === 'pontos') return carregarPontos();
  if (nome === 'parques') return carregarParques();
  if (nome === 'editor-mapa') return iniciarEditorMapa();
  if (nome === 'pagamentos') return carregarPagamentos();
  if (nome === 'sugestoes') return carregarSugestoes();
  if (nome === 'notificacoes' && EH_SUPER) return;
  if (nome === 'precos' && EH_SUPER) { carregarPrecos(); carregarLimitesCidades(); }
  if (nome === 'administradores' && EH_SUPER) return carregarAdmins();
  if (nome === 'logs') return carregarLogs();
  if (nome === 'sos') return carregarSos();
}

async function carregarDashboard() {
  const json = await kgApiJSON('/api/admin/dashboard');
  const container = document.getElementById('dashboard-stats');

  // Tons escritos por extenso (e não compostos em tempo de execução): o
  // Tailwind lê este ficheiro como texto para saber que classes gerar, e uma
  // classe montada a partir de variáveis nunca lá aparece — ficaria sem estilo.
  const TONS = {
    neutro: { cartao: '', chip: 'bg-slate-100 text-slate-500', num: 'text-slate-900' },
    ambar: { cartao: 'bg-amber-50/50 ring-1 ring-amber-200', chip: 'bg-amber-100 text-amber-700', num: 'text-amber-700' },
    vermelho: { cartao: 'bg-red-50/50 ring-1 ring-red-200', chip: 'bg-red-100 text-red-700', num: 'text-red-700' },
  };

  // 'alerta' é o tom que o cartão toma quando o número for maior que zero.
  // Sem alerta (ou com zero) fica neutro: um painel onde tudo está sempre
  // colorido não distingue o que precisa de ação do que é só contagem.
  const META = {
    passageiros: { label: 'Passageiros', icone: 'utilizadores' },
    condutores: { label: 'Condutores ativos', icone: 'condutor' },
    condutores_pendentes: { label: 'Condutores pendentes', icone: 'condutor', alerta: 'ambar' },
    veiculos: { label: 'Veículos aprovados', icone: 'veiculo' },
    veiculos_pendentes: { label: 'Veículos pendentes', icone: 'veiculo', alerta: 'ambar' },
    pontos: { label: 'Pontos aprovados', icone: 'ponto' },
    pontos_pendentes: { label: 'Pontos pendentes', icone: 'ponto', alerta: 'ambar' },
    parques: { label: 'Parques', icone: 'parque' },
    pagamentos_pendentes: { label: 'Pagamentos pendentes', icone: 'pagamento', alerta: 'ambar' },
    faturas_pendentes: { label: 'Faturas pendentes', icone: 'fatura', alerta: 'ambar' },
    faturas_vencidas: { label: 'Faturas vencidas', icone: 'alerta', alerta: 'vermelho' },
    administradores_ativos: { label: 'Admins ativos', icone: 'administrador' },
    sos_pendentes: { label: 'SOS pendentes', icone: 'sos', alerta: 'vermelho' },
  };

  // Os mesmos grupos do menu lateral. Treze cartões seguidos leem-se como uma
  // parede de números; separados por área, cada linha responde a uma pergunta.
  const GRUPOS = [
    ['Operação', ['passageiros', 'condutores', 'condutores_pendentes', 'veiculos', 'veiculos_pendentes']],
    ['Infraestrutura', ['pontos', 'pontos_pendentes', 'parques']],
    ['Financeiro', ['pagamentos_pendentes', 'faturas_pendentes', 'faturas_vencidas']],
    ['Sistema', ['administradores_ativos', 'sos_pendentes']],
  ];

  const stats = json.stats || {};
  const arrumadas = new Set(GRUPOS.flatMap(([, chaves]) => chaves));
  const sobras = Object.keys(stats).filter(k => !arrumadas.has(k));
  const grupos = sobras.length ? [...GRUPOS, ['Outros', sobras]] : GRUPOS;

  function cartao(chave) {
    const m = META[chave] || { label: chave, icone: 'dashboard' };
    const valor = Number(stats[chave]) || 0;
    const tom = TONS[valor > 0 ? m.alerta : null] || TONS.neutro;
    return `<div class="adm-stat ${esc(tom.cartao)}">
      <span class="adm-stat-icone ${esc(tom.chip)}">${svgIcone(m.icone)}</span>
      <span class="min-w-0">
        <span class="adm-stat-num ${esc(tom.num)}">${esc(valor.toLocaleString('pt-PT'))}</span>
        <span class="adm-stat-rotulo">${esc(m.label)}</span>
      </span>
    </div>`;
  }

  container.innerHTML = grupos.map(([titulo, chaves]) => {
    const cartoes = chaves.filter(k => k in stats).map(cartao).join('');
    if (!cartoes) return '';
    return `<section>
      <h3 class="adm-stat-grupo">${esc(titulo)}</h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">${cartoes}</div>
    </section>`;
  }).join('');

  document.getElementById('dashboard-logs').innerHTML = json.ultimas_acoes.map(l =>
    `<tr><td>${esc(l.admin_nome || '—')}</td><td>${esc(rotularAcao(l.acao))}</td><td class="text-slate-500">${esc(rotularEntidade(l.entidade, l.entidade_id))}</td><td class="text-slate-500 tabular-nums">${esc(formatarData(l.criado_em))}</td></tr>`
  ).join('') || '<tr><td colspan="4" class="vm-small">Sem registos.</td></tr>';
}

// Ao contrário do filtro de pacotes, este corre no servidor: a lista de
// utilizadores passa dos 200 que a API devolve, e filtrar no browser só o
// que já veio mostraria um resultado incompleto sem avisar ninguém.
function filtroUtilizadoresQuery() {
  const valor = id => (document.getElementById(id)?.value || '').trim();
  const periodo = valor('filtro-utilizador-periodo');
  const params = new URLSearchParams();

  const juntar = (chave, v) => { if (v) params.set(chave, v); };
  juntar('q', valor('filtro-utilizador-texto'));
  juntar('tipo', valor('filtro-utilizador-tipo'));
  juntar('status', valor('filtro-utilizador-status'));
  juntar('periodo', periodo);
  juntar('ordem', valor('filtro-utilizador-ordem'));
  // As datas só valem no modo personalizado — deixá-las na query com um
  // período nomeado escolhido daria dois filtros de data a competir.
  if (periodo === 'personalizado') {
    juntar('data_inicio', valor('filtro-utilizador-inicio'));
    juntar('data_fim', valor('filtro-utilizador-fim'));
  }

  return params;
}

// "Ordenar por" muda a vista, não restringe a lista: com só ele preenchido a
// tabela vazia quer dizer "não há utilizadores", e não "o filtro não achou".
function filtroUtilizadoresRestringe() {
  return [...filtroUtilizadoresQuery().keys()].some(chave => chave !== 'ordem');
}

function sincronizarBotoesTipoUtilizador() {
  const tipo = document.getElementById('filtro-utilizador-tipo')?.value || '';
  document.querySelectorAll('[data-filtro-tipo]').forEach(btn => {
    const ativo = btn.dataset.filtroTipo === tipo;
    btn.classList.toggle('vm-btn--outline', !ativo);
    btn.classList.toggle('vm-btn--primario', ativo);
  });
}

async function carregarUtilizadores() {
  sincronizarBotoesTipoUtilizador();
  const query = filtroUtilizadoresQuery().toString();
  const json = await kgApiJSON('/api/admin/utilizadores' + (query ? `?${query}` : ''));
  document.getElementById('tabela-utilizadores').innerHTML = json.utilizadores.map(u => `
    <tr>
      <td>${esc(u.nome)}</td><td>${esc(u.tipo)}</td><td>${esc(u.telefone)}</td><td>${esc(u.nif)}</td>
      <td class="text-slate-500 tabular-nums">${esc(formatarData(u.criado_em))}</td>
      <td><span class="vm-badge vm-badge--${esc(u.status === 'ativo' ? 'confirmado' : u.status === 'pendente' ? 'pendente' : 'recusado')}">${esc(u.status)}</span></td>
      <td>
        ${u.status === 'ativo' ? `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-suspender-id="${esc(u.id)}">Suspender</button>` : `<button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="reativar" data-id="${esc(u.id)}">Reativar</button>`}
        <button class="vm-btn vm-btn--sm vm-btn--ghost" data-acao="eliminar" data-id="${esc(u.id)}">Eliminar</button>
      </td>
    </tr>`).join('') || `<tr><td colspan="7" class="vm-small">${filtroUtilizadoresRestringe() ? 'Nenhum utilizador corresponde ao filtro.' : 'Sem utilizadores registados.'}</td></tr>`;

  const resumo = document.getElementById('filtro-utilizadores-resumo');
  if (resumo) resumo.textContent = json.total > json.utilizadores.length
    ? `A mostrar os primeiros ${json.utilizadores.length} de ${json.total} utilizadores — restrinja o filtro para ver os restantes.`
    : `A mostrar ${json.total} utilizador${json.total === 1 ? '' : 'es'}.`;

  // "Suspender" pede sempre o motivo (fica no registo de auditoria) — por
  // isso usa um botão à parte, fora do ligarAcoesTabela genérico.
  document.querySelectorAll('#tabela-utilizadores [data-suspender-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const motivo = prompt('Motivo da suspensão (fica registado em auditoria):');
      if (motivo === null) return;
      const resp = await fetch('/api/admin/utilizadores', { method: 'POST', body: fd({ acao: 'suspender', id: btn.dataset.suspenderId, motivo }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast('Utilizador suspenso.', 'sucesso');
      carregarUtilizadores();
    });
  });
  ligarAcoesTabela('tabela-utilizadores', '/api/admin/utilizadores', carregarUtilizadores);
}

// Os botões de topo são atalhos para o campo "Tipo" do filtro: o select
// continua a ser o único sítio onde o tipo escolhido vive.
document.querySelectorAll('[data-filtro-tipo]').forEach(btn => btn.addEventListener('click', () => {
  const sel = document.getElementById('filtro-utilizador-tipo');
  if (sel) sel.value = btn.dataset.filtroTipo;
  carregarUtilizadores();
}));

function alternarIntervaloUtilizadores() {
  const personalizado = document.getElementById('filtro-utilizador-periodo')?.value === 'personalizado';
  document.querySelectorAll('#filtro-utilizadores [data-intervalo-personalizado]').forEach(el => { el.hidden = !personalizado; });
}

const filtroUtilizadores = document.getElementById('filtro-utilizadores');
if (filtroUtilizadores) {
  // Escrever no campo de pesquisa não pode disparar um pedido por tecla.
  let temporizador;
  const recarregar = atraso => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => { alternarIntervaloUtilizadores(); carregarUtilizadores(); }, atraso);
  };
  filtroUtilizadores.addEventListener('input', e => recarregar(e.target.type === 'search' ? 350 : 0));
  filtroUtilizadores.addEventListener('change', () => recarregar(0));
  // O reset do formulário só limpa os campos depois deste evento correr.
  filtroUtilizadores.addEventListener('reset', () => recarregar(0));
  filtroUtilizadores.addEventListener('submit', e => e.preventDefault());
  alternarIntervaloUtilizadores();
}

// Como o dos utilizadores, este filtro corre no servidor: a frota passa do
// limite de 200 que a API devolve e filtrar no browser só o que já veio
// esconderia veículos sem ninguém dar por isso.
function filtroVeiculosQuery() {
  const valor = id => (document.getElementById(id)?.value || '').trim();
  const periodo = valor('filtro-veiculo-periodo');
  const params = new URLSearchParams();

  const juntar = (chave, v) => { if (v) params.set(chave, v); };
  juntar('q', valor('filtro-veiculo-texto'));
  juntar('tipo', valor('filtro-veiculo-tipo'));
  juntar('tipo_servico', valor('filtro-veiculo-servico'));
  juntar('estado', valor('filtro-veiculo-estado'));
  juntar('aprovado', valor('filtro-veiculo-aprovado'));
  juntar('ponto', valor('filtro-veiculo-ponto'));
  juntar('periodo', periodo);
  juntar('ordem', valor('filtro-veiculo-ordem'));
  // As datas só valem no modo personalizado — deixá-las na query com um
  // período nomeado escolhido daria dois filtros de data a competir.
  if (periodo === 'personalizado') {
    juntar('data_inicio', valor('filtro-veiculo-inicio'));
    juntar('data_fim', valor('filtro-veiculo-fim'));
  }

  return params;
}

// "Ordenar por" muda a vista, não restringe a lista: com só ele preenchido a
// tabela vazia quer dizer "não há veículos", e não "o filtro não achou".
function filtroVeiculosRestringe() {
  return [...filtroVeiculosQuery().keys()].some(chave => chave !== 'ordem');
}

function sincronizarBotoesAprovadoVeiculo() {
  const aprovado = document.getElementById('filtro-veiculo-aprovado')?.value || '';
  document.querySelectorAll('[data-filtro-aprovado]').forEach(btn => {
    const ativo = btn.dataset.filtroAprovado === aprovado;
    btn.classList.toggle('vm-btn--outline', !ativo);
    btn.classList.toggle('vm-btn--primario', ativo);
  });
}

const ESTADOS_VEICULO = {
  no_ponto: 'No ponto', na_fila: 'Na fila', partiu_da_fila: 'Em rota',
  em_movimento: 'Em movimento', chegou_destino: 'Chegou ao destino',
};

const SERVICOS_VEICULO = {
  urbano: 'urbano', intermunicipal: 'intermunicipal', ambos: 'urbano + intermunicipal',
};

async function carregarVeiculos() {
  sincronizarBotoesAprovadoVeiculo();
  const query = filtroVeiculosQuery().toString();
  const json = await kgApiJSON('/api/admin/veiculos' + (query ? `?${query}` : ''));
  document.getElementById('tabela-veiculos').innerHTML = json.veiculos.map(v => {
    // Depois de guardar o ponto de partida/destino, o condutor fica 24 h sem
    // o botão "Guardar" (Condutor\VeiculoController::definirPonto). Quem se
    // enganou no ponto só sai daí por aqui: "Libertar ponto" limpa o prazo e
    // devolve-lhe o botão no refrescamento seguinte do painel dele.
    const bloqueioAte = v.ponto_bloqueado_ate ? new Date(v.ponto_bloqueado_ate) : null;
    const pontoBloqueado = !!bloqueioAte && !isNaN(bloqueioAte.getTime()) && bloqueioAte.getTime() > Date.now();
    return `
    <tr>
      <td>${esc(v.matricula)}</td>
      <td>${esc(v.tipo)}<span class="vm-small block text-slate-500">${esc(SERVICOS_VEICULO[v.tipo_servico] || v.tipo_servico)}</span></td>
      <td>${esc(v.condutor_nome)}</td>
      <td class="text-slate-500 tabular-nums">${esc(formatarData(v.criado_em))}</td>
      <td>${esc(ESTADOS_VEICULO[v.estado] || v.estado)}${pontoBloqueado ? ` <span class="vm-badge vm-badge--pendente">Ponto trancado até ${esc(formatarData(v.ponto_bloqueado_ate))}</span>` : ''}</td>
      <td>${esc(v.aprovado ? 'Sim' : 'Não')}</td>
      <td>
        ${!v.aprovado ? `<button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="aprovar" data-id="${esc(v.id)}">Aprovar</button>` : `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="rejeitar" data-id="${esc(v.id)}">Revogar</button>`}
        ${pontoBloqueado ? `<button class="vm-btn vm-btn--sm vm-btn--outline" data-acao="libertar_ponto" data-id="${esc(v.id)}"
          data-confirmar="Autorizar ${esc(v.condutor_nome)} a alterar já o ponto de partida do veículo ${esc(v.matricula)}, antes das 24 horas?">Libertar ponto</button>` : ''}
        <button class="vm-btn vm-btn--sm vm-btn--ghost" data-acao="eliminar" data-id="${esc(v.id)}"
          data-confirmar="Eliminar o veículo ${esc(v.matricula)} definitivamente? Apaga também os lugares, reservas e pagamentos associados. Esta ação não pode ser desfeita.">Eliminar</button>
      </td>
    </tr>`;
  }).join('') || `<tr><td colspan="7" class="vm-small">${filtroVeiculosRestringe() ? 'Nenhum veículo corresponde ao filtro.' : 'Sem veículos registados.'}</td></tr>`;

  const resumo = document.getElementById('filtro-veiculos-resumo');
  if (resumo) resumo.textContent = json.total > json.veiculos.length
    ? `A mostrar os primeiros ${json.veiculos.length} de ${json.total} veículos — restrinja o filtro para ver os restantes.`
    : `A mostrar ${json.total} veículo${json.total === 1 ? '' : 's'}.`;

  ligarAcoesTabela('tabela-veiculos', '/api/admin/veiculos', carregarVeiculos);
}

// Os botões de topo são atalhos para o campo "Aprovação" do filtro: o select
// continua a ser o único sítio onde o estado de aprovação escolhido vive.
document.querySelectorAll('[data-filtro-aprovado]').forEach(btn => btn.addEventListener('click', () => {
  const sel = document.getElementById('filtro-veiculo-aprovado');
  if (sel) sel.value = btn.dataset.filtroAprovado;
  carregarVeiculos();
}));

function alternarIntervaloVeiculos() {
  const personalizado = document.getElementById('filtro-veiculo-periodo')?.value === 'personalizado';
  document.querySelectorAll('#filtro-veiculos [data-intervalo-veiculo]').forEach(el => { el.hidden = !personalizado; });
}

const filtroVeiculos = document.getElementById('filtro-veiculos');
if (filtroVeiculos) {
  // Escrever no campo de pesquisa não pode disparar um pedido por tecla.
  let temporizador;
  const recarregar = atraso => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => { alternarIntervaloVeiculos(); carregarVeiculos(); }, atraso);
  };
  filtroVeiculos.addEventListener('input', e => recarregar(e.target.type === 'search' ? 350 : 0));
  filtroVeiculos.addEventListener('change', () => recarregar(0));
  // O reset do formulário só limpa os campos depois deste evento correr.
  filtroVeiculos.addEventListener('reset', () => recarregar(0));
  filtroVeiculos.addEventListener('submit', e => e.preventDefault());
  alternarIntervaloVeiculos();
}

async function carregarCondutoresPendentes() {
  // Filtrar por estado no servidor e não sobre os 200 condutores mais
  // recentes: um condutor à espera de aprovação atrás desse corte nunca
  // chegava a aparecer nesta fila.
  const json = await kgApiJSON('/api/admin/utilizadores?tipo=condutor&status=pendente&ordem=antigos');
  document.getElementById('tabela-condutores-pendentes').innerHTML = json.utilizadores.map(u => `
    <tr><td>${esc(u.nome)}</td><td>${esc(u.telefone)}</td><td>${esc(u.nif)}</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="aprovar_condutor" data-id="${esc(u.id)}">Aprovar</button></td></tr>`
  ).join('') || '<tr><td colspan="4" class="vm-small">Sem condutores pendentes.</td></tr>';
  ligarAcoesTabela('tabela-condutores-pendentes', '/api/admin/utilizadores', carregarCondutoresPendentes);
}

function ligarAcoesTabela(tabelaId, endpoint, recarregar) {
  document.querySelectorAll(`#${tabelaId} [data-acao]`).forEach(btn => {
    btn.addEventListener('click', async () => {
      // Ações destrutivas trazem a pergunta no próprio botão (data-confirmar).
      if (btn.dataset.confirmar && !confirm(btn.dataset.confirmar)) return;
      const resp = await fetch(endpoint, { method: 'POST', body: fd({ acao: btn.dataset.acao, id: btn.dataset.id }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast('Ação concluída.', 'sucesso');
      recarregar();
    });
  });
}

async function carregarProprietarios() {
  const json = await kgApiJSON('/api/admin/proprietarios');
  document.getElementById('tabela-proprietarios').innerHTML = (json.proprietarios || []).map(p => `
    <tr><td>${esc(p.nome)}</td><td>${esc(p.telefone)}</td><td>${esc(p.nif)}</td>
    <td>${p.condutor_nome ? `Sim — ${esc(p.condutor_nome)}` : 'Não'}</td>
    <td>${esc(p.total_condutores)}</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--outline" data-editar='${esc(JSON.stringify(p))}'>Editar</button>
        <button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="eliminar" data-id="${esc(p.id)}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="6" class="vm-small">Sem proprietários registados.</td></tr>';
  document.querySelectorAll('#tabela-proprietarios [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalProprietario(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-proprietarios', '/api/admin/proprietarios', carregarProprietarios);
}

async function abrirModalProprietario(p) {
  document.getElementById('proprietario-id').value = p?.id || '';
  document.getElementById('proprietario-acao').value = p ? 'editar' : 'criar';
  document.getElementById('proprietario-nome').value = p?.nome || '';
  document.getElementById('proprietario-telefone').value = p?.telefone || '';
  document.getElementById('proprietario-nif').value = p?.nif || '';

  const selCondutor = document.getElementById('proprietario-condutor');
  const json = await kgApiJSON('/api/admin/utilizadores?tipo=condutor');
  selCondutor.innerHTML = '<option value="">Não conduz</option>';
  json.utilizadores.forEach(u => selCondutor.add(new Option(`${u.nome} (${u.telefone})`, u.id)));
  selCondutor.value = p?.utilizador_condutor_id || '';

  document.getElementById('modal-proprietario').style.display = 'flex';
}
document.getElementById('btn-novo-proprietario').addEventListener('click', () => abrirModalProprietario(null));
document.getElementById('btn-fechar-proprietario').addEventListener('click', () => document.getElementById('modal-proprietario').style.display = 'none');
document.getElementById('form-proprietario').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/proprietarios', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('proprietario-msg').textContent = json.erro; return; }
  document.getElementById('modal-proprietario').style.display = 'none';
  toast('Proprietário guardado.', 'sucesso');
  carregarProprietarios();
});

function badgePontoStatus(status) {
  const classe = status === 'aprovado' ? 'confirmado' : status === 'pendente' ? 'pendente' : 'recusado';
  return `<span class="vm-badge vm-badge--${esc(classe)}">${esc(status)}</span>`;
}

// Botões de aprovação: pendente tem Aprovar+Recusar; recusado pode voltar a
// ser aprovado; aprovado só tem Recusar (equivalente a despublicar).
function acoesPontoStatus(p) {
  if (p.status === 'pendente') {
    return `<button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="aprovar" data-id="${esc(p.id)}">Aprovar</button>
            <button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="recusar" data-id="${esc(p.id)}">Recusar</button>`;
  }
  if (p.status === 'recusado') {
    return `<button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="aprovar" data-id="${esc(p.id)}">Aprovar</button>`;
  }
  return `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="recusar" data-id="${esc(p.id)}">Recusar</button>`;
}

// A API devolve a lista de pontos inteira (não tem limite como a dos
// veículos) e ela já vive em pontosCache, que também alimenta os marcadores
// do editor. Por isso o filtro personalizado corre no browser — e só sobre a
// tabela: o editor de mapa continua a mostrar todos os pontos, para não
// desaparecerem marcadores por causa de um filtro escolhido noutra secção.
function valorFiltroPonto(id) {
  return (document.getElementById(id)?.value || '').trim();
}

// criado_em chega da API em UTC e a operação vive em Cabo Verde (UTC-1):
// comparar a data crua atrasaria um dia tudo o que foi criado depois das
// 23h. Reduz-se cada data ao dia civil em Cabo Verde, em ISO, que é também o
// formato dos <input type="date"> — assim a comparação é texto com texto.
const DIA_CABO_VERDE = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Atlantic/Cape_Verde', year: 'numeric', month: '2-digit', day: '2-digit',
});

function diaCaboVerde(valor) {
  if (!valor) return '';
  const d = new Date(valor);
  return isNaN(d.getTime()) ? '' : DIA_CABO_VERDE.format(d);
}

// Traduz o período escolhido para [início, fim] em dias ISO, com a mesma
// leitura do filtro de veículos: "semana", "mês" e "ano" são o calendário
// corrente, os "N dias" contam para trás a partir de hoje e o personalizado
// é o que o admin escreveu nos dois campos de data.
function intervaloPeriodoPonto() {
  const periodo = valorFiltroPonto('filtro-ponto-periodo');
  if (periodo === 'personalizado') {
    return [valorFiltroPonto('filtro-ponto-inicio'), valorFiltroPonto('filtro-ponto-fim')];
  }
  if (!periodo) return ['', ''];

  // "Hoje" é hoje em Cabo Verde, e não no fuso de quem abriu o painel.
  const hojeISO = diaCaboVerde(new Date());
  const [ano, mes] = hojeISO.split('-').map(Number);
  const hoje = new Date(`${hojeISO}T00:00:00Z`);
  const dia = n => new Date(hoje.getTime() + n * 86400000).toISOString().slice(0, 10);
  // A semana começa à segunda-feira, como no servidor (Carbon::startOfWeek).
  const desdeSegunda = (hoje.getUTCDay() + 6) % 7;

  switch (periodo) {
    case 'hoje': return [dia(0), dia(0)];
    case 'ontem': return [dia(-1), dia(-1)];
    case 'semana': return [dia(-desdeSegunda), dia(6 - desdeSegunda)];
    // O dia 0 do mês seguinte é o último dia deste mês.
    case 'mes': return [`${hojeISO.slice(0, 7)}-01`, new Date(Date.UTC(ano, mes, 0)).toISOString().slice(0, 10)];
    case 'ano': return [`${ano}-01-01`, `${ano}-12-31`];
    case '7dias': return [dia(-6), dia(0)];
    case '30dias': return [dia(-29), dia(0)];
    default: return ['', ''];
  }
}

// "Ordenar por" muda a vista, não restringe a lista: com só ele escolhido, a
// tabela vazia quer dizer "não há pontos", e não "o filtro não achou nada".
function filtroPontosRestringe() {
  const [inicio, fim] = intervaloPeriodoPonto();
  return [
    valorFiltroPonto('filtro-ponto-texto'), valorFiltroPonto('filtro-ponto-cidade'),
    valorFiltroPonto('filtro-ponto-zona'), valorFiltroPonto('filtro-ponto-status'),
    valorFiltroPonto('filtro-ponto-visibilidade'), inicio, fim,
  ].some(Boolean);
}

function pontosFiltrados() {
  const texto = valorFiltroPonto('filtro-ponto-texto').toLowerCase();
  const cidade = valorFiltroPonto('filtro-ponto-cidade');
  const zona = valorFiltroPonto('filtro-ponto-zona');
  const status = valorFiltroPonto('filtro-ponto-status');
  const visibilidade = valorFiltroPonto('filtro-ponto-visibilidade');
  const [inicio, fim] = intervaloPeriodoPonto();

  const lista = pontosCache.filter(p => {
    // Quem procura chega com o que tem à mão: o nome do ponto, a cidade, ou
    // o admin que o criou/decidiu (útil para rever o trabalho de alguém).
    const alvo = `${p.nome} ${p.cidade} ${p.criado_por_nome || ''} ${p.aprovado_por_nome || ''}`.toLowerCase();
    if (texto && !alvo.includes(texto)) return false;
    if (cidade && p.cidade !== cidade) return false;
    if (zona && p.zona !== zona) return false;
    if (status && p.status !== status) return false;
    if (visibilidade && String(p.ativo ? 1 : 0) !== visibilidade) return false;
    if (inicio || fim) {
      const criado = diaCaboVerde(p.criado_em);
      if (!criado) return false;
      if (inicio && criado < inicio) return false;
      if (fim && criado > fim) return false;
    }
    return true;
  });

  const porNome = (a, b) => String(a.nome).localeCompare(String(b.nome), 'pt');
  const porCriacao = (a, b) => new Date(a.criado_em || 0) - new Date(b.criado_em || 0) || a.id - b.id;

  // filter() já devolveu um array novo — ordenar aqui não mexe em pontosCache.
  return lista.sort((a, b) => {
    switch (valorFiltroPonto('filtro-ponto-ordem')) {
      case 'nome': return porNome(a, b);
      case 'nome_desc': return porNome(b, a);
      case 'cidade': return String(a.cidade).localeCompare(String(b.cidade), 'pt') || porNome(a, b);
      case 'recentes': return porCriacao(b, a);
      case 'antigos': return porCriacao(a, b);
      // Padrão igual ao da API: o que espera decisão aparece primeiro.
      default: return (b.status === 'pendente') - (a.status === 'pendente') || porNome(a, b);
    }
  });
}

// A lista de cidades sai dos próprios pontos — não há tabela de cidades e
// escrevê-las à mão aqui deixaria de fora qualquer cidade nova.
function preencherCidadesPonto() {
  const sel = document.getElementById('filtro-ponto-cidade');
  if (!sel) return;
  const escolhida = sel.value;
  const cidades = [...new Set(pontosCache.map(p => p.cidade).filter(Boolean))]
    .sort((a, b) => String(a).localeCompare(String(b), 'pt'));
  sel.innerHTML = '<option value="">Todas</option>'
    + cidades.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
  // Se o último ponto de uma cidade foi eliminado, essa cidade desaparece da
  // lista: o filtro volta a "Todas" em vez de ficar preso numa opção morta.
  sel.value = cidades.includes(escolhida) ? escolhida : '';
}

function sincronizarBotoesStatusPonto() {
  const status = valorFiltroPonto('filtro-ponto-status');
  document.querySelectorAll('[data-filtro-ponto-status]').forEach(btn => {
    const ativo = btn.dataset.filtroPontoStatus === status;
    btn.classList.toggle('vm-btn--outline', !ativo);
    btn.classList.toggle('vm-btn--primario', ativo);
  });
}

function desenharPontos() {
  const corpo = document.getElementById('tabela-pontos');
  if (!corpo) return;
  sincronizarBotoesStatusPonto();

  const lista = pontosFiltrados();
  corpo.innerHTML = lista.map(p => `
    <tr><td>${esc(p.nome)}</td><td>${esc(p.cidade)}</td><td>${esc(p.zona)}</td>
    <td class="tabular-nums">${esc(parseFloat(p.lat).toFixed(4))}, ${esc(parseFloat(p.lng).toFixed(4))}</td>
    <td class="text-slate-500 tabular-nums">${esc(formatarData(p.criado_em))}</td>
    <td>${badgePontoStatus(p.status)}${p.ativo ? '' : ' <span class="vm-badge vm-badge--recusado">desativado</span>'}</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--outline" data-editar='${esc(JSON.stringify(p))}'>Editar</button>
        ${acoesPontoStatus(p)}</td></tr>`
  ).join('') || `<tr><td colspan="7" class="vm-small">${filtroPontosRestringe() ? 'Nenhum ponto corresponde ao filtro.' : 'Sem pontos de partida registados.'}</td></tr>`;

  const resumo = document.getElementById('filtro-pontos-resumo');
  if (resumo) resumo.textContent = pontosCache.length
    ? `A mostrar ${lista.length} de ${pontosCache.length} ponto${pontosCache.length === 1 ? '' : 's'}.`
    : '';

  corpo.querySelectorAll('[data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalPonto(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-pontos', '/api/admin/pontos', carregarPontos);
}

// Ponto único de carregamento: alimenta a tabela (secção "Pontos") e os
// marcadores do editor de mapa (secção "Editor de mapa") a partir dos
// mesmos dados, para nunca ficarem dessincronizados (tarefa 5 do relatório).
async function carregarPontos() {
  const json = await kgApiJSON('/api/admin/pontos');
  pontosCache = json.pontos;
  preencherCidadesPonto();
  desenharPontos();
  atualizarMarcadoresPontosEditor();
}

// Os botões de topo são atalhos para o campo "Status" do filtro: o select
// continua a ser o único sítio onde o status escolhido vive.
document.querySelectorAll('[data-filtro-ponto-status]').forEach(btn => btn.addEventListener('click', () => {
  const sel = document.getElementById('filtro-ponto-status');
  if (sel) sel.value = btn.dataset.filtroPontoStatus;
  desenharPontos();
}));

function alternarIntervaloPontos() {
  const personalizado = valorFiltroPonto('filtro-ponto-periodo') === 'personalizado';
  document.querySelectorAll('#filtro-pontos [data-intervalo-ponto]').forEach(el => { el.hidden = !personalizado; });
}

const filtroPontos = document.getElementById('filtro-pontos');
if (filtroPontos) {
  const redesenhar = () => { alternarIntervaloPontos(); desenharPontos(); };
  // Sem pedido ao servidor por tecla escrita, não é preciso atrasar nada.
  filtroPontos.addEventListener('input', redesenhar);
  filtroPontos.addEventListener('change', redesenhar);
  // O reset do formulário só limpa os campos depois deste evento correr.
  filtroPontos.addEventListener('reset', () => setTimeout(redesenhar, 0));
  filtroPontos.addEventListener('submit', ev => ev.preventDefault());
  alternarIntervaloPontos();
}

let pontoEditorMarkerIds = new Set();

// Popup do marcador no editor: nome, cidade, coordenadas, status e ações
// (Aprovar/Recusar, "Ativar arrasto", "Zoom" e "Eliminar") — tarefas 2 e 3.
// Usa data-ponto-acao (nunca onclick="...") porque o CSP do site não permite
// atributos de evento inline (script-src sem 'unsafe-inline') — ver o
// listener delegado registado uma única vez logo a seguir.
function popupPontoEditor(p) {
  return `<div class="kg-ponto-popup">
    <strong>${esc(p.nome)}</strong><br>
    ${esc(p.cidade)} · ${esc(p.zona)}<br>
    <span data-coords>${esc(parseFloat(p.lat).toFixed(6))}, ${esc(parseFloat(p.lng).toFixed(6))}</span><br>
    ${badgePontoStatus(p.status)}
    <div class="mt-2 flex flex-wrap gap-2">
      ${p.status !== 'aprovado' ? `<button class="vm-btn vm-btn--sm vm-btn--cta" data-ponto-acao="aprovar" data-id="${esc(p.id)}">Aprovar</button>` : ''}
      ${p.status !== 'recusado' ? `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-ponto-acao="recusar" data-id="${esc(p.id)}">Recusar</button>` : ''}
      <button class="vm-btn vm-btn--sm vm-btn--outline" data-ponto-acao="arrastar" data-id="${esc(p.id)}">Ativar arrasto</button>
      <button class="vm-btn vm-btn--sm vm-btn--outline" data-ponto-acao="zoom" data-id="${esc(p.id)}">Zoom</button>
      ${EH_SUPER ? `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-ponto-acao="eliminar" data-id="${esc(p.id)}">Eliminar</button>` : ''}
    </div>
  </div>`;
}

// Listener único e delegado (o popup do Leaflet é recriado sempre que abre,
// por isso não vale a pena religar handlers a cada abertura — delega-se no
// documento, que existe sempre).
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('[data-ponto-acao]');
  if (!btn) return;
  handlePontoAcaoMapa(btn.dataset.pontoAcao, parseInt(btn.dataset.id, 10));
});

async function handlePontoAcaoMapa(acao, id) {
  if (acao === 'arrastar') {
    mapaEditor.setMarkerDraggable(`p-${id}`, true);
    toast('Arraste o marcador para a nova posição — a coordenada é guardada ao soltar.', 'sucesso');
    return;
  }
  if (acao === 'zoom') {
    const p = pontosCache.find(x => String(x.id) === String(id));
    if (p) mapaEditor.flyTo(parseFloat(p.lat), parseFloat(p.lng), 18);
    return;
  }
  if (acao === 'eliminar') {
    if (!confirm('Eliminar este ponto de partida definitivamente? Esta ação não pode ser desfeita.')) return;
    const resp = await fetch('/api/admin/pontos', { method: 'POST', body: fd({ acao: 'eliminar_definitivo', id }) });
    const json = await resp.json();
    if (!resp.ok) { toast(json.erro || 'Não foi possível eliminar o ponto.', 'erro'); return; }
    toast('Ponto eliminado.', 'sucesso');
    carregarPontos();
    return;
  }
  // aprovar / recusar — espelha os botões da tabela.
  const resp = await fetch('/api/admin/pontos', { method: 'POST', body: fd({ acao, id }) });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro || 'Não foi possível concluir a ação.', 'erro'); return; }
  toast(acao === 'aprovar' ? 'Ponto aprovado.' : 'Ponto recusado.', 'sucesso');
  carregarPontos();
}

async function salvarPosicaoPontoMapa(id, lat, lng) {
  const resp = await fetch('/api/admin/pontos', {
    method: 'POST',
    body: fd({ acao: 'mover', id, lat: lat.toFixed(6), lng: lng.toFixed(6) }),
  });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro || 'Não foi possível guardar a posição.', 'erro'); carregarPontos(); return; }
  toast('Ponto atualizado!', 'sucesso');
  mapaEditor.setMarkerDraggable(`p-${id}`, false);
  carregarPontos();
}

let editorPontosAjustadoInicialmente = false;

// Redesenha os marcadores do editor a partir de pontosCache (sem novo
// pedido à API) — chamada sempre que carregarPontos() atualiza os dados.
function atualizarMarcadoresPontosEditor() {
  if (!mapaEditor) return;
  const idsAnteriores = pontoEditorMarkerIds;
  const idsAtuais = new Set();
  const contagem = document.getElementById('conta-camada-pontos');
  if (contagem) contagem.textContent = pontosCache.length;
  // Camada "Pontos" desligada no editor: tiram-se os marcadores todos.
  (camadasMapa.pontos ? pontosCache : []).forEach(p => {
    const id = `p-${p.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(p.lat);
    const lng = parseFloat(p.lng);
    if (idsAnteriores.has(id)) mapaEditor.removeMarker(id);
    mapaEditor.addMarker(id, 'ponto', lat, lng, {
      classes: p.status,
      popupHtml: popupPontoEditor(p),
      draggable: false,
      onDragEnd: (novaLat, novaLng) => salvarPosicaoPontoMapa(p.id, novaLat, novaLng),
    });
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) mapaEditor.removeMarker(id); });
  pontoEditorMarkerIds = idsAtuais;

  // Só ajusta a vista automaticamente na primeira vez que há pontos — depois
  // disso o admin pode já ter dado zoom/pan manualmente e não deve ser
  // reposicionado a cada aprovação/recusa/arrasto.
  if (!editorPontosAjustadoInicialmente && pontosCache.length) {
    editorPontosAjustadoInicialmente = true;
    mapaEditor.fitBounds(pontosCache.map(p => [parseFloat(p.lat), parseFloat(p.lng)]));
  }
}

function abrirModalPonto(p) {
  document.getElementById('ponto-id').value = p?.id || '';
  document.getElementById('ponto-acao').value = p ? 'editar' : 'criar';
  document.getElementById('ponto-nome').value = p?.nome || '';
  document.getElementById('ponto-cidade').value = p?.cidade || '';
  document.getElementById('ponto-zona').value = p?.zona || 'urbana';
  document.getElementById('ponto-lat').value = p?.lat || '';
  document.getElementById('ponto-lng').value = p?.lng || '';
  document.getElementById('modal-ponto').style.display = 'flex';
}
document.getElementById('btn-novo-ponto').addEventListener('click', () => abrirModalPonto(null));
document.getElementById('btn-fechar-ponto').addEventListener('click', () => document.getElementById('modal-ponto').style.display = 'none');
document.getElementById('form-ponto').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const criando = document.getElementById('ponto-acao').value === 'criar';
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/pontos', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('ponto-msg').textContent = json.erro; return; }
  document.getElementById('modal-ponto').style.display = 'none';
  toast(criando ? 'Ponto criado — fica pendente até ser aprovado.' : 'Ponto guardado.', 'sucesso');
  carregarPontos();
});

async function carregarParques() {
  const json = await kgApiJSON('/api/admin/parques');
  document.getElementById('tabela-parques').innerHTML = json.parques.map(p => `
    <tr><td>${esc(p.nome)}</td><td>${esc(p.cidade)}</td><td>${esc(p.vagas_ocupadas)}/${esc(p.capacidade_total)}</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--outline" data-editar='${esc(JSON.stringify(p))}'>Editar</button>
        <button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="eliminar" data-id="${esc(p.id)}">Eliminar</button></td></tr>`
  ).join('');
  document.querySelectorAll('#tabela-parques [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalParque(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-parques', '/api/admin/parques', carregarParques);
}

function abrirModalParque(p) {
  document.getElementById('parque-id').value = p?.id || '';
  document.getElementById('parque-acao').value = p ? 'editar' : 'criar';
  document.getElementById('parque-nome').value = p?.nome || '';
  document.getElementById('parque-morada').value = p?.morada || '';
  document.getElementById('parque-cidade').value = p?.cidade || '';
  document.getElementById('parque-lat').value = p?.lat || '';
  document.getElementById('parque-lng').value = p?.lng || '';
  document.getElementById('parque-capacidade').value = p?.capacidade_total || '';
  document.getElementById('parque-ocupadas').value = p?.vagas_ocupadas || 0;
  document.getElementById('modal-parque').style.display = 'flex';
}
document.getElementById('btn-novo-parque').addEventListener('click', () => abrirModalParque(null));
document.getElementById('btn-fechar-parque').addEventListener('click', () => document.getElementById('modal-parque').style.display = 'none');
document.getElementById('form-parque').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/parques', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('parque-msg').textContent = json.erro; return; }
  document.getElementById('modal-parque').style.display = 'none';
  toast('Parque guardado.', 'sucesso');
  carregarParques();
});

// ---------------------------------------------------------------------
// Editor de mapa: vista geral de condutores e passageiros
// ---------------------------------------------------------------------
// Os marcadores são os do mapa do condutor (KGMap.veiculoIconeHtml /
// veiculoPopupHtml), para o admin ler o mapa da mesma forma que quem conduz.
// A diferença está nos dados: /api/admin/mapa devolve o sistema inteiro, e
// os popups juntam o que só o admin pode ver (contactos do passageiro,
// estado do pagamento do condutor, idade da posição GPS).
const MAPA_ADMIN_ATUALIZAR_MS = 10000;
const camadasMapa = { veiculos: true, passageiros: true, pontos: true, parques: false };
let mapaAdminDados = { veiculos: [], passageiros: [], parques: [] };
let mapaAdminMarcadores = new Map(); // id do marcador -> { assinatura, marker }
let mapaAdminTimer = null;

const VEICULO_ESTADO_LABEL = {
  no_ponto: 'No ponto', na_fila: 'Na fila', em_movimento: 'A caminho',
  partiu_da_fila: 'Partiu da fila', chegou_destino: 'Chegou ao destino',
};
const RESERVA_ESTADO_LABEL = {
  pendente: 'Pedido em aberto', confirmado: 'Confirmado', a_caminho: 'Condutor a caminho',
};

function secaoMapaAtiva() {
  return document.getElementById('secao-editor-mapa').classList.contains('ativa');
}

function telefoneHtml(telefone) {
  if (!telefone) return '';
  return `<a href="tel:${esc(String(telefone).replace(/[^\d+]/g, ''))}">${esc(telefone)}</a>`;
}

function hora(valor) {
  if (!valor) return '—';
  return new Date(valor).toLocaleTimeString('pt-PT', { timeZone: 'Atlantic/Cape_Verde', hour: '2-digit', minute: '2-digit' });
}

function textoPesquisaMapa() {
  return (document.getElementById('mapa-admin-pesquisa')?.value || '').trim().toLowerCase();
}

function correspondePesquisa(campos) {
  const termo = textoPesquisaMapa();
  if (!termo) return true;
  return campos.some(c => c != null && String(c).toLowerCase().includes(termo));
}

function veiculoVisivelNoMapa(v) {
  return correspondePesquisa([v.matricula, v.condutor_nome, v.condutor_telefone, v.ponto_partida_nome, v.destino_nome, v.modelo]);
}

function passageiroVisivelNoMapa(p) {
  return correspondePesquisa([p.passageiro_nome, p.passageiro_telefone, p.ponto_partida_nome, p.destino_nome, p.veiculo_matricula, p.condutor_nome]);
}

function popupVeiculoAdmin(v) {
  const extra = [
    `<span class="vm-badge">${esc(VEICULO_ESTADO_LABEL[v.estado] || v.estado)}</span>`,
    v.ponto_partida_nome ? `<span>Rota: ${esc(v.ponto_partida_nome)}${v.destino_nome ? ' → ' + esc(v.destino_nome) : ''}</span>` : '<span>Sem ponto (circula livremente)</span>',
    `<span>Serviço: ${esc(TIPO_SERVICO_LABEL[v.tipo_servico] || v.tipo_servico)}${v.modelo ? ' · ' + esc(v.modelo) : ''}</span>`,
    `<span>Pagamento: ${v.pagamento_em_dia ? 'em dia' : '<strong class="text-red-700">em falta</strong>'} · Conta ${esc(v.condutor_status)}</span>`,
    `<span>Última posição: ${esc(hora(v.ultima_posicao_em))}${v.posicao_antiga ? ' <strong class="text-amber-700">(desatualizada)</strong>' : ''}</span>`,
  ];
  return KGMap.veiculoPopupHtml(v).replace(/<\/div>$/, `<br>${extra.join('<br>')}</div>`);
}

function popupPassageiroAdmin(p) {
  const linhas = [
    `<strong>${esc(p.passageiro_nome)}</strong>`,
    telefoneHtml(p.passageiro_telefone),
    `<span class="vm-badge vm-badge--${esc(p.estado)}">${esc(RESERVA_ESTADO_LABEL[p.estado] || p.estado)}</span> ${esc(p.tipo_viagem === 'urbano' ? 'Urbano' : 'Intermunicipal')}`,
    `<span>Partida: ${esc(p.ponto_partida_nome)}</span>`,
    p.destino_nome ? `<span>Destino: ${esc(p.destino_nome)}</span>` : '',
    `<span>${esc(p.lugares)} lugar${p.lugares > 1 ? 'es' : ''} · ${esc(Number(p.preco_final).toFixed(0))} CVE</span>`,
    `<span>${p.com_gps ? 'GPS às ' + esc(hora(p.localizacao_em)) : 'Sem GPS — mostrado no ponto de partida'}</span>`,
    p.veiculo_matricula
      ? `<span>Veículo: ${esc(p.veiculo_matricula)} · ${esc(p.condutor_nome || '')} ${telefoneHtml(p.condutor_telefone)}</span>`
      : '<span>Ainda sem condutor</span>',
    `<span>Pedido às ${esc(hora(p.criado_em))}</span>`,
  ].filter(Boolean);
  return `<div class="kg-veiculo-popup">${linhas.join('<br>')}</div>`;
}

function popupParqueAdmin(p) {
  return `<div class="kg-ponto-popup"><strong>${esc(p.nome)}</strong><br>${esc(p.morada || '')} ${esc(p.cidade)}<br>${esc(p.vagas_ocupadas)}/${esc(p.capacidade_total)} vagas ocupadas</div>`;
}

function classeParque(p) {
  const livres = p.capacidade_total - p.vagas_ocupadas;
  if (livres <= 0) return 'cheio';
  return livres / Math.max(1, p.capacidade_total) < 0.2 ? 'poucas' : 'livre';
}

// Sincroniza um grupo de marcadores com a lista desejada. Um marcador cuja
// aparência não mudou só desliza para a posição nova (como os colegas no
// mapa do condutor); se mudou (estado, lugares, popup), é recriado.
function sincronizarMarcadoresMapaAdmin(prefixo, desejados) {
  const atuais = new Set();
  desejados.forEach(d => {
    atuais.add(d.id);
    const existente = mapaAdminMarcadores.get(d.id);
    if (existente && existente.assinatura === d.assinatura) {
      mapaEditor.moveMarkerSuave(d.id, d.lat, d.lng);
      existente.lat = d.lat;
      existente.lng = d.lng;
      return;
    }
    const aberto = existente?.marker?.isPopupOpen?.();
    if (existente) mapaEditor.removeMarker(d.id);
    const marker = mapaEditor.addMarker(d.id, d.tipo, d.lat, d.lng, d.opcoes);
    if (marker) {
      mapaAdminMarcadores.set(d.id, { assinatura: d.assinatura, marker, lat: d.lat, lng: d.lng });
      if (aberto) marker.openPopup();
    } else {
      mapaAdminMarcadores.delete(d.id);
    }
  });
  [...mapaAdminMarcadores.keys()].forEach(id => {
    if (id.startsWith(prefixo) && !atuais.has(id)) {
      mapaEditor.removeMarker(id);
      mapaAdminMarcadores.delete(id);
    }
  });
}

function desenharMapaAdmin() {
  if (!mapaEditor) return;
  const veiculos = camadasMapa.veiculos ? mapaAdminDados.veiculos.filter(veiculoVisivelNoMapa) : [];
  const passageiros = camadasMapa.passageiros ? mapaAdminDados.passageiros.filter(passageiroVisivelNoMapa) : [];
  const parques = camadasMapa.parques ? mapaAdminDados.parques : [];

  sincronizarMarcadoresMapaAdmin('adm-v-', veiculos.map(v => {
    const popupHtml = popupVeiculoAdmin(v);
    const html = KGMap.veiculoIconeHtml(v);
    const classes = KGMap.veiculoEstadoClasse(v.estado);
    const badge = v.estado === 'na_fila' && v.posicao_fila ? String(v.posicao_fila) : null;
    return {
      id: `adm-v-${v.id}`, tipo: 'veiculo', lat: v.lat, lng: v.lng,
      assinatura: [classes, badge, html, popupHtml].join('|'),
      opcoes: { classes, badge, html, popupHtml, zIndexOffset: 500 },
    };
  }));
  sincronizarMarcadoresMapaAdmin('adm-p-', passageiros.map(p => {
    const popupHtml = popupPassageiroAdmin(p);
    return {
      id: `adm-p-${p.id}`, tipo: 'passageiro', lat: p.lat, lng: p.lng,
      assinatura: popupHtml,
      opcoes: { popupHtml, zIndexOffset: 400 },
    };
  }));
  sincronizarMarcadoresMapaAdmin('adm-k-', parques.filter(k => k.lat != null && k.lng != null).map(k => {
    const popupHtml = popupParqueAdmin(k);
    const classes = classeParque(k);
    return {
      id: `adm-k-${k.id}`, tipo: 'parque', lat: parseFloat(k.lat), lng: parseFloat(k.lng),
      assinatura: classes + popupHtml,
      opcoes: { classes, popupHtml },
    };
  }));

  document.getElementById('conta-camada-veiculos').textContent = mapaAdminDados.veiculos.length;
  document.getElementById('conta-camada-passageiros').textContent = mapaAdminDados.passageiros.length;
  document.getElementById('conta-camada-parques').textContent = mapaAdminDados.parques.length;
  document.getElementById('conta-camada-pontos').textContent = pontosCache.length;

  desenharListasMapaAdmin(veiculos, passageiros);
}

function focarMarcadorMapaAdmin(id) {
  const m = mapaAdminMarcadores.get(id);
  if (!m) return;
  const pos = m.marker.getLatLng();
  mapaEditor.flyTo(pos.lat, pos.lng, 17);
  setTimeout(() => m.marker.openPopup(), 600);
}

function desenharListasMapaAdmin(veiculos, passageiros) {
  const listaV = document.getElementById('lista-mapa-veiculos');
  listaV.innerHTML = veiculos.length ? veiculos.map(v => `
    <button type="button" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-2 text-left text-sm hover:bg-slate-50" data-focar-mapa="adm-v-${esc(v.id)}">
      ${KGMap.veiculoChipHtml(v)}
      <span class="min-w-0 flex-1">
        <span class="block truncate font-semibold text-slate-900">${esc(v.matricula)} · ${esc(v.condutor_nome)}</span>
        <span class="block truncate text-xs text-slate-500">${esc(VEICULO_ESTADO_LABEL[v.estado] || v.estado)}${v.posicao_fila && v.estado === 'na_fila' ? ' #' + esc(v.posicao_fila) : ''} · ${esc(v.ocupacao?.a_bordo ?? 0)}/${esc(v.ocupacao?.lugares_total ?? '')} a bordo${v.posicao_antiga && !['no_ponto', 'na_fila'].includes(v.estado) ? ' · GPS desatualizado' : ''}</span>
      </span>
    </button>`).join('') : `<p class="vm-small mb-0">${camadasMapa.veiculos ? 'Nenhum veículo com posição conhecida.' : 'Camada de veículos desligada.'}</p>`;

  const listaP = document.getElementById('lista-mapa-passageiros');
  listaP.innerHTML = passageiros.length ? passageiros.map(p => `
    <button type="button" class="block w-full rounded-xl border border-slate-200 bg-white p-2 text-left text-sm hover:bg-slate-50" data-focar-mapa="adm-p-${esc(p.id)}">
      <span class="flex items-center justify-between gap-2">
        <span class="truncate font-semibold text-slate-900">${esc(p.passageiro_nome)}</span>
        <span class="vm-badge vm-badge--${esc(p.estado)} shrink-0">${esc(RESERVA_ESTADO_LABEL[p.estado] || p.estado)}</span>
      </span>
      <span class="block truncate text-xs text-slate-500">${esc(p.ponto_partida_nome)}${p.destino_nome ? ' → ' + esc(p.destino_nome) : ''}${p.veiculo_matricula ? ' · ' + esc(p.veiculo_matricula) : ''}</span>
    </button>`).join('') : `<p class="vm-small mb-0">${camadasMapa.passageiros ? 'Nenhum passageiro com pedido em curso.' : 'Camada de passageiros desligada.'}</p>`;
}

document.getElementById('secao-editor-mapa').addEventListener('click', (ev) => {
  const btn = ev.target.closest('[data-focar-mapa]');
  if (btn) focarMarcadorMapaAdmin(btn.dataset.focarMapa);
});

document.querySelectorAll('#camadas-mapa-admin [data-camada]').forEach(input => {
  input.addEventListener('change', () => {
    camadasMapa[input.dataset.camada] = input.checked;
    if (input.dataset.camada === 'pontos') atualizarMarcadoresPontosEditor();
    desenharMapaAdmin();
  });
});

document.getElementById('mapa-admin-pesquisa').addEventListener('input', desenharMapaAdmin);

async function carregarMapaAdmin() {
  try {
    mapaAdminDados = await kgApiJSON('/api/admin/mapa');
  } catch (erro) {
    document.getElementById('mapa-admin-atualizado').textContent = 'Não foi possível atualizar o mapa.';
    return;
  }
  document.getElementById('mapa-admin-atualizado').textContent = `Atualizado às ${hora(mapaAdminDados.atualizado_em)} · a cada ${MAPA_ADMIN_ATUALIZAR_MS / 1000} s`;
  desenharMapaAdmin();
}

function todasCoordenadasMapaAdmin() {
  return [...mapaAdminMarcadores.values()].map(m => [m.lat, m.lng])
    .concat(camadasMapa.pontos ? pontosCache.map(p => [parseFloat(p.lat), parseFloat(p.lng)]) : []);
}

// A atualização periódica só corre com a secção à vista e o separador
// ativo: fora disso seria carga no servidor para um mapa que ninguém vê.
function ligarAtualizacaoMapaAdmin() {
  if (mapaAdminTimer) return;
  mapaAdminTimer = setInterval(() => {
    if (!secaoMapaAtiva()) { clearInterval(mapaAdminTimer); mapaAdminTimer = null; return; }
    if (document.visibilityState === 'visible') carregarMapaAdmin();
  }, MAPA_ADMIN_ATUALIZAR_MS);
}

function iniciarEditorMapa() {
  ligarAtualizacaoMapaAdmin();
  if (mapaEditor) { carregarPontos(); carregarMapaAdmin(); return; }
  // maxZoom 18 para ajuste preciso de coordenadas dos pontos e para chegar
  // ao nível da rua a que o condutor vê os passageiros.
  mapaEditor = KGMap.create('admin-map', { center: { lat: 14.9177, lng: -23.5092 }, zoom: 13, maxZoom: 18 });
  KGMap.ligarPausaDeAnimacoes?.(mapaEditor);
  mapaEditor.onClick((lat, lng) => {
    abrirModalPonto(null);
    document.getElementById('ponto-lat').value = lat.toFixed(6);
    document.getElementById('ponto-lng').value = lng.toFixed(6);
  });

  // Delimitação por município, ligada por omissão como no mapa do condutor.
  mapaEditor.mostrarMunicipios();
  document.getElementById('btn-mapa-admin-municipios').addEventListener('click', (ev) => {
    const botao = ev.currentTarget;
    const visiveis = mapaEditor.municipiosVisiveis();
    if (visiveis) mapaEditor.esconderMunicipios(); else mapaEditor.mostrarMunicipios();
    botao.setAttribute('aria-pressed', String(!visiveis));
    botao.classList.toggle('kg-map-btn--off', visiveis);
  });
  document.getElementById('btn-mapa-admin-tudo').addEventListener('click', () => {
    const coords = todasCoordenadasMapaAdmin();
    if (coords.length) mapaEditor.fitBounds(coords); else mapaEditor.fitCaboVerde();
  });

  document.querySelectorAll('[data-legenda-veiculo]').forEach(el => {
    el.innerHTML = KGMap.veiculoIconeHtml({ tipo: 'hiace', cor: 'branco' });
  });

  // Busca sempre os pontos aqui (não depende de já se ter visitado a secção
  // "Pontos" antes) — tarefa 2 do relatório. O enquadramento inicial espera
  // pelas duas cargas e cobre pontos, veículos e passageiros de uma vez (o
  // ajuste automático só dos pontos fica desligado para a vista não saltar
  // duas vezes). Depois disso a vista é do admin.
  editorPontosAjustadoInicialmente = true;
  Promise.allSettled([carregarPontos(), carregarMapaAdmin()]).then(() => {
    const coords = todasCoordenadasMapaAdmin();
    if (coords.length) mapaEditor.fitBounds(coords);
  });
}

const notifDestinatario = document.getElementById('notif-destinatario');
if (notifDestinatario) notifDestinatario.addEventListener('change', (ev) => {
  document.getElementById('campo-notif-id').style.display = ev.target.value === 'individual' ? 'block' : 'none';
});
const formNotificacao = document.getElementById('form-notificacao');
if (formNotificacao) formNotificacao.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  dados.set('acao', 'enviar');
  const resp = await fetch('/api/admin/notificacoes', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('notificacao-msg');
  if (!resp.ok) { msg.classList.remove('e-sucesso'); msg.textContent = json.erro || 'Erro ao enviar.'; return; }
  msg.classList.add('e-sucesso');
  msg.textContent = `Enviada para ${json.total_destinatarios} destinatário(s).`;
  ev.target.reset();
  toast('Notificação enviada.', 'sucesso');
});

async function carregarSugestoes() {
  const json = await kgApiJSON('/api/admin/sugestoes');
  const estadoBadge = { pendente: 'recusado', visto: 'pendente', implementado: 'confirmado', resolvido: 'confirmado' };
  document.getElementById('tabela-sugestoes').innerHTML = (json.sugestoes || []).map(s => `
    <tr>
      <td>${esc(s.tipo === 'sugestao' ? 'Sugestão' : 'Reclamação')}</td>
      <td>${esc(s.utilizador_nome)} (${esc(s.utilizador_tipo)})</td>
      <td>${esc(s.condutor_nome || '—')}</td>
      <td title="${esc(s.descricao)}">${esc(s.titulo)}</td>
      <td><span class="vm-badge vm-badge--${esc(estadoBadge[s.status])}">${esc(s.status)}</span></td>
      <td class="text-slate-500 tabular-nums">${esc(formatarData(s.criado_em))}</td>
      <td>
        <select class="vm-select w-auto" data-sug-status="${esc(s.id)}">
          ${['pendente', 'visto', 'implementado', 'resolvido'].map(v => `<option value="${esc(v)}" ${esc(v === s.status ? 'selected' : '')}>${esc(v)}</option>`).join('')}
        </select>
      </td>
    </tr>`
  ).join('') || '<tr><td colspan="7" class="vm-small">Sem registos.</td></tr>';

  document.querySelectorAll('[data-sug-status]').forEach(sel => {
    sel.addEventListener('change', async () => {
      await fetch('/api/admin/sugestoes', { method: 'POST', body: fd({ acao: 'atualizar_status', id: sel.dataset.sugStatus, status: sel.value }) });
      toast('Estado atualizado.', 'sucesso');
    });
  });
}

// Como as listas de utilizadores e de veículos, este filtro corre no
// servidor: os pagamentos acumulam-se a cada renovação de pacote e passam do
// limite de 200 que a API devolve — filtrar no browser só o que já veio
// esconderia pedidos sem ninguém dar por isso.
function filtroPagamentosQuery() {
  const valor = id => (document.getElementById(id)?.value || '').trim();
  const periodo = valor('filtro-pagamento-periodo');
  const params = new URLSearchParams();

  const juntar = (chave, v) => { if (v) params.set(chave, v); };
  juntar('q', valor('filtro-pagamento-texto'));
  juntar('status', valor('filtro-pagamento-status'));
  juntar('pacote', valor('filtro-pagamento-pacote'));
  juntar('tipo_servico', valor('filtro-pagamento-servico'));
  juntar('comprovativo', valor('filtro-pagamento-comprovativo'));
  juntar('validade', valor('filtro-pagamento-validade'));
  juntar('valor_min', valor('filtro-pagamento-valor-min'));
  juntar('valor_max', valor('filtro-pagamento-valor-max'));
  juntar('periodo', periodo);
  juntar('ordem', valor('filtro-pagamento-ordem'));
  // As datas só valem no modo personalizado — deixá-las na query com um
  // período nomeado escolhido daria dois filtros de data a competir.
  if (periodo === 'personalizado') {
    juntar('data_inicio', valor('filtro-pagamento-inicio'));
    juntar('data_fim', valor('filtro-pagamento-fim'));
  }

  return params;
}

// "Ordenar por" muda a vista, não restringe a lista: com só ele escolhido, a
// tabela vazia quer dizer "não há pagamentos", e não "o filtro não achou".
function filtroPagamentosRestringe() {
  return [...filtroPagamentosQuery().keys()].some(chave => chave !== 'ordem');
}

function sincronizarBotoesStatusPagamento() {
  const status = document.getElementById('filtro-pagamento-status')?.value || '';
  document.querySelectorAll('[data-filtro-pagamento-status]').forEach(btn => {
    const ativo = btn.dataset.filtroPagamentoStatus === status;
    btn.classList.toggle('vm-btn--outline', !ativo);
    btn.classList.toggle('vm-btn--primario', ativo);
  });
}

// As opções do "Pacote" vêm da API (os pacotes que têm pagamentos) e não
// dependem do filtro escolhido, por isso a lista não encolhe à medida que o
// admin restringe a tabela.
function preencherPacotesPagamento(pacotes) {
  const sel = document.getElementById('filtro-pagamento-pacote');
  if (!sel) return;
  const escolhido = sel.value;
  const ids = (pacotes || []).map(p => String(p.id));
  sel.innerHTML = '<option value="">Todos</option>'
    + (pacotes || []).map(p => `<option value="${esc(p.id)}">${esc(p.nome)} (${esc(p.duracao_dias)}d)</option>`).join('')
    // Os pagamentos anteriores aos pacotes (taxa fixa por rota) continuam na
    // lista e não pertencem a pacote nenhum — sem esta opção não havia como
    // os isolar.
    + '<option value="sem">Sem pacote (taxa por rota)</option>';
  // Se o último pagamento de um pacote saiu da lista, essa opção desaparece:
  // o filtro volta a "Todos" em vez de ficar preso numa escolha morta.
  sel.value = escolhido === 'sem' || ids.includes(escolhido) ? escolhido : '';
}

// O recibo abre no browser ("Ver recibo", num separador novo) e também se
// guarda ("Descarregar", que é o mesmo endpoint com ?descarregar=1).
//
// Só aparece em pagamentos aprovados porque é só para esses que a API o
// serve: um pagamento revertido e depois recusado mantém o ficheiro antigo em
// recibo_path, e o botão dava um 404 ao admin.
function acoesReciboPagamento(p) {
  if (p.status !== 'aprovado' || !p.recibo_path) return '';
  return `<a class="vm-btn vm-btn--sm vm-btn--outline" href="/api/admin/recibo?id=${esc(p.id)}" target="_blank">Ver recibo</a>
    <a class="vm-btn vm-btn--sm vm-btn--ghost" href="/api/admin/recibo?id=${esc(p.id)}&amp;descarregar=1">Descarregar</a>`;
}

async function carregarPagamentos() {
  sincronizarBotoesStatusPagamento();
  const query = filtroPagamentosQuery().toString();
  const json = await kgApiJSON('/api/admin/pagamentos' + (query ? `?${query}` : ''));
  preencherPacotesPagamento(json.pacotes);

  const lista = json.pagamentos || [];
  document.getElementById('tabela-pagamentos').innerHTML = lista.map(p => `
    <tr>
      <td>${esc(p.condutor_nome)}${p.condutor_status === 'pendente' ? ' <span class="vm-badge vm-badge--pendente" title="Aprovar este pagamento também ativa a conta">conta nova</span>' : ''}</td><td>${esc(p.matricula)}</td>
      <td>${p.pacote_nome ? `${esc(p.pacote_nome)} (${esc(p.pacote_duracao_dias)}d) &mdash; ${esc(TIPO_SERVICO_LABEL[p.pacote_tipo_servico] || p.pacote_tipo_servico)}` : (p.origem_nome ? `${esc(p.origem_nome)} &rarr; ${esc(p.destino_nome)}` : '—')}</td>
      <td>${p.comprovativo_path ? `<a class="vm-btn vm-btn--sm vm-btn--outline" href="/api/admin/comprovativo?id=${esc(p.id)}" target="_blank">Ver ${esc(p.comprovativo_tipo === 'pdf' ? 'PDF' : 'foto')}</a>` : '—'}</td>
      <td>${esc(p.valor_pago)} CVE</td>
      <td><span class="vm-badge vm-badge--${esc(p.status === 'aprovado' ? 'confirmado' : p.status === 'pendente' ? 'pendente' : 'recusado')}">${esc(p.status)}</span></td>
      <td class="tabular-nums">${esc(formatarData(p.data_validade))}</td>
      <td>${esc(p.aprovado_por_nome || '—')}</td>
      <td>${p.status === 'pendente' ? `
        <button class="vm-btn vm-btn--sm vm-btn--cta" data-pag-acao="aprovar" data-id="${esc(p.id)}">Aprovar</button>
        <button class="vm-btn vm-btn--sm vm-btn--perigo" data-pag-acao="recusar" data-id="${esc(p.id)}">Recusar</button>`
        : acoesReciboPagamento(p)}
        ${p.status === 'aprovado' && EH_SUPER ? `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-pag-acao="reverter" data-id="${esc(p.id)}">Reverter</button>` : ''}
      </td>
    </tr>`
  ).join('') || `<tr><td colspan="9" class="vm-small">${filtroPagamentosRestringe() ? 'Nenhum pagamento corresponde ao filtro.' : 'Sem pedidos de pagamento.'}</td></tr>`;

  const resumo = document.getElementById('filtro-pagamentos-resumo');
  const total = json.total ?? lista.length;
  if (resumo) resumo.textContent = total > lista.length
    ? `A mostrar os primeiros ${lista.length} de ${total} pagamentos — restrinja o filtro para ver os restantes.`
    : `A mostrar ${total} pagamento${total === 1 ? '' : 's'}.`;

  document.querySelectorAll('[data-pag-acao]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (btn.dataset.pagAcao === 'reverter' && !confirm('Reverter esta aprovação? O condutor perde acesso ao mapa imediatamente até um novo pagamento ser aprovado.')) return;
      const resp = await fetch('/api/admin/pagamentos', { method: 'POST', body: fd({ acao: btn.dataset.pagAcao, id: btn.dataset.id }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      const msgs = { aprovar: 'Pagamento aprovado e recibo emitido.', recusar: 'Pagamento recusado.', reverter: 'Aprovação revertida.' };
      toast(msgs[btn.dataset.pagAcao] || 'Ação concluída.', 'sucesso');
      carregarPagamentos();
    });
  });
}

// Os botões de topo são atalhos para o campo "Estado" do filtro: o select
// continua a ser o único sítio onde o estado escolhido vive.
document.querySelectorAll('[data-filtro-pagamento-status]').forEach(btn => btn.addEventListener('click', () => {
  const sel = document.getElementById('filtro-pagamento-status');
  if (sel) sel.value = btn.dataset.filtroPagamentoStatus;
  carregarPagamentos();
}));

function alternarIntervaloPagamentos() {
  const personalizado = document.getElementById('filtro-pagamento-periodo')?.value === 'personalizado';
  document.querySelectorAll('#filtro-pagamentos [data-intervalo-pagamento]').forEach(el => { el.hidden = !personalizado; });
}

const filtroPagamentos = document.getElementById('filtro-pagamentos');
if (filtroPagamentos) {
  // Escrever no campo de pesquisa não pode disparar um pedido por tecla.
  let temporizadorPagamentos;
  const recarregarPagamentos = atraso => {
    clearTimeout(temporizadorPagamentos);
    temporizadorPagamentos = setTimeout(() => { alternarIntervaloPagamentos(); carregarPagamentos(); }, atraso);
  };
  filtroPagamentos.addEventListener('input', e => recarregarPagamentos(e.target.type === 'search' ? 350 : 0));
  filtroPagamentos.addEventListener('change', () => recarregarPagamentos(0));
  // O reset do formulário só limpa os campos depois deste evento correr.
  filtroPagamentos.addEventListener('reset', () => recarregarPagamentos(0));
  filtroPagamentos.addEventListener('submit', e => e.preventDefault());
  alternarIntervaloPagamentos();
}

// A lista de pacotes chega inteira do servidor e fica aqui: são poucas
// linhas, por isso o filtro personalizado corre no browser e mexer num
// campo não custa um pedido novo.
let pacotesCache = [];

function pacotesFiltrados() {
  const texto = (document.getElementById('filtro-pacote-texto')?.value || '').trim().toLowerCase();
  const tipo = document.getElementById('filtro-pacote-tipo')?.value || '';
  const estado = document.getElementById('filtro-pacote-estado')?.value || '';
  // Campo de número vazio é "sem limite", e não zero.
  const limite = id => {
    const valor = (document.getElementById(id)?.value || '').trim();
    return valor === '' ? null : Number(valor);
  };
  const precoMin = limite('filtro-pacote-preco-min');
  const precoMax = limite('filtro-pacote-preco-max');
  const diasMin = limite('filtro-pacote-dias-min');
  const diasMax = limite('filtro-pacote-dias-max');

  return pacotesCache.filter(p => {
    if (texto && !`${p.nome} ${p.descricao || ''}`.toLowerCase().includes(texto)) return false;
    if (tipo && p.tipo_servico !== tipo) return false;
    if (estado && String(p.ativo ? 1 : 0) !== estado) return false;
    if (precoMin !== null && Number(p.preco) < precoMin) return false;
    if (precoMax !== null && Number(p.preco) > precoMax) return false;
    if (diasMin !== null && Number(p.duracao_dias) < diasMin) return false;
    if (diasMax !== null && Number(p.duracao_dias) > diasMax) return false;
    return true;
  });
}

function desenharPacotes() {
  const corpo = document.getElementById('tabela-pacotes');
  if (!corpo) return;
  const lista = pacotesFiltrados();

  corpo.innerHTML = lista.map(p => `
    <tr>
      <td>${esc(p.nome)}</td>
      <td>${esc(TIPO_SERVICO_LABEL[p.tipo_servico] || p.tipo_servico)}</td>
      <td>${esc(p.descricao || '—')}</td>
      <td class="tabular-nums">${esc(p.preco)}</td>
      <td class="tabular-nums">${esc(p.duracao_dias)}</td>
      <td><span class="vm-badge vm-badge--${esc(p.ativo ? 'confirmado' : 'recusado')}">${esc(p.ativo ? 'Ativo' : 'Inativo')}</span></td>
      <td class="flex flex-wrap gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--outline" data-editar-pacote='${esc(JSON.stringify(p))}'>Editar</button>
        <button class="vm-btn vm-btn--sm ${esc(p.ativo ? 'vm-btn--perigo' : 'vm-btn--cta')}" data-toggle-pacote="${esc(p.id)}" data-ativo="${esc(p.ativo ? 1 : 0)}">${esc(p.ativo ? 'Desativar' : 'Ativar')}</button>
        <button class="vm-btn vm-btn--sm vm-btn--ghost" data-eliminar-pacote="${esc(p.id)}" data-nome="${esc(p.nome)}">Eliminar</button>
      </td>
    </tr>`
  ).join('') || `<tr><td colspan="7" class="vm-small">${pacotesCache.length ? 'Nenhum pacote corresponde ao filtro.' : 'Sem pacotes definidos.'}</td></tr>`;

  const resumo = document.getElementById('filtro-pacotes-resumo');
  if (resumo) resumo.textContent = pacotesCache.length
    ? `A mostrar ${lista.length} de ${pacotesCache.length} pacotes.`
    : '';

  corpo.querySelectorAll('[data-editar-pacote]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalPacote(JSON.parse(btn.dataset.editarPacote)));
  });

  corpo.querySelectorAll('[data-toggle-pacote]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.togglePacote;
      const ativo = btn.dataset.ativo === '1';
      // Desativar tem ação própria; ativar volta a guardar o pacote como está
      // na tabela, só que com ativo = 1.
      const pacote = pacotesCache.find(p => String(p.id) === String(id));
      if (!ativo && !pacote) return;
      const resp = await fetch('/api/admin/precos', { method: 'POST', body: fd(ativo
        ? { acao: 'remover_pacote', id }
        : {
          acao: 'guardar_pacote', id, ativo: 1,
          nome: pacote.nome, tipo_servico: pacote.tipo_servico, descricao: pacote.descricao || '',
          preco: pacote.preco, duracao_dias: pacote.duracao_dias,
        }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast(ativo ? 'Pacote desativado.' : 'Pacote ativado.', 'sucesso');
      carregarPrecos();
    });
  });

  corpo.querySelectorAll('[data-eliminar-pacote]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm(`Eliminar o pacote "${btn.dataset.nome}" definitivamente? Só é possível enquanto nenhum pagamento o tiver usado — nesse caso, desative-o.`)) return;
      const resp = await fetch('/api/admin/precos', { method: 'POST', body: fd({ acao: 'eliminar_pacote', id: btn.dataset.eliminarPacote }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast('Pacote eliminado.', 'sucesso');
      carregarPrecos();
    });
  });
}

const filtroPacotes = document.getElementById('filtro-pacotes');
if (filtroPacotes) {
  filtroPacotes.addEventListener('input', desenharPacotes);
  filtroPacotes.addEventListener('change', desenharPacotes);
  // O reset do formulário só limpa os campos depois deste evento correr.
  filtroPacotes.addEventListener('reset', () => setTimeout(desenharPacotes, 0));
}

function abrirModalPacote(p) {
  document.getElementById('pacote-editar-id').value = p.id;
  document.getElementById('pacote-editar-nome').value = p.nome;
  document.getElementById('pacote-editar-tipo').value = p.tipo_servico;
  document.getElementById('pacote-editar-descricao').value = p.descricao || '';
  document.getElementById('pacote-editar-preco').value = p.preco;
  document.getElementById('pacote-editar-duracao').value = p.duracao_dias;
  document.getElementById('pacote-editar-estado').value = p.ativo ? '1' : '0';
  document.getElementById('pacote-editar-msg').textContent = '';
  document.getElementById('modal-pacote').style.display = 'flex';
}
const btnFecharPacote = document.getElementById('btn-fechar-pacote');
if (btnFecharPacote) btnFecharPacote.addEventListener('click', () => document.getElementById('modal-pacote').style.display = 'none');
const formPacote = document.getElementById('form-pacote');
if (formPacote) formPacote.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const resp = await fetch('/api/admin/precos', { method: 'POST', body: fd({
    acao: 'guardar_pacote',
    id: document.getElementById('pacote-editar-id').value,
    nome: document.getElementById('pacote-editar-nome').value,
    tipo_servico: document.getElementById('pacote-editar-tipo').value,
    descricao: document.getElementById('pacote-editar-descricao').value,
    preco: document.getElementById('pacote-editar-preco').value,
    duracao_dias: document.getElementById('pacote-editar-duracao').value,
    ativo: document.getElementById('pacote-editar-estado').value,
  }) });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('pacote-editar-msg').textContent = json.erro; return; }
  document.getElementById('modal-pacote').style.display = 'none';
  toast('Pacote atualizado.', 'sucesso');
  carregarPrecos();
});

async function carregarPrecos() {
  const json = await kgApiJSON('/api/admin/precos');

  pacotesCache = json.pacotes || [];
  desenharPacotes();

  document.getElementById('precos-km').innerHTML = ['urbana', 'intermunicipal'].map(zona => {
    const atual = json.precos_km.find(p => p.zona === zona);
    return `<div class="vm-field mb-0"><label class="vm-label">${esc(zona === 'urbana' ? 'Dentro de cidade' : 'Fora de cidade')} (CVE/km)</label>
      <div class="flex flex-wrap gap-2"><input class="vm-input min-w-0 flex-1" type="number" step="0.5" min="0" value="${esc(atual ? atual.preco_por_km_cve : 0)}" data-zona="${esc(zona)}">
      <button class="vm-btn vm-btn--primario vm-btn--sm" data-salvar-km="${esc(zona)}">Guardar</button></div></div>`;
  }).join('');

  document.querySelectorAll('[data-salvar-km]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const zona = btn.dataset.salvarKm;
      const valor = document.querySelector(`[data-zona="${zona}"]`).value;
      await fetch('/api/admin/precos', { method: 'POST', body: fd({ acao: 'atualizar_km', zona, preco_por_km_cve: valor }) });
      toast('Preço por km atualizado.', 'sucesso');
      carregarPrecos();
    });
  });

  const configMeta = {
    valor_minimo: 'Valor mínimo da viagem (CVE)',
    valor_maximo: 'Valor máximo da viagem (CVE)',
    taxa_operacao_rota: 'Taxa de operação por rota, ida e volta (CVE)',
  };
  document.getElementById('precos-config').innerHTML = Object.entries(configMeta).map(([chave, label]) => `
    <div class="vm-field mb-0"><label class="vm-label">${esc(label)}</label>
      <div class="flex flex-wrap gap-2"><input class="vm-input min-w-0 flex-1" type="number" step="1" min="0" value="${esc(json.config[chave] ?? 0)}" data-config="${esc(chave)}">
      <button class="vm-btn vm-btn--primario vm-btn--sm" data-salvar-config="${esc(chave)}">Guardar</button></div></div>`
  ).join('');
  document.querySelectorAll('[data-salvar-config]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const chave = btn.dataset.salvarConfig;
      const valor = document.querySelector(`[data-config="${chave}"]`).value;
      await fetch('/api/admin/precos', { method: 'POST', body: fd({ acao: 'atualizar_config', chave, valor }) });
      toast('Configuração atualizada.', 'sucesso');
    });
  });

  const selOrigem = document.getElementById('rota-origem');
  const selDestino = document.getElementById('rota-destino');
  selOrigem.innerHTML = '';
  selDestino.innerHTML = '';
  const pontosJson = await kgApiJSON('/api/admin/pontos');
  pontosJson.pontos.forEach(p => { selOrigem.add(new Option(p.nome, p.id)); selDestino.add(new Option(p.nome, p.id)); });

  document.getElementById('tabela-rotas').innerHTML = json.precos_rotas.map(r => `
    <tr><td>${esc(r.origem_nome)}</td><td>${esc(r.destino_nome)}</td><td>${esc(r.distancia_km != null ? r.distancia_km + ' km' : '—')}</td><td>${esc(r.preco_fixo_cve)} CVE</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--perigo" data-eliminar-rota="${esc(r.id)}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="5" class="vm-small">Sem rotas fixas definidas.</td></tr>';
  document.querySelectorAll('[data-eliminar-rota]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/precos', { method: 'POST', body: fd({ acao: 'eliminar_rota', id: btn.dataset.eliminarRota }) });
      toast('Rota eliminada.', 'sucesso');
      carregarPrecos();
    });
  });
}

const formNovoPacote = document.getElementById('form-novo-pacote');
if (formNovoPacote) formNovoPacote.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const resp = await fetch('/api/admin/precos', { method: 'POST', body: fd({
    acao: 'guardar_pacote',
    nome: document.getElementById('pacote-nome').value,
    tipo_servico: document.getElementById('pacote-tipo-servico').value,
    descricao: document.getElementById('pacote-descricao').value,
    preco: document.getElementById('pacote-preco').value,
    duracao_dias: document.getElementById('pacote-duracao').value,
  }) });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro, 'erro'); return; }
  toast('Pacote criado.', 'sucesso');
  formNovoPacote.reset();
  carregarPrecos();
});

const formNovaRota = document.getElementById('form-nova-rota');
if (formNovaRota) formNovaRota.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const resp = await fetch('/api/admin/precos', { method: 'POST', body: fd({
    acao: 'definir_rota',
    ponto_origem_id: document.getElementById('rota-origem').value,
    ponto_destino_id: document.getElementById('rota-destino').value,
    preco_fixo_cve: document.getElementById('rota-preco').value,
  }) });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro, 'erro'); return; }
  toast('Rota definida.', 'sucesso');
  carregarPrecos();
});

async function carregarLimitesCidades() {
  const json = await kgApiJSON('/api/admin/limites_cidades');
  document.getElementById('tabela-limites-cidades').innerHTML = json.limites.map(l => `
    <tr><td>${esc(l.cidade)}</td><td>${esc(parseFloat(l.lat).toFixed(4))}, ${esc(parseFloat(l.lng).toFixed(4))}</td><td>${esc(l.raio_km)}</td>
    <td><button class="vm-btn vm-btn--sm vm-btn--outline" data-editar='${esc(JSON.stringify(l))}'>Editar</button>
        <button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="eliminar" data-id="${esc(l.id)}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="4" class="vm-small">Sem limites de cidade definidos.</td></tr>';
  document.querySelectorAll('#tabela-limites-cidades [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalLimite(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-limites-cidades', '/api/admin/limites_cidades', carregarLimitesCidades);
}

function abrirModalLimite(l) {
  document.getElementById('limite-id').value = l?.id || '';
  document.getElementById('limite-acao').value = l ? 'editar' : 'criar';
  document.getElementById('limite-cidade').value = l?.cidade || '';
  document.getElementById('limite-lat').value = l?.lat || '';
  document.getElementById('limite-lng').value = l?.lng || '';
  document.getElementById('limite-raio').value = l?.raio_km || '';
  document.getElementById('modal-limite-cidade').style.display = 'flex';
}
const btnNovoLimite = document.getElementById('btn-novo-limite');
if (btnNovoLimite) btnNovoLimite.addEventListener('click', () => abrirModalLimite(null));
const btnFecharLimite = document.getElementById('btn-fechar-limite');
if (btnFecharLimite) btnFecharLimite.addEventListener('click', () => document.getElementById('modal-limite-cidade').style.display = 'none');
const formLimiteCidade = document.getElementById('form-limite-cidade');
if (formLimiteCidade) formLimiteCidade.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/limites_cidades', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('limite-msg').textContent = json.erro; return; }
  document.getElementById('modal-limite-cidade').style.display = 'none';
  toast('Limite de cidade guardado.', 'sucesso');
  carregarLimitesCidades();
});

async function carregarAdmins() {
  const json = await kgApiJSON('/api/admin/administradores');
  document.getElementById('tabela-admins').innerHTML = json.administradores.map(a => `
    <tr><td>${esc(a.nome)}</td><td>${esc(a.email)}</td><td>${esc(a.nivel)}</td>
    <td>${esc(a.ativo ? 'Ativo' : 'Inativo')}${esc(a.senha_temporaria ? ' · senha temporária' : '')}</td>
    <td>${a.ativo ? `<button class="vm-btn vm-btn--sm vm-btn--perigo" data-acao="desativar" data-id="${esc(a.id)}">Desativar</button>` : `<button class="vm-btn vm-btn--sm vm-btn--cta" data-acao="reativar" data-id="${esc(a.id)}">Reativar</button>`}
    <button class="vm-btn vm-btn--sm vm-btn--ghost" data-acao="eliminar" data-id="${esc(a.id)}">Eliminar</button></td></tr>`
  ).join('');
  ligarAcoesTabela('tabela-admins', '/api/admin/administradores', carregarAdmins);
}
const formNovoAdmin = document.getElementById('form-novo-admin');
if (formNovoAdmin) formNovoAdmin.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  dados.set('acao', 'criar');
  const resp = await fetch('/api/admin/administradores', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro, 'erro'); return; }
  document.getElementById('senha-admin-valor').value = json.senha_temporaria;
  document.getElementById('modal-senha-admin').style.display = 'flex';
  ev.target.reset();
  carregarAdmins();
});

document.getElementById('btn-fechar-senha-admin').addEventListener('click', () => {
  document.getElementById('modal-senha-admin').style.display = 'none';
});
document.getElementById('btn-copiar-senha-admin').addEventListener('click', async () => {
  const campo = document.getElementById('senha-admin-valor');
  await navigator.clipboard.writeText(campo.value);
  toast('Senha copiada.', 'sucesso');
});

async function carregarLogs() {
  const json = await kgApiJSON('/api/admin/logs');
  document.getElementById('tabela-logs').innerHTML = json.logs.map(l => `
    <tr><td>${esc(l.admin_nome || '—')}</td><td>${esc(rotularAcao(l.acao))}</td><td class="text-slate-500">${esc(rotularEntidade(l.entidade, l.entidade_id))}</td><td class="text-slate-500 tabular-nums">${esc(formatarData(l.criado_em))}</td></tr>`
  ).join('');
}

async function carregarSos() {
  const json = await kgApiJSON('/api/admin/sos');
  document.getElementById('tabela-sos').innerHTML = json.alarmes.map(a => `
    <tr><td>${esc(a.utilizador_nome)} (${esc(a.telefone)})</td><td>${esc(a.tipo_utilizador)}</td>
    <td>${esc(parseFloat(a.lat).toFixed(5))}, ${esc(parseFloat(a.lng).toFixed(5))}</td>
    <td><span class="vm-badge vm-badge--${esc(a.estado === 'pendente' ? 'recusado' : a.estado === 'em_curso' ? 'pendente' : 'confirmado')}">${esc(a.estado)}</span></td>
    <td class="text-slate-500 tabular-nums">${esc(formatarData(a.criado_em))}</td>
    <td>
      ${a.estado === 'pendente' ? `<button class="vm-btn vm-btn--sm vm-btn--outline" data-sos-estado="em_curso" data-id="${esc(a.id)}">Em curso</button>` : ''}
      ${a.estado !== 'resolvido' ? `<button class="vm-btn vm-btn--sm vm-btn--cta" data-sos-estado="resolvido" data-id="${esc(a.id)}">Resolvido</button>` : ''}
    </td></tr>`
  ).join('') || '<tr><td colspan="6" class="vm-small">Sem alarmes registados.</td></tr>';

  document.querySelectorAll('[data-sos-estado]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/sos', { method: 'POST', body: fd({ id: btn.dataset.id, estado: btn.dataset.sosEstado }) });
      carregarSos();
    });
  });
}

async function carregarNotificacoesAdmin() {
  const json = await kgApiJSON('/api/admin/notificacoes');
  const badge = document.getElementById('badge-notificacoes-admin');
  if (json.nao_lidas > 0) { badge.style.display = 'inline'; badge.textContent = json.nao_lidas; } else { badge.style.display = 'none'; }

  document.getElementById('lista-notificacoes-admin').innerHTML = (json.recebidas || []).map(n => `
    <div class="vm-card p-4 ${esc(n.lida ? 'opacity-60' : '')}">
      <div class="flex flex-wrap items-center justify-between gap-2"><strong>${esc(n.titulo)}</strong><span class="vm-badge vm-badge--${esc(n.tipo === 'urgente' ? 'recusado' : n.tipo === 'alerta' ? 'pendente' : 'confirmado')}">${esc(n.tipo)}</span></div>
      <p class="vm-small">${esc(n.mensagem)}</p>
      <p class="vm-small mt-1 text-xs text-slate-400">${esc(formatarData(n.criado_em))}</p>
      ${!n.lida ? `<button class="vm-btn vm-btn--sm vm-btn--outline" data-marcar-lida-admin="${esc(n.id)}">Marcar como lida</button>` : ''}
    </div>`
  ).join('') || '<p class="vm-small">Sem notificações.</p>';

  document.querySelectorAll('[data-marcar-lida-admin]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/notificacoes', { method: 'POST', body: fd({ acao: 'marcar_lida', id: btn.dataset.marcarLidaAdmin }) });
      carregarNotificacoesAdmin();
    });
  });
}
document.getElementById('btn-notificacoes-admin').addEventListener('click', () => {
  document.getElementById('modal-notificacoes-admin').style.display = 'flex';
  carregarNotificacoesAdmin();
});
document.getElementById('btn-fechar-notificacoes-admin').addEventListener('click', () => {
  document.getElementById('modal-notificacoes-admin').style.display = 'none';
});

carregarDashboard();
carregarNotificacoesAdmin();
setInterval(carregarNotificacoesAdmin, 15000);
setInterval(() => { if (document.getElementById('secao-sos').classList.contains('ativa')) carregarSos(); }, 8000);
</script>
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
