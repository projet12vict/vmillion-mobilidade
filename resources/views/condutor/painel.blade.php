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
<title>Painel do Condutor — V-MILLION</title>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
{{-- Marcadores, controlos e painéis do mapa continuam no CSS legado: é folha
     independente (declara as suas próprias variáveis) e nada tem que ver com
     o tema. --}}
<link rel="stylesheet" href="@kgAsset('assets/css/kg-map-markers.css')">
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="h-dvh overflow-hidden bg-slate-100 font-sans text-slate-700 antialiased">

{{-- O mapa ocupa o ecrã todo por baixo da barra de topo, em qualquer largura.
     O que antes era a barra lateral passou a menu em popup (#menu-condutor),
     aberto pelo separador encostado à esquerda do mapa. --}}
<div class="flex h-dvh flex-col">

  {{-- ============================ BARRA DE TOPO ============================ --}}
  <header class="vm-barra px-seguro [--px-seguro:0.75rem] sm:[--px-seguro:1.25rem] relative z-30 flex items-center justify-between gap-2 bg-brand-900 bg-linear-135 from-brand-950 via-brand-800 to-brand-700 shadow-lg shadow-brand-950/20">
    <div class="flex min-w-0 items-center gap-2.5 text-white">
      <x-logo class="shadow-lg shadow-brand-950/25" />
      <span class="truncate text-base font-bold tracking-tight sm:text-lg">
        V-MILLION <span class="hidden text-gold-400 sm:inline">Condutor</span>
      </span>
      {{-- O @if fica em linha própria de propósito: colado a uma palavra
           ("Condutor@if"), o Blade não reconhece a diretiva e deixa passar um
           @endif órfão. --}}
      @if ($proprietarioAssociado)
        <span class="vm-badge vm-badge--confirmado hidden sm:inline-flex">Proprietário</span>
      @endif
    </div>

    <div class="flex items-center gap-1 sm:gap-2">
      <span class="hidden text-sm text-blue-100/90 lg:inline">{{ $condutor->nome }}</span>
      <button class="vm-btn vm-btn--sm vm-btn--claro relative px-3" id="btn-notificacoes" type="button">
        <span class="sr-only">Notificações</span>
        <x-icone nome="sino" tamanho="h-4 w-4" />
        <span id="badge-notificacoes" style="display:none;"
              class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-[0.65rem] font-bold leading-none text-white"></span>
      </button>
      <button class="vm-btn vm-btn--sm vm-btn--claro px-3" id="btn-sons" type="button" title="Preferências de som">
        <span class="sr-only">Preferências de som</span>
        <x-icone nome="som" tamanho="h-4 w-4" />
      </button>
      <button class="vm-btn vm-btn--sm vm-btn--claro" id="btn-sugestao" type="button">Sugestão</button>
      <a href="/logout" class="vm-btn vm-btn--sm vm-btn--claro">Sair</a>
    </div>
  </header>

  {{-- =============================== MAPA =============================== --}}
  <div class="relative min-h-0 flex-1">
    {{-- Separador do menu: a meio da altura, encostado à esquerda — o canto
         superior esquerdo é da navegação guiada/município, o direito do zoom
         e dos controlos, e o inferior do aviso de descida e do SOS. --}}
    <button type="button" id="btn-menu-condutor"
            class="absolute left-0 top-1/2 z-20 flex -translate-y-1/2 flex-col items-center gap-1 rounded-r-2xl bg-brand-900 px-2 py-3 text-white shadow-lg shadow-brand-950/30 transition hover:bg-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-400"
            aria-controls="menu-condutor" aria-expanded="false" aria-label="Abrir menu">
      <x-icone nome="menu" />
      <span class="text-[0.65rem] font-semibold uppercase tracking-wide [writing-mode:vertical-rl] rotate-180">Menu</span>
    </button>
    @if ($mostrarMapa)
    <div id="map" class="absolute inset-0"></div>
    {{-- Os dois cartões do canto superior esquerdo empilhados: a navegação
         guiada aparece e desaparece, e a pastilha do município tem de descer
         quando ela aparece em vez de ficar por baixo dela (no telemóvel o
         painel de navegação ocupa a largura toda). --}}
    <div class="kg-map-topo">
      <div class="kg-nav-panel" id="nav-panel" style="display:none;">
        <button type="button" class="kg-nav-panel__fechar" id="btn-nav-fechar" aria-label="Fechar navegação"><x-icone nome="fechar" tamanho="h-4 w-4" /></button>
        <div class="kg-nav-panel__titulo"><span id="nav-icone" class="inline-flex"><x-icone nome="bussola" tamanho="h-4 w-4" /></span> <span id="nav-titulo"></span></div>
        <div class="kg-nav-panel__info" id="nav-info"></div>
      </div>
      {{-- Ao nível da rua (zoom 17, o da condução) os nomes dos concelhos ficam
           todos fora do ecrã — é esta pastilha que diz ao condutor em que
           município está a conduzir. --}}
      <div class="kg-municipio-atual" id="municipio-atual" style="display:none;"></div>
    </div>
    <div class="kg-map-controls">
      <button type="button" class="kg-map-btn" id="btn-recentrar" title="A minha localização" aria-label="Recentrar no meu GPS"><x-icone nome="localizar" /></button>
      {{-- Delimitação do país por concelho (os 22 de docs/Municipio.pdf). Fica
           ligada por omissão, mas com botão para desligar: em cima de uma
           fronteira, a linha tracejada corre ao lado da rota e há condutores
           que preferem o mapa limpo. --}}
      <button type="button" class="kg-map-btn" id="btn-municipios" title="Limites dos municípios" aria-label="Mostrar ou esconder os limites dos municípios" aria-pressed="true"><x-icone nome="mapa" /></button>
    </div>
    {{-- Aviso de pedido novo: por cima de tudo o que está no topo do mapa (a
         navegação guiada e os controlos), porque é o que o condutor não pode
         deixar passar. Fica até ele carregar em "Ver" ou fechar, ou até os
         pedidos saírem da lista (reclamados por outro condutor). --}}
    <div id="alerta-pedido" role="alert" aria-live="assertive" hidden
         class="absolute inset-x-3 top-3 z-25 mx-auto flex max-w-md items-center gap-3 rounded-2xl border-l-4 border-gold-400 bg-brand-900 px-4 py-3 text-white shadow-2xl shadow-brand-950/40">
      <span class="grid h-10 w-10 shrink-0 animate-pulse place-items-center rounded-xl bg-gold-400 text-brand-950"><x-icone nome="sino" tamanho="h-5 w-5" /></span>
      <div class="min-w-0 flex-1">
        <p class="font-bold leading-tight" id="alerta-pedido-titulo">Novo pedido</p>
        <p class="truncate text-sm text-blue-100/90" id="alerta-pedido-texto"></p>
      </div>
      <button type="button" class="vm-btn vm-btn--cta vm-btn--sm" id="alerta-pedido-ver">Ver</button>
      <button type="button" class="vm-btn vm-btn--sm vm-btn--claro px-2" id="alerta-pedido-fechar" aria-label="Fechar aviso"><x-icone nome="fechar" tamanho="h-4 w-4" /></button>
    </div>
    <div class="kg-map-aviso" id="aviso-descida" style="display:none;">
      <div class="kg-map-aviso__titulo"><x-icone nome="sino" tamanho="h-4 w-4" /> Pontos de descida próximos</div>
      <div id="aviso-descida-lista"></div>
    </div>
    @else
    <div class="vm-card my-5 ml-16 mr-5">
      <h3 class="vm-h3"><x-icone nome="pagamento" class="vm-h3-icone" /> Pagamento pendente</h3>
      <p class="vm-small">O mapa e a lista de passageiros ficam disponíveis depois de o pagamento (pacote diário/semanal/mensal/anual) ser aprovado pelo administrador. Veja o estado em "Pagamento", no menu.</p>
      <button class="vm-btn vm-btn--primario vm-btn--sm mt-3" id="btn-abrir-menu-pagamento" type="button">Abrir menu</button>
    </div>
    @endif
    {{-- Fora do gate de pagamento de propósito: o SOS é segurança, não é uma
         funcionalidade paga. O condutor com o mapa bloqueado continua a poder
         pedir socorro (a rota /api/sos também não exige pagamento). --}}
    <button type="button" class="kg-map-sos kg-map-sos--fixo" id="btn-sos">SOS</button>
  </div>

  </div>

  {{-- ================================ MENU ================================ --}}
  {{-- Popup a deslizar da esquerda por cima do mapa. z-900: acima do SOS e dos
       controlos do mapa, abaixo dos modais (z-1000), que são abertos a partir
       dos cartões do menu e têm de lhe ficar por cima. --}}
  <div id="menu-condutor" class="fixed inset-0 z-900 invisible" aria-hidden="true">
    <div id="menu-condutor-fundo" class="absolute inset-0 bg-slate-900/50 opacity-0 backdrop-blur-sm transition-opacity duration-200"></div>
    <aside role="dialog" aria-modal="true" aria-labelledby="menu-condutor-titulo" tabindex="-1"
           class="absolute inset-y-0 left-0 flex w-[min(26rem,calc(100vw-2.5rem))] -translate-x-full flex-col bg-slate-100 shadow-2xl transition-transform duration-200 ease-out">
      <div class="flex items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-3" style="padding-top: max(0.75rem, env(safe-area-inset-top)); padding-left: max(1rem, env(safe-area-inset-left));">
        <h2 id="menu-condutor-titulo" class="text-base font-bold text-slate-800">Menu</h2>
        <button type="button" class="vm-btn vm-btn--sm vm-btn--ghost px-2" id="btn-menu-fechar" aria-label="Fechar menu"><x-icone nome="fechar" /></button>
      </div>
      <div id="menu-condutor-conteudo" class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-4 pb-seguro [--pb-seguro:1rem]" style="padding-left: max(1rem, env(safe-area-inset-left));">
    <x-menu-secao titulo="Os meus veículos" icone="veiculo">
      <div id="lista-meus-veiculos"></div>
      <button class="vm-btn vm-btn--outline vm-btn--full vm-btn--sm mt-2" id="btn-novo-veiculo" type="button">+ Adicionar veículo</button>
    </x-menu-secao>

    <x-menu-secao titulo="Pagamento" icone="pagamento">
      <div id="card-pagamento"><p class="vm-small">A verificar estado...</p></div>
    </x-menu-secao>

    @if ($mostrarMapa)
    <x-menu-secao titulo="Passageiros urbanos" icone="cidade">
      <p class="vm-small">Só pedidos urbanos em aberto (sem veículo escolhido ainda) — de qualquer condutor aprovado, esteja ou não num ponto. Pedidos intermunicipais e urbanos já associados a um dos seus veículos aparecem em "Passageiros", mais abaixo. Precisa de ter um veículo selecionado abaixo para poder "Ir buscar".</p>
      <div id="lista-passageiros-urbanos" class="mt-3"><p class="vm-small">Nenhum pedido em aberto neste momento.</p></div>
    </x-menu-secao>

    <x-menu-secao titulo="Veículo ativo" id="card-veiculo-ativo" style="display:none;">
      {{-- O ícone do título é o do próprio veículo selecionado (preenchido em
           atualizarIconeVeiculoAtivo); a bandeira genérica só fica enquanto
           nenhum veículo estiver escolhido. --}}
      <x-slot:iconeSlot><span id="icone-veiculo-ativo" class="inline-flex"><x-icone nome="bandeira" /></span></x-slot:iconeSlot>
      {{-- Ponto de partida, destino e fila são do modelo intermunicipal. Um
           veículo só urbano (o táxi, tipicamente) não tem nada disto — a
           regra é do servidor: em PassageiroController::urbanos, 'urbano' e
           'ambos' sem ponto circulam livremente e recebem pedidos urbanos.
           Mostrar-lhe estes campos não era só inútil: ficavam preenchidos com
           o ponto do veículo anterior e um "Guardar" ancorava o táxi a um
           ponto que não é dele (passando-o a intermunicipal). --}}
      <div id="bloco-ponto-condutor">
        <div class="vm-field">
          <label class="vm-label">Ponto de partida</label>
          <select class="vm-select" id="sel-ponto-condutor"></select>
        </div>
        <div class="vm-field">
          <label class="vm-label">Destino</label>
          <select class="vm-select" id="sel-destino-condutor"></select>
        </div>
        <button class="vm-btn vm-btn--primario vm-btn--sm" id="btn-guardar-ponto" type="button">Guardar</button>
        {{-- Ocupa o lugar do botão enquanto o ponto está trancado (24 h depois
             de guardado, ver Condutor\VeiculoController::definirPonto): sem
             uma linha a dizer porquê, o botão parecia ter desaparecido por
             avaria. --}}
        <p class="vm-small" id="aviso-ponto-bloqueado" style="display:none;"></p>

        <div class="mt-4 flex flex-wrap gap-2">
          <button class="vm-btn vm-btn--cta vm-btn--sm" id="btn-entrar-fila" type="button">Entrar na fila</button>
          <button class="vm-btn vm-btn--perigo vm-btn--sm" id="btn-sair-fila" type="button">Sair da fila</button>
        </div>
      </div>

      <div id="bloco-urbano-condutor" style="display:none;">
        <p class="vm-small">Veículo urbano: circula livremente, sem ponto de partida nem fila. Os pedidos chegam a "Passageiros urbanos" e recolhe-os com "Ir buscar"; quem já vai a bordo fica em "Passageiros".</p>
        <button class="vm-btn vm-btn--outline vm-btn--sm mt-3" id="btn-ver-pedidos-urbanos" type="button">Ver pedidos urbanos</button>
      </div>

      <p class="vm-small mt-2" id="estado-veiculo-txt"></p>
    </x-menu-secao>

    <x-menu-secao titulo="Pedidos do ponto" icone="estrada">
      <p class="vm-small">Passageiros que escolheram o seu ponto de partida sem escolher carro — o pedido é de todos os condutores deste ponto e fica de quem carregar primeiro em "Ir buscar". Quem escolheu um lugar concreto num dos seus veículos aparece só em "Passageiros", mais abaixo.</p>
      <div id="lista-passageiros-ponto" class="mt-3"><p class="vm-small">Selecione um veículo ativo.</p></div>
    </x-menu-secao>

    <x-menu-secao titulo="Passageiros" icone="utilizadores">
      <div id="lista-passageiros"><p class="vm-small">Selecione um veículo ativo.</p></div>
    </x-menu-secao>

    <x-menu-secao titulo="Comunicação" icone="chat">
      <div id="fila-chamadas" class="mb-2 flex flex-wrap gap-1"></div>
      <div id="chat-mensagens-condutor" class="mb-2 max-h-56 space-y-1.5 overflow-y-auto overscroll-contain rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200"></div>
      <form id="form-chat-condutor">
        <select class="vm-select" id="chat-responder-a">
          <option value="">Autofalante (todos os passageiros)</option>
        </select>
        <div class="mt-1.5 flex flex-wrap gap-2">
          <input class="vm-input min-w-0 flex-1" id="chat-input-condutor" placeholder="Escreva uma mensagem..." maxlength="500">
          <button class="vm-btn vm-btn--cta vm-btn--sm" type="submit">Enviar</button>
        </div>
      </form>
    </x-menu-secao>

    <x-menu-secao titulo="Outros condutores na rota" icone="condutor">
      <div id="lista-outros-condutores"><p class="vm-small">—</p></div>
    </x-menu-secao>
    @endif
      </div>
    </aside>
  </div>


<!-- Modal novo veículo -->
<div class="vm-modal-overlay" id="modal-veiculo" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Registar veículo</h3>
    <form id="form-veiculo">
      <div class="vm-field"><label class="vm-label">Matrícula</label><input class="vm-input" name="matricula" required></div>
      <div class="vm-field">
        <label class="vm-label">Tipo</label>
        <select class="vm-select" name="tipo" id="veiculo-tipo"><option value="hiace">Hiace</option><option value="taxi">Táxi</option><option value="autocarro">Autocarro</option></select>
      </div>
      <div class="vm-field">
        <label class="vm-label">Tipo de serviço</label>
        <select class="vm-select" name="tipo_servico" id="veiculo-tipo-servico">
          <option value="ambos">Urbano + Intermunicipal</option>
          <option value="urbano">Só urbano (dentro da cidade)</option>
          <option value="intermunicipal">Só intermunicipal (entre cidades)</option>
        </select>
      </div>
      <div class="vm-field" id="campo-rota-fixa" style="display:none;">
        <label class="vm-label">Rota fixa (opcional)</label>
        <select class="vm-select" name="rota_fixa_id" id="veiculo-rota-fixa"><option value="">Nenhuma (preço por km)</option></select>
      </div>
      <div class="vm-field"><label class="vm-label">Cor</label><input class="vm-input" name="cor" required></div>
      <div class="vm-field"><label class="vm-label">Modelo</label><input class="vm-input" name="modelo" required></div>
      <div class="vm-field">
        <label class="vm-label" for="veiculo-lugares">Lugares para passageiros</label>
        <input class="vm-input" type="number" name="lugares" id="veiculo-lugares" min="1" max="16" step="1" value="14" inputmode="numeric" required>
        <p class="vm-small mt-1" id="veiculo-lugares-ajuda">Sem contar com o condutor. Depois de registar, pode tirar ou acrescentar lugares no botão "Lugares" do veículo.</p>
      </div>
      <div id="veiculo-msg" class="vm-erro"></div>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-veiculo">Cancelar</button>
        <button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Registar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de lugares do veículo -->
<div class="vm-modal-overlay" id="modal-lugares" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-1">Lugares · <span id="lugares-matricula"></span></h3>
    <p class="vm-small mb-3 font-semibold" id="lugares-resumo"></p>
    <div class="vm-field">
      <label class="vm-label" for="lugares-nome">Nome do passageiro (opcional)</label>
      <input class="vm-input" id="lugares-nome" maxlength="60" placeholder="Ex.: Sr. Tavares">
    </div>
    <div class="mb-3 grid grid-cols-2 gap-2">
      <button type="button" class="vm-btn vm-btn--outline" id="btn-passageiro-remover">− Remover passageiro</button>
      <button type="button" class="vm-btn vm-btn--cta" id="btn-passageiro-adicionar">+ Adicionar passageiro</button>
    </div>
    <div class="vm-assentos" id="lugares-grid"></div>
    <p class="vm-small mt-2">Verde: livre — toque para sentar um passageiro. Amarelo: passageiro sem app — toque para o remover. Cinzento: reservado pela app (sai em "Passageiros").</p>
    <div id="lugares-msg" class="vm-erro"></div>
    <button type="button" class="vm-btn vm-btn--ghost vm-btn--full mt-4" id="btn-fechar-lugares">Fechar</button>
  </div>
</div>

<!-- Modal de notificações -->
<div class="vm-modal-overlay" id="modal-notificacoes" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Notificações</h3>
    <div id="lista-notificacoes" class="max-h-[60dvh] space-y-2 overflow-y-auto overscroll-contain"></div>
    <button type="button" class="vm-btn vm-btn--ghost vm-btn--full mt-4" id="btn-fechar-notificacoes">Fechar</button>
  </div>
</div>

<!-- Modal de notificação urgente -->
<div class="vm-modal-overlay" id="modal-notificacao-urgente" style="display:none;">
  <div class="vm-modal">
    <span class="vm-badge vm-badge--recusado">Urgente</span>
    <h3 class="vm-h3 mt-3" id="urgente-titulo"></h3>
    <p class="mb-4 text-slate-600" id="urgente-mensagem"></p>
    <button type="button" class="vm-btn vm-btn--primario vm-btn--full" id="btn-fechar-urgente">Entendido</button>
  </div>
</div>

<!-- Modal de sugestão -->
<div class="vm-modal-overlay" id="modal-sugestao" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3">Enviar sugestão</h3>
    <p class="vm-small mb-4">Visível apenas para o Super Admin.</p>
    <form id="form-sugestao">
      <input type="hidden" name="tipo" value="sugestao">
      <div class="vm-field"><label class="vm-label">Título</label><input class="vm-input" name="titulo" required></div>
      <div class="vm-field"><label class="vm-label">Descrição</label><textarea class="vm-input" name="descricao" rows="4" required></textarea></div>
      <div id="sugestao-msg" class="vm-erro"></div>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-sugestao">Cancelar</button>
        <button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Enviar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal sair da fila -->
<div class="vm-modal-overlay" id="modal-sair-fila" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Sair da fila</h3>
    <div class="vm-field">
      <label class="vm-label">Motivo</label>
      <select class="vm-select" id="sel-motivo-saida">
        <option value="Carro cheio">Carro cheio</option>
        <option value="Problema mecânico">Problema mecânico</option>
        <option value="Fim do turno">Fim do turno</option>
        <option value="Outro">Outro</option>
      </select>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="vm-btn vm-btn--ghost" id="btn-cancelar-saida">Cancelar</button>
      <button type="button" class="vm-btn vm-btn--perigo vm-btn--full" id="btn-confirmar-saida">Confirmar saída</button>
    </div>
  </div>
</div>

<div class="vm-modal-overlay" id="modal-chamada" style="display:none;">
  <div class="vm-modal text-center">
    <div id="chamada-icone" class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-800"><x-icone nome="telefone" tamanho="h-7 w-7" /></div>
    <h3 class="vm-h3 mt-2" id="chamada-titulo">Chamada</h3>
    <p class="vm-small" id="chamada-subtitulo"></p>
    <div class="mt-4 flex flex-wrap justify-center gap-2" id="chamada-acoes"></div>
    <audio id="chamada-audio-remoto" autoplay playsinline></audio>
  </div>
</div>

<div class="vm-modal-overlay" id="modal-sons" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Preferências de som</h3>
    <div class="vm-field flex-row items-center justify-between">
      <label class="vm-label" for="sons-ativo">Sons ativos</label>
      <input type="checkbox" id="sons-ativo" class="h-5 w-5 accent-brand-800">
    </div>
    <div class="vm-field">
      <label class="vm-label" for="sons-volume">Volume</label>
      <input type="range" id="sons-volume" min="0" max="100" step="5" class="w-full accent-brand-800">
    </div>
    <div class="mt-4 border-t border-slate-200 pt-4">
      <div class="vm-field flex-row items-center justify-between">
        <label class="vm-label" for="ecra-ligado">Manter o ecrã ligado</label>
        <input type="checkbox" id="ecra-ligado" class="h-5 w-5 accent-brand-800">
      </div>
      <p class="vm-small" id="ecra-ligado-estado">Com um veículo selecionado, o telemóvel não bloqueia sozinho e os pedidos novos continuam a chegar.</p>
    </div>
    <div class="mt-4 border-t border-slate-200 pt-4">
      <div class="vm-field flex-row items-center justify-between">
        <label class="vm-label" for="btn-push">Avisar mesmo com a app fechada</label>
        <button type="button" class="vm-btn vm-btn--sm vm-btn--outline" id="btn-push">Ativar</button>
      </div>
      <p class="vm-small" id="push-estado">A verificar...</p>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <button type="button" class="vm-btn vm-btn--outline" id="sons-testar">Testar som</button>
      <button type="button" class="vm-btn vm-btn--primario vm-btn--full" id="btn-fechar-sons">Fechar</button>
    </div>
  </div>
</div>

<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script src="@kgAsset('assets/js/kg-esc.js')"></script>
<script src="@kgAsset('assets/js/kg-datas.js')"></script>
<script src="/assets/vendor/socket.io/socket.io.min.js"></script>
<script src="@kgAsset('assets/js/kg-sons.js')"></script>
<script src="@kgAsset('assets/js/kg-geolocation.js')"></script>
<script src="@kgAsset('assets/js/kg-map.js')"></script>
<script src="@kgAsset('assets/js/kg-realtime.js')"></script>
<script src="@kgAsset('assets/js/kg-chamada.js')"></script>
<script src="@kgAsset('assets/js/kg-push.js')"></script>
<script nonce="{{ $cspNonce }}">
const CSRF_TOKEN = @json(csrf_token());
const UTILIZADOR_ID = @json((int) $condutor->id);
const MOSTRAR_MAPA = @json($mostrarMapa);

// Ícones (config/icones.php) — a mesma lista que o componente x-icone usa do
// lado do servidor. As listas de passageiros e os popups do mapa são
// montados aqui, e não podiam ficar com um conjunto próprio.
// (Sem os sinais de menor/maior à volta do nome do componente: o Blade
// compila isso como componente a sério, mesmo dentro de um comentário.)
const ICONES = @json(config('icones'));

function svgIcone(nome, classe) {
  const desenho = ICONES[nome];
  if (!desenho) return '';
  return `<svg class="${classe || 'h-4 w-4 shrink-0'}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${desenho}</svg>`;
}

// O tipo de viagem vinha como emoji colado a 'Urbana'/'Intermunicipal' em texto corrido,
// no meio de uma linha já cheia. Como badge separa-se do resto de relance.
function badgeViagem(tipo) {
  const urbano = tipo === 'urbano';
  return `<span class="vm-badge ${urbano ? 'vm-badge--a_bordo' : ''}">${
    svgIcone(urbano ? 'cidade' : 'estrada', 'h-3.5 w-3.5')}${urbano ? 'Urbana' : 'Intermunicipal'}</span>`;
}

// Ícone + texto na mesma linha, alinhados pelo meio. Usado nas listas de
// passageiros, onde antes ia um emoji colado à palavra.
function linhaIcone(nome, texto) {
  return `<span class="inline-flex items-center gap-1.5 align-middle">${svgIcone(nome)}<span>${texto}</span></span>`;
}
let mapa = null;
let pontos = [];
let veiculoAtivoId = null;
let veiculoAtivoData = null;
// Par "partida|destino" do servidor que as listas já refletem. Serve para
// distinguir uma mudança vinda do servidor (a inversão automática na chegada
// ao destino, ou um "Guardar ponto") de uma escolha ainda por guardar que o
// condutor tem aberta nas listas: o polling de 10 s reescrevia-lhe a escolha
// por baixo do dedo se sincronizasse sempre.
let pontosVeiculoSincronizados = null;
let watchId = null;
// Envio da posição ao servidor: desacoplado do callback do GPS. Com
// enableHighAccuracy o telemóvel emite ~1 leitura/s e, em andamento, todas
// passam o filtro dos 5 m — era 1 POST por segundo por condutor, cada um com
// escrita na BD e query às reservas, e com pedidos a sobreporem-se. Agora o
// callback só guarda a última posição boa e este temporizador envia-a, no
// máximo uma vez a cada ENVIO_POSICAO_MS. (Mesmo padrão do painel do
// passageiro.) 3 s são ~42 m a 50 km/h, bem dentro do raio de chegada de
// 100 m usado pelo PosicaoController.
const ENVIO_POSICAO_MS = 3000;
let posicaoPorEnviar = null;   // { lat, lng, accuracy } ainda não enviada
let envioPosicaoTimer = null;
let envioPosicaoEmCurso = false;
let avisosDescidaAnunciados = new Set(); // reservas que já tocaram som de aviso
let passageiroMarkerIds = new Set();
let passageiroUrbanoMarkerIds = new Set();
let passageiroPontoMarkerIds = new Set();
let outroCondutorMarkerIds = new Set();
let outroCondutorMarcadoEstado = new Map();
let pollingAtivo = null;
let meuVeiculoMarcadoId = null;
let meuVeiculoMarcadoEstado = null;
// Ocupação do veículo ativo tal como o servidor a calcula (capacidade, quem
// vai a bordo, reservados, livres) — é o que o marcador do condutor mostra.
// Vive à parte de veiculoAtivoData porque esse objeto é substituído inteiro a
// cada carregarMeusVeiculos() e a ocupação vem de outro endpoint.
let ocupacaoVeiculo = null;
// Se o mapa acompanha o GPS do condutor. Para quando ele arrasta o mapa ou
// quando se centra num veículo parado longe dele; o botão recentrar retoma.
let aSeguirCondutor = true;
let ultimaLatCondutor = null;
let ultimaLngCondutor = null;
let navegacaoAlvo = null; // { tipo: 'passageiro'|'destino', lat, lng, nome }
let navegacaoTimer = null;
// Ultrapassagem do alvo: navMenorDistancia é a menor distância já alcançada
// ao alvo nesta navegação — se a distância voltar a crescer bastante depois
// de ter chegado perto, o condutor passou ao lado dele.
let navMenorDistancia = null;
let navUltrapassouAvisado = false;
let navUltimoRecalculo = 0;

// Rota dinâmica: o traçado completo devolvido pelo motor de rotas fica
// guardado aqui para que cada sinal de GPS possa recortar a parte já
// percorrida (KGMap.rotaAFrente) e medir a distância à estrada — tudo sem
// voltar a pedir a rota ao servidor. Só um desvio a sério pede rota nova.
let rotaNavegacaoCoords = null;
let rotaNavegacaoDistanciaM = null;
let rotaNavegacaoDuracaoS = null;
let navSinaisForaDaRota = 0;
// Progresso já feito no traçado (índice do segmento). Sem ele, uma rota que
// passa duas vezes pela mesma rua fazia o recorte saltar para a passagem
// seguinte e desaparecer com meia viagem. Duas casas de tolerância para trás
// absorvem o tremido normal do GPS.
let navIndiceNaRota = 0;
const TOLERANCIA_INDICE_ROTA = 2;
// Zoom usado sempre que o mapa salta para a posição do condutor (arranque e
// botão de recentrar): nível de rua, onde já se distingue o sentido da via e
// o lado onde está o passageiro. Com 15 via-se o bairro, não a esquina.
const ZOOM_SEGUIMENTO = 17;
// Distância à estrada abaixo da qual a posição do condutor é considerada
// ruído de GPS e a linha da rota arranca já em cima do traçado — o erro
// urbano típico é desta ordem e desenhá-lo dava a impressão de um desvio.
const ENCOSTAR_ROTA_M = 25;
// Distância à rota a partir da qual se considera que o condutor saiu dela.
// Larga o suficiente para o erro do GPS urbano e para uma rua paralela não
// disparar um recálculo a cada esquina.
const DESVIO_MAX_M = 60;
// Nunca a partir de um sinal só: uma leitura má sozinha não é um desvio.
const DESVIO_SINAIS_MIN = 2;
// Intervalo mínimo entre dois pedidos de rota nova, para um troço de sinal
// mau não gerar uma rajada de recálculos.
const RECALCULO_MIN_MS = 10000;
// Estilo único da rota de navegação: ela é redesenhada a cada sinal de GPS
// (recorte do troço já percorrido) e a cada recálculo, e bastava uma das
// cópias divergir para a linha mudar de aspeto a meio da viagem.
const ESTILO_ROTA_NAV = { color: '#00B4D8', weight: 5, opacity: 0.9 };

function fd(extra) {
  const d = new FormData();
  d.set('_token', CSRF_TOKEN);
  // Campos null/undefined são omitidos em vez de irem como a string "null":
  // um campo opcional no servidor ('nullable') rejeita "null" no numeric e
  // fazia o pedido inteiro falhar com 422 (ex: accuracy sem valor).
  Object.entries(extra || {}).forEach(([k, v]) => {
    if (v !== null && v !== undefined) d.set(k, v);
  });
  return d;
}

// Pedido GET com tratamento uniforme de sessão expirada (401): evita que um
// .map()/Object.entries() sobre um JSON de erro rebente com TypeError.
async function kgApiJSON(url, opts) {
  const resp = await fetch(url, opts);
  if (resp.status === 401) {
    window.location.href = '/login';
    throw new Error('Sessão expirada, a redirecionar para o login.');
  }
  const json = await resp.json();
  if (!resp.ok) {
    throw new Error(json.erro || `Erro ${resp.status} em ${url}`);
  }
  return json;
}

async function carregarPontosDropdowns() {
  const json = await kgApiJSON('/api/passageiro/pontos');
  pontos = json.pontos || [];
  const selP = document.getElementById('sel-ponto-condutor');
  const selD = document.getElementById('sel-destino-condutor');
  selP.innerHTML = selD.innerHTML = '';
  // Opção vazia à cabeça: um veículo sem ponto (urbano, ou 'ambos' ainda a
  // circular livre) tem de poder ficar assim. Sem ela, o primeiro ponto da
  // lista aparecia escolhido e um "Guardar" distraído ancorava o veículo a um
  // ponto que o condutor nunca escolheu.
  selP.add(new Option('— Sem ponto (a circular) —', ''));
  selD.add(new Option('— Sem destino —', ''));
  pontos.forEach(p => {
    selP.add(new Option(p.nome, p.id));
    selD.add(new Option(p.nome, p.id));
    mapa.addMarker(`ponto-${p.id}`, 'ponto', parseFloat(p.lat), parseFloat(p.lng), { popupHtml: `<strong>${esc(p.nome)}</strong>` });
  });
}

async function carregarMeusVeiculos() {
  const json = await kgApiJSON('/api/condutor/veiculos');
  const lista = document.getElementById('lista-meus-veiculos');
  lista.innerHTML = '';

  (json.veiculos || []).forEach(v => {
    const div = document.createElement('div');
    div.className = 'flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-b-0';
    const pendentes = v.passageiros_pendentes || 0;
    // Badge de passageiros à espera por veículo (relatório "pedidos
    // intermunicipais invisíveis"): um condutor com mais do que um veículo
    // só via os pedidos do veículo que tinha selecionado como ativo — um
    // pedido preso noutro veículo seu ficava sem qualquer pista visível.
    const badgePendentes = pendentes > 0
      ? ` <span class="vm-badge vm-badge--recusado">${esc(pendentes)} à espera</span>`
      : '';
    div.innerHTML = `
      <div>
        <strong>${esc(v.matricula)}</strong>
        <span class="vm-badge ${esc(v.aprovado ? 'vm-badge--confirmado' : 'vm-badge--pendente')}">${esc(v.aprovado ? 'Aprovado' : 'Pendente')}</span>${badgePendentes}
        <div class="vm-small">${esc(v.tipo)} (${esc(v.tipo_servico)}) · ${esc(v.cor)} · ${esc(v.lugares_livres)}/${esc(v.lugares_total)} livres · ${esc(v.estado)}</div>
      </div>
      <div class="flex gap-2">
        <button class="vm-btn vm-btn--sm vm-btn--ghost" type="button" data-lugares>Lugares</button>
        <button class="vm-btn vm-btn--sm vm-btn--outline" type="button" data-selecionar>Selecionar</button>
      </div>`;
    div.querySelector('[data-selecionar]').addEventListener('click', () => {
      selecionarVeiculo(v);
      mostrarVeiculoAtivoNoMapa();
    });
    div.querySelector('[data-lugares]').addEventListener('click', () => abrirModalLugares(v));
    lista.appendChild(div);
  });

  if (!json.veiculos || !json.veiculos.length) {
    lista.innerHTML = '<p class="vm-small">Ainda não tem veículos registados.</p>';
  }

  // Mantém o cartão "Veículo ativo" sincronizado com o estado real depois de
  // ações como Entrar/Sair da fila — sem isto, os botões ficavam-se pelo
  // estado antigo até o condutor voltar a clicar "Selecionar" na lista.
  if (veiculoAtivoId) {
    const atual = (json.veiculos || []).find(v => v.id === veiculoAtivoId);
    if (atual) {
      veiculoAtivoData = atual;
      ajustarCartaoVeiculoAtivo(atual);
      document.getElementById('estado-veiculo-txt').textContent = textoEstadoVeiculo(atual);
      atualizarBotoesEstadoVeiculo(atual.estado);
      // O estado que decide se o GPS pode mexer o veículo (emRota, no watch
      // abaixo) vinha só do primeiro tick do GPS e da resposta ao POST de
      // posição — nunca daqui. Depois de "Iniciar rota", meuVeiculoMarcadoEstado
      // ficava preso em 'no_ponto'/'na_fila': o marcador não saía do ponto e
      // nenhuma posição era enviada, pelo que a resposta do servidor (a única
      // que o corrigia) nunca chegava. Encravava até um reload.
      sincronizarEstadoMeuVeiculo(atual.estado);
      // Chegada ao destino: o servidor inverte ponto de partida e destino, mas
      // o estado ('chegou_destino') já vinha aplicado pela resposta ao POST da
      // posição, pelo que sincronizarEstadoMeuVeiculo sai a meio e nunca
      // chegava a redesenhar a rota. As listas e a linha do mapa ficavam a
      // mostrar o sentido da ida até um reload ou um novo "Selecionar".
      if (sincronizarListasPontoVeiculo(atual)) {
        tracarRotaCondutor();
        if (atual.ponto_partida_id) carregarOutrosCondutores(atual.ponto_partida_id);
      }
    }
  } else {
    // Condutor urbano não precisa de "selecionar" o veículo manualmente
    // (relatório "condutor urbano já está associado") — se só tem um
    // veículo aprovado, fica ativo automaticamente assim que a lista carrega.
    // Com mais do que um veículo aprovado, prioriza automaticamente o que
    // já tem passageiros à espera — antes ficava-se sempre pelo primeiro,
    // deixando pedidos noutro veículo invisíveis até o condutor descobrir
    // sozinho que tinha de trocar de "Selecionar".
    const aprovados = (json.veiculos || []).filter(v => v.aprovado);
    if (aprovados.length === 1) {
      selecionarVeiculo(aprovados[0]);
    } else if (aprovados.length > 1) {
      const comPendentes = aprovados.find(v => (v.passageiros_pendentes || 0) > 0);
      if (comPendentes) selecionarVeiculo(comPendentes);
    }
  }
}

// Verdadeiro quando o GPS já pode mexer o veículo: veículo sem ponto
// definido (urbano a circular livremente) ou já depois de "Iniciar rota".
// Parado no ponto (no_ponto/na_fila/chegou_destino) o marcador fica ancorado
// nas coordenadas do ponto (relatório "carro fora do ponto").
function veiculoEmRota(estado) {
  if (!veiculoAtivoData?.ponto_partida_id) return true;
  return estado === 'partiu_da_fila' || estado === 'em_movimento';
}

// Melhor posição conhecida para desenhar o meu veículo: o GPS quando o carro
// já anda, as coordenadas do ponto enquanto está parado nele.
function posicaoMeuVeiculo(estado) {
  const emRota = veiculoEmRota(estado);
  const lat = emRota && ultimaLatCondutor != null ? ultimaLatCondutor : parseFloat(veiculoAtivoData?.lat);
  const lng = emRota && ultimaLngCondutor != null ? ultimaLngCondutor : parseFloat(veiculoAtivoData?.lng);
  return (isNaN(lat) || isNaN(lng)) ? null : { lat, lng };
}

// (Re)cria o marcador do meu veículo. É recriado, e não só movido, sempre que
// o estado muda, porque a cor/classe do ícone depende do estado.
// O meu veículo com a ocupação ao vivo colada. A ocupação é juntada aqui, e
// não guardada dentro de veiculoAtivoData, porque esse objeto é substituído a
// cada carregarMeusVeiculos() — o marcador ficava a mostrar a contagem de
// lugares em vez da de embarques logo no primeiro poll a seguir a um embarque.
function dadosMeuVeiculo() {
  return veiculoAtivoData ? Object.assign({}, veiculoAtivoData, { ocupacao: ocupacaoVeiculo }) : null;
}

// O detalhe que o popup mostra ao clicar no meu veículo: matrícula, tipo, cor
// e o bloco de lugares (capacidade, quem vai a bordo com destino e assento,
// quem falta embarcar). Vive numa função só porque dois marcadores o mostram
// — o do veículo e, quando está parado no ponto, o próprio ponto do GPS.
function popupMeuVeiculoHtml() {
  const dados = dadosMeuVeiculo();
  return dados ? KGMap.veiculoPopupHtml(dados) : 'O meu veículo';
}

function desenharMeuVeiculo(id, lat, lng, estado) {
  if (meuVeiculoMarcadoId) mapa.removeMarker(meuVeiculoMarcadoId);
  const dados = dadosMeuVeiculo();
  const marcador = mapa.addMarker(id, 'veiculo', lat, lng, {
    classes: KGMap.veiculoEstadoClasse(estado),
    html: dados ? KGMap.veiculoIconeHtml(dados, { comSeta: true }) : '',
    popupHtml: popupMeuVeiculoHtml(),
    // Acima do ponto do GPS, que fica na mesma coordenada quando o condutor
    // está dentro do próprio carro: é este marcador que tem o detalhe, por
    // isso é este que tem de receber o clique.
    zIndexOffset: 1000,
  });
  // addMarker devolve null com coordenadas fora de Cabo Verde: nesse caso o
  // marcador não existe mesmo, e dar-lhe o id por bom deixava o veículo
  // invisível para sempre (os updateMarker seguintes não teriam o que mover).
  meuVeiculoMarcadoId = marcador ? id : null;
  if (marcador) meuVeiculoMarcadoEstado = estado;
  atualizarIconeGpsCondutor();
}

// Alinha o estado local do marcador do meu veículo com o do servidor.
function sincronizarEstadoMeuVeiculo(estado) {
  if (!estado || estado === meuVeiculoMarcadoEstado) return;
  meuVeiculoMarcadoEstado = estado;
  if (veiculoAtivoData) veiculoAtivoData.estado = estado;
  atualizarIconeVeiculoAtivo(estado);
  atualizarIconeGpsCondutor();
  // A linha ponto de partida -> destino depende do estado: em "Iniciar rota"
  // apaga-se, ao voltar ao ponto volta a ser desenhada. Fica aqui, por onde
  // passam todas as mudanças de estado (botão, poll dos veículos e resposta
  // ao envio de posição), e não só no clique do botão.
  tracarRotaCondutor();
  if (!mapa || !meuVeiculoMarcadoId) return;

  // O marcador é redesenhado já aqui, na melhor posição conhecida, em vez de
  // ficar destruído à espera do próximo tick do GPS: kgWatchPosition descarta
  // leituras com menos de 5 m de deslocamento, por isso o condutor parado no
  // ponto que carregava em "Iniciar rota" ficava sem marcador do próprio
  // veículo até o carro andar mesmo.
  const pos = posicaoMeuVeiculo(estado);

  if (!pos) {
    // Ainda sem posição nenhuma para o desenhar — fica ao cuidado do próximo
    // tick do GPS, como antes.
    mapa.removeMarker(meuVeiculoMarcadoId);
    meuVeiculoMarcadoId = null;
    atualizarIconeGpsCondutor();
    return;
  }

  desenharMeuVeiculo(`v-${veiculoAtivoId}`, pos.lat, pos.lng, estado);
}

// Guarda a ocupação vinda do servidor e redesenha o marcador do condutor com
// a contagem e a lista novas (requisito "atualizar o marcador em tempo real
// após cada embarque"). Só redesenha quando os números mudaram mesmo: recriar
// o marcador a cada poll de 10 s fechava o popup na cara de quem o abrisse.
function aplicarOcupacao(ocupacao) {
  if (!ocupacao) return;
  const mudou = JSON.stringify(ocupacao) !== JSON.stringify(ocupacaoVeiculo);
  ocupacaoVeiculo = ocupacao;
  if (!mudou) return;
  // O cartão "Veículo ativo" conta os mesmos lugares que o marcador: depois
  // de um embarque tem de ser reescrito já aqui, senão ficava a dizer o
  // número anterior até ao próximo poll dos veículos (até 10 s).
  const estadoTxt = document.getElementById('estado-veiculo-txt');
  if (estadoTxt && veiculoAtivoData) estadoTxt.textContent = textoEstadoVeiculo(veiculoAtivoData);
  if (!mapa) return;

  // Badge e popup mudam no lugar, em vez de o marcador ser recriado: recriá-lo
  // fechava o popup na cara de quem o tivesse aberto, e é precisamente
  // durante um embarque que o condutor está a olhar para ele.
  if (meuVeiculoMarcadoId) {
    const dados = dadosMeuVeiculo();
    if (dados) mapa.setMarkerIcone(meuVeiculoMarcadoId, KGMap.veiculoIconeHtml(dados));
    mapa.setMarkerPopup(meuVeiculoMarcadoId, popupMeuVeiculoHtml());
  }
  // O ponto do GPS mostra o mesmo detalhe enquanto o veículo está parado no
  // ponto — tem de acompanhar a contagem nova.
  atualizarIconeGpsCondutor();
}

function pontoPorId(id) {
  return pontos.find(p => String(p.id) === String(id));
}

// Alinha as listas de ponto de partida/destino com o que o servidor tem
// guardado para o veículo. Só escreve quando o par guardado mudou desde a
// última sincronização (ou com forcar, ao trocar de veículo): assim a
// inversão automática da chegada ao destino aparece nas listas de imediato,
// sem que o polling de 10 s apague uma escolha que o condutor ainda não
// guardou. Devolve true quando mexeu em alguma coisa.
function sincronizarListasPontoVeiculo(v, forcar = false) {
  const selPonto = document.getElementById('sel-ponto-condutor');
  const selDestino = document.getElementById('sel-destino-condutor');
  if (!selPonto || !selDestino) return false;

  const par = `${v?.ponto_partida_id || ''}|${v?.destino_id || ''}`;
  if (!forcar && par === pontosVeiculoSincronizados) return false;

  // Sempre atribuídos, mesmo vazios: ficando com o valor do veículo anterior,
  // o cartão dizia que este veículo estava num ponto que não é o dele — e a
  // rota desenhada no mapa e os "outros condutores no ponto" vinham daí.
  selPonto.value = v?.ponto_partida_id || '';
  selDestino.value = v?.destino_id || '';

  // Um <select> ainda sem opções (lista de pontos por carregar, ou painel sem
  // mapa) descarta a atribuição e fica em ''. Dar o par por sincronizado nesse
  // caso trancava a sincronização seguinte, a boa.
  if (`${selPonto.value}|${selDestino.value}` !== par) return false;

  pontosVeiculoSincronizados = par;
  return true;
}

// Destino real de um passageiro a bordo: o ponto de descida que ele
// escreveu/escolheu, com fallback para o destino do veículo (viagens
// intermunicipais sem pin específico). Mesma regra usada ao embarcar e ao
// restaurar a navegação depois de um reload/login — antes só existia no
// clique de "Embarcar", por isso a rota desaparecia ao atualizar a página.
function destinoDoPassageiro(p) {
  const lat = parseFloat(p.ponto_descida_lat);
  const lng = parseFloat(p.ponto_descida_lng);
  if (!isNaN(lat) && !isNaN(lng)) return { lat, lng, nome: p.ponto_descida_nome || 'destino do passageiro' };
  const destino = pontoPorId(veiculoAtivoData?.destino_id);
  return destino ? { lat: parseFloat(destino.lat), lng: parseFloat(destino.lng), nome: destino.nome } : null;
}

// Traça a rota real (por estrada) entre o ponto de partida e o destino
// escolhidos pelo condutor, para que a rota seja visível no mapa assim que
// o destino é definido (secção B do relatório do mapa).
// Os pedidos ao motor de rotas ficam em fila em vez de correrem em paralelo:
// com dois em voo (trocar de veículo, ou mudar o ponto, enquanto o anterior
// ainda não respondeu), o mais lento desenhava por cima a rota do veículo que
// já não está selecionado. Em fila, quem pinta por último é sempre a chamada
// mais recente, que lê o veículo e os pontos atuais quando lhe chega a vez.
let rotaCondutorFila = Promise.resolve();
function tracarRotaCondutor() {
  rotaCondutorFila = rotaCondutorFila.then(desenharRotaCondutor, desenharRotaCondutor);
  return rotaCondutorFila;
}

async function desenharRotaCondutor() {
  if (!mapa) return;
  // A linha ponto de partida -> destino descreve a viagem enquanto o veículo
  // ainda está parado no ponto. Depois de "Iniciar rota" deixa de dizer onde
  // o veículo está — a partir daí quem desenha o caminho é a navegação
  // guiada, a partir da posição real do GPS. Apaga-se aqui, e não só no
  // clique do botão, para que nenhum redesenho posterior (trocar de veículo,
  // mudar o ponto/destino) a traga de volta com o veículo já a caminho.
  const estado = veiculoAtivoData?.estado;
  if (estado === 'partiu_da_fila' || estado === 'em_movimento') {
    mapa.clearRoute('condutor-rota');
    return;
  }
  // O sentido vem do veículo (o que o servidor tem guardado), não das listas:
  // na chegada ao destino o servidor troca partida e destino, e uma linha lida
  // do DOM continuava a desenhar o sentido da ida mesmo depois da inversão.
  const partida = pontoPorId(veiculoAtivoData?.ponto_partida_id);
  const destino = pontoPorId(veiculoAtivoData?.destino_id);
  if (!partida || !destino) { mapa.clearRoute('condutor-rota'); return; }
  await KGMap.tracarRota(
    mapa, 'condutor-rota',
    { lat: parseFloat(partida.lat), lng: parseFloat(partida.lng) },
    { lat: parseFloat(destino.lat), lng: parseFloat(destino.lng) }
  );
}

// Município onde o condutor está agora, a partir da delimitação carregada em
// mostrarMunicipios() (KGMap.municipioEm faz a leitura sobre as fronteiras dos
// 22 concelhos, sem chamar o servidor). Chamado a cada leitura filtrada do
// GPS: é uma volta a 22 polígonos, barata o suficiente para isso.
//
// Fora do território (o GPS pode entregar uma leitura no mar, ou ainda a
// assentar) a pastilha desaparece em vez de deixar lá o último concelho
// conhecido — um município errado seria pior do que nenhum.
function atualizarMunicipioAtual(lat, lng) {
  const caixa = document.getElementById('municipio-atual');
  if (!caixa || lat == null || lng == null) return;
  const m = KGMap.municipioEm(lat, lng);
  if (!m) {
    // Enquanto o GeoJSON não chega, municipioEm devolve null e não há nada a
    // dizer — a pastilha só aparece quando houver mesmo resposta.
    caixa.style.display = 'none';
    return;
  }
  caixa.innerHTML = `${esc(m.nome)} <span class="kg-municipio-atual__ilha">· ilha ${esc(m.ilha)}</span>`;
  caixa.style.display = '';
}

// Navegação guiada (relatório do mapa, tarefas B/C/D/E): desenha a rota por
// estrada do veículo até ao alvo (passageiro a buscar ou destino final),
// ajusta o mapa para mostrar as duas pontas (nunca um zoom fixo que pode
// cair sobre zona sem detalhe no OSM — era isso, e não um "overlay escuro",
// que fazia o mapa parecer ficar em branco/escuro ao clicar "Ir buscar") e
// mostra distância/tempo estimado no painel de navegação.
function formatarDistancia(m) {
  if (m == null) return null;
  return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
}
function formatarDuracao(s) {
  if (s == null) return null;
  const min = Math.round(s / 60);
  return min < 1 ? '< 1 min' : min + ' min';
}

function mostrarInfoNavegacao(distanciaM, duracaoS) {
  const alvo = document.getElementById('nav-info');
  if (!alvo) return;
  const dist = formatarDistancia(distanciaM);
  const dur = formatarDuracao(duracaoS);
  // Sem estimativa de tempo (o motor de rotas não respondeu e a linha é
  // direta) mostra-se a distância na mesma — é o que se sabe, e é mais útil
  // do que a mensagem genérica que estava aqui antes.
  alvo.textContent = dist && dur ? `${dist} · ${dur}` : (dist || 'Rota traçada (linha direta — sem dados de estrada).');
}

// Pede a rota por estrada, da posição dada até ao alvo atual, e guarda o
// traçado. É o único sítio que fala com o serviço de rotas: o seguimento ao
// vivo (seguirRotaNavegacao) trabalha sempre sobre o que aqui ficou guardado.
async function calcularRotaNavegacao(origem) {
  const alvo = navegacaoAlvo;
  if (!mapa || !alvo) return null;
  // Marcado antes do pedido, e não depois: sem isto, os sinais de GPS que
  // chegassem enquanto esta rota estava a ser calculada viam o carimbo
  // antigo e pediam outra rota por cima desta.
  navUltimoRecalculo = Date.now();
  navSinaisForaDaRota = 0;

  const info = await KGMap.tracarRota(mapa, 'rota-navegacao', origem, { lat: alvo.lat, lng: alvo.lng }, ESTILO_ROTA_NAV);
  // O alvo pode ter mudado enquanto o pedido corria (outro "Ir buscar", um
  // embarque, um "Entregue"): este traçado já não é o da navegação em curso.
  if (navegacaoAlvo !== alvo) return null;

  rotaNavegacaoCoords = info.geometria;
  rotaNavegacaoDistanciaM = info.distanciaM;
  rotaNavegacaoDuracaoS = info.duracaoS;
  navIndiceNaRota = 0; // traçado novo: o progresso recomeça do princípio

  // Recorta já o troço atrás do veículo, para a linha nunca chegar a aparecer
  // com rasto — nem no primeiro desenho, nem depois de um recálculo.
  if (ultimaLatCondutor != null && ultimaLngCondutor != null) {
    seguirRotaNavegacao(ultimaLatCondutor, ultimaLngCondutor);
  } else {
    mostrarInfoNavegacao(info.distanciaM, info.duracaoS);
  }
  return info;
}

/**
 * Seguimento ao vivo da rota, a cada sinal de GPS (requisito "GPS dinâmico"):
 *
 *  - recorta a linha ao que falta percorrer, da posição atual para a frente,
 *    para o mapa nunca mostrar o caminho já feito;
 *  - mede a distância do veículo à estrada traçada e, quando ele sai dela,
 *    pede um caminho alternativo a partir de onde está agora.
 *
 * Entre recálculos não há nenhum pedido ao servidor: é tudo geometria sobre o
 * traçado já conhecido, por isso pode correr a cada leitura do GPS.
 */
function seguirRotaNavegacao(lat, lng) {
  if (!mapa || !navegacaoAlvo || !rotaNavegacaoCoords) return;
  const frente = KGMap.rotaAFrente(
    rotaNavegacaoCoords, lat, lng,
    Math.max(0, navIndiceNaRota - TOLERANCIA_INDICE_ROTA),
    { encostarM: ENCOSTAR_ROTA_M }
  );
  if (!frente) return;

  if (frente.projecao.distanciaM > DESVIO_MAX_M) {
    navSinaisForaDaRota++;
    if (navSinaisForaDaRota >= DESVIO_SINAIS_MIN && Date.now() - navUltimoRecalculo > RECALCULO_MIN_MS) {
      document.getElementById('nav-info').textContent = 'Saiu da rota — a calcular caminho alternativo...';
      atualizarNavegacao();
    }
    // Fora da rota, o traçado antigo já não descreve o caminho do condutor:
    // fica como está até chegar o alternativo, em vez de desenhar uma linha a
    // ligar o veículo a uma estrada que ele deixou.
    return;
  }

  navSinaisForaDaRota = 0;
  navIndiceNaRota = frente.projecao.indice;
  mapa.drawRoute('rota-navegacao', frente.coords, ESTILO_ROTA_NAV);
  // Tempo estimado à escala do que falta: entre dois recálculos não há
  // estimativa nova do motor de rotas, e um tempo parado no valor inicial
  // durante toda a viagem seria pior do que nenhum.
  const duracaoRestante = rotaNavegacaoDuracaoS != null && rotaNavegacaoDistanciaM > 0
    ? rotaNavegacaoDuracaoS * Math.min(1, frente.distanciaRestanteM / rotaNavegacaoDistanciaM)
    : null;
  mostrarInfoNavegacao(frente.distanciaRestanteM, duracaoRestante);
}

// 'reservaId' só faz sentido nas recolhas (tipo 'passageiro'): é por ele que
// carregarPassageiros confirma que a rota desenhada ainda pertence a uma
// reserva do veículo selecionado.
async function iniciarNavegacao(tipo, lat, lng, nome, reservaId) {
  if (!mapa || isNaN(lat) || isNaN(lng)) return;
  navegacaoAlvo = { tipo, lat, lng, nome, reservaId: reservaId != null ? String(reservaId) : null, iniciadoEm: Date.now() };
  navMenorDistancia = null;
  navUltrapassouAvisado = false;
  rotaNavegacaoCoords = null;
  const origem = { lat: ultimaLatCondutor ?? parseFloat(veiculoAtivoData?.lat), lng: ultimaLngCondutor ?? parseFloat(veiculoAtivoData?.lng) };

  document.getElementById('nav-panel').style.display = 'block';
  document.getElementById('nav-icone').innerHTML = svgIcone(tipo === 'passageiro' ? 'ponto' : 'bandeira');
  document.getElementById('nav-titulo').textContent = tipo === 'passageiro' ? `A caminho de ${nome}` : `A caminho do destino: ${nome}`;
  document.getElementById('nav-info').textContent = 'A calcular rota...';

  if (isNaN(origem.lat) || isNaN(origem.lng)) {
    mapa.flyTo(lat, lng, 15);
    document.getElementById('nav-info').textContent = 'Sem posição GPS do veículo ainda — a aguardar sinal.';
    return;
  }

  mapa.fitBounds([[origem.lat, origem.lng], [lat, lng]]);
  await calcularRotaNavegacao(origem);

  clearInterval(navegacaoTimer);
  navegacaoTimer = setInterval(() => atualizarNavegacao(), 30000);
}

// Rota nova a partir da posição atual: o caminho alternativo depois de um
// desvio, a rede de segurança do temporizador de 30 s e o reajuste depois de
// uma ultrapassagem passam todos por aqui.
async function atualizarNavegacao() {
  if (!navegacaoAlvo || ultimaLatCondutor == null) return;
  await calcularRotaNavegacao({ lat: ultimaLatCondutor, lng: ultimaLngCondutor });
}

function pararNavegacao() {
  navegacaoAlvo = null;
  navMenorDistancia = null;
  navUltrapassouAvisado = false;
  rotaNavegacaoCoords = null;
  rotaNavegacaoDistanciaM = null;
  rotaNavegacaoDuracaoS = null;
  navSinaisForaDaRota = 0;
  navIndiceNaRota = 0;
  clearInterval(navegacaoTimer);
  navegacaoTimer = null;
  if (mapa) mapa.clearRoute('rota-navegacao');
  document.getElementById('nav-panel').style.display = 'none';
}
document.getElementById('btn-nav-fechar')?.addEventListener('click', pararNavegacao);

function pararPollingCondutor() {
  if (pollingAtivo) { clearInterval(pollingAtivo); pollingAtivo = null; }
}

// Mantém passageiros, outros condutores e o autofalante atualizados mesmo
// sem o servidor de tempo real (socket.io) ativo — secção 5.4 / relatório do
// mapa. O autofalante dependia só de eventos kgRt (comunicacao:nova); sem o
// socket.io a correr, nunca chegava nada — por isso entra também aqui.
function iniciarPollingCondutor() {
  pararPollingCondutor();
  pollingAtivo = setInterval(() => {
    if (!veiculoAtivoId) return;
    carregarPassageiros();
    // Também atualiza o badge "N à espera" dos OUTROS veículos do condutor
    // (relatório "pedidos intermunicipais invisíveis") — sem isto, um pedido
    // novo num veículo não selecionado só aparecia depois de uma ação manual.
    carregarMeusVeiculos();
    carregarChatCondutor();
    carregarPassageirosDoPonto();
    const pontoId = document.getElementById('sel-ponto-condutor').value;
    if (pontoId) carregarOutrosCondutores(pontoId);
  }, 10000);
}

// Os botões "Entrar na fila"/"Sair da fila" estavam sempre visíveis,
// mesmo quando o veículo nunca chegou a entrar na fila — o condutor não
// tinha nenhuma forma óbvia de sinalizar que ia partir diretamente do ponto
// (relatório do mapa, tarefa D: "Iniciar rota"). O endpoint por trás do
// botão (fila_sair) já funciona em qualquer estado, o que faltava era
// o botão certo aparecer com o rótulo certo.
// Chip do veículo selecionado no título do cartão "Veículo ativo": emoji do
// tipo e pastilha com a cor real, os mesmos do marcador no mapa. Sem isto, o
// condutor com mais do que um veículo só sabia qual tinha escolhido em
// "Selecionar" pelo texto do estado, que é igual em todos.
function atualizarIconeVeiculoAtivo(estado) {
  const alvo = document.getElementById('icone-veiculo-ativo');
  if (!alvo) return;
  if (!veiculoAtivoData || !window.KGMap?.veiculoChipHtml) return; // fica a bandeira genérica
  alvo.innerHTML = KGMap.veiculoChipHtml(Object.assign({}, veiculoAtivoData, { estado: estado ?? veiculoAtivoData.estado }));
}

// O ponto do GPS mostra o veículo selecionado (emoji do tipo + pastilha da
// cor), em vez de um ponto azul anónimo: com mais do que um veículo, o
// condutor via no mapa dois marcadores iguais e não sabia qual estava a
// conduzir. Só o tipo e a cor são passados — o badge de lugares é do marcador
// do veículo, não deve aparecer duas vezes quando os dois se sobrepõem.
function atualizarIconeGpsCondutor() {
  if (!mapa?.setMarkerIcone) return;
  // Em rota, o marcador do próprio veículo já está desenhado por cima da
  // posição do GPS (ver posicaoMeuVeiculo): vestir também o ponto do GPS
  // punha dois ícones iguais, um sobre o outro, com molduras desalinhadas.
  // Só aí o ponto do GPS volta a ser o ponto azul — o veículo continua a
  // aparecer, é o marcador de cima. Parado no ponto é o contrário: o veículo
  // fica ancorado no ponto e é o ponto do GPS que precisa de o identificar.
  const estado = meuVeiculoMarcadoEstado || veiculoAtivoData?.estado;
  const jaDesenhadoNoGps = !!meuVeiculoMarcadoId && veiculoEmRota(estado);
  const vesteVeiculo = !!veiculoAtivoData && !jaDesenhadoNoGps && !!window.KGMap?.veiculoIconeHtml;
  const html = vesteVeiculo
    ? KGMap.veiculoIconeHtml({ tipo: veiculoAtivoData.tipo, cor: veiculoAtivoData.cor })
    : '';
  mapa.setMarkerIcone('eu', html, 'kg-marker--eu-veiculo');
  // Vestido de veículo, este é o ícone que o condutor tem debaixo do dedo
  // (está onde ele está, e o veículo fica ancorado no ponto) — clicar nele
  // não dava nada, porque o ponto do GPS nunca teve popup. Leva o mesmo
  // detalhe do marcador do veículo: é o mesmo carro. Despido, volta a ser um
  // ponto de posição e não tem detalhe nenhum a dar.
  mapa.setMarkerPopup('eu', vesteVeiculo ? popupMeuVeiculoHtml() : '');
}

const ESTADO_VEICULO_LABEL = {
  no_ponto: 'no ponto', na_fila: 'na fila', partiu_da_fila: 'em rota',
  em_movimento: 'em movimento', chegou_destino: 'chegou ao destino',
};

// Texto de estado do cartão "Veículo ativo". Num veículo urbano os estados da
// fila não dizem nada ("partiu_da_fila" num táxi que nunca esteve numa fila):
// o que lhe interessa é estar a receber pedidos e quantos lugares tem livres.
//
// A contagem de lugares aparece nos dois casos: é a mesma que o marcador do
// mapa mostra, sobe a cada embarque e desce a cada desembarque, e o condutor
// a conduzir não tem como abrir o popup para a ler.
function textoEstadoVeiculo(v) {
  if (!v) return '';
  // A ocupação ao vivo (com quem está mesmo dentro do carro) só existe para o
  // veículo ativo; nos outros fica-se pelos lugares livres da própria linha.
  const ocupacao = veiculoAtivoData && v.id === veiculoAtivoData.id ? ocupacaoVeiculo : null;
  const lugares = ocupacao
    ? ` · ${ocupacao.a_bordo} a bordo · ${ocupacao.lugares_livres}/${ocupacao.lugares_total} lugares livres`
    : (v.lugares_livres != null ? ` · ${v.lugares_livres}/${v.lugares_total} lugares livres` : '');

  if (v.tipo_servico === 'urbano') {
    return `Em circulação — a receber pedidos urbanos${lugares}`;
  }
  const estado = ESTADO_VEICULO_LABEL[v.estado] || v.estado;
  return `Estado: ${estado}${v.posicao_fila ? ' · posição na fila: ' + v.posicao_fila : ''}${lugares}`;
}

// Um veículo só urbano não tem ponto de partida nem fila (ver o comentário no
// cartão, mais acima): troca esse bloco pelo que lhe diz respeito, em vez de
// lhe mostrar campos que só o podem prejudicar. 'ambos' mantém o bloco do
// ponto — é por lá que passa a operar como intermunicipal.
function ajustarCartaoVeiculoAtivo(v) {
  const urbano = v?.tipo_servico === 'urbano';
  const blocoPonto = document.getElementById('bloco-ponto-condutor');
  const blocoUrbano = document.getElementById('bloco-urbano-condutor');
  if (blocoPonto) blocoPonto.style.display = urbano ? 'none' : '';
  if (blocoUrbano) blocoUrbano.style.display = urbano ? '' : 'none';
  atualizarBloqueioPonto(v);
}

// Guardar o ponto tranca-o durante 24 horas (o prazo é do servidor, que
// devolve 'ponto_bloqueado_ate' e recusa o POST até lá): o botão "Guardar"
// desaparece nesse período e as listas ficam bloqueadas, com uma linha a
// dizer a partir de quando volta. Antes do prazo, só o administrador o
// liberta ("Libertar ponto", no painel de administração).
//
// A reposição não precisa de temporizador próprio: o polling de 10s refresca
// a lista de veículos, e daí vem sempre a data atual — quer o prazo tenha
// passado, quer o administrador o tenha levantado.
function atualizarBloqueioPonto(v) {
  const btn = document.getElementById('btn-guardar-ponto');
  const aviso = document.getElementById('aviso-ponto-bloqueado');
  if (!btn || !aviso) return;

  const ate = v && v.ponto_bloqueado_ate ? new Date(v.ponto_bloqueado_ate) : null;
  const bloqueado = !!ate && !isNaN(ate.getTime()) && ate.getTime() > Date.now();

  btn.style.display = bloqueado ? 'none' : '';
  aviso.style.display = bloqueado ? '' : 'none';
  aviso.textContent = bloqueado
    ? `Ponto guardado. Só o pode voltar a alterar a partir de ${formatarData(v.ponto_bloqueado_ate)} — antes disso, apenas com autorização do administrador.`
    : '';

  // As listas acompanham o botão: deixá-las editáveis mostrava um ponto
  // escolhido que o servidor nunca chegaria a guardar.
  const selPonto = document.getElementById('sel-ponto-condutor');
  const selDestino = document.getElementById('sel-destino-condutor');
  if (selPonto) selPonto.disabled = bloqueado;
  if (selDestino) selDestino.disabled = bloqueado;
}

// ---- Menu em popup (antiga barra lateral) ----
// Fica montado no DOM mesmo fechado: as listas lá dentro continuam a ser
// atualizadas pelos polls, e ao abrir o condutor vê-as já em dia.
const menuCondutor = document.getElementById('menu-condutor');
const btnMenuCondutor = document.getElementById('btn-menu-condutor');

function menuCondutorAberto() {
  return menuCondutor?.getAttribute('aria-hidden') === 'false';
}

function abrirMenuCondutor() {
  if (!menuCondutor || menuCondutorAberto()) return;
  menuCondutor.classList.remove('invisible');
  menuCondutor.setAttribute('aria-hidden', 'false');
  btnMenuCondutor?.setAttribute('aria-expanded', 'true');
  // Um frame depois de tirar o 'invisible', senão a transição não corre.
  requestAnimationFrame(() => {
    document.getElementById('menu-condutor-fundo')?.classList.add('opacity-100');
    menuCondutor.querySelector('aside')?.classList.remove('-translate-x-full');
    menuCondutor.querySelector('aside')?.focus();
  });
}

function fecharMenuCondutor() {
  if (!menuCondutor || !menuCondutorAberto()) return;
  menuCondutor.setAttribute('aria-hidden', 'true');
  btnMenuCondutor?.setAttribute('aria-expanded', 'false');
  document.getElementById('menu-condutor-fundo')?.classList.remove('opacity-100');
  menuCondutor.querySelector('aside')?.classList.add('-translate-x-full');
  // 'invisible' só no fim do deslize (duration-200), para não o cortar.
  setTimeout(() => { if (!menuCondutorAberto()) menuCondutor.classList.add('invisible'); }, 200);
  btnMenuCondutor?.focus();
}

btnMenuCondutor?.addEventListener('click', abrirMenuCondutor);
document.getElementById('btn-abrir-menu-pagamento')?.addEventListener('click', abrirMenuCondutor);
document.getElementById('btn-menu-fechar')?.addEventListener('click', fecharMenuCondutor);
document.getElementById('menu-condutor-fundo')?.addEventListener('click', fecharMenuCondutor);
document.addEventListener('keydown', (ev) => {
  // Com um modal aberto por cima do menu, o Escape não é para o menu.
  if (ev.key !== 'Escape' || document.querySelector('.vm-modal-overlay[style*="flex"]')) return;
  fecharMenuCondutor();
});

document.getElementById('btn-ver-pedidos-urbanos')?.addEventListener('click', () => {
  // A lista vive numa secção que pode estar fechada: abre-a antes de rolar.
  const secao = document.getElementById('lista-passageiros-urbanos')?.closest('details');
  if (secao) secao.open = true;
  document.getElementById('lista-passageiros-urbanos')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

function atualizarBotoesEstadoVeiculo(estado) {
  atualizarIconeVeiculoAtivo(estado);
  atualizarIconeGpsCondutor();
  const btnEntrar = document.getElementById('btn-entrar-fila');
  const btnSair = document.getElementById('btn-sair-fila');
  if (estado === 'na_fila') {
    btnEntrar.style.display = 'none';
    btnSair.style.display = '';
    btnSair.textContent = 'Sair da fila';
  } else if (estado === 'no_ponto' || estado === 'chegou_destino') {
    // Parado (no ponto original ou acabado de chegar/inverter) — pode
    // entrar na fila ou partir logo, sem ter de passar pela fila.
    btnEntrar.style.display = '';
    btnSair.style.display = '';
    btnSair.textContent = 'Iniciar rota (sair do ponto)';
  } else {
    // partiu_da_fila / em_movimento — já a caminho; nada a fazer aqui até
    // chegar ao destino (inversão automática por GPS).
    btnEntrar.style.display = 'none';
    btnSair.style.display = 'none';
  }
}

// "Selecionar" na lista de veículos manda também o cartão de Pagamento para
// esse veículo: os dois cartões falam do mesmo veículo e, sem isto, era
// preciso trocar duas vezes — na lista e no seletor do pagamento — para ver
// o estado certo.
function sincronizarPagamentoComVeiculo(veiculoId) {
  const sel = document.getElementById('sel-veiculo-pagamento');
  // O cartão de Pagamento ainda pode estar a carregar; quando acabar, abre
  // já no veículo ativo (ver carregarPagamento).
  if (!sel || String(sel.value) === String(veiculoId)) return;
  if (!Array.from(sel.options).some(o => o.value === String(veiculoId))) return;
  sel.value = String(veiculoId);
  sel.dispatchEvent(new Event('change'));
}

function selecionarVeiculo(v) {
  // Antes do corte do MOSTRAR_MAPA de propósito: é precisamente o condutor
  // sem pagamento válido que precisa de ver o cartão de Pagamento saltar
  // para o veículo que acabou de escolher — para ele, o clique em
  // "Selecionar" não fazia rigorosamente nada.
  sincronizarPagamentoComVeiculo(v.id);
  if (!MOSTRAR_MAPA) return; // sem pagamento válido, o mapa/passageiros ficam bloqueados
  if (veiculoAtivoId !== v.id) {
    if (meuVeiculoMarcadoId) {
      mapa.removeMarker(meuVeiculoMarcadoId);
      meuVeiculoMarcadoId = null;
      meuVeiculoMarcadoEstado = null;
    }
    // A ocupação é de um veículo concreto: mantê-la ao trocar de veículo
    // punha no marcador do novo a lista de passageiros do anterior.
    ocupacaoVeiculo = null;
    // Fora do "if (meuVeiculoMarcadoId)" de propósito: um veículo ainda sem
    // marcador (sem posição gravada e sem o primeiro sinal de GPS) não parava
    // a navegação ao ser trocado, e a rota de recolha do veículo anterior
    // ficava no mapa por cima do veículo agora selecionado.
    pararNavegacao();
  }
  veiculoAtivoId = v.id;
  veiculoAtivoData = v;
  const cardVeiculoAtivo = document.getElementById('card-veiculo-ativo');
  // Ao escolher um veículo, a secção dele abre logo: é lá que está o passo
  // seguinte (ponto, destino, fila).
  if (cardVeiculoAtivo.style.display === 'none') cardVeiculoAtivo.open = true;
  cardVeiculoAtivo.style.display = 'block';
  ajustarCartaoVeiculoAtivo(v);
  // forcar: ao trocar de veículo as listas têm de passar para o veículo novo
  // mesmo que o par partida/destino calhe ser igual ao do anterior.
  sincronizarListasPontoVeiculo(v, true);
  document.getElementById('estado-veiculo-txt').textContent = textoEstadoVeiculo(v);
  atualizarBotoesEstadoVeiculo(v.estado);
  carregarPassageiros();
  carregarChatCondutor();
  tracarRotaCondutor();
  // Elegibilidade para pedidos urbanos depende do veículo selecionado
  // (tipo_servico + ponto definido) — refresca de imediato ao trocar de
  // veículo, sem esperar pelo polling de 10s.
  carregarPassageirosUrbanos();
  // Os pedidos em aberto do ponto são os do ponto DESTE veículo: trocar de
  // veículo (ou de ponto) muda a lista toda.
  carregarPassageirosDoPonto();
  if (v.ponto_partida_id) carregarOutrosCondutores(v.ponto_partida_id);
  iniciarPollingCondutor();
  atualizarEcraLigado();
  if (window.kgRt) window.kgRt.join(`veiculo:${v.id}`);
}

// Só no clique em "Selecionar" (não na seleção automática do arranque, que
// competia com o voo inicial para o GPS): fecha o menu e leva o mapa até ao
// veículo escolhido, já desenhado na melhor posição conhecida.
function mostrarVeiculoAtivoNoMapa() {
  if (!MOSTRAR_MAPA || !mapa || !veiculoAtivoData) return;
  const estado = meuVeiculoMarcadoEstado || veiculoAtivoData.estado || 'no_ponto';
  const pos = posicaoMeuVeiculo(estado);
  // Sem posição nenhuma (nem ponto nem GPS), o menu fecha na mesma: o
  // marcador aparece sozinho com o primeiro sinal de GPS (ver o watch).
  if (!pos) {
    fecharMenuCondutor();
    return;
  }
  if (meuVeiculoMarcadoId !== `v-${veiculoAtivoId}`) {
    desenharMeuVeiculo(`v-${veiculoAtivoId}`, pos.lat, pos.lng, estado);
  }
  // Parado no ponto, o veículo pode estar longe do condutor: continuar a
  // seguir o GPS arrastava a vista para longe dele à primeira leitura.
  aSeguirCondutor = veiculoEmRota(estado);
  if (!aSeguirCondutor) mapa.pararSeguimento();
  fecharMenuCondutor();
  mapa.flyTo(pos.lat, pos.lng, ZOOM_SEGUIMENTO);
}

document.getElementById('btn-guardar-ponto')?.addEventListener('click', async () => {
  if (!veiculoAtivoId) { alert('Selecione um veículo primeiro.'); return; }
  const pontoId = document.getElementById('sel-ponto-condutor').value;
  const destinoId = document.getElementById('sel-destino-condutor').value;
  // Com a opção "sem ponto" à cabeça das listas, guardar em branco passou a
  // ser possível: dizer já aqui o que falta é mais claro do que o 422 do
  // servidor, que vem no formato de validação e não em { erro }.
  if (!pontoId || !destinoId) { alert('Selecione o ponto de partida e o destino.'); return; }
  const resp = await fetch('/api/condutor/veiculo_ponto', {
    method: 'POST',
    body: fd({
      veiculo_id: veiculoAtivoId,
      ponto_partida_id: pontoId,
      destino_id: destinoId,
    }),
  });
  const json = await resp.json();
  if (!resp.ok) {
    // Um 422 de ponto trancado traz a data: aproveita-a para esconder já o
    // botão, senão ele ficava a convidar a um clique que nunca resulta.
    if (json.ponto_bloqueado_ate && veiculoAtivoData) {
      veiculoAtivoData.ponto_bloqueado_ate = json.ponto_bloqueado_ate;
      atualizarBloqueioPonto(veiculoAtivoData);
    }
    alert(json.erro || json.message || 'Não foi possível guardar o ponto.');
    return;
  }
  // Sem esperar pelo carregarMeusVeiculos (assíncrono): o botão tem de sair
  // no mesmo instante em que o condutor o clicou.
  if (veiculoAtivoData) {
    veiculoAtivoData.ponto_bloqueado_ate = json.ponto_bloqueado_ate;
    atualizarBloqueioPonto(veiculoAtivoData);
    // A rota do mapa lê o sentido daqui, e o carregarMeusVeiculos que traz os
    // valores do servidor é assíncrono: sem esta escrita, o tracarRotaCondutor
    // logo abaixo redesenhava o sentido anterior.
    veiculoAtivoData.ponto_partida_id = pontoId;
    veiculoAtivoData.destino_id = destinoId;
    sincronizarListasPontoVeiculo(veiculoAtivoData, true);
  }
  carregarMeusVeiculos();
  carregarOutrosCondutores(pontoId);
  carregarPassageirosUrbanos();
  carregarPassageirosDoPonto();
  tracarRotaCondutor();
});

document.getElementById('btn-entrar-fila')?.addEventListener('click', async () => {
  if (!veiculoAtivoId) return;
  const resp = await fetch('/api/condutor/fila_entrar', { method: 'POST', body: fd({ veiculo_id: veiculoAtivoId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro); return; }
  if (json.estado) sincronizarEstadoMeuVeiculo(json.estado);
  carregarMeusVeiculos();
});

document.getElementById('btn-sair-fila')?.addEventListener('click', () => {
  document.getElementById('modal-sair-fila').style.display = 'flex';
});
document.getElementById('btn-cancelar-saida').addEventListener('click', () => {
  document.getElementById('modal-sair-fila').style.display = 'none';
});
document.getElementById('btn-confirmar-saida').addEventListener('click', async () => {
  const motivo = document.getElementById('sel-motivo-saida').value;
  const resp = await fetch('/api/condutor/fila_sair', { method: 'POST', body: fd({ veiculo_id: veiculoAtivoId, motivo }) });
  const json = await resp.json();
  document.getElementById('modal-sair-fila').style.display = 'none';
  if (!resp.ok) { alert(json.erro); return; }
  // Sem esperar pelos 10s do polling: a rota acabou de começar, o GPS tem de
  // passar a mexer o veículo já no próximo tick.
  if (json.estado) sincronizarEstadoMeuVeiculo(json.estado);
  carregarMeusVeiculos();
  carregarPassageiros();
});

// Etiquetas legíveis dos estados da reserva: o valor cru da base de dados
// ('a_caminho') aparecia tal e qual no cartão do passageiro.
const ESTADO_RESERVA_LABEL = {
  pendente: 'Pendente',
  confirmado: 'Confirmado',
  a_caminho: 'A caminho',
  a_bordo: 'A bordo',
  concluido: 'Concluído',
  recusado: 'Recusado',
};

// Desenha/atualiza no mapa os passageiros associados ao veículo ativo, com a
// localização exata (GPS ao vivo) quando o passageiro já a enviou, ou o
// ponto de partida como fallback (secção 5.3 / 9.4). Visibilidade privada:
// esta lista só contém passageiros com reserva neste veículo (ver
// api/condutor/passageiros), nunca de outros veículos.
//
// Só entram no mapa os passageiros que o condutor já foi buscar (no_mapa,
// vindo do servidor): antes de "Ir buscar" ainda não há recolha em curso, e
// depois de "Embarcar" o passageiro deixa de ter marcador próprio e passa a
// contar dentro do marcador do condutor.
function atualizarMarcadoresPassageiros(passageiros) {
  const idsAnteriores = passageiroMarkerIds;
  const idsAtuais = new Set();
  passageiros.filter(p => p.no_mapa).forEach(p => {
    if (p.mapa_lat == null || p.mapa_lng == null) return;
    const id = `passageiro-${p.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(p.mapa_lat);
    const lng = parseFloat(p.mapa_lng);
    if (idsAnteriores.has(id)) {
      mapa.updateMarker(id, lat, lng);
    } else {
      // Popup completo (relatório "destino visível no popup do passageiro
      // para o condutor"): nome, estado, destino escrito, distância e
      // ações rápidas — antes só mostrava nome+estado, sem destino.
      const distancia = p.distancia_m != null ? (p.distancia_m >= 1000 ? (p.distancia_m / 1000).toFixed(1) + ' km' : Math.round(p.distancia_m) + ' m') : null;
      const popup = `<div class="kg-veiculo-popup">
        <strong>${esc(p.passageiro_nome)}</strong><br>
        <span>${esc(ESTADO_RESERVA_LABEL[p.estado] || p.estado)}${esc(p.passageiro_lat != null ? ' · GPS ao vivo' : ' · ponto de partida')}</span><br>
        ${linhaIcone('destino', 'Destino: ' + esc(p.ponto_descida_nome || 'destino final'))}${distancia ? `<br>${linhaIcone('distancia', esc(distancia))}` : ''}
      </div>`;
      mapa.addMarker(id, 'passageiro', lat, lng, { popupHtml: popup });
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) mapa.removeMarker(id); });
  passageiroMarkerIds = idsAtuais;
}

// Tira do mapa, já, o ponto de um passageiro que deixou de ter marcador
// próprio: entrou no carro, desceu ou foi recusado. A partir daí quem o
// representa é o marcador do condutor e a sua contagem de lugares. O
// carregarPassageiros() seguinte chegaria à mesma conclusão (o servidor
// devolve no_mapa = false), mas esperar por ele deixava o ponto aceso mais um
// instante, já com a pessoa sentada ao lado do condutor.
function retirarPassageiroDoMapa(reservaId) {
  const idMarcador = `passageiro-${reservaId}`;
  if (!mapa || !passageiroMarkerIds.has(idMarcador)) return false;
  mapa.removeMarker(idMarcador);
  passageiroMarkerIds.delete(idMarcador);
  return true;
}

async function carregarPassageiros() {
  const veiculoPedido = veiculoAtivoId;
  if (!veiculoPedido) return;
  const pedidoEm = Date.now();
  const json = await kgApiJSON(`/api/condutor/passageiros?veiculo_id=${veiculoPedido}`);
  // Entretanto o condutor trocou de veículo: aplicar esta resposta punha no
  // mapa os marcadores e a rota de recolha do veículo anterior.
  if (veiculoAtivoId !== veiculoPedido) return;
  // Só as reservas 'pendente' são pedidos novos: as outras já passaram pelo
  // condutor (confirmou, foi buscar), e uma que ele próprio reclamou chega
  // aqui já 'a_caminho'.
  registarPedidos('passageiros', String(veiculoPedido),
    (json.passageiros || []).filter(p => p.estado === 'pendente').map(p => ({
      id: p.id, texto: `Reserva de ${p.passageiro_nome}`,
    })));
  const lista = document.getElementById('lista-passageiros');
  lista.innerHTML = '';

  atualizarMarcadoresPassageiros(json.passageiros || []);
  aplicarOcupacao(json.ocupacao);

  // Restaura a rota de navegação para o destino sempre que há um passageiro
  // a bordo — não só no momento do clique em "Embarcar", mas também aqui,
  // que corre ao carregar o painel e a cada poll (10s). É isto que faz a
  // rota reaparecer depois de um reload ou de um logout/login: sem isto,
  // "navegacaoAlvo" ficava só em memória e esvaziava-se a cada refresh.
  const passageiroABordo = (json.passageiros || []).find(p => p.estado === 'a_bordo');
  const passageiroACaminho = (json.passageiros || []).find(p => p.no_mapa);

  // Uma recolha em curso tem de ser de uma reserva DESTE veículo. Sem esta
  // verificação, a rota de recolha do veículo anterior sobrevivia à troca de
  // veículo (o ramo de baixo só cancelava as rotas de tipo 'destino') e
  // continuava a ser recalculada de 30 em 30 s. Cancelada aqui, o ramo final
  // volta a arrancá-la a partir do passageiro certo, se este veículo tiver um.
  // Só uma resposta pedida DEPOIS de a navegação arrancar serve para a
  // cancelar: o poll de 10 s que já estava em voo quando o condutor carregou
  // em "Ir buscar" ainda traz a reserva por recolher e matava no berço a rota
  // que o clique acabou de traçar.
  if (navegacaoAlvo?.tipo === 'passageiro'
      && pedidoEm > navegacaoAlvo.iniciadoEm
      && !(json.passageiros || []).some(p => p.no_mapa && String(p.id) === navegacaoAlvo.reservaId)) {
    pararNavegacao();
  }

  if (passageiroABordo) {
    const alvo = destinoDoPassageiro(passageiroABordo);
    const jaANavegarParaAli = navegacaoAlvo?.tipo === 'destino' && alvo && navegacaoAlvo.lat === alvo.lat && navegacaoAlvo.lng === alvo.lng;
    if (alvo && !jaANavegarParaAli) iniciarNavegacao('destino', alvo.lat, alvo.lng, alvo.nome);
  } else if (navegacaoAlvo?.tipo === 'destino') {
    // Já não há ninguém a bordo (chegou/concluído) — a rota de destino
    // deixa de fazer sentido no mapa.
    pararNavegacao();
  } else if (passageiroACaminho && !navegacaoAlvo && passageiroACaminho.mapa_lat != null) {
    // Recolha a meio, sem navegação ativa: pelo mesmo motivo do bloco acima,
    // a rota até ao passageiro tem de renascer de 'a_caminho' (que está na
    // base de dados) e não do clique, que se perde num refresh.
    iniciarNavegacao('passageiro', parseFloat(passageiroACaminho.mapa_lat), parseFloat(passageiroACaminho.mapa_lng), passageiroACaminho.passageiro_nome, passageiroACaminho.id);
  }

  if (!json.passageiros || !json.passageiros.length) {
    lista.innerHTML = '<p class="vm-small">Sem passageiros neste momento.</p>';
    return;
  }

  json.passageiros.forEach(p => {
    const tel = (p.passageiro_telefone || '').replace(/\D/g, '');
    const wa = tel;
    const div = document.createElement('div');
    div.className = 'vm-card mb-2 space-y-2 p-4';
    const distancia = p.distancia_m != null ? (p.distancia_m >= 1000 ? (p.distancia_m / 1000).toFixed(1) + ' km' : p.distancia_m + ' m') : '—';
    div.innerHTML = `
      <div class="flex flex-wrap items-center justify-between gap-2">
        <strong>${esc(p.passageiro_nome)}</strong>
        <span class="vm-badge vm-badge--${esc(p.estado)}">${esc(ESTADO_RESERVA_LABEL[p.estado] || p.estado)}</span>
      </div>
      <p class="vm-small">${badgeViagem(p.tipo_viagem)} Embarque: ${esc(p.ponto_partida_nome)} (${esc(distancia)})</p>
      <p class="vm-small">${linhaIcone('destino', 'Destino: <strong>' + esc(p.ponto_descida_nome || 'destino final') + '</strong>')} · ${esc(p.preco_final)} CVE</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="vm-btn vm-btn--outline vm-btn--sm" data-chamar data-para-id="${esc(p.passageiro_id ?? '')}" data-para-nome="${esc(p.passageiro_nome)}">${svgIcone('telefone')} Chamar</button>
        <a href="https://wa.me/${esc(wa)}" target="_blank" class="vm-small inline-flex items-center gap-1.5 font-semibold text-emerald-600 hover:underline">${svgIcone('chat')} WhatsApp</a>
      </div>
      <div class="mt-2 flex flex-wrap gap-2">
        ${p.pode_ir_buscar ? `<button class="vm-btn vm-btn--outline vm-btn--sm" data-acao="ir_buscar" data-id="${esc(p.id)}" data-lat="${esc(p.mapa_lat ?? '')}" data-lng="${esc(p.mapa_lng ?? '')}" data-nome="${esc(p.passageiro_nome)}">${svgIcone('ponto')} Ir buscar</button>` : ''}
        ${p.estado === 'pendente' ? `
          <button class="vm-btn vm-btn--cta vm-btn--sm" data-acao="confirmar" data-id="${esc(p.id)}">Confirmar</button>
          <button class="vm-btn vm-btn--perigo vm-btn--sm" data-acao="recusar" data-id="${esc(p.id)}">Recusar</button>` : ''}
        ${p.no_mapa ? `<button class="vm-btn vm-btn--cta vm-btn--sm" data-acao="embarcar" data-id="${esc(p.id)}" ${p.pode_embarcar ? '' : 'disabled'} data-descida-lat="${esc(p.ponto_descida_lat ?? '')}" data-descida-lng="${esc(p.ponto_descida_lng ?? '')}" data-descida-nome="${esc(p.ponto_descida_nome ?? '')}">Embarcar</button>` : ''}
        ${p.estado === 'a_bordo' ? `<button class="vm-btn vm-btn--cta vm-btn--sm" data-acao="chegou" data-id="${esc(p.id)}" ${p.pode_entregar ? '' : 'disabled'}>Entregue</button>` : ''}
        ${['confirmado', 'a_caminho'].includes(p.estado) ? `<button class="vm-btn vm-btn--outline vm-btn--sm" data-acao="nao_apareceu" data-id="${esc(p.id)}">Não apareceu</button>` : ''}
        ${p.estado === 'a_bordo' ? `<button class="vm-btn vm-btn--perigo vm-btn--sm" data-acao="remover" data-id="${esc(p.id)}">Remover</button>` : ''}
      </div>
      ${p.motivo_bloqueio ? `<p class="vm-small font-semibold text-red-600">${esc(p.motivo_bloqueio)}</p>` : ''}`;
    lista.appendChild(div);
  });

  lista.querySelectorAll('[data-chamar]').forEach(btn => {
    btn.addEventListener('click', () => {
      const paraId = parseInt(btn.dataset.paraId, 10);
      if (!isNaN(paraId)) KGChamada.iniciar(paraId, btn.dataset.paraNome);
    });
  });

  lista.querySelectorAll('button[data-acao]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const acao = btn.dataset.acao;
      // Tirar alguém da viagem não se desfaz: pede um segundo toque, em vez
      // de um confirm() que congela a página (e o GPS) até ser fechado.
      // O texto de confirmação é curto e a largura fica presa de propósito:
      // um texto maior partia a linha e o botão fugia do dedo, e o segundo
      // toque, dado no mesmo sítio, caía no vazio.
      if (['nao_apareceu', 'remover'].includes(acao) && !btn.dataset.armado) {
        const texto = btn.textContent;
        btn.dataset.armado = '1';
        btn.style.minWidth = `${btn.offsetWidth}px`;
        btn.textContent = 'Confirmar?';
        setTimeout(() => {
          if (!btn.isConnected || btn.disabled) return;
          delete btn.dataset.armado;
          btn.textContent = texto;
        }, 4000);
        return;
      }
      // Trava o botão enquanto o pedido corre. Sem isto, um duplo clique em
      // "Embarcar" mandava dois pedidos e o segundo voltava com um erro ("já
      // está a bordo") de uma ação que afinal tinha corrido bem.
      if (btn.disabled) return;
      const textoOriginal = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'A processar…';

      let resp, json;
      try {
        resp = await fetch('/api/condutor/reserva_estado', {
          method: 'POST',
          body: fd({ reserva_id: btn.dataset.id, acao }),
        });
        json = await resp.json();
      } catch (e) {
        // Falha de rede: nada mudou no servidor, por isso o botão volta ao
        // que era e nenhum marcador é desenhado — o mapa nunca fica com um
        // passageiro "fantasma" de uma recolha que não chegou a existir.
        btn.disabled = false;
        btn.textContent = textoOriginal;
        alert('Sem ligação ao servidor — a ação não foi registada. Tente outra vez.');
        return;
      }

      if (!resp.ok) {
        btn.disabled = false;
        btn.textContent = textoOriginal;
        alert(json.erro);
        // Realinha lista e mapa com o estado real do servidor: se a ação foi
        // recusada porque outro condutor já lá chegou, é aqui que o painel
        // deixa de mostrar o que já não é verdade.
        carregarPassageiros();
        return;
      }

      if (window.kgRt) window.kgRt.emit('reserva:atualizada', { passageiroId: json.passageiro_id });
      if (json.lugares_livres !== null && window.kgRt) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
      // Marcador do condutor atualizado já com a contagem e a lista novas,
      // sem esperar pelo poll de 10 s.
      aplicarOcupacao(json.ocupacao);
      if (['embarcar', 'chegou', 'recusar', 'nao_apareceu', 'remover'].includes(acao)) retirarPassageiroDoMapa(btn.dataset.id);
      // Quem não apareceu era muitas vezes o alvo da recolha: a rota até ele
      // deixa de ter para onde ir. (A rota para o destino de quem foi
      // removido a bordo cai sozinha em carregarPassageiros.)
      if (acao === 'nao_apareceu' && navegacaoAlvo?.tipo === 'passageiro' && navegacaoAlvo.reservaId === String(btn.dataset.id)) pararNavegacao();
      // "Ir buscar" navega a sério (rota por estrada + distância/tempo), em
      // vez de só centrar o mapa num zoom fixo — relatório do mapa, tarefas
      // B/C. O passageiro aparece no mapa por atualizarMarcadoresPassageiros,
      // a partir do estado que o servidor acabou de gravar.
      if (acao === 'ir_buscar') {
        const lat = parseFloat(btn.dataset.lat);
        const lng = parseFloat(btn.dataset.lng);
        if (!isNaN(lat) && !isNaN(lng)) iniciarNavegacao('passageiro', lat, lng, btn.dataset.nome, btn.dataset.id);
      }
      // Depois de embarcar, a navegação passa a ser para o destino REAL
      // deste passageiro (ponto de descida que ele escreveu/escolheu) — usar
      // veiculos.destino_id era um bug: para viagens urbanas esse campo é só
      // um placeholder igual ao ponto de partida (nunca um destino real),
      // por isso a rota "desaparecia" (origem = destino, distância zero).
      // Sem ponto de descida específico (intermunicipal sem pin), cai no
      // destino do veículo, que nesse caso é sempre um ponto válido.
      if (acao === 'embarcar') {
        const alvo = destinoDoPassageiro({
          ponto_descida_lat: btn.dataset.descidaLat,
          ponto_descida_lng: btn.dataset.descidaLng,
          ponto_descida_nome: btn.dataset.descidaNome,
        });
        if (alvo) iniciarNavegacao('destino', alvo.lat, alvo.lng, alvo.nome);
      }
      carregarPassageiros();
      carregarMeusVeiculos();
    });
  });
}

// Marcadores dos pedidos de viagem urbana em aberto — visíveis mesmo sem
// veículo selecionado, para qualquer condutor aprovado e em dia (relatório
// "condutores fora do ponto veem passageiros urbanos"). Sem telefone/
// WhatsApp aqui — só depois de reclamado é que o contacto fica visível
// (mesma regra de privacidade já usada no resto do sistema).
function atualizarMarcadoresPassageirosUrbanos(passageiros) {
  if (!mapa) return;
  const idsAnteriores = passageiroUrbanoMarkerIds;
  const idsAtuais = new Set();
  passageiros.forEach(p => {
    if (p.passageiro_lat == null || p.passageiro_lng == null) return;
    const id = `urbano-${p.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(p.passageiro_lat);
    const lng = parseFloat(p.passageiro_lng);
    if (idsAnteriores.has(id)) {
      mapa.updateMarker(id, lat, lng);
    } else {
      mapa.addMarker(id, 'passageiro', lat, lng, {
        popupHtml: `<strong>${esc(p.passageiro_nome)}</strong><br>${linhaIcone('destino', esc(p.ponto_descida_nome))}`,
      });
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) mapa.removeMarker(id); });
  passageiroUrbanoMarkerIds = idsAtuais;
}

// Chamado ao receber 'urbano:reclamado' de outro condutor: tira já o
// passageiro da lista/mapa deste condutor, em vez de esperar até 10s pelo
// próximo poll (era essa janela que deixava dois condutores a caminho do
// mesmo passageiro — relatório "passageiro visível para todos após Buscar").
function removerPassageiroUrbanoDaLista(reservaId) {
  esquecerPedido('urbanos', reservaId);
  const btn = document.querySelector(`#lista-passageiros-urbanos [data-reclamar="${reservaId}"]`);
  if (btn) {
    btn.closest('.vm-card')?.remove();
    const lista = document.getElementById('lista-passageiros-urbanos');
    if (!lista.children.length) {
      lista.innerHTML = '<p class="vm-small">Nenhum pedido em aberto neste momento.</p>';
    }
  }
  const markerId = `urbano-${reservaId}`;
  if (passageiroUrbanoMarkerIds.has(markerId)) {
    mapa?.removeMarker(markerId);
    passageiroUrbanoMarkerIds.delete(markerId);
  }
}

async function carregarPassageirosUrbanos() {
  const params = new URLSearchParams();
  if (ultimaLatCondutor != null && ultimaLngCondutor != null) {
    params.set('lat', ultimaLatCondutor);
    params.set('lng', ultimaLngCondutor);
  }
  if (veiculoAtivoId) params.set('veiculo_id', veiculoAtivoId);
  const qs = params.toString() ? `?${params.toString()}` : '';
  const lista = document.getElementById('lista-passageiros-urbanos');
  const veiculoPedido = veiculoAtivoId;
  let json;
  try {
    json = await kgApiJSON(`/api/condutor/passageiros_urbanos${qs}`);
  } catch (e) {
    // Antes isto ficava em silêncio e mostrava sempre "Nenhum pedido em
    // aberto" — indistinguível de não haver mesmo procura. A causa mais
    // comum é a conta ainda não ter aprovação/pagamento válido (ver
    // VeiculoService::condutorPodeVerMapa); mostrar a
    // mensagem real evita o condutor pensar que não há passageiros quando
    // na verdade é a própria conta que ainda não tem acesso.
    lista.innerHTML = `<p class="vm-small text-red-600">${esc(e.message)}</p>`;
    return;
  }
  const passageiros = json.passageiros || [];
  atualizarMarcadoresPassageirosUrbanos(passageiros);

  if (json.elegivel === false) {
    registarPedidos('urbanos', null, []);
    lista.innerHTML = `<p class="vm-small">${esc(json.motivo)}</p>`;
    return;
  }

  // Sem veículo selecionado não há "Ir buscar", por isso também não há aviso.
  // O município entra no contexto: ao mudar de concelho, a lista toda é
  // nova para este condutor mas nenhum pedido acabou de chegar.
  registarPedidos('urbanos',
    veiculoPedido && veiculoPedido === veiculoAtivoId ? `${veiculoPedido}|${json.municipio || ''}` : null,
    passageiros.map(p => ({ id: p.id, texto: `Urbano: ${p.passageiro_nome}` })));

  if (!passageiros.length) {
    // Com o município no fim da frase, "não há pedidos" deixa de ser
    // ambíguo: o condutor vê logo que a procura é do concelho onde está e
    // não do país todo.
    lista.innerHTML = `<p class="vm-small">Nenhum pedido em aberto ${json.municipio ? 'em ' + esc(json.municipio) : 'neste momento'}.</p>`;
    return;
  }

  lista.innerHTML = '';
  passageiros.forEach(p => {
    const distancia = p.distancia_m != null ? (p.distancia_m >= 1000 ? (p.distancia_m / 1000).toFixed(1) + ' km' : p.distancia_m + ' m') : '—';
    const div = document.createElement('div');
    div.className = 'vm-card mb-2 space-y-2 p-4';
    div.innerHTML = `
      <strong>${esc(p.passageiro_nome)}</strong>
      <p class="vm-small">${linhaIcone('destino', 'Destino: <strong>' + esc(p.ponto_descida_nome) + '</strong>')} · ${linhaIcone('distancia', esc(distancia))} · ${esc(p.preco_final)} CVE</p>
      <button class="vm-btn vm-btn--cta vm-btn--sm vm-btn--full" data-reclamar="${esc(p.id)}" data-lat="${esc(p.passageiro_lat)}" data-lng="${esc(p.passageiro_lng)}" data-nome="${esc(p.passageiro_nome)}">${svgIcone('ponto')} Ir buscar</button>`;
    lista.appendChild(div);
  });

  lista.querySelectorAll('[data-reclamar]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!veiculoAtivoId) { alert('Selecione um veículo primeiro.'); return; }
      if (btn.disabled) return;
      const textoOriginal = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'A processar…';
      const resp = await fetch('/api/condutor/recolher_urbano', {
        method: 'POST',
        body: fd({ reserva_id: btn.dataset.reclamar, veiculo_id: veiculoAtivoId }),
      });
      const json2 = await resp.json();
      if (!resp.ok) { alert(json2.erro); btn.disabled = false; btn.textContent = textoOriginal; return; }
      if (window.kgRt) {
        window.kgRt.emit('reserva:atualizada', { passageiroId: json2.passageiro_id });
        window.kgRt.emit('urbano:reclamado', { reservaId: btn.dataset.reclamar });
      }
      const lat = parseFloat(btn.dataset.lat), lng = parseFloat(btn.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) iniciarNavegacao('passageiro', lat, lng, btn.dataset.nome, btn.dataset.reclamar);
      carregarPassageirosUrbanos();
      carregarPassageiros();
      carregarMeusVeiculos();
    });
  });
}

// Pedidos intermunicipais em aberto no ponto deste veículo: o passageiro
// escolheu o ponto (e o destino da carreira) sem escolher carro, por isso o
// pedido é de todos os condutores ancorados no mesmo ponto. Quem escolheu um
// lugar no mapa de assentos nunca aparece aqui — essa reserva já nasce
// presa a um único veículo e vai direta para "Passageiros".
function atualizarMarcadoresPassageirosDoPonto(passageiros) {
  if (!mapa) return;
  const idsAnteriores = passageiroPontoMarkerIds;
  const idsAtuais = new Set();
  passageiros.forEach(p => {
    if (p.mapa_lat == null || p.mapa_lng == null) return;
    const id = `pedido-ponto-${p.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(p.mapa_lat);
    const lng = parseFloat(p.mapa_lng);
    if (idsAnteriores.has(id)) {
      mapa.updateMarker(id, lat, lng);
    } else {
      // Sem telefone/WhatsApp: o contacto só aparece depois de reclamado,
      // a mesma regra de privacidade dos pedidos urbanos.
      mapa.addMarker(id, 'passageiro', lat, lng, {
        popupHtml: `<strong>${esc(p.passageiro_nome)}</strong><br>${linhaIcone('destino', esc(p.ponto_descida_nome || p.destino_nome))}`,
      });
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) mapa.removeMarker(id); });
  passageiroPontoMarkerIds = idsAtuais;
}

// Outro condutor do ponto reclamou o pedido: sai já da lista e do mapa, sem
// esperar pelos 10 s do polling — era essa janela que punha dois condutores
// a contar com o mesmo passageiro.
function removerPassageiroDoPontoDaLista(reservaId) {
  esquecerPedido('ponto', reservaId);
  const btn = document.querySelector(`#lista-passageiros-ponto [data-reclamar="${reservaId}"]`);
  if (btn) {
    btn.closest('.vm-card')?.remove();
    const lista = document.getElementById('lista-passageiros-ponto');
    if (!lista.children.length) {
      lista.innerHTML = '<p class="vm-small">Nenhum pedido em aberto neste ponto.</p>';
    }
  }
  const markerId = `pedido-ponto-${reservaId}`;
  if (passageiroPontoMarkerIds.has(markerId)) {
    mapa?.removeMarker(markerId);
    passageiroPontoMarkerIds.delete(markerId);
  }
}

async function carregarPassageirosDoPonto() {
  const lista = document.getElementById('lista-passageiros-ponto');
  if (!veiculoAtivoId) {
    atualizarMarcadoresPassageirosDoPonto([]);
    registarPedidos('ponto', null, []);
    lista.innerHTML = '<p class="vm-small">Selecione um veículo ativo.</p>';
    return;
  }

  const contextoPonto = `${veiculoAtivoId}|${veiculoAtivoData?.ponto_partida_id ?? ''}`;
  let json;
  try {
    json = await kgApiJSON(`/api/condutor/passageiros_ponto?veiculo_id=${veiculoAtivoId}`);
  } catch (e) {
    // Mesma razão do bloco equivalente em carregarPassageirosUrbanos: sem a
    // mensagem real, uma conta ainda por aprovar parecia um ponto sem procura.
    lista.innerHTML = `<p class="vm-small text-red-600">${esc(e.message)}</p>`;
    return;
  }

  const passageiros = json.passageiros || [];
  atualizarMarcadoresPassageirosDoPonto(passageiros);

  if (json.elegivel === false) {
    registarPedidos('ponto', null, []);
    lista.innerHTML = `<p class="vm-small">${esc(json.motivo)}</p>`;
    return;
  }

  const contextoAtual = `${veiculoAtivoId}|${veiculoAtivoData?.ponto_partida_id ?? ''}`;
  registarPedidos('ponto', contextoPonto === contextoAtual ? contextoAtual : null,
    passageiros.map(p => ({ id: p.id, texto: `No ponto: ${p.passageiro_nome}` })));

  if (!passageiros.length) {
    lista.innerHTML = '<p class="vm-small">Nenhum pedido em aberto neste ponto.</p>';
    return;
  }

  lista.innerHTML = '';
  passageiros.forEach(p => {
    const div = document.createElement('div');
    div.className = 'vm-card mb-2 space-y-2 p-4';
    // O destino do passageiro pode não ser o do condutor: o pedido é do
    // ponto, não da carreira. Fica à vista para o condutor decidir.
    const destino = p.ponto_descida_nome ? `${p.destino_nome} (${p.ponto_descida_nome})` : p.destino_nome;
    div.innerHTML = `
      <strong>${esc(p.passageiro_nome)}</strong>${p.mesmo_destino ? '' : ' <span class="vm-badge">Outro destino</span>'}
      <p class="vm-small">${linhaIcone('destino', 'Destino: <strong>' + esc(destino) + '</strong>')} · ${esc(p.lugares)} lugar(es) · ${esc(p.preco_final)} CVE</p>
      <button class="vm-btn vm-btn--cta vm-btn--sm vm-btn--full" data-reclamar="${esc(p.id)}" data-lat="${esc(p.mapa_lat)}" data-lng="${esc(p.mapa_lng)}" data-nome="${esc(p.passageiro_nome)}">${svgIcone('ponto')} Ir buscar</button>`;
    lista.appendChild(div);
  });

  lista.querySelectorAll('[data-reclamar]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      const textoOriginal = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'A processar…';
      const resp = await fetch('/api/condutor/recolher_pedido', {
        method: 'POST',
        body: fd({ reserva_id: btn.dataset.reclamar, veiculo_id: veiculoAtivoId }),
      });
      const json2 = await resp.json();
      if (!resp.ok) { alert(json2.erro); btn.disabled = false; btn.textContent = textoOriginal; return; }
      if (window.kgRt) {
        window.kgRt.emit('reserva:atualizada', { passageiroId: json2.passageiro_id });
        window.kgRt.emit('pedido:reclamado', { reservaId: btn.dataset.reclamar });
      }
      const lat = parseFloat(btn.dataset.lat), lng = parseFloat(btn.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) iniciarNavegacao('passageiro', lat, lng, btn.dataset.nome, btn.dataset.reclamar);
      carregarPassageirosDoPonto();
      carregarPassageiros();
      carregarMeusVeiculos();
    });
  });
}

let chatCondutorUltimoIdVisto = 0;
let chatCondutorUltimoVeiculoId = null;
async function carregarChatCondutor() {
  if (!veiculoAtivoId) return;
  if (chatCondutorUltimoVeiculoId !== veiculoAtivoId) {
    chatCondutorUltimoVeiculoId = veiculoAtivoId;
    chatCondutorUltimoIdVisto = 0;
  }
  const resp = await fetch(`/api/comunicacao/listar?veiculo_id=${veiculoAtivoId}`);
  if (!resp.ok) return;
  const json = await resp.json();
  const mensagens = json.mensagens || [];

  const novasDeOutrem = mensagens.filter(m => m.id > chatCondutorUltimoIdVisto && m.remetente_id !== UTILIZADOR_ID);
  if (chatCondutorUltimoIdVisto > 0 && novasDeOutrem.length > 0) KGSons.tocarMensagem();
  if (mensagens.length) chatCondutorUltimoIdVisto = Math.max(chatCondutorUltimoIdVisto, ...mensagens.map(m => m.id));

  const box = document.getElementById('chat-mensagens-condutor');
  box.innerHTML = mensagens.map(m => `
    <div class="${esc(m.remetente_id === UTILIZADOR_ID ? 'text-right' : 'text-left')}">
      <span class="vm-small inline-block max-w-[80%] rounded-xl px-2.5 py-1.5 ${esc(m.remetente_id === UTILIZADOR_ID ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200')}">
        ${m.destinatario_id === null ? `<span class="mr-1 inline-flex align-middle text-brand-700" title="Autofalante">${svgIcone('comunicar', 'h-3.5 w-3.5')}</span>` : ''}<strong>${esc(m.remetente_id !== UTILIZADOR_ID ? m.remetente_nome + ': ' : '')}</strong>${esc(m.mensagem)}
      </span>
    </div>`
  ).join('') || '<p class="vm-small">Sem mensagens ainda.</p>';
  box.scrollTop = box.scrollHeight;

  // Fila de chamadas: passageiros com mensagens por ler dirigidas ao condutor.
  const pendentes = new Map();
  mensagens.filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    pendentes.set(m.remetente_id, (pendentes.get(m.remetente_id) || { nome: m.remetente_nome, count: 0 }));
    pendentes.get(m.remetente_id).count++;
  });
  const filaEl = document.getElementById('fila-chamadas');
  filaEl.innerHTML = [...pendentes.entries()].map(([id, info]) => `
    <button type="button" class="vm-btn vm-btn--sm vm-btn--perigo" data-responder="${esc(id)}">${svgIcone('telefone')} ${esc(info.nome)} (${esc(info.count)})</button>`
  ).join('');
  filaEl.querySelectorAll('[data-responder]').forEach(btn => {
    btn.addEventListener('click', () => { document.getElementById('chat-responder-a').value = btn.dataset.responder; });
  });

  // Marca como lidas as mensagens recebidas.
  mensagens.filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    fetch('/api/comunicacao/marcar_lida', { method: 'POST', body: fd({ id: m.id }) });
  });

  // Opções do select "responder a": todos os passageiros que já falaram neste veículo.
  const sel = document.getElementById('chat-responder-a');
  const atual = sel.value;
  const remetentes = new Map();
  mensagens.filter(m => m.remetente_id !== UTILIZADOR_ID).forEach(m => remetentes.set(m.remetente_id, m.remetente_nome));
  sel.innerHTML = '<option value="">Autofalante (todos os passageiros)</option>' +
    [...remetentes.entries()].map(([id, nome]) => `<option value="${esc(id)}">${esc(nome)}</option>`).join('');
  sel.value = atual;
}
// form-chat-condutor só existe no HTML quando MOSTRAR_MAPA é true (gate de
// pagamento — ver includes/page_guard e a variável $mostrarMapa acima).
// Sem esta guarda, um condutor com pagamento pendente rebentava aqui
// (elemento null) e o resto do script nem chegava a correr.
const formChatCondutor = document.getElementById('form-chat-condutor');
if (formChatCondutor) formChatCondutor.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const input = document.getElementById('chat-input-condutor');
  const texto = input.value.trim();
  if (!texto || !veiculoAtivoId) return;
  const destinatarioId = document.getElementById('chat-responder-a').value;
  input.value = '';
  const resp = await fetch('/api/comunicacao/enviar', {
    method: 'POST',
    body: fd(destinatarioId ? { veiculo_id: veiculoAtivoId, mensagem: texto, destinatario_id: destinatarioId } : { veiculo_id: veiculoAtivoId, mensagem: texto }),
  });
  if (resp.ok && window.kgRt) window.kgRt.emit('comunicacao:nova', { veiculoId: veiculoAtivoId });
  carregarChatCondutor();
});

// Desenha no mapa os outros veículos aprovados da mesma rota — parados no
// ponto (com a ordem da fila em badge) e também os que já vão a caminho, com
// a posição GPS real deles (secção 9.6). A cor/classe do marcador vem sempre
// do estado real via KGMap.veiculoEstadoClasse, por isso quem está em
// movimento já se distingue de quem está na fila sem mais nada aqui.
function atualizarMarcadoresOutrosCondutores(condutores) {
  const idsAnteriores = outroCondutorMarkerIds;
  const idsAtuais = new Set();
  condutores.forEach(c => {
    if (c.lat == null || c.lng == null) return;
    const id = `outro-v-${c.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(c.lat);
    const lng = parseFloat(c.lng);
    // Recria o marcador quando o estado real mudou (ex: saiu da fila) — só
    // mover a posição não bastaria para atualizar a cor/badge (tarefa 5).
    if (idsAnteriores.has(id) && outroCondutorMarcadoEstado.get(id) === c.estado) {
      // Desliza até à posição nova: as posições dos outros veículos chegam de
      // 10 em 10 s e, colocadas de uma vez, faziam-nos aparecer de sítio em
      // sítio no mapa do condutor.
      mapa.moveMarkerSuave(id, lat, lng);
    } else {
      if (idsAnteriores.has(id)) mapa.removeMarker(id);
      const naFila = c.estado === 'na_fila';
      mapa.addMarker(id, 'veiculo', lat, lng, {
        classes: KGMap.veiculoEstadoClasse(c.estado),
        badge: naFila ? String(c.posicao_fila) : null,
        html: KGMap.veiculoIconeHtml(c),
        popupHtml: KGMap.veiculoPopupHtml(c),
      });
      outroCondutorMarcadoEstado.set(id, c.estado);
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) { mapa.removeMarker(id); outroCondutorMarcadoEstado.delete(id); } });
  outroCondutorMarkerIds = idsAtuais;
}

// Texto da situação de um colega na lista: posição na fila para quem está
// parado, ou "a caminho" com a distância até nós para quem já saiu do ponto
// (a distância só aparece quando ambos os GPS são conhecidos — nunca é
// estimada a partir do ponto de partida, que para um veículo em movimento já
// não diz nada).
function situacaoOutroCondutor(c) {
  if (!c.em_rota) return c.estado === 'na_fila' ? ` · Fila #${c.posicao_fila}` : '';
  const temPosicoes = ultimaLatCondutor != null && ultimaLngCondutor != null && c.lat != null && c.lng != null;
  if (!temPosicoes) return ' · a caminho';
  const m = KGMap.distanciaMetros(ultimaLatCondutor, ultimaLngCondutor, parseFloat(c.lat), parseFloat(c.lng));
  return ` · a caminho (${m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m'})`;
}

async function carregarOutrosCondutores(pontoId) {
  if (!pontoId) return;
  const qsVeiculo = veiculoAtivoId ? `&veiculo_id=${veiculoAtivoId}` : '';
  const json = await kgApiJSON(`/api/condutor/outros_condutores?ponto_id=${pontoId}${qsVeiculo}`);
  const lista = document.getElementById('lista-outros-condutores');
  lista.innerHTML = '';

  atualizarMarcadoresOutrosCondutores(json.condutores || []);

  if (!json.condutores || !json.condutores.length) {
    lista.innerHTML = '<p class="vm-small">Nenhum outro condutor no ponto nem nesta rota.</p>';
    return;
  }
  json.condutores.forEach(c => {
    const wa = (c.condutor_telefone || '').replace(/\D/g, '');
    const div = document.createElement('div');
    div.className = 'vm-small border-b border-slate-100 py-1.5 last:border-b-0';
    div.innerHTML = `${esc(c.matricula)} (${esc(c.condutor_nome)})${esc(situacaoOutroCondutor(c))} — <a href="https://wa.me/${esc(wa)}" target="_blank" class="font-semibold text-emerald-600 hover:underline">WhatsApp</a>`;
    lista.appendChild(div);
  });
}

// Novo veículo
let rotasFixasCarregadas = false;
document.getElementById('veiculo-tipo-servico').addEventListener('change', (ev) => {
  document.getElementById('campo-rota-fixa').style.display = ev.target.value === 'urbano' ? 'none' : 'block';
});
// Capacidade por tipo (o táxi leva 4 e o Hiace 14) e o teto de cada um: os
// mesmos mapas que o servidor usa (VeiculoService::lugaresTotal e
// lugaresMax), para o campo já nascer com o número certo e não deixar
// escrever um valor que o registo ia recusar.
const LUGARES_POR_TIPO = @json(config('vmillion.veiculos.lugares_por_tipo'));
const LUGARES_MAX_POR_TIPO = @json(config('vmillion.veiculos.lugares_max_por_tipo'));
const LUGARES_POR_OMISSAO = @json((int) config('vmillion.veiculos.lugares_total'));
// Só o valor que o condutor escreveu à mão sobrevive a uma troca de tipo:
// sem isto, passar de Hiace para Táxi deixava 14 lugares num táxi.
let lugaresEditadosAMao = false;
function mostrarLugaresDoTipo() {
  const tipo = document.getElementById('veiculo-tipo').value;
  const campo = document.getElementById('veiculo-lugares');
  const origem = LUGARES_POR_TIPO[tipo] ?? LUGARES_POR_OMISSAO;
  const maximo = Math.max(origem, LUGARES_MAX_POR_TIPO[tipo] ?? 0);
  campo.max = String(maximo);
  if (!lugaresEditadosAMao || parseInt(campo.value, 10) > maximo) {
    campo.value = String(origem);
    lugaresEditadosAMao = false;
  }
  document.getElementById('veiculo-lugares-ajuda').textContent =
    `Sem contar com o condutor · entre 1 e ${maximo} para este tipo. Depois de registar, pode tirar ou acrescentar lugares no botão "Lugares" do veículo.`;
}
document.getElementById('veiculo-lugares').addEventListener('input', () => { lugaresEditadosAMao = true; });
document.getElementById('veiculo-tipo').addEventListener('change', mostrarLugaresDoTipo);
mostrarLugaresDoTipo();
document.getElementById('btn-novo-veiculo').addEventListener('click', async () => {
  if (!rotasFixasCarregadas) {
    const json = await kgApiJSON('/api/condutor/rotas_fixas');
    const sel = document.getElementById('veiculo-rota-fixa');
    (json.rotas || []).forEach(r => sel.add(new Option(`${r.origem_nome} → ${r.destino_nome} (${r.preco_fixo_cve} CVE)`, r.id)));
    rotasFixasCarregadas = true;
  }
  document.getElementById('modal-veiculo').style.display = 'flex';
});
document.getElementById('btn-fechar-veiculo').addEventListener('click', () => {
  document.getElementById('modal-veiculo').style.display = 'none';
});
document.getElementById('form-veiculo').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/condutor/veiculos', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('veiculo-msg');
  if (!resp.ok) { msg.classList.remove('e-sucesso'); msg.textContent = json.erro || 'Erro ao registar.'; return; }
  msg.classList.add('e-sucesso');
  msg.textContent = json.mensagem;
  carregarMeusVeiculos();
  setTimeout(() => { document.getElementById('modal-veiculo').style.display = 'none'; msg.textContent=''; ev.target.reset(); lugaresEditadosAMao = false; mostrarLugaresDoTipo(); }, 1500);
});

// Lugares do veículo: o condutor adiciona e remove passageiros que entram
// sem app. Adicionar ocupa um lugar livre, remover devolve-o — a capacidade
// do carro não muda aqui. As regras são todas do servidor
// (VeiculoService::ocuparSemApp e ::libertarSemApp); aqui só se desenha o
// mapa que ele devolve.
let lugaresVeiculoId = null;
let lugaresAPedir = false;

function desenharLugares(json) {
  // Um lugar tirado em versões anteriores do painel não existe no carro:
  // não entra no mapa nem nas contas.
  const assentos = (json.assentos || []).filter(a => a.ativo);
  const semApp = assentos.filter(a => a.ocupado_sem_app).length;
  const ocupados = json.lugares_total - json.lugares_livres;
  document.getElementById('lugares-resumo').textContent =
    `${ocupados} ocupado${ocupados === 1 ? '' : 's'} · ${json.lugares_livres} livre${json.lugares_livres === 1 ? '' : 's'} · ${json.lugares_total} lugares`;
  document.getElementById('btn-passageiro-adicionar').disabled = json.lugares_livres < 1;
  document.getElementById('btn-passageiro-remover').disabled = semApp < 1;

  const grid = document.getElementById('lugares-grid');
  grid.innerHTML = '';
  const filas = {};
  assentos.forEach(a => { (filas[a.fila] = filas[a.fila] || []).push(a); });

  Object.keys(filas).sort((a, b) => a - b).forEach(filaNum => {
    const filaEl = document.createElement('div');
    filaEl.className = 'vm-assentos__fila' + (filaNum === '0' ? ' vm-assentos__fila--condutor' : '');
    filas[filaNum].sort((a, b) => a.coluna - b.coluna).forEach(a => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = a.numero;
      btn.style.gridColumnStart = a.coluna;
      if (a.ocupado_sem_app) {
        btn.className = 'vm-assento vm-assento--sem-app';
        btn.title = `${a.nome_sem_app || 'Passageiro sem app'} — toque para remover`;
        btn.addEventListener('click', () => alterarLugar('libertar', a.id));
      } else if (a.ocupado) {
        btn.className = 'vm-assento vm-assento--ocupado';
        btn.title = 'Reservado pela app';
        btn.disabled = true;
      } else {
        btn.className = 'vm-assento vm-assento--livre';
        btn.title = 'Lugar livre — toque para sentar um passageiro';
        btn.addEventListener('click', () => alterarLugar('ocupar', a.id, document.getElementById('lugares-nome').value));
      }
      filaEl.appendChild(btn);
    });
    grid.appendChild(filaEl);
  });
}

async function abrirModalLugares(v) {
  lugaresVeiculoId = v.id;
  const msg = document.getElementById('lugares-msg');
  msg.textContent = '';
  document.getElementById('lugares-matricula').textContent = v.matricula;
  document.getElementById('lugares-resumo').textContent = 'A carregar...';
  document.getElementById('lugares-grid').innerHTML = '';
  document.getElementById('modal-lugares').style.display = 'flex';
  try {
    desenharLugares(await kgApiJSON(`/api/condutor/assentos?veiculo_id=${v.id}`));
  } catch (e) {
    msg.textContent = e.message;
  }
}

async function alterarLugar(acao, assentoId, nome) {
  // Um toque de cada vez: dois seguidos no mesmo lugar tiravam-no e logo o
  // repunham com o mapa ainda por redesenhar.
  if (lugaresAPedir) return;
  lugaresAPedir = true;
  const msg = document.getElementById('lugares-msg');
  msg.textContent = '';
  try {
    const resp = await fetch('/api/condutor/veiculo_lugares', {
      method: 'POST',
      body: fd({ veiculo_id: lugaresVeiculoId, acao, assento_id: assentoId, nome: nome || null }),
    });
    if (resp.status === 401) { window.location.href = '/login'; return; }
    const json = await resp.json();
    if (!resp.ok) { msg.textContent = json.erro || 'Não foi possível alterar os lugares.'; return; }
    if (acao === 'ocupar') document.getElementById('lugares-nome').value = '';
    desenharLugares(json);
    carregarMeusVeiculos();
    if (json.veiculo_id === veiculoAtivoId) carregarPassageiros();
    if (window.kgRt) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
  } catch (e) {
    msg.textContent = 'Sem ligação ao servidor — os lugares ficaram como estavam.';
  } finally {
    lugaresAPedir = false;
  }
}

// Sem lugar escolhido: o servidor senta no primeiro livre e remove o último
// passageiro sem app sentado.
document.getElementById('btn-passageiro-adicionar').addEventListener('click', () => {
  alterarLugar('ocupar', null, document.getElementById('lugares-nome').value);
});
document.getElementById('btn-passageiro-remover').addEventListener('click', () => alterarLugar('libertar'));
document.getElementById('btn-fechar-lugares').addEventListener('click', () => {
  document.getElementById('modal-lugares').style.display = 'none';
});

// Notificações
const estadoBadgeTipoNotif = { alerta: 'pendente', informativo: 'confirmado', urgente: 'recusado' };
async function carregarNotificacoes() {
  const json = await kgApiJSON('/api/notificacoes/listar');
  const badge = document.getElementById('badge-notificacoes');
  if (json.nao_lidas > 0) { badge.style.display = 'inline'; badge.textContent = json.nao_lidas; } else { badge.style.display = 'none'; }

  const urgenteNaoLida = (json.notificacoes || []).find(n => n.tipo === 'urgente' && !n.lida);
  if (urgenteNaoLida && document.getElementById('modal-notificacao-urgente').style.display !== 'flex') {
    document.getElementById('urgente-titulo').textContent = urgenteNaoLida.titulo;
    document.getElementById('urgente-mensagem').textContent = urgenteNaoLida.mensagem;
    document.getElementById('modal-notificacao-urgente').style.display = 'flex';
    document.getElementById('btn-fechar-urgente').onclick = async () => {
      await fetch('/api/notificacoes/marcar_lida', { method: 'POST', body: fd({ id: urgenteNaoLida.id }) });
      document.getElementById('modal-notificacao-urgente').style.display = 'none';
      carregarNotificacoes();
    };
  }

  document.getElementById('lista-notificacoes').innerHTML = (json.notificacoes || []).map(n => `
    <div class="vm-card p-4 ${esc(n.lida ? 'opacity-60' : '')}">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <strong>${esc(n.titulo)}</strong>
        <span class="vm-badge vm-badge--${esc(estadoBadgeTipoNotif[n.tipo])}">${esc(n.tipo)}</span>
      </div>
      <p class="vm-small">${esc(n.mensagem)}</p>
      <p class="vm-small mt-1 text-xs text-slate-400">${esc(formatarData(n.criado_em))}</p>
      ${!n.lida ? `<button class="vm-btn vm-btn--sm vm-btn--outline" data-marcar-lida="${esc(n.id)}">Marcar como lida</button>` : ''}
    </div>`
  ).join('') || '<p class="vm-small">Sem notificações.</p>';

  document.querySelectorAll('[data-marcar-lida]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/notificacoes/marcar_lida', { method: 'POST', body: fd({ id: btn.dataset.marcarLida }) });
      carregarNotificacoes();
    });
  });
}
document.getElementById('btn-notificacoes').addEventListener('click', () => {
  document.getElementById('modal-notificacoes').style.display = 'flex';
  carregarNotificacoes();
});
document.getElementById('btn-fechar-notificacoes').addEventListener('click', () => {
  document.getElementById('modal-notificacoes').style.display = 'none';
});
carregarNotificacoes();
setInterval(carregarNotificacoes, 15000);

// ---------------------------------------------------------------------
// Pedidos novos — o aviso para o condutor distraído
// ---------------------------------------------------------------------
// As listas já se atualizavam a cada 10 s, mas em silêncio: com o menu
// fechado e os olhos na estrada, um passageiro novo só era descoberto por
// acaso. Cada lista guarda os ids que já mostrou; um id que aparece de novo
// no mesmo contexto (mesmo veículo, mesmo ponto, mesmo concelho) é um pedido
// que acabou de chegar, e esse toca, vibra, pisca o título e aparece em
// cima do mapa.
//
// Com a app fechada ou o ecrã bloqueado o JavaScript da página nem corre: aí
// quem avisa é o push do servidor (App\Services\AvisoPedidoService).

const SECAO_DA_LISTA = {
  urbanos: 'lista-passageiros-urbanos',
  ponto: 'lista-passageiros-ponto',
  passageiros: 'lista-passageiros',
};
const LEMBRETE_PEDIDO_MS = 20000;
const LEMBRETES_PEDIDO_MAX = 5;
const TITULO_PAINEL = document.title;

// Por lista: o contexto em que os ids foram vistos e os ids em si.
const pedidosVistos = {};
// Pedidos que avisaram e o condutor ainda não viu: chave "lista:id".
const pedidosPorVer = new Map();
let lembretePedidoTimer = null;
let lembretesPedido = 0;
let tituloPedidoTimer = null;

/**
 * Regista o que a lista mostra agora e avisa dos pedidos que não estavam lá.
 * contexto null = esta lista não está a receber pedidos (sem veículo, não
 * elegível): esquece o que viu, para a primeira leitura válida a seguir não
 * ser tratada como uma enxurrada de pedidos novos.
 */
function registarPedidos(lista, contexto, pedidos) {
  const anterior = pedidosVistos[lista];
  const ids = new Set(pedidos.map(p => String(p.id)));
  pedidosVistos[lista] = contexto === null ? null : { contexto, ids };

  // Os que saíram da lista (reclamados, cancelados) deixam de estar por ver.
  for (const chave of Array.from(pedidosPorVer.keys())) {
    const [dono, id] = chave.split(':');
    if (dono === lista && (contexto === null || !ids.has(id))) pedidosPorVer.delete(chave);
  }

  // Primeira leitura (arranque, troca de veículo/ponto/concelho): é a lista
  // que já existia, não chegou nada.
  if (contexto === null || !anterior || anterior.contexto !== contexto) {
    desenharAlertaPedido();
    return;
  }

  const novos = pedidos.filter(p => !anterior.ids.has(String(p.id)));
  novos.forEach(p => pedidosPorVer.set(`${lista}:${p.id}`, { lista, texto: p.texto }));
  if (novos.length) tocarAlertaPedido(novos[0]);
  desenharAlertaPedido();
}

function esquecerPedido(lista, reservaId) {
  pedidosPorVer.delete(`${lista}:${reservaId}`);
  pedidosVistos[lista]?.ids.delete(String(reservaId));
  desenharAlertaPedido();
}

function vibrarPedido() {
  try { navigator.vibrate?.([400, 150, 400, 150, 400]); } catch (e) { /* sem vibração, fica o som */ }
}

function tocarAlertaPedido(pedido) {
  KGSons.tocarPedido();
  vibrarPedido();

  // Separador em segundo plano (outro separador à frente, app minimizada no
  // computador): o som pode passar despercebido, a notificação do sistema
  // não. A tag é a mesma do push do servidor para o mesmo pedido, e um
  // substitui o outro em vez de aparecerem dois.
  if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
    navigator.serviceWorker?.ready.then((reg) => reg.showNotification('Novo pedido', {
      body: pedido.texto,
      icon: '/assets/icons/icon-192.png',
      tag: `vm-pedido-${pedido.id}`,
      renotify: true,
      requireInteraction: true,
      data: { url: '/condutor/painel' },
    })).catch(() => {});
  }

  // Lembrete enquanto ninguém olhar: um só toque perde-se numa rotunda.
  lembretesPedido = 0;
  clearInterval(lembretePedidoTimer);
  lembretePedidoTimer = setInterval(() => {
    if (!pedidosPorVer.size || lembretesPedido >= LEMBRETES_PEDIDO_MAX) {
      clearInterval(lembretePedidoTimer);
      lembretePedidoTimer = null;
      return;
    }
    lembretesPedido++;
    KGSons.tocarPedido();
    vibrarPedido();
  }, LEMBRETE_PEDIDO_MS);
}

function desenharAlertaPedido() {
  const alerta = document.getElementById('alerta-pedido');
  if (!alerta) return;
  const total = pedidosPorVer.size;

  if (!total) {
    alerta.hidden = true;
    clearInterval(lembretePedidoTimer);
    lembretePedidoTimer = null;
    clearInterval(tituloPedidoTimer);
    tituloPedidoTimer = null;
    document.title = TITULO_PAINEL;
    return;
  }

  const ultimo = Array.from(pedidosPorVer.values()).pop();
  document.getElementById('alerta-pedido-titulo').textContent = total === 1 ? 'Novo pedido' : `${total} pedidos novos`;
  document.getElementById('alerta-pedido-texto').textContent = ultimo.texto;
  alerta.hidden = false;

  // O título a piscar é o que se vê na barra de separadores e na lista de
  // apps abertas, com o painel atrás de outra coisa.
  if (!tituloPedidoTimer) {
    let ligado = false;
    tituloPedidoTimer = setInterval(() => {
      ligado = !ligado;
      document.title = ligado ? `(${pedidosPorVer.size}) Novo pedido!` : TITULO_PAINEL;
    }, 1000);
  }
}

function dispensarAlertaPedido() {
  pedidosPorVer.clear();
  desenharAlertaPedido();
}

document.getElementById('alerta-pedido-fechar')?.addEventListener('click', dispensarAlertaPedido);
document.getElementById('alerta-pedido-ver')?.addEventListener('click', () => {
  const ultimo = Array.from(pedidosPorVer.values()).pop();
  const lista = document.getElementById(SECAO_DA_LISTA[ultimo?.lista] || 'lista-passageiros-urbanos');
  dispensarAlertaPedido();
  abrirMenuCondutor();
  const secao = lista?.closest('details');
  if (secao) secao.open = true;
  // Depois do deslize do menu (duration-200), senão rola dentro de um painel
  // ainda fora do ecrã.
  setTimeout(() => lista?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250);
});

// ---------------------------------------------------------------------
// Ecrã ligado (Screen Wake Lock)
// ---------------------------------------------------------------------
// Com o ecrã bloqueado, o browser congela a página: nem polling, nem som,
// nem aviso. Enquanto há um veículo selecionado o painel pede ao sistema para
// não apagar o ecrã. O sistema larga o pedido sempre que a página deixa de
// estar visível, por isso volta a ser feito quando ela regressa.
const CHAVE_ECRA_LIGADO = 'kg_ecra_ligado';
let wakeLock = null;
let wakeLockAPedir = false;

function ecraLigadoPreferido() {
  try { return localStorage.getItem(CHAVE_ECRA_LIGADO) !== '0'; } catch (e) { return true; }
}

async function atualizarEcraLigado() {
  if (!('wakeLock' in navigator)) return;
  const querer = ecraLigadoPreferido() && !!veiculoAtivoId && document.visibilityState === 'visible';

  if (!querer) {
    if (wakeLock) { wakeLock.release().catch(() => {}); wakeLock = null; }
    return;
  }
  if (wakeLock || wakeLockAPedir) return;

  wakeLockAPedir = true;
  try {
    wakeLock = await navigator.wakeLock.request('screen');
    wakeLock.addEventListener('release', () => { wakeLock = null; });
  } catch (e) {
    // Bateria fraca, modo de poupança, ou o browser recusou: o painel segue
    // sem isto, só o ecrã é que apaga como de costume.
    wakeLock = null;
  } finally {
    wakeLockAPedir = false;
  }
}

document.addEventListener('visibilitychange', atualizarEcraLigado);

(() => {
  const caixa = document.getElementById('ecra-ligado');
  if (!caixa) return;
  if (!('wakeLock' in navigator)) {
    caixa.disabled = true;
    document.getElementById('ecra-ligado-estado').textContent = 'Este browser não permite manter o ecrã ligado.';
    return;
  }
  caixa.checked = ecraLigadoPreferido();
  caixa.addEventListener('change', () => {
    try { localStorage.setItem(CHAVE_ECRA_LIGADO, caixa.checked ? '1' : '0'); } catch (e) { /* fica só nesta sessão */ }
    atualizarEcraLigado();
  });
})();

// Preferências de som (KGSons — ver kg-sons.js)
document.getElementById('btn-sons').addEventListener('click', () => {
  document.getElementById('sons-ativo').checked = KGSons.estaAtivo();
  document.getElementById('sons-volume').value = KGSons.obterVolume();
  document.getElementById('modal-sons').style.display = 'flex';
});
document.getElementById('btn-fechar-sons').addEventListener('click', () => {
  document.getElementById('modal-sons').style.display = 'none';
});
document.getElementById('sons-ativo').addEventListener('change', (ev) => {
  KGSons.definirAtivo(ev.target.checked);
});
document.getElementById('sons-volume').addEventListener('input', (ev) => {
  KGSons.definirVolume(parseInt(ev.target.value, 10));
});
document.getElementById('sons-testar').addEventListener('click', () => {
  KGSons.tocarPedido();
});

// Notificações push das chamadas (KGPush — ver kg-push.js). O estado é
// desenhado sempre a partir do que o browser diz e nunca de uma preferência
// guardada aqui: a permissão vive no browser e pode ser mudada nas
// definições do site, fora da app.
const PUSH_TEXTOS = {
  ativo: 'Ativo: as chamadas tocam mesmo com a app fechada.',
  inativo: 'Desligado: só é avisado com a app aberta.',
  negado: 'Bloqueado nas definições deste site no browser.',
  inseguro: 'Só funciona em https:// (ou localhost).',
  indisponivel: 'Este browser não suporta notificações push.',
};

function desenharPush(estado) {
  const botao = document.getElementById('btn-push');
  document.getElementById('push-estado').textContent = PUSH_TEXTOS[estado] || PUSH_TEXTOS.inativo;
  botao.textContent = estado === 'ativo' ? 'Desativar' : 'Ativar';
  botao.disabled = estado === 'negado' || estado === 'inseguro' || estado === 'indisponivel';
}

KGPush.aoMudar(desenharPush);
document.getElementById('btn-push').addEventListener('click', async () => {
  // O pedido de permissão tem de nascer deste clique: o Safari e o Chrome em
  // iOS recusam um requestPermission() fora de um gesto do utilizador.
  document.getElementById('btn-push').disabled = true;

  if (KGPush.estado() === 'ativo') {
    await KGPush.desativar();
  } else {
    await KGPush.ativar();
  }

  desenharPush(KGPush.estado());
});
KGPush.configurar({ csrfToken: CSRF_TOKEN }).then(() => desenharPush(KGPush.estado()));


// Sugestão
document.getElementById('btn-sugestao').addEventListener('click', () => {
  document.getElementById('modal-sugestao').style.display = 'flex';
});
document.getElementById('btn-fechar-sugestao').addEventListener('click', () => {
  document.getElementById('modal-sugestao').style.display = 'none';
});
document.getElementById('form-sugestao').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/sugestoes/enviar', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('sugestao-msg');
  if (!resp.ok) { msg.classList.remove('e-sucesso'); msg.textContent = json.erro || 'Erro ao enviar.'; return; }
  msg.classList.add('e-sucesso');
  msg.textContent = 'Enviado. Obrigado pelo seu feedback.';
  setTimeout(() => { document.getElementById('modal-sugestao').style.display = 'none'; msg.textContent = ''; ev.target.reset(); }, 1200);
});

// SOS
// O servidor exige lat/lng (SosController) — sem coordenadas não há alarme
// nenhum a criar. Por isso: tenta uma leitura fresca do GPS e, se ela falhar
// ou demorar, recorre à última posição conhecida do seguimento em vez de
// deixar o condutor a carregar num botão de emergência que não faz nada.
async function enviarSos(lat, lng, aviso) {
  try {
    const resp = await fetch('/api/sos', { method: 'POST', body: fd({ lat, lng }) });
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      alert(`Não foi possível enviar o alerta SOS: ${json.erro || 'erro ' + resp.status}. Tente novamente ou ligue diretamente para as autoridades.`);
      return;
    }
    alert(`Alerta SOS enviado.${aviso ? '\n\n' + aviso : ''}`);
    if (window.kgRt) window.kgRt.emit('sos:ativado', { lat, lng });
  } catch (erro) {
    alert('Sem ligação para enviar o alerta SOS. Verifique a rede e tente novamente.');
  }
}

document.getElementById('btn-sos')?.addEventListener('click', () => {
  if (!confirm('Ativar alerta SOS?')) return;
  const btn = document.getElementById('btn-sos');
  btn.disabled = true;
  const texto = btn.textContent;
  btn.textContent = '...';
  const terminar = () => { btn.disabled = false; btn.textContent = texto; };

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      terminar();
      enviarSos(pos.coords.latitude, pos.coords.longitude, '');
    },
    (err) => {
      terminar();
      console.warn('V-MILLION SOS: GPS indisponível, code =', err.code, err.message);
      if (ultimaLatCondutor !== null && ultimaLngCondutor !== null) {
        enviarSos(ultimaLatCondutor, ultimaLngCondutor, 'Atenção: o GPS não respondeu agora — foi enviada a sua última posição conhecida, que pode estar desatualizada.');
        return;
      }
      alert('Não foi possível obter a sua localização e o alerta SOS precisa dela. Ative a localização do dispositivo e tente de novo — se for uma emergência, ligue diretamente para as autoridades.');
    },
    // Timeout curto face aos 20 s do arranque normal: numa emergência é
    // preferível cair depressa na última posição conhecida do que ficar à
    // espera de um fix perfeito.
    { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
  );
});

const PACOTE_LABEL = { diario: 'Diário', semanal: 'Semanal', mensal: 'Mensal', anual: 'Anual' };
const COMPROVATIVO_MAX_BYTES = 5 * 1024 * 1024; // 5MB — igual ao 'max:5120' do servidor
const TIPO_SERVICO_LABEL = { urbano: 'Urbano', intermunicipal: 'Intermunicipal', ambos: 'Urbano + Intermunicipal' };

// O cartão de Pagamento é sempre relativo ao veículo escolhido no seletor:
// um condutor com dois veículos pode ter um em dia e o outro por pagar, e o
// backend valida pacote, aprovação e pedido pendente veículo a veículo.
// veiculoIdPreferido mantém a escolha do condutor quando o cartão é
// recarregado depois de um envio.
async function carregarPagamento(veiculoIdPreferido = null) {
  const [json, veiculosJson] = await Promise.all([
    kgApiJSON('/api/condutor/pagamentos'),
    fetch('/api/condutor/veiculos').then(r => r.json()),
  ]);
  const card = document.getElementById('card-pagamento');
  const pagamentos = json.pagamentos || [];
  const pacotes = json.pacotes || [];
  const veiculos = veiculosJson.veiculos || [];
  const veiculosValidos = new Set((json.veiculos_validos || []).map(String));
  // Pacote de teste = preço 0: não leva comprovativo, é aprovado logo e só
  // pode ser usado uma vez por condutor (regra aplicada no servidor).
  const pacoteTesteUsado = !!json.pacote_teste_usado;
  const eTeste = (p) => Number(p && p.preco) <= 0;

  card.innerHTML = `<div id="pagamento-estado"></div>
  <div class="mt-3 space-y-1.5">
    <label class="vm-label block" for="sel-veiculo-pagamento">Veículo</label>
    <select class="vm-select" id="sel-veiculo-pagamento"></select>
    <label class="vm-label block pt-2">Pacote</label>
    <p class="vm-small" id="pacotes-tipo-servico"></p>
    <div id="pacotes-grid" class="grid grid-cols-[repeat(auto-fit,minmax(110px,1fr))] gap-1.5"></div>
    <div id="bloco-comprovativo-pagamento" class="space-y-1.5">
      <label class="vm-label block pt-2">Comprovativo (imagem ou PDF, até 5MB)</label>
      <input class="vm-input" type="file" id="input-comprovativo-pagamento" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <p class="vm-small text-red-600" id="pagamento-bloqueio"></p>
    <p class="vm-small" id="pagamento-escolher-pacote">Selecione um pacote para poder solicitar o pagamento.</p>
    <button class="vm-btn vm-btn--cta vm-btn--sm vm-btn--full mt-1.5" id="btn-solicitar-pagamento" type="button" disabled>Solicitar pagamento</button>
  </div>`;

  const selVeiculo = document.getElementById('sel-veiculo-pagamento');
  const caixaEstado = document.getElementById('pagamento-estado');
  const grid = document.getElementById('pacotes-grid');
  const rotulo = document.getElementById('pacotes-tipo-servico');
  const inputFicheiro = document.getElementById('input-comprovativo-pagamento');
  const blocoComprovativo = document.getElementById('bloco-comprovativo-pagamento');
  const caixaBloqueio = document.getElementById('pagamento-bloqueio');
  const botao = document.getElementById('btn-solicitar-pagamento');
  const avisoEscolherPacote = document.getElementById('pagamento-escolher-pacote');
  // Motivo de bloqueio do veículo escolhido (preenchido em renderVeiculoEscolhido).
  let bloqueioVeiculo = null;

  veiculos.forEach(v => selVeiculo.add(new Option(
    `${v.matricula}${v.modelo ? ' — ' + v.modelo : ''}${v.aprovado ? '' : ' (por aprovar)'}`,
    v.id
  )));

  // Sem escolha anterior, abre no veículo ativo — é aquele em que o condutor
  // está a trabalhar e, por isso, aquele cujo pagamento lhe interessa.
  const preferido = [veiculoIdPreferido, veiculoAtivoId]
    .filter(id => id !== null && id !== undefined)
    .map(String)
    .find(id => veiculos.some(v => String(v.id) === id));
  if (preferido) selVeiculo.value = preferido;

  const veiculoEscolhido = () => veiculos.find(v => String(v.id) === String(selVeiculo.value));
  const pagamentosDo = (veiculo) => pagamentos.filter(p => String(p.veiculo_id) === String(veiculo.id));

  function renderEstado(veiculo) {
    if (!veiculo) {
      caixaEstado.innerHTML = `<span class="vm-badge vm-badge--recusado">Ainda não tem veículos</span> <span class="vm-small">Registe um veículo antes de pagar.</span>`;
      return;
    }

    // A API devolve os pagamentos do mais recente para o mais antigo.
    const doVeiculo = pagamentosDo(veiculo);
    const ultimo = doVeiculo[0];
    const pendente = doVeiculo.find(p => p.status === 'pendente');
    const ultimoAprovado = doVeiculo.find(p => p.status === 'aprovado');
    // "Expirado" não existe na BD: é um aprovado cuja data_validade já
    // passou, por isso deriva-se de veiculos_validos (calculado no servidor
    // com o scope validos()) e não de uma comparação de datas aqui.
    const valido = veiculosValidos.has(String(veiculo.id));
    const expirado = !valido && !!ultimoAprovado;

    let html = '';
    if (valido) {
      html += `<span class="vm-badge vm-badge--confirmado">Pagamento em dia</span> <span class="vm-small">${esc(veiculo.matricula)} — válido até ${esc(formatarData(ultimoAprovado && ultimoAprovado.data_validade))}.</span>`;
    } else if (pendente) {
      html += `<span class="vm-badge vm-badge--pendente">Pagamento enviado a aguardar aprovação</span> <span class="vm-small">${esc(veiculo.matricula)} — enviado em ${esc(formatarData(pendente.criado_em))}.</span>`;
    } else if (expirado) {
      html += `<span class="vm-badge vm-badge--recusado">Pagamento expirado</span> <span class="vm-small">${esc(veiculo.matricula)} — expirou em ${esc(formatarData(ultimoAprovado.data_validade))}. Escolha um pacote abaixo para renovar.</span>`;
    } else if (ultimo && ultimo.status === 'recusado') {
      html += `<span class="vm-badge vm-badge--recusado">Pagamento recusado</span> <span class="vm-small">${esc(veiculo.matricula)}</span>`;
    } else {
      html += `<span class="vm-badge vm-badge--recusado">Ainda não tem um pagamento registado</span> <span class="vm-small">${esc(veiculo.matricula)} — escolha um pacote e envie o comprovativo.</span>`;
    }

    // Uma renovação enviada enquanto o pagamento atual ainda é válido não
    // aparece no badge (que mostra "em dia"), mas explica por que motivo o
    // formulário está bloqueado logo abaixo.
    if (valido && pendente) {
      html += `<p class="vm-small mt-1.5">Já enviou uma renovação para este veículo, a aguardar aprovação.</p>`;
    }
    if (ultimoAprovado && ultimoAprovado.recibo_path) {
      html += ` <a class="vm-small font-semibold text-brand-800 hover:underline" href="/api/condutor/recibo?id=${esc(ultimoAprovado.id)}" target="_blank">Ver recibo</a>`;
    }
    if (ultimo && ultimo.status === 'recusado' && ultimo.observacao_admin) {
      html += `<p class="vm-small mt-1.5 text-red-600">Motivo da recusa: ${esc(ultimo.observacao_admin)}. Envie um novo comprovativo.</p>`;
    }
    if (ultimo && ultimo.comprovativo_path) {
      html += `<p class="vm-small"><a href="/api/condutor/comprovativo?id=${esc(ultimo.id)}" target="_blank">Ver comprovativo enviado (${esc(PACOTE_LABEL[ultimo.pacote_nome] || ultimo.pacote_nome || '—')})</a></p>`;
    }
    caixaEstado.innerHTML = html;
  }

  // Cada pacote pertence a um tipo de serviço (urbano/intermunicipal/ambos)
  // — só faz sentido mostrar ao condutor os pacotes do mesmo tipo do
  // veículo escolhido, senão ele podia pagar um pacote que não usa.
  function renderPacotesGrid(veiculo) {
    if (!veiculo) {
      grid.innerHTML = '<p class="vm-small">Registe um veículo primeiro.</p>';
      rotulo.textContent = '';
      return;
    }
    rotulo.textContent = `Pacotes disponíveis para o tipo de serviço deste veículo: ${TIPO_SERVICO_LABEL[veiculo.tipo_servico] || veiculo.tipo_servico}.`;
    const pacotesVeiculo = pacotes.filter(p => p.tipo_servico === veiculo.tipo_servico);
    grid.innerHTML = pacotesVeiculo.map(p => {
      const teste = eTeste(p);
      const indisponivel = teste && pacoteTesteUsado;
      return `
      <label class="vm-card p-2 text-center transition sm:p-2 has-checked:border-brand-800 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-800 ${indisponivel ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:border-brand-600'}">
        <input type="radio" name="pacote-pagamento" value="${esc(p.id)}" class="mx-auto mb-1 block accent-brand-800"${indisponivel ? ' disabled' : ''}>
        <strong class="text-xs">${esc(PACOTE_LABEL[p.nome] || p.nome)}</strong>
        <div class="vm-small">${teste ? 'Grátis (teste)' : `${esc(p.preco)} CVE`}</div>
        <div class="vm-small">${esc(p.duracao_dias)}d</div>
        ${indisponivel ? '<div class="vm-small">Já utilizado</div>' : ''}
      </label>`;
    }).join('') || '<p class="vm-small">Nenhum pacote disponível para este tipo de serviço — contacte o administrador.</p>';
    atualizarComprovativo();
  }

  const pacoteEscolhido = () => {
    const id = document.querySelector('input[name="pacote-pagamento"]:checked')?.value;
    return pacotes.find(p => String(p.id) === String(id));
  };

  // O comprovativo só faz sentido num pacote pago: escondê-lo no de teste
  // evita que o condutor pense que tem de anexar alguma coisa.
  function atualizarComprovativo() {
    const pacote = pacoteEscolhido();
    const teste = eTeste(pacote);
    blocoComprovativo.style.display = teste ? 'none' : '';
    botao.textContent = teste ? 'Ativar pacote de teste' : 'Solicitar pagamento';
    // O botão só fica ativo depois de escolhido um pacote (e sem bloqueio do
    // veículo): antes disso carregar nele só dava o alerta "Escolha um pacote".
    botao.disabled = !!bloqueioVeiculo || !pacote;
    avisoEscolherPacote.style.display = !bloqueioVeiculo && !pacote ? '' : 'none';
  }
  grid.addEventListener('change', atualizarComprovativo);

  // As mesmas recusas que o servidor faria (403 no veículo por aprovar, 409
  // no pedido já pendente), ditas antes de o condutor preencher o formulário.
  function motivoBloqueio(veiculo) {
    if (!veiculo) return 'Registe um veículo antes de solicitar o pagamento.';
    if (!veiculo.aprovado) return 'Este veículo ainda aguarda aprovação do administrador — só depois poderá pagar.';
    if (pagamentosDo(veiculo).some(p => p.status === 'pendente')) return 'Já existe um pedido pendente para este veículo. Aguarde a decisão do administrador.';
    return null;
  }

  function renderVeiculoEscolhido() {
    const veiculo = veiculoEscolhido();
    bloqueioVeiculo = motivoBloqueio(veiculo);
    renderEstado(veiculo);
    renderPacotesGrid(veiculo);

    caixaBloqueio.textContent = bloqueioVeiculo || '';
    caixaBloqueio.style.display = bloqueioVeiculo ? '' : 'none';
    // .vm-btn já trata o :disabled (opacidade + cursor) no design system.
    inputFicheiro.disabled = !!bloqueioVeiculo;
    atualizarComprovativo();
  }

  selVeiculo.addEventListener('change', renderVeiculoEscolhido);
  renderVeiculoEscolhido();

  botao.addEventListener('click', async () => {
    const veiculoId = selVeiculo.value;
    if (!veiculoId) { alert('Registe um veículo primeiro.'); return; }
    const pacote = pacoteEscolhido();
    if (!pacote) { alert('Escolha um pacote.'); return; }
    const pacoteId = pacote.id;
    if (eTeste(pacote)) {
      const resp = await fetch('/api/condutor/pagamentos', { method: 'POST', body: fd({ acao: 'solicitar', veiculo_id: veiculoId, pacote_id: pacoteId }) });
      const json2 = await resp.json();
      if (!resp.ok) { alert(json2.erro); return; }
      alert(`Pacote de teste ativado (ref. ${json2.referencia}). Acesso válido até ${formatarData(json2.data_validade)}.`);
      // O mapa é decidido no servidor, pelo pagamento válido: só um
      // recarregamento o mostra, ao contrário de um pedido pago que fica pendente.
      location.reload();
      return;
    }
    const ficheiro = inputFicheiro.files[0];
    if (!ficheiro) { alert('Anexe o comprovativo (imagem ou PDF).'); return; }
    // Mesmo limite do 'max:5120' do servidor, verificado aqui para não obrigar
    // o condutor a esperar pelo upload inteiro antes de saber que é grande
    // demais — em ligação móvel isso são vários minutos até ao erro.
    if (ficheiro.size > COMPROVATIVO_MAX_BYTES) { alert('Ficheiro demasiado grande. Use PDF/JPG/PNG até 5MB.'); return; }
    const dados = fd({ acao: 'solicitar', veiculo_id: veiculoId, pacote_id: pacoteId });
    dados.set('comprovativo', ficheiro);
    const resp = await fetch('/api/condutor/pagamentos', { method: 'POST', body: dados });
    const json2 = await resp.json();
    if (!resp.ok) { alert(json2.erro); return; }
    alert(`Pedido enviado (ref. ${json2.referencia}, ${json2.valor} CVE). Aguarde a aprovação do administrador.`);
    carregarPagamento(veiculoId);
  });
}

// Avisos de proximidade dos pontos de descida (<500 m), calculados pelo
// PosicaoController e devolvidos em passageiros_proximos a cada posição.
function mostrarAvisosDescida(lista) {
  const caixa = document.getElementById('aviso-descida');
  const alvo = document.getElementById('aviso-descida-lista');
  if (!caixa || !alvo) return;

  if (!lista.length) {
    caixa.style.display = 'none';
    avisosDescidaAnunciados = new Set();
    return;
  }

  const ordenada = [...lista].sort((a, b) => a.distancia_m - b.distancia_m);
  alvo.innerHTML = ordenada
    .map((pp) => `<div class="kg-map-aviso__item">${esc(pp.ponto_descida_nome || 'Ponto de descida')} — a ${esc(pp.distancia_m)} m</div>`)
    .join('');
  caixa.style.display = 'block';

  // Som só quando um ponto de descida entra no raio pela primeira vez — a
  // cada posição enviada tocaria sem parar durante toda a aproximação.
  if (ordenada.some((pp) => !avisosDescidaAnunciados.has(pp.reserva_id))) KGSons.tocarSucesso();
  avisosDescidaAnunciados = new Set(ordenada.map((pp) => pp.reserva_id));
}

// Envia ao servidor a última posição guardada pelo watch do GPS (ver
// ENVIO_POSICAO_MS). Fica fora do callback do GPS de propósito: assim a
// frequência dos pedidos depende deste temporizador e não da cadência de
// leituras do telemóvel.
async function enviarPosicaoPendente() {
  if (!posicaoPorEnviar || !veiculoAtivoId) return;
  // Este POST escreve na BD e pode concluir as viagens a bordo (chegada ao
  // destino). Se o anterior ainda não respondeu, espera-se pelo próximo
  // ciclo em vez de o abortar — abortar não desfaz a escrita no servidor e
  // perderia a resposta 'chegou_destino'.
  if (envioPosicaoEmCurso) return;

  const { lat, lng, accuracy } = posicaoPorEnviar;
  posicaoPorEnviar = null; // consumida: só se envia posição nova, nunca repetida
  envioPosicaoEmCurso = true;

  try {
    const resp = await fetch('/api/condutor/posicao', {
      method: 'POST',
      body: fd({ veiculo_id: veiculoAtivoId, lat, lng, accuracy }),
    });
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      // 403 (rota ainda não iniciada) e 422 (precisão insuficiente) são
      // recusas esperadas — antes passavam em silêncio total, agora pelo
      // menos ficam registadas na consola.
      console.warn('V-MILLION GPS: posição recusada pelo servidor —', resp.status, json.erro || '');
      return;
    }

    const idMeuVeiculo = `v-${veiculoAtivoId}`;
    // O estado real (ex: partiu_da_fila -> em_movimento) só é confirmado
    // pelo servidor — se mudou, recria o marcador para refletir a cor/estado
    // correta (posição já está certa, só falta o estilo).
    if (json.estado && json.estado !== meuVeiculoMarcadoEstado) {
      if (veiculoAtivoData) veiculoAtivoData.estado = json.estado;
      desenharMeuVeiculo(idMeuVeiculo, lat, lng, json.estado);
    }

    mostrarAvisosDescida(json.passageiros_proximos || []);

    if (json.chegou_destino) {
      mostrarAvisosDescida([]);
      pararNavegacao();
      // A inversão é aplicada ANTES do alert: alert/confirm bloqueiam a
      // thread, e o carregarMeusVeiculos abaixo só responde depois de o
      // condutor os fechar — até lá as listas ainda diriam o sentido da ida,
      // logo no ecrã onde se lhe anuncia que ele foi invertido.
      if (veiculoAtivoData && json.novo_ponto_partida_id) {
        veiculoAtivoData.ponto_partida_id = json.novo_ponto_partida_id;
        veiculoAtivoData.destino_id = json.novo_destino_id;
        if (sincronizarListasPontoVeiculo(veiculoAtivoData)) tracarRotaCondutor();
      }
      alert('Chegou ao destino! Todos os passageiros foram marcados como descidos. Ponto de partida e destino foram invertidos.');
      carregarMeusVeiculos();
      const entrarFila = confirm('Deseja entrar na fila no novo ponto? (Cancelar = ficar livre no ponto)');
      if (entrarFila) document.getElementById('btn-entrar-fila').click();
    }
    if (window.kgRt) window.kgRt.emit('veiculo:posicao', { veiculoId: veiculoAtivoId, lat, lng });
  } catch (erro) {
    // Rede em baixo/túnel: a posição seguinte volta a tentar.
    console.warn('V-MILLION GPS: falha ao enviar posição —', erro.message);
  } finally {
    envioPosicaoEmCurso = false;
  }
}

// Arranque
carregarPagamento();
if (MOSTRAR_MAPA) {
  // O mapa aparece de imediato (centro por omissão, Cabo Verde) — nunca
  // fica à espera do GPS para desenhar estradas/tiles. A geolocalização
  // (kgEnsureGeolocation, abaixo) só entra depois, para centrar e colocar
  // o marcador 'eu'; se falhar ou demorar, o resto do painel (veículos,
  // pagamento, procura urbana) continua a funcionar na mesma (relatório
  // "mapa cinzento": estava tudo dependente do GPS resolver primeiro,
  // incluindo a própria criação do mapa).
  // maxZoom 19 (acima dos 15 por omissão do KGMap): o condutor precisa de
  // chegar ao nível da rua — e do edifício — para distinguir o lado da estrada
  // onde está a paragem/o passageiro. 19 é também o último nível que o
  // servidor de tiles do OSM serve com imagem real (ver MAX_ZOOM_TILES).
  mapa = KGMap.create('map', { zoom: 13, maxZoom: 19 });
  KGMap.ligarPausaDeAnimacoes(mapa);

  // Delimitação do território por município (docs/Municipio.pdf): as
  // fronteiras dos 22 concelhos por cima dos tiles, com o nome de cada um até
  // ao zoom de ilha. O condutor deixa de precisar de adivinhar onde acaba o
  // concelho em que está — e a pastilha abaixo diz-lhe qual é a qualquer zoom.
  mapa.mostrarMunicipios().then(() => {
    // O GPS pode ter respondido antes do GeoJSON chegar; nesse caso a pastilha
    // ficou vazia à espera desta lista.
    if (ultimaLatCondutor != null) atualizarMunicipioAtual(ultimaLatCondutor, ultimaLngCondutor);
  });
  document.getElementById('btn-municipios')?.addEventListener('click', (ev) => {
    const botao = ev.currentTarget;
    if (mapa.municipiosVisiveis()) {
      mapa.esconderMunicipios();
      botao.setAttribute('aria-pressed', 'false');
      botao.classList.add('kg-map-btn--off');
    } else {
      mapa.mostrarMunicipios();
      botao.setAttribute('aria-pressed', 'true');
      botao.classList.remove('kg-map-btn--off');
    }
  });

  // Segue a posição do condutor automaticamente (relatório "GPS a
  // aparecer fora do mapa") — só para de seguir se o próprio condutor
  // arrastar o mapa; o botão de recentrar retoma. Ver o mesmo mecanismo no painel
  // do passageiro para a explicação completa.
  mapa.onDragStart(() => {
    aSeguirCondutor = false;
    // O seguimento é contínuo (seguirSuave): sem o parar aqui, a interpolação
    // já lançada continuava a puxar a vista de volta enquanto o condutor
    // arrastava o mapa com o dedo.
    mapa.pararSeguimento();
  });
  document.getElementById('btn-recentrar')?.addEventListener('click', () => {
    aSeguirCondutor = true;
    if (ultimaLatCondutor != null && ultimaLngCondutor != null) mapa.flyTo(ultimaLatCondutor, ultimaLngCondutor, ZOOM_SEGUIMENTO);
  });

  // Mesma origem da página (Apache faz proxy de /socket.io/ para o Node em
  // 127.0.0.1:3001 — ver public/.htaccess) em vez de ligar direto à porta
  // 3001, que não tem certificado TLS próprio e falhava com wss://.
  window.kgRt = KGRealtime.connect({ url: window.location.origin });
  window.kgRt.join(`utilizador:${UTILIZADOR_ID}`);
  KGChamada.configurar({ rt: window.kgRt, meuId: UTILIZADOR_ID, csrfToken: CSRF_TOKEN });
  window.kgRt.on('comunicacao:nova', (payload) => {
    if (payload.veiculoId === veiculoAtivoId) carregarChatCondutor();
  });
  window.kgRt.on('urbano:reclamado', (payload) => {
    if (payload && payload.reservaId != null) removerPassageiroUrbanoDaLista(payload.reservaId);
  });
  window.kgRt.on('pedido:reclamado', (payload) => {
    if (payload && payload.reservaId != null) removerPassageiroDoPontoDaLista(payload.reservaId);
  });
  // Pedido novo no ponto: só interessa a quem está ancorado nesse ponto — o
  // servidor de tempo real faz broadcast e o filtro é aqui.
  window.kgRt.on('pedido:ponto', (payload) => {
    if (!payload || !veiculoAtivoData) return;
    if (String(payload.pontoId) === String(veiculoAtivoData.ponto_partida_id)) carregarPassageirosDoPonto();
  });

  carregarPontosDropdowns().then(carregarMeusVeiculos);

  // Independente de ter (ou não) um veículo selecionado — um condutor
  // aprovado e em dia vê sempre a procura urbana em aberto (relatório
  // "condutores fora do ponto veem passageiros urbanos").
  carregarPassageirosUrbanos();
  setInterval(carregarPassageirosUrbanos, 10000);

  // Ao contrário dos urbanos, os pedidos do ponto dependem do veículo
  // selecionado (é dele que vem o ponto), por isso a atualização periódica
  // vive no polling do condutor — aqui fica só o texto inicial certo.
  carregarPassageirosDoPonto();

  // Geolocalização: pedida em paralelo com o resto do arranque acima —
  // assim que houver uma posição válida, centra o mapa, cria o marcador
  // 'eu' e só então começa a seguir/enviar a posição do condutor.
  kgEnsureGeolocation((pos) => {
    console.log('V-MILLION GPS: posição inicial obtida, precisão =', pos.coords.accuracy, 'm');
    mapa.setUserPosition(pos.coords.latitude, pos.coords.longitude);
    mapa.setUserAccuracy(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
    atualizarMunicipioAtual(pos.coords.latitude, pos.coords.longitude);
    // A procura urbana é a do município onde o condutor está, por isso o
    // servidor não a dá sem posição. Esta é a primeira da sessão: a seguinte
    // só chega com a primeira leitura filtrada do watch (que com o condutor
    // parado pode nem chegar), e até lá o painel pediria um GPS que já
    // respondeu.
    if (ultimaLatCondutor == null) {
      ultimaLatCondutor = pos.coords.latitude;
      ultimaLngCondutor = pos.coords.longitude;
      carregarPassageirosUrbanos();
    }
    // O marcador 'eu' acabou de nascer: se já havia veículo selecionado (a
    // lista carrega antes do GPS responder), veste-o já com esse veículo.
    atualizarIconeGpsCondutor();
    mapa.flyTo(pos.coords.latitude, pos.coords.longitude, ZOOM_SEGUIMENTO);

    // kgEnsureGeolocation pode entregar uma posição mais do que uma vez na
    // mesma sessão (permissão revogada e reposta, botão "Tentar novamente").
    // Sem limpar o watch anterior ficavam dois observadores vivos a seguir o
    // mesmo condutor e a enviar posições em paralelo.
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    if (envioPosicaoTimer !== null) clearInterval(envioPosicaoTimer);
    envioPosicaoTimer = setInterval(enviarPosicaoPendente, ENVIO_POSICAO_MS);

    watchId = kgWatchPosition((lat, lng, pos, info) => {
      console.log('V-MILLION GPS: atualização, precisão =', pos.coords.accuracy, 'm');
      // O ponto do GPS e o veículo já foram movidos por onLeituraBruta
      // (abaixo), até à coordenada exata da última leitura. Aqui trata-se só
      // do que exige uma deslocação mesmo confirmada: seguir o mapa, medir o
      // rumo, recalcular a rota e reportar a posição ao servidor.
      // Seguimento contínuo, alimentado pela posição filtrada: o mapa desliza
      // com o condutor em vez de dar um puxão a cada leitura (panTo arrancava
      // uma animação nova por segundo, cada uma a acelerar e a travar, e a
      // cortar a anterior a meio). Com o condutor parado não chega posição
      // filtrada nova, por isso a vista fica quieta em vez de tremer com o
      // ruído do GPS.
      if (aSeguirCondutor) mapa.seguirSuave(lat, lng);
      // Antes do return de "sem veículo selecionado": saber em que município
      // se está não depende de haver veículo ativo.
      atualizarMunicipioAtual(lat, lng);
      if (!veiculoAtivoId) return;
      const idMeuVeiculo = `v-${veiculoAtivoId}`;
      const estadoConhecido = meuVeiculoMarcadoEstado || veiculoAtivoData?.estado || 'no_ponto';

      // Parado no ponto, o marcador fica nas coordenadas do ponto e o GPS do
      // telemóvel não o arrasta para fora (ver veiculoEmRota).
      const emRota = veiculoEmRota(estadoConhecido);
      const veicLat = emRota ? lat : parseFloat(veiculoAtivoData.lat ?? lat);
      const veicLng = emRota ? lng : parseFloat(veiculoAtivoData.lng ?? lng);

      if (meuVeiculoMarcadoId !== idMeuVeiculo) {
        desenharMeuVeiculo(idMeuVeiculo, veicLat, veicLng, estadoConhecido);
      } else if (!emRota) {
        // Parado no ponto o marcador fica fixo nas coordenadas do ponto, por
        // isso é colocado de uma vez. Em rota NÃO se lhe toca aqui: quem o
        // move é onLeituraBruta (abaixo), com movimento contínuo — uma
        // colocação direta a meio do percurso cancelava-o e trazia de volta o
        // salto de leitura em leitura.
        mapa.updateMarker(idMeuVeiculo, veicLat, veicLng);
      }

      // Seta de navegação (tarefa D): só faz sentido enquanto o veículo se
      // move mesmo — parado no ponto, fica sem rotação (nada a apontar).
      if (emRota) {
        if (ultimaLatCondutor != null && ultimaLngCondutor != null) {
          mapa.setMarkerRotation(idMeuVeiculo, KGMap.calcularRumo(ultimaLatCondutor, ultimaLngCondutor, lat, lng));
        } else if (navegacaoAlvo) {
          mapa.setMarkerRotation(idMeuVeiculo, KGMap.calcularRumo(lat, lng, navegacaoAlvo.lat, navegacaoAlvo.lng));
        }
      }
      ultimaLatCondutor = lat;
      ultimaLngCondutor = lng;

      // Parado no ponto: nada para enviar ao servidor — o veículo só começa
      // a reportar posição depois de "Iniciar rota" (PosicaoController recusa-o
      // de qualquer forma, mas evitamos o pedido desnecessário). Descarta-se
      // também qualquer posição ainda por enviar, para o temporizador não
      // mandar uma leitura de quando ainda estava em rota.
      if (!emRota) {
        posicaoPorEnviar = null;
        mostrarAvisosDescida([]);
        return;
      }

      // Rota dinâmica: a linha é recortada ao que falta percorrer e, se o
      // condutor saiu da estrada traçada (atalho ou desvio), pede-se um
      // caminho alternativo a partir de onde ele está agora — sem esperar
      // pelo ciclo de 30 s.
      if (navegacaoAlvo) {
        seguirRotaNavegacao(lat, lng);

        // Ultrapassagem do alvo, medida em linha reta: quem passa ao lado do
        // passageiro continua em cima da estrada traçada, por isso a
        // distância à rota (acima) não apanha este caso.
        const distAlvo = KGMap.distanciaMetros(lat, lng, navegacaoAlvo.lat, navegacaoAlvo.lng);
        if (navMenorDistancia === null || distAlvo < navMenorDistancia) {
          navMenorDistancia = distAlvo;
        } else if (navMenorDistancia < 500 && distAlvo > navMenorDistancia + 150 && !navUltrapassouAvisado) {
          navUltrapassouAvisado = true;
          alert('Ultrapassou o destino. Por favor, verifique o percurso.');
          atualizarNavegacao();
        }
      }

      // Rede: não é feita aqui. A posição fica pendente e é enviada pelo
      // temporizador (enviarPosicaoPendente), no máximo 1x por
      // ENVIO_POSICAO_MS. Uma reemissão por perda de sinal (info.obsoleta)
      // serve para o mapa, mas nunca é enviada: eram coordenadas antigas a
      // renovar 'ultima_posicao_em' e a fazer o veículo parecer vivo onde já
      // não estava.
      if (info && info.obsoleta) return;
      posicaoPorEnviar = { lat, lng, accuracy: pos.coords.accuracy };
    }, {
      // O ponto azul do condutor segue TODAS as leituras aceites, sem passar
      // pelo filtro dos 5 m nem pelo de deriva: pedia-se que aparecesse
      // exatamente no sítio, e com os filtros ficava até 5 m atrás da posição
      // real (e podia ignorar uma leitura melhor no mesmo sítio, mantendo o
      // ponto no erro da leitura anterior). O mapa só é seguido (seguirSuave)
      // na função de cima, com o sinal já filtrado — senão a vista tremia a
      // cada segundo com o condutor parado.
      onLeituraBruta: (lat, lng, pos) => {
        // Desliza até à leitura nova em vez de saltar para ela: entre duas
        // leituras (~1 s) o ponto continua a andar, ao ritmo do ecrã.
        mapa.moveMarkerSuave('eu', lat, lng);
        // O círculo acompanha o ponto: o condutor vê de imediato se está a ver
        // uma posição fiável (círculo pequeno) ou uma estimativa grosseira.
        // Só o raio muda aqui — a posição vai a reboque do ponto 'eu', para os
        // dois nunca se separarem a meio de um percurso.
        mapa.setUserAccuracy(lat, lng, pos.coords.accuracy, true);

        // O veículo do condutor anda com a MESMA leitura do ponto do GPS, e
        // não só com as filtradas: os dois estão na mesma coordenada (o
        // condutor vai dentro do carro) e, com o veículo à espera de uma
        // deslocação de 5 m confirmada, ficava visivelmente atrás do seu
        // próprio ponto azul — e a andar aos saltos de 5 em 5 m. Parado no
        // ponto não se aplica: aí o marcador pertence ao ponto, não ao GPS.
        if (!veiculoAtivoId || meuVeiculoMarcadoId !== `v-${veiculoAtivoId}`) return;
        const estado = meuVeiculoMarcadoEstado || veiculoAtivoData?.estado || 'no_ponto';
        if (veiculoEmRota(estado)) mapa.moveMarkerSuave(meuVeiculoMarcadoId, lat, lng);
      },
    });
  });
} else {
  carregarMeusVeiculos();
}
</script>
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
