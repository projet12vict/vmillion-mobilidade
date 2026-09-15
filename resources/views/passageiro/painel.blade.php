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
<title>Painel do Passageiro — V-MILLION</title>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
{{-- Marcadores, controlos e painéis do mapa continuam no CSS legado: é folha
     independente (declara as suas próprias variáveis) e nada tem que ver com
     o tema. --}}
<link rel="stylesheet" href="@kgAsset('assets/css/kg-map-markers.css')">
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-slate-100 font-sans text-slate-700 antialiased">

{{-- ============================ BARRA DE TOPO ============================ --}}
<header class="vm-barra px-seguro [--px-seguro:0.75rem] sm:[--px-seguro:1.25rem] relative z-30 flex items-center justify-between gap-2 bg-brand-900 bg-linear-135 from-brand-950 via-brand-800 to-brand-700 shadow-lg shadow-brand-950/20">
  <a href="/" class="flex shrink-0 items-center gap-2.5 text-white transition-opacity hover:opacity-90">
    <x-logo class="shadow-lg shadow-brand-950/25" />
    <span class="hidden text-lg font-bold tracking-tight sm:inline">V-MILLION</span>
  </a>

  <div class="flex items-center gap-1 sm:gap-2">
    <span class="hidden text-sm text-blue-100/90 lg:inline">{{ $passageiro->nome }}</span>
    <button class="vm-btn vm-btn--sm vm-btn--claro relative px-3" id="btn-notificacoes" type="button">
      <span class="sr-only">Notificações</span>
      <x-icone nome="sino" tamanho="h-4 w-4" />
      <span id="badge-notificacoes" style="display:none;"
            class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-[0.65rem] font-bold leading-none text-white"></span>
    </button>
    <button class="vm-btn vm-btn--sm vm-btn--claro px-3" id="btn-sons" title="Preferências de som" type="button">
      <span class="sr-only">Preferências de som</span>
      <x-icone nome="som" tamanho="h-4 w-4" />
    </button>
    <button class="vm-btn vm-btn--sm vm-btn--claro px-3 md:px-3.5" id="btn-sugestao" type="button" title="Sugestão ou reclamação">
      <span class="hidden md:inline">Sugestão/Reclamação</span>
      <x-icone nome="chat" tamanho="h-4 w-4" class="md:hidden" />
      <span class="sr-only md:hidden">Sugestão ou reclamação</span>
    </button>
    <button class="vm-btn vm-btn--sm vm-btn--claro" id="btn-perfil" type="button">Perfil</button>
    <a href="/logout" class="vm-btn vm-btn--sm vm-btn--claro" id="link-sair">Sair</a>
  </div>
</header>

{{-- =============================== MAPA =============================== --}}
<div class="relative h-[48svh] md:h-[58svh]">
  <div id="map" class="absolute inset-0"></div>
  <div class="kg-map-controls">
    <button type="button" class="kg-map-btn" id="btn-recentrar" title="A minha localização" aria-label="Recentrar no meu GPS"><x-icone nome="localizar" /></button>
  </div>
  <button type="button" class="kg-map-sos" id="btn-sos">SOS</button>
  <div class="kg-map-legend">
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--ponto" style="width:14px;height:14px;"></span> Ponto de partida</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--veiculo" style="width:18px;height:18px;"></span> Veículo</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--eu" style="width:12px;height:12px;"></span> A sua localização</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--descida" style="width:14px;height:14px;"></span> Ponto de descida</div>
  </div>
</div>

{{-- =========================== FOLHA INFERIOR =========================== --}}
<section class="relative z-20 -mt-6 min-h-[42svh] rounded-t-3xl bg-white p-5 pb-seguro [--pb-seguro:1.25rem] shadow-2xl shadow-slate-900/10 sm:p-6 sm:[--pb-seguro:1.5rem]">
  <div id="reserva-ativa" class="vm-card mb-4 hidden">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="vm-h3">A sua viagem</h3>
      <span class="vm-badge" id="reserva-badge"></span>
    </div>
    <p class="vm-small" id="reserva-detalhes"></p>
    <div id="reserva-condutor"></div>
    <button type="button" class="vm-btn vm-btn--outline vm-btn--sm vm-btn--full mt-2" id="btn-abrir-chat" style="display:none;"><x-icone nome="chat" tamanho="h-4 w-4" /> Falar com o condutor</button>
    <button type="button" class="vm-btn vm-btn--perigo vm-btn--sm vm-btn--full mt-2" id="btn-sair-reserva" style="display:none;">Sair desta reserva / escolher outro veículo</button>
    <button type="button" class="vm-btn vm-btn--cta vm-btn--sm vm-btn--full mt-2" id="btn-finalizar-viagem" style="display:none;"><x-icone nome="verificado" tamanho="h-4 w-4" /> Finalizar viagem (já cheguei)</button>
    <div id="chat-condutor" class="mt-2" style="display:none;">
      <div id="chat-mensagens" class="mb-2 max-h-56 space-y-1.5 overflow-y-auto overscroll-contain rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200"></div>
      <form id="form-chat" class="flex flex-wrap gap-2">
        <input class="vm-input min-w-0 flex-1" id="chat-input" placeholder="Escreva uma mensagem..." maxlength="500">
        <button class="vm-btn vm-btn--cta vm-btn--sm" type="submit">Enviar</button>
      </form>
    </div>
  </div>

  <div id="form-wrap">
    <h3 class="vm-h3">Reservar viagem</h3>
    <div class="mb-4 mt-3 flex flex-col gap-2 sm:flex-row">
      <button type="button" class="vm-btn vm-btn--outline flex-1" id="btn-modo-urbana" data-modo="urbana"><x-icone nome="cidade" tamanho="h-4 w-4" /> Viagem urbana</button>
      <button type="button" class="vm-btn vm-btn--outline flex-1" id="btn-modo-intermunicipal" data-modo="intermunicipal"><x-icone nome="estrada" tamanho="h-4 w-4" /> Viagem intermunicipal</button>
    </div>
    <div class="grid gap-3 md:grid-cols-2" id="form-viagem" style="display:none;">
      <div class="vm-field" id="campo-ponto-partida">
        <label class="vm-label" for="sel-ponto">Ponto de partida</label>
        <select class="vm-select" id="sel-ponto"><option value="">A carregar...</option></select>
      </div>
      <div class="vm-field" id="campo-destino">
        <label class="vm-label" for="sel-destino">Destino</label>
        <select class="vm-select" id="sel-destino"><option value="">A carregar...</option></select>
      </div>
      <div class="vm-field">
        <label class="vm-label" for="sel-lugares">Nº de lugares</label>
        <select class="vm-select" id="sel-lugares">
          @for ($i = 1; $i <= $maxLugares; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
        </select>
      </div>
      <div class="vm-field">
        <label class="vm-label" for="sel-motivo">Motivo</label>
        <select class="vm-select" id="sel-motivo">
          <option value="normal">Normal</option>
          <option value="grupo">Grupo</option>
          <option value="passeio">Passeio</option>
        </select>
      </div>
    </div>
    <p class="vm-small mt-1" id="info-rota-preview" style="display:none;"></p>
    <div class="vm-field relative">
      <label class="vm-label" for="input-descida" id="label-descida">Ponto de descida (opcional)</label>
      <input class="vm-input" type="text" id="input-descida" placeholder="Escreva um local ou clique no mapa" autocomplete="off">
      <div id="descida-sugestoes" class="absolute inset-x-0 top-full z-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10" style="display:none;"></div>
      <p class="vm-small mt-1" id="info-urbano" style="display:none;" class="vm-small mt-1 inline-flex items-center gap-1.5"><x-icone nome="ponto" tamanho="h-4 w-4" /> A sua localização atual será partilhada com os condutores.</p>
    </div>

    <div class="flex flex-wrap gap-2">
      <button class="vm-btn vm-btn--primario" id="btn-buscar" type="button">Ver veículos disponíveis</button>
      <button class="vm-btn vm-btn--ghost" id="btn-ver-taxis-especificos" type="button" style="display:none;"><x-icone nome="veiculo" tamanho="h-4 w-4" /> Ver taxis específicos</button>
      <button class="vm-btn vm-btn--ghost" id="btn-pedir-ponto" type="button" style="display:none;"><x-icone nome="utilizadores" tamanho="h-4 w-4" /> Pedir a qualquer condutor deste ponto</button>
    </div>
    <p class="vm-small mt-2" id="msg-pedido-urbano"></p>

    <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain py-2" id="lista-veiculos"></div>
  </div>
</section>

<!-- Modal de escolha de assento -->
<div class="vm-modal-overlay" id="modal-assentos" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3">Escolha o seu lugar</h3>
    <p class="vm-small mb-4" id="assentos-nota-fila">Fila 0 é junto ao condutor.</p>
    <div class="vm-assentos" id="assentos-grid"></div>
    <div class="mt-4 flex flex-wrap gap-2">
      <button class="vm-btn vm-btn--ghost" id="btn-cancelar-assento" type="button">Cancelar</button>
      <button class="vm-btn vm-btn--cta vm-btn--full" id="btn-confirmar-reserva" type="button" disabled>Reservar lugar</button>
    </div>
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

<!-- Modal de sugestão/reclamação -->
<div class="vm-modal-overlay" id="modal-sugestao" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">Sugestão ou reclamação</h3>
    <form id="form-sugestao">
      <div class="vm-field">
        <label class="vm-label">Tipo</label>
        <select class="vm-select" id="sugestao-tipo" name="tipo">
          <option value="sugestao">Sugestão de melhoria</option>
          <option value="reclamacao">Reclamação sobre um condutor</option>
        </select>
      </div>
      <div class="vm-field" id="campo-sugestao-condutor" style="display:none;">
        <label class="vm-label">Condutor</label>
        <select class="vm-select" id="sugestao-condutor" name="condutor_id"></select>
      </div>
      <div class="vm-field"><label class="vm-label">Título</label><input class="vm-input" name="titulo" id="sugestao-titulo" required></div>
      <div class="vm-field"><label class="vm-label">Descrição</label><textarea class="vm-input" name="descricao" id="sugestao-descricao" rows="4" required></textarea></div>
      <div id="sugestao-msg" class="vm-erro"></div>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-sugestao">Cancelar</button>
        <button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Enviar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de avaliação do condutor -->
<div class="vm-modal-overlay" id="modal-avaliacao" style="display:none;">
  <div class="vm-modal">
    <div class="flex items-start justify-between gap-2">
      <h3 class="vm-h3">Como foi a viagem?</h3>
      <button type="button" class="vm-small cursor-pointer px-1 leading-none" id="btn-fechar-avaliacao" aria-label="Fechar">&times;</button>
    </div>
    <p class="vm-small" id="avaliacao-condutor-nome"></p>
    <div class="my-4 flex justify-center gap-1.5 text-gold-500" id="avaliacao-estrelas">
      @for ($e = 1; $e <= 5; $e++)
        <button type="button" data-estrela="{{ $e }}" class="cursor-pointer rounded p-0.5 transition hover:scale-110"
                aria-label="{{ $e }} de 5 estrelas">
          <x-icone nome="estrela" tamanho="h-9 w-9" />
        </button>
      @endfor
    </div>
    <div class="vm-field"><label class="vm-label">Comentário (opcional)</label><textarea class="vm-input" id="avaliacao-comentario" rows="2"></textarea></div>
    <div class="mt-4 flex flex-wrap gap-2">
      <button type="button" class="vm-btn vm-btn--ghost" id="btn-saltar-avaliacao">Agora não</button>
      <button type="button" class="vm-btn vm-btn--cta vm-btn--full" id="btn-enviar-avaliacao" disabled>Enviar avaliação</button>
    </div>
  </div>
</div>

<!-- Modal de perfil -->
<div class="vm-modal-overlay" id="modal-perfil" style="display:none;">
  <div class="vm-modal">
    <h3 class="vm-h3 mb-4">O meu perfil</h3>
    <form id="form-perfil">
      <div class="vm-field"><label class="vm-label">Nome</label><input class="vm-input" name="nome" id="perfil-nome" required></div>
      <div class="vm-field"><label class="vm-label">Telefone</label><input class="vm-input" name="telefone" id="perfil-telefone" required></div>
      <div class="vm-field"><label class="vm-label">NIF</label><input class="vm-input" name="nif" id="perfil-nif" required maxlength="9"></div>
      <hr class="my-4 border-slate-200">
      <p class="vm-small mb-3">Para alterar a senha, preencha os campos abaixo.</p>
      <x-campo-senha variante="vm" nome="senha_atual" id="perfil-senha-atual" etiqueta="Senha atual" autocomplete="current-password" :obrigatorio="false" />
      <x-campo-senha variante="vm" nome="nova_senha" id="perfil-nova-senha" etiqueta="Nova senha" autocomplete="new-password" :obrigatorio="false" />
      <div id="perfil-msg" class="vm-erro"></div>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="vm-btn vm-btn--ghost" id="btn-fechar-perfil">Fechar</button>
        <button type="submit" class="vm-btn vm-btn--primario vm-btn--full">Guardar</button>
      </div>
    </form>
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

<div id="toast-container" class="fixed right-4 top-seguro [--top-seguro:1rem] z-1100 flex flex-col gap-2 sm:right-5 sm:[--top-seguro:1.25rem]"></div>

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
<script src="@kgAsset('assets/js/vm-senha.js')"></script>
<script nonce="{{ $cspNonce }}">
const CSRF_TOKEN = @json(csrf_token());
const UTILIZADOR_ID = @json((int) $passageiro->id);

// Ícones (config/icones.php) — a mesma lista que o componente x-icone usa do
// lado do servidor. A lista de veículos e a viagem em curso são montadas
// aqui, e não podiam ficar com um conjunto próprio. Expor svgIcone também
// faz o kg-chamada.js desenhar os estados da chamada com estes ícones em vez
// dos emoji de reserva.
// (Sem os sinais de menor/maior à volta do nome do componente: o Blade
// compila isso como componente a sério, mesmo dentro de um comentário.)
const ICONES = @json(config('icones'));

function svgIcone(nome, classe) {
  const desenho = ICONES[nome];
  if (!desenho) return '';
  return `<svg class="${classe || 'h-4 w-4 shrink-0'}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${desenho}</svg>`;
}

// Ícone + texto na mesma linha, alinhados pelo meio.
function linhaIcone(nome, texto) {
  return `<span class="inline-flex items-center gap-1.5 align-middle">${svgIcone(nome)}<span>${texto}</span></span>`;
}
let mapa = null;
let pontoDescidaEscolhido = null; // {nome, lat, lng}
let veiculoEscolhidoId = null;
let assentoEscolhidoId = null;
let viagemTipo = null; // 'urbana' | 'intermunicipal'
let posicaoAtual = null;   // última posição BOA conhecida (mapa, rota, pedido urbano, SOS)
// Envio da posição ao servidor: desacoplado do callback do GPS e com consumo
// único. Antes, o temporizador reenviava 'posicaoAtual' de 5 em 5 s para
// sempre — mesmo com o GPS morto há minutos — e cada POST renovava
// 'passageiro_localizacao_em', deixando o condutor a ver o passageiro "vivo"
// numa coordenada que ele já tinha deixado. (Mesmo padrão do painel do
// condutor.)
const ENVIO_LOCALIZACAO_MS = 5000;
// Zoom usado sempre que o mapa salta para a posição do passageiro (arranque,
// botão de recentrar, mudança para modo urbano): nível de rua, onde já se
// distingue o lado da via em que ele está à espera. Com 15 via-se o bairro.
const ZOOM_SEGUIMENTO = 17;
let posicaoPorEnviar = null;      // { lat, lng, accuracy } ainda não enviada
let envioLocalizacaoTimer = null;
let envioLocalizacaoEmCurso = false;
let watchIdPassageiro = null;
let pontosCache = [];
let veiculoMarkerIds = new Set();
let veiculoMarcadoEstado = new Map();
// Salas socket dos veículos que este passageiro segue. O servidor só envia
// 'veiculo:posicao' a quem está na sala `veiculo:<id>`, e sem sair das salas
// antigas o passageiro ia acumulando, durante toda a sessão, veículos que já
// não vê no mapa — e a receber a posição deles.
let salasVeiculosAtivas = new Set();  // salas em que estamos mesmo dentro
let salaVeiculoReserva = null;        // sala do veículo da reserva ativa
let salasVeiculosLista = new Set();   // salas dos veículos disponíveis no mapa
let buscaVeiculosFeita = false;       // já houve uma pesquisa: o mapa pode ser mantido atualizado
let reservaAtivaDados = null;         // último payload de /minha_reserva (usado pelo evento em tempo real)
// A rota por estrada custa um pedido ao serviço de rotas; a posição do
// condutor chega a cada ~3 s. O marcador acompanha todas as atualizações, a
// rota por estrada é redesenhada no máximo a este ritmo.
const ROTA_RECOLHA_MIN_MS = 6000;
// Traço das rotas por estrada: fino e contínuo, para acompanhar a estrada
// sem a tapar. As pontas/junções redondas evitam os cantos serrilhados nas
// curvas e o smoothFactor baixo mantém o traçado colado ao desenho da via.
const ESTILO_ROTA = { color: '#003893', weight: 2.5, opacity: 0.9, lineCap: 'round', lineJoin: 'round', smoothFactor: 0.5 };
const ESTILO_ROTA_DESTINO = Object.assign({}, ESTILO_ROTA, { color: '#00B4D8' });
let ultimaRotaRecolhaEm = 0;
let reservaAtivaAtual = false;
let reservaEstadoAnterior = null;
// Estado do veículo da reserva no ciclo anterior: é a comparação com este que
// diz que o veículo acabou de sair do ponto (ver avisarSaidaDoPonto).
let reservaVeiculoEstadoAnterior = null;
// A reserva foi fechada pelo próprio passageiro ("Sair desta reserva" ou
// "Finalizar viagem"): ele já sabe o que aconteceu, e sem isto a reserva a
// desaparecer dava-lhe por cima um aviso a dizer que tinha sido o condutor.
let reservaFechadaPeloPassageiro = false;
let reservaAtivaId = null;
let meuVeiculoReservaMarcado = null;
let meuVeiculoReservaEstado = null;
let destinoMarkerCriado = false;
let destinoArrastando = false;
// Do embarque ('a_bordo') até à entrega, o mapa mostra só o GPS do veículo
// desta viagem — ver aplicarModoABordo().
let modoABordo = false;

function kgHeaders() {
  return { 'X-CSRF-Token': CSRF_TOKEN };
}

function toast(mensagem, tipo) {
  const el = document.createElement('div');
  el.className = 'vm-toast' + (tipo === 'erro' ? ' vm-toast--erro' : tipo === 'sucesso' ? ' vm-toast--sucesso' : '');
  el.textContent = mensagem;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

function fd(extra) {
  const d = new FormData();
  d.set('_token', CSRF_TOKEN);
  Object.entries(extra || {}).forEach(([k, v]) => d.set(k, v));
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

async function carregarPontos() {
  const json = await kgApiJSON('/api/passageiro/pontos');
  pontosCache = json.pontos || [];
  const selPonto = document.getElementById('sel-ponto');
  const selDestino = document.getElementById('sel-destino');
  selPonto.innerHTML = '<option value="">Selecione...</option>';
  selDestino.innerHTML = '<option value="">Selecione...</option>';
  pontosCache.forEach(p => {
    const opt1 = new Option(`${p.nome} (${p.cidade})`, p.id);
    const opt2 = new Option(`${p.nome} (${p.cidade})`, p.id);
    selPonto.add(opt1);
    selDestino.add(opt2);
  });
  desenharPontosNoMapa();
}

// Os pontos de paragem são fixos: ficam à parte do carregamento para poderem
// sair do mapa em bloco no modo a bordo e voltar depois da entrega.
function desenharPontosNoMapa() {
  if (modoABordo) return;
  removerPontosDoMapa(); // idempotente: nunca deixa dois marcadores no mesmo ponto
  pontosCache.forEach(p => {
    mapa.addMarker(`ponto-${p.id}`, 'ponto', parseFloat(p.lat), parseFloat(p.lng), {
      popupHtml: `<strong>${esc(p.nome)}</strong><br>${esc(p.cidade)}`,
    });
  });
}

function removerPontosDoMapa(manterPontoId) {
  pontosCache.forEach(p => {
    if (manterPontoId != null && String(p.id) === String(manterPontoId)) return;
    mapa.removeMarker(`ponto-${p.id}`);
  });
}

function distanciaMetros(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Modo de viagem (secção 3): urbana = só destino (partida = ponto urbano mais
// próximo da localização atual); intermunicipal = partida + destino.
function escolherModoViagem(modo) {
  viagemTipo = modo;
  document.getElementById('btn-modo-urbana').classList.toggle('vm-btn--cta', modo === 'urbana');
  document.getElementById('btn-modo-urbana').classList.toggle('vm-btn--outline', modo !== 'urbana');
  document.getElementById('btn-modo-intermunicipal').classList.toggle('vm-btn--cta', modo === 'intermunicipal');
  document.getElementById('btn-modo-intermunicipal').classList.toggle('vm-btn--outline', modo !== 'intermunicipal');
  document.getElementById('form-viagem').style.display = 'grid';
  document.getElementById('lista-veiculos').innerHTML = '';

  const campoPartida = document.getElementById('campo-ponto-partida');
  const campoDestino = document.getElementById('campo-destino');
  const labelDescida = document.getElementById('label-descida');
  const infoUrbano = document.getElementById('info-urbano');
  const btnBuscar = document.getElementById('btn-buscar');
  document.getElementById('msg-pedido-urbano').textContent = '';
  if (modo === 'urbana') {
    campoPartida.style.display = 'none';
    // Viagem urbana: o destino não se escolhe de uma lista — escreve-se (o
    // mesmo campo de autocomplete Nominatim que já existia como "ponto de
    // descida opcional" passa a ser o destino, obrigatório aqui).
    campoDestino.style.display = 'none';
    document.getElementById('sel-destino').value = '';
    labelDescida.textContent = 'Destino';
    document.getElementById('input-descida').placeholder = 'Ex: Plateau, Achada Santo António, Palmarejo...';
    infoUrbano.style.display = 'block';
    btnBuscar.innerHTML = `${svgIcone('sos')} Pedir taxi (qualquer um)`;
    document.getElementById('btn-ver-taxis-especificos').style.display = '';
    document.getElementById('btn-pedir-ponto').style.display = 'none';
    // Reafirma a localização real do passageiro no mapa (marcador kg-marker--eu
    // já existe desde o arranque, mas volta a centrar/dar destaque ao mudar
    // para modo urbano — relatório "localização real no mapa").
    if (posicaoAtual && mapa) {
      mapa.flyTo(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, ZOOM_SEGUIMENTO);
    }
    if (posicaoAtual && pontosCache.length) {
      const urbanos = pontosCache.filter(p => p.zona === 'urbana');
      const candidatos = urbanos.length ? urbanos : pontosCache;
      let maisProximo = candidatos[0];
      let menorDist = Infinity;
      candidatos.forEach(p => {
        const d = distanciaMetros(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, parseFloat(p.lat), parseFloat(p.lng));
        if (d < menorDist) { menorDist = d; maisProximo = p; }
      });
      document.getElementById('sel-ponto').value = maisProximo.id;
    }
  } else {
    campoPartida.style.display = 'block';
    campoDestino.style.display = 'block';
    labelDescida.textContent = 'Ponto de descida (opcional)';
    document.getElementById('input-descida').placeholder = 'Escreva um local ou clique no mapa';
    infoUrbano.style.display = 'none';
    btnBuscar.textContent = 'Ver veículos disponíveis';
    document.getElementById('btn-ver-taxis-especificos').style.display = 'none';
    // O caminho oposto ao de escolher um lugar: o pedido fica do ponto, não
    // de um condutor (ver pedirViagemNoPonto).
    document.getElementById('btn-pedir-ponto').style.display = '';
    mapa.clearRoute('preview-rota');
  }
  atualizarRotaPreview();
}
document.getElementById('btn-modo-urbana').addEventListener('click', () => escolherModoViagem('urbana'));
document.getElementById('btn-modo-intermunicipal').addEventListener('click', () => escolherModoViagem('intermunicipal'));

function formatarDistancia(m) {
  if (m == null) return null;
  return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
}
function formatarDuracao(s) {
  if (s == null) return null;
  const min = Math.round(s / 60);
  return min < 1 ? '< 1 min' : min + ' min';
}
// Mostra a distância/tempo estimado (secção B do relatório do mapa) da
// última rota traçada — some quando caiu no fallback de linha reta (sem
// dados reais de estrada para mostrar).
function mostrarInfoRota(info) {
  const el = document.getElementById('info-rota-preview');
  const dist = formatarDistancia(info?.distanciaM);
  const dur = formatarDuracao(info?.duracaoS);
  if (dist && dur) {
    el.innerHTML = `${linhaIcone('estrada', dist)} · ${linhaIcone('relogio', dur)}`;
    el.style.display = 'block';
  } else {
    el.style.display = 'none';
  }
}

// Traça a rota real (por estrada) entre o ponto de partida e o destino
// escolhidos, assim que ambos estão definidos (secção B do relatório do mapa).
async function atualizarRotaPreview() {
  if (!mapa) return;
  const partida = pontosCache.find(p => String(p.id) === document.getElementById('sel-ponto').value);
  const destino = pontosCache.find(p => String(p.id) === document.getElementById('sel-destino').value);
  if (!partida || !destino) { mapa.clearRoute('preview-rota'); mostrarInfoRota(null); return; }
  const info = await KGMap.tracarRota(
    mapa, 'preview-rota',
    { lat: parseFloat(partida.lat), lng: parseFloat(partida.lng) },
    { lat: parseFloat(destino.lat), lng: parseFloat(destino.lng) },
    ESTILO_ROTA
  );
  mostrarInfoRota(info);
}
document.getElementById('sel-ponto').addEventListener('change', atualizarRotaPreview);
document.getElementById('sel-destino').addEventListener('change', atualizarRotaPreview);

// Entra nas salas dos veículos que interessam agora (o da reserva ativa + os
// que estão no mapa) e sai das que deixaram de interessar. É isto que faz o
// evento 'veiculo:posicao' chegar a este passageiro.
function sincronizarSalasVeiculos() {
  if (!window.kgRt) return;
  const desejadas = new Set(salasVeiculosLista);
  if (salaVeiculoReserva) desejadas.add(salaVeiculoReserva);
  salasVeiculosAtivas.forEach((sala) => { if (!desejadas.has(sala)) window.kgRt.leave(sala); });
  desejadas.forEach((sala) => { if (!salasVeiculosAtivas.has(sala)) window.kgRt.join(sala); });
  salasVeiculosAtivas = desejadas;
}

// Mostra no mapa os veículos disponíveis no ponto (parados ou na fila, com a
// ordem em badge) — para o passageiro ver o carro antes de o escolher.
function atualizarMarcadoresVeiculos(veiculos) {
  // A bordo o mapa é só do veículo desta viagem: os táxis disponíveis já
  // saíram (aplicarModoABordo) e não voltam a ser desenhados até à entrega.
  if (modoABordo) return;
  const idsAnteriores = veiculoMarkerIds;
  const idsAtuais = new Set();
  veiculos.forEach(v => {
    if (v.lat == null || v.lng == null) return;
    const id = `veiculo-${v.id}`;
    idsAtuais.add(id);
    const lat = parseFloat(v.lat);
    const lng = parseFloat(v.lng);
    // Recria o marcador quando o estado real mudou (ex: entrou na fila) —
    // só mover a posição não atualizaria a cor/badge (tarefa 5 do relatório).
    if (idsAnteriores.has(id) && veiculoMarcadoEstado.get(id) === v.estado) {
      // Desliza até à posição nova, como o veículo da reserva: os táxis da
      // lista também andam no mapa enquanto o passageiro escolhe.
      mapa.moveMarkerSuave(id, lat, lng);
    } else {
      if (idsAnteriores.has(id)) mapa.removeMarker(id);
      const naFila = v.estado === 'na_fila';
      mapa.addMarker(id, 'veiculo', lat, lng, {
        classes: KGMap.veiculoEstadoClasse(v.estado),
        badge: naFila ? String(v.posicao_fila) : null,
        html: KGMap.veiculoIconeHtml(v),
        popupHtml: KGMap.veiculoPopupHtml(v),
      });
      veiculoMarcadoEstado.set(id, v.estado);
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) { mapa.removeMarker(id); veiculoMarcadoEstado.delete(id); } });
  veiculoMarkerIds = idsAtuais;

  // Seguir em tempo real os veículos que estão mesmo no mapa: sem isto, um
  // táxi listado só mudava de sítio na pesquisa seguinte.
  salasVeiculosLista = new Set();
  veiculos.forEach(v => { if (v.lat != null && v.lng != null) salasVeiculosLista.add(`veiculo:${v.id}`); });
  sincronizarSalasVeiculos();
}

// Parâmetros da procura de veículos, iguais nas duas chamadas (a pesquisa e
// o ciclo que mantém o mapa vivo).
//
// A posição vai junto porque o servidor só mostra os táxis que estão dentro
// do município onde o passageiro está — numa viagem urbana é o GPS dele que
// define esse município (ver Passageiro\VeiculoController::disponiveis).
// Enquanto o GPS não responde, o servidor usa o ponto escolhido.
function parametrosVeiculos(pontoId, destinoId) {
  const qs = new URLSearchParams({ ponto_id: pontoId });
  if (destinoId) qs.set('destino_id', destinoId);
  if (viagemTipo) qs.set('viagem_tipo', viagemTipo);
  if (posicaoAtual) {
    qs.set('lat', posicaoAtual.coords.latitude);
    qs.set('lng', posicaoAtual.coords.longitude);
  }
  return qs;
}

async function buscarVeiculos() {
  const pontoId = document.getElementById('sel-ponto').value;
  const destinoId = document.getElementById('sel-destino').value;
  const lista = document.getElementById('lista-veiculos');
  if (!pontoId) { lista.innerHTML = '<p class="vm-small">Escolha o ponto de partida.</p>'; return; }

  lista.innerHTML = '<div class="vm-skeleton h-32 w-56 shrink-0"></div>';
  const qs = parametrosVeiculos(pontoId, destinoId);

  const json = await kgApiJSON(`/api/passageiro/veiculos_disponiveis?${qs}`);
  const veiculos = json.veiculos || [];

  buscaVeiculosFeita = true;
  atualizarMarcadoresVeiculos(veiculos);

  if (!veiculos.length) {
    // O município na frase explica a lista vazia: os táxis mostrados são os
    // que estão dentro do concelho da procura, não os do país todo.
    lista.innerHTML = `<p class="vm-small">Nenhum veículo disponível${json.municipio ? ' em ' + esc(json.municipio) : ''} neste momento.</p>`;
    return;
  }

  lista.innerHTML = '';
  veiculos.forEach(v => {
    const card = document.createElement('div');
    card.className = 'vm-card pass-veiculo w-56 shrink-0 snap-start p-4 transition hover:-translate-y-0.5 hover:shadow-lg sm:p-4';
    card.dataset.veiculoId = v.id;
    const estrelas = v.condutor_avaliacao_total > 0
      ? `<span class="inline-flex items-center gap-1 align-middle">${svgIcone('estrela', 'h-3.5 w-3.5 fill-current')}${esc(v.condutor_avaliacao_media)} (${esc(v.condutor_avaliacao_total)})</span>`
      : 'Sem avaliações ainda';
    card.innerHTML = `
      <h4 class="vm-h3 mb-1 text-base">${esc(v.matricula)}</h4>
      <p class="vm-small mb-1">${esc(v.tipo)} · ${esc(v.cor)} · ${esc(v.condutor_nome)}</p>
      ${v.destino_nome ? `<p class="vm-small mb-1">${linhaIcone('bandeira', 'Destino: <strong>' + esc(v.destino_nome) + '</strong>')}</p>` : ''}
      <p class="vm-small mb-2 font-semibold text-gold-700">${estrelas}</p>
      <p class="vm-small pass-veiculo-lugares mb-2">${esc(v.lugares_livres)} lugares livres ${v.estado === 'na_fila' ? `· Fila #${esc(v.posicao_fila ?? '')}` : ''}</p>
      <button class="vm-btn vm-btn--primario vm-btn--sm vm-btn--full" type="button">${
        viagemTipo === 'urbana' ? `${svgIcone('telefone')} Chamar este taxi` : 'Escolher'}</button>`;
    card.querySelector('button').addEventListener('click', () => abrirModalAssentos(v.id));
    lista.appendChild(card);
  });
}

// Movimento dos táxis disponíveis: depois da pesquisa, os marcadores são
// seguidos ao vivo pelo evento 'veiculo:posicao' (salas de veiculoMarkerIds).
// Este ciclo é a rede de segurança — traz os que entraram ou saíram do ponto
// entretanto e mantém o mapa certo quando o tempo real cai para modo polling.
// Só mexe no mapa: a lista em HTML não é redesenhada, para não saltar debaixo
// do dedo do passageiro a meio de uma escolha.
async function atualizarVeiculosNoMapa() {
  if (!buscaVeiculosFeita || modoABordo) return; // a bordo não há táxis disponíveis para atualizar
  const pontoId = document.getElementById('sel-ponto').value;
  if (!pontoId) return;
  const qs = parametrosVeiculos(pontoId, document.getElementById('sel-destino').value);
  try {
    const json = await kgApiJSON(`/api/passageiro/veiculos_disponiveis?${qs}`);
    atualizarMarcadoresVeiculos(json.veiculos || []);
  } catch (erro) {
    // Rede em baixo: os marcadores atuais ficam como estão até ao próximo ciclo.
  }
}

// Viagem urbana: em vez de escolher um carro específico de uma lista, o
// pedido fica em aberto e visível a qualquer condutor aprovado e em dia —
// mesmo fora de um ponto (relatório "condutores fora do ponto veem
// passageiros urbanos"). O primeiro a clicar "Ir buscar" fica com ele.
async function pedirViagemUrbana() {
  const msg = document.getElementById('msg-pedido-urbano');
  if (!posicaoAtual) { msg.textContent = 'A aguardar sinal de GPS...'; return; }
  if (!pontoDescidaEscolhido) { msg.textContent = 'Escreva o destino (escolha uma das sugestões da pesquisa).'; return; }
  const pontoId = document.getElementById('sel-ponto').value;
  if (!pontoId) { msg.textContent = 'A localizar o ponto de referência mais próximo...'; return; }

  const btn = document.getElementById('btn-buscar');
  btn.disabled = true;
  msg.textContent = 'A pedir viagem...';

  const resp = await fetch('/api/passageiro/pedir_urbano', {
    method: 'POST',
    body: fd({
      ponto_partida_id: pontoId,
      origem_lat: posicaoAtual.coords.latitude,
      origem_lng: posicaoAtual.coords.longitude,
      ponto_descida_nome: pontoDescidaEscolhido.nome,
      ponto_descida_lat: pontoDescidaEscolhido.lat,
      ponto_descida_lng: pontoDescidaEscolhido.lng,
      lugares: document.getElementById('sel-lugares').value,
      motivo: document.getElementById('sel-motivo').value,
    }),
  });
  const json = await resp.json();
  btn.disabled = false;
  if (!resp.ok) { msg.textContent = json.erro || 'Não foi possível pedir a viagem.'; return; }
  msg.textContent = '';
  await atualizarReservaAtiva();
}

// Viagem intermunicipal sem escolher carro: o pedido fica em aberto no ponto
// e é visto por todos os condutores ancorados nele — o primeiro a carregar em
// "Ir buscar" fica com ele. Escolher um lugar no mapa de assentos é o caminho
// oposto: essa reserva nasce presa a um único condutor.
async function pedirViagemNoPonto() {
  const msg = document.getElementById('msg-pedido-urbano');
  const pontoId = document.getElementById('sel-ponto').value;
  const destinoId = document.getElementById('sel-destino').value;
  if (!pontoId) { msg.textContent = 'Escolha o ponto de partida.'; return; }
  if (!destinoId) { msg.textContent = 'Escolha o destino.'; return; }

  const btn = document.getElementById('btn-pedir-ponto');
  btn.disabled = true;
  msg.textContent = 'A enviar o pedido aos condutores do ponto...';

  const dados = {
    ponto_partida_id: pontoId,
    destino_id: destinoId,
    lugares: document.getElementById('sel-lugares').value,
    motivo: document.getElementById('sel-motivo').value,
  };
  if (pontoDescidaEscolhido) {
    dados.ponto_descida_nome = pontoDescidaEscolhido.nome || '';
    dados.ponto_descida_lat = pontoDescidaEscolhido.lat;
    dados.ponto_descida_lng = pontoDescidaEscolhido.lng;
  }

  try {
    const resp = await fetch('/api/passageiro/pedir_ponto', { method: 'POST', body: fd(dados) });
    const json = await resp.json();
    if (!resp.ok) { msg.textContent = json.erro || 'Não foi possível enviar o pedido.'; return; }
    msg.textContent = '';
    // Avisa já os condutores do ponto, em vez de os deixar esperar pelo
    // próximo ciclo de polling da lista.
    if (window.kgRt) window.kgRt.emit('pedido:ponto', { pontoId: json.ponto_partida_id, reservaId: json.reserva_id });
    await atualizarReservaAtiva();
  } finally {
    btn.disabled = false;
  }
}

// Atualiza a lotação apresentada em tempo real quando um passageiro embarca/desce.
function atualizarLugaresNaLista(veiculoId, lugaresLivres) {
  const card = document.querySelector(`.pass-veiculo[data-veiculo-id="${veiculoId}"] .pass-veiculo-lugares`);
  if (card) card.textContent = card.textContent.replace(/^\d+/, String(lugaresLivres));
}

async function abrirModalAssentos(veiculoId) {
  veiculoEscolhidoId = veiculoId;
  assentoEscolhidoId = null;
  document.getElementById('btn-confirmar-reserva').disabled = true;

  const json = await kgApiJSON(`/api/passageiro/assentos?veiculo_id=${veiculoId}`);
  const grid = document.getElementById('assentos-grid');
  grid.innerHTML = '';

  const filas = {};
  (json.assentos || []).forEach(a => {
    filas[a.fila] = filas[a.fila] || [];
    filas[a.fila].push(a);
  });

  // Um veículo sem fila 0 (todos os lugares no banco de trás) não precisa da
  // nota sobre ela — só confundia.
  document.getElementById('assentos-nota-fila').style.display = filas['0'] ? '' : 'none';

  Object.keys(filas).sort((a, b) => a - b).forEach(filaNum => {
    const filaEl = document.createElement('div');
    filaEl.className = 'vm-assentos__fila' + (filaNum === '0' ? ' vm-assentos__fila--condutor' : '');
    filas[filaNum].sort((a, b) => a.coluna - b.coluna).forEach(a => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'vm-assento ' + (a.ocupado ? 'vm-assento--ocupado' : 'vm-assento--livre');
      btn.textContent = a.numero;
      // Cada banco na sua coluna: um lugar retirado pelo condutor não vem na
      // lista, e sem isto os bancos seguintes deslizavam para o buraco.
      btn.style.gridColumnStart = a.coluna;
      btn.disabled = !!a.ocupado;
      btn.addEventListener('click', () => {
        grid.querySelectorAll('.vm-assento--selecionado').forEach(el => el.classList.remove('vm-assento--selecionado'));
        btn.classList.add('vm-assento--selecionado');
        assentoEscolhidoId = a.id;
        document.getElementById('btn-confirmar-reserva').disabled = false;
      });
      filaEl.appendChild(btn);
    });
    grid.appendChild(filaEl);
  });

  document.getElementById('modal-assentos').style.display = 'flex';
}

document.getElementById('btn-cancelar-assento').addEventListener('click', () => {
  document.getElementById('modal-assentos').style.display = 'none';
});

document.getElementById('btn-confirmar-reserva').addEventListener('click', async () => {
  if (viagemTipo === 'urbana' && !pontoDescidaEscolhido) {
    alert('Escreva o destino (escolha uma das sugestões da pesquisa).');
    return;
  }

  const btn = document.getElementById('btn-confirmar-reserva');
  btn.disabled = true;
  btn.textContent = 'A reservar...';

  const dados = new FormData();
  dados.set('_token', CSRF_TOKEN);
  dados.set('veiculo_id', veiculoEscolhidoId);
  dados.set('assento_id', assentoEscolhidoId);
  dados.set('ponto_partida_id', document.getElementById('sel-ponto').value);
  dados.set('destino_id', document.getElementById('sel-destino').value);
  dados.set('tipo_viagem', viagemTipo === 'urbana' ? 'urbano' : 'intermunicipal');
  dados.set('lugares', document.getElementById('sel-lugares').value);
  dados.set('motivo', document.getElementById('sel-motivo').value);
  if (pontoDescidaEscolhido) {
    dados.set('ponto_descida_nome', pontoDescidaEscolhido.nome || '');
    dados.set('ponto_descida_lat', pontoDescidaEscolhido.lat);
    dados.set('ponto_descida_lng', pontoDescidaEscolhido.lng);
  }

  try {
    const resp = await fetch('/api/passageiro/reservar', { method: 'POST', body: dados });
    const json = await resp.json();
    if (!resp.ok) { alert(json.erro || 'Não foi possível reservar.'); return; }

    if (window.kgRt) window.kgRt.emit('reserva:nova', { veiculoId: json.veiculo_id, reservaId: json.reserva_id });
    document.getElementById('modal-assentos').style.display = 'none';
    await atualizarReservaAtiva();
  } finally {
    btn.disabled = false;
    btn.textContent = 'Reservar lugar';
  }
});

document.getElementById('btn-buscar').addEventListener('click', () => {
  if (viagemTipo === 'urbana') pedirViagemUrbana(); else buscarVeiculos();
});
// Viagem urbana: em vez de esperar por qualquer condutor, o passageiro pode
// ver e escolher um taxi específico (relatório "passageiro vê taxis urbanos
// disponíveis" — "pode escolher um específico... ou apenas pedir").
document.getElementById('btn-ver-taxis-especificos').addEventListener('click', buscarVeiculos);
document.getElementById('btn-pedir-ponto').addEventListener('click', pedirViagemNoPonto);

// Escolher um destino (de destinos_urbanos já gravados ou de uma sugestão
// Nominatim) — lógica partilhada pelas duas fontes de sugestões abaixo.
function escolherPontoDescida(nome, lat, lng) {
  pontoDescidaEscolhido = { nome, lat, lng };
  document.getElementById('input-descida').value = nome;
  document.getElementById('descida-sugestoes').style.display = 'none';
  mapa.addMarker('descida', 'descida', lat, lng);
  mapa.updateMarker('descida', lat, lng);
  // Viagem urbana: o destino escrito É o ponto de descida — mostra logo a
  // rota real (da minha posição GPS, não do ponto mais próximo) e roda a
  // seta do meu marcador para essa direção (relatório "rota e seta da
  // localização real para o destino").
  if (viagemTipo === 'urbana' && posicaoAtual) {
    const origem = { lat: posicaoAtual.coords.latitude, lng: posicaoAtual.coords.longitude };
    KGMap.tracarRota(mapa, 'preview-rota', origem, { lat, lng }, ESTILO_ROTA).then(mostrarInfoRota);
    mapa.setMarkerRotation('eu', KGMap.calcularRumo(origem.lat, origem.lng, lat, lng));
  }
}

function itemSugestaoDescida(texto, icone, onClick) {
  const item = document.createElement('div');
  item.className = 'vm-small flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3.5 py-2.5 text-slate-700 transition hover:bg-slate-50';
  // 'icone' é o nome em config/icones.php (era um emoji em texto).
  item.innerHTML = `${svgIcone(icone)}<span>${esc(texto)}</span>`;
  item.addEventListener('click', onClick);
  return item;
}

// Autocomplete de ponto de descida: primeiro os destinos urbanos já
// gravados por outros passageiros (rápido, sem pedido externo — secção
// "destinos criados por passageiros são gravados e reutilizáveis"),
// depois a pesquisa Nominatim (restrita a Cabo Verde) para o resto.
let debounceTimer = null;
document.getElementById('input-descida').addEventListener('input', (ev) => {
  clearTimeout(debounceTimer);
  const termo = ev.target.value.trim();
  const box = document.getElementById('descida-sugestoes');
  if (termo.length < 3) { box.style.display = 'none'; return; }
  debounceTimer = setTimeout(async () => {
    box.innerHTML = '';
    let totalSugestoes = 0;

    try {
      const jsonGravados = await kgApiJSON(`/api/passageiro/destinos_urbanos?q=${encodeURIComponent(termo)}`);
      (jsonGravados.destinos || []).forEach(d => {
        box.appendChild(itemSugestaoDescida(d.nome, 'estrela', () => escolherPontoDescida(d.nome, parseFloat(d.lat), parseFloat(d.lng))));
        totalSugestoes++;
      });
    } catch (e) { /* silencioso */ }

    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&countrycodes=cv&viewbox=-25.4,17.2,-22.7,14.7&bounded=1&q=${encodeURIComponent(termo)}`;
      const resp = await fetch(url, { headers: { 'Accept-Language': 'pt' } });
      const resultados = await resp.json();
      resultados.slice(0, 5).forEach(r => {
        box.appendChild(itemSugestaoDescida(r.display_name, 'ponto', () => escolherPontoDescida(r.display_name, parseFloat(r.lat), parseFloat(r.lon))));
        totalSugestoes++;
      });
    } catch (e) { /* silencioso */ }

    box.style.display = totalSugestoes ? 'block' : 'none';
  }, 400);
});

let reservaParaAvaliar = null;
let estrelaEscolhida = 0;

// A classificação nunca é obrigatória: quando o passageiro fecha o modal,
// marcamos a reserva como dispensada no servidor para o painel não voltar a
// propô-la — aqui ou noutro dispositivo. O conjunto local só cobre o intervalo
// entre o fecho e a resposta do pedido, para o ciclo de 8 s não reabrir o
// modal pelo meio.
const avaliacoesDispensadas = new Set();

function fecharModalAvaliacao({ dispensar = false } = {}) {
  const reservaId = reservaParaAvaliar;
  reservaParaAvaliar = null;
  document.getElementById('modal-avaliacao').style.display = 'none';
  if (!dispensar || !reservaId) return;

  avaliacoesDispensadas.add(reservaId);
  // Sem alerta se falhar: o passageiro pediu para sair do modal, e o pior
  // caso é a avaliação voltar a ser proposta na visita seguinte.
  fetch('/api/avaliacao/dispensar', { method: 'POST', body: fd({ reserva_id: reservaId }) })
    .catch(() => { /* silencioso */ });
}

function abrirModalAvaliacao(pendente) {
  reservaParaAvaliar = pendente.reserva_id;
  estrelaEscolhida = 0;
  document.getElementById('avaliacao-condutor-nome').textContent = `Condutor: ${pendente.condutor_nome} (${pendente.matricula})`;
  document.getElementById('avaliacao-comentario').value = '';
  document.getElementById('btn-enviar-avaliacao').disabled = true;
  pintarEstrelas(0);
  document.getElementById('modal-avaliacao').style.display = 'flex';
}

function pintarEstrelas(n) {
  // fill-current ganha ao fill="none" do <svg> (CSS vence atributos de
  // apresentação), por isso a mesma estrela serve cheia e vazia.
  document.querySelectorAll('#avaliacao-estrelas [data-estrela] svg').forEach(svg => {
    const cheia = Number(svg.closest('[data-estrela]').dataset.estrela) <= n;
    svg.classList.toggle('fill-current', cheia);
  });
}

document.querySelectorAll('#avaliacao-estrelas [data-estrela]').forEach(el => {
  el.addEventListener('click', () => {
    estrelaEscolhida = Number(el.dataset.estrela);
    pintarEstrelas(estrelaEscolhida);
    document.getElementById('btn-enviar-avaliacao').disabled = false;
  });
});
['btn-saltar-avaliacao', 'btn-fechar-avaliacao'].forEach(id => {
  document.getElementById(id).addEventListener('click', () => fecharModalAvaliacao({ dispensar: true }));
});
document.getElementById('modal-avaliacao').addEventListener('click', (ev) => {
  if (ev.target === ev.currentTarget) fecharModalAvaliacao({ dispensar: true });
});
document.addEventListener('keydown', (ev) => {
  if (ev.key === 'Escape' && document.getElementById('modal-avaliacao').style.display === 'flex') {
    fecharModalAvaliacao({ dispensar: true });
  }
});
document.getElementById('btn-enviar-avaliacao').addEventListener('click', async () => {
  if (!reservaParaAvaliar || !estrelaEscolhida) return;
  const resp = await fetch('/api/avaliacao/criar', {
    method: 'POST',
    body: fd({ reserva_id: reservaParaAvaliar, avaliacao: estrelaEscolhida, comentario: document.getElementById('avaliacao-comentario').value }),
  });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível enviar a avaliação.'); return; }
  fecharModalAvaliacao();
});

// Marcador do veículo da reserva + as rotas que dependem da posição dele.
// Fica fora de atualizarReservaAtiva porque tem dois chamadores: o ciclo de
// 8 s (que traz o resto do estado da reserva) e o evento 'veiculo:posicao'
// em tempo real, que traz só a coordenada nova do condutor.
function desenharVeiculoReserva(r, lat, lng) {
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
  const idVeiculo = `v-${r.veiculo_id}`;
  // Recria o marcador quando o estado real do veículo mudou (no_ponto ->
  // na_fila -> em_movimento -> ...), para a cor refletir a posição real
  // (relatório do mapa, tarefa 5) — não só quando o veículo muda.
  if (meuVeiculoReservaMarcado !== idVeiculo || meuVeiculoReservaEstado !== r.veiculo_estado) {
    if (meuVeiculoReservaMarcado) mapa.removeMarker(meuVeiculoReservaMarcado);
    // Mesma correção do texto acima: para viagem urbana, r.destino_nome é
    // só o placeholder da FK (= ponto de partida) — o popup do veículo
    // tem de mostrar o destino real (ponto_descida_nome), não esse.
    const rParaPopup = r.tipo_viagem === 'urbano' ? Object.assign({}, r, { destino_nome: r.ponto_descida_nome }) : r;
    mapa.addMarker(idVeiculo, 'veiculo', lat, lng, {
      classes: KGMap.veiculoEstadoClasse(r.veiculo_estado),
      html: KGMap.veiculoIconeHtml(r),
      popupHtml: KGMap.veiculoPopupHtml(rParaPopup),
    });
    meuVeiculoReservaMarcado = idVeiculo;
    meuVeiculoReservaEstado = r.veiculo_estado;
  } else {
    // Desliza até à posição nova em vez de saltar para ela: a posição do
    // condutor chega a cada ~3 s e, colocada de uma vez, o carro aparecia de
    // sítio em sítio — quem está à espera dele só via saltos, não o
    // movimento. Entre duas posições o marcador continua a andar.
    mapa.moveMarkerSuave(idVeiculo, lat, lng);
  }

  // A bordo o mapa acompanha o carro — é nele que o passageiro vai, e o ponto
  // azul do telemóvel deixou de estar no mapa. Seguimento contínuo (e não
  // panTo): cada panTo arrancava uma animação própria do Leaflet, que acelera
  // e trava, e era cortada pela posição seguinte — o mapa andava aos puxões.
  if (modoABordo && aSeguirUtilizador) mapa.seguirSuave(lat, lng);

  // Rota do condutor até ao meu ponto de partida, enquanto ainda não
  // embarquei. Limitada a ROTA_RECOLHA_MIN_MS: o marcador acompanha cada
  // atualização, mas a rota por estrada é um pedido ao servidor e não vale a
  // pena refazê-la a cada 3 s por causa de uns metros.
  if (r.estado !== 'a_bordo' && r.ponto_partida_lat && r.ponto_partida_lng) {
    if (Date.now() - ultimaRotaRecolhaEm >= ROTA_RECOLHA_MIN_MS) {
      ultimaRotaRecolhaEm = Date.now();
      KGMap.tracarRota(mapa, 'rota-recolha', { lat, lng }, { lat: parseFloat(r.ponto_partida_lat), lng: parseFloat(r.ponto_partida_lng) }, ESTILO_ROTA);
    }
  } else {
    mapa.clearRoute('rota-recolha');
  }
}

// Modo a bordo (embarque -> entrega): o mapa fica só com o veículo desta
// viagem, o destino e a rota que falta. Sai tudo o resto — táxis disponíveis,
// pontos de paragem e o próprio ponto do GPS do passageiro, que a esta altura
// está dentro do carro, em cima do marcador dele, e só dava um segundo ponto
// a competir pelo mesmo sítio. Volta tudo quando a viagem termina.
function aplicarModoABordo(ativo) {
  if (ativo === modoABordo) return;
  modoABordo = ativo;

  if (ativo) {
    veiculoMarkerIds.forEach(id => mapa.removeMarker(id));
    veiculoMarkerIds = new Set();
    veiculoMarcadoEstado.clear();
    // Sem marcadores destes veículos no mapa, não há razão para continuar a
    // receber a posição deles: fica só a sala do veículo da reserva.
    salasVeiculosLista = new Set();
    sincronizarSalasVeiculos();
    // Numa viagem intermunicipal o destino é um ponto da carreira: esse fica
    // no mapa, senão o passageiro via o carro a andar sem saber para onde.
    // (No urbano, destino_id é só o placeholder do ponto de partida — ver
    // ReservaController::reservar — e não representa destino nenhum.)
    removerPontosDoMapa(
      reservaAtivaDados && reservaAtivaDados.tipo_viagem !== 'urbano' ? reservaAtivaDados.destino_id : null
    );
    mapa.removeMarker('eu');
    mapa.clearUserAccuracy();
    mapa.clearRoute('preview-rota');
    mapa.clearRoute('rota-recolha');
    if (reservaAtivaDados && reservaAtivaDados.veiculo_lat && reservaAtivaDados.veiculo_lng) {
      mapa.flyTo(parseFloat(reservaAtivaDados.veiculo_lat), parseFloat(reservaAtivaDados.veiculo_lng), ZOOM_SEGUIMENTO);
    }
    return;
  }

  // Entregue (ou reserva terminada): repor o mapa normal. Os táxis disponíveis
  // voltam sozinhos no próximo ciclo de atualizarVeiculosNoMapa.
  // A rota até ao destino da carreira (desenhada abaixo só a bordo) sai aqui:
  // fora do modo a bordo quem manda no traçado é o bloco do ponto de descida.
  mapa.clearRoute('rota-destino');
  desenharPontosNoMapa();
  if (posicaoAtual) {
    mapa.setUserPosition(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude);
    mapa.setUserAccuracy(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, posicaoAtual.coords.accuracy);
    if (aSeguirUtilizador) mapa.flyTo(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, ZOOM_SEGUIMENTO);
  }
}

// Enquanto o táxi já confirmou (e até à entrega), o passageiro não sai da
// reserva nem termina a sessão pelo topo: esconder ambos os "Sair".
function aplicarViagemComprometida(ativo) {
  const link = document.getElementById('link-sair');
  if (link) link.style.display = ativo ? 'none' : '';
}

// Estados de um veículo parado no ponto e de um veículo já em rota: a
// passagem de um grupo para o outro é a saída do ponto.
const ESTADOS_VEICULO_NO_PONTO = ['no_ponto', 'na_fila', 'chegou_destino'];
const ESTADOS_VEICULO_EM_ROTA = ['partiu_da_fila', 'em_movimento'];

// O condutor carregou em "Iniciar rota (sair do ponto)": o veículo passa a
// 'partiu_da_fila' e, na primeira posição de GPS que mandar, a
// 'em_movimento'. Quem está à espera no ponto não tem como saber disto — o
// mesmo aviso vai por push (ver App\Services\AvisoViagemService), para chegar
// também com a app fechada.
//
// O que se compara são os dois grupos, e não 'partiu_da_fila' em concreto: o
// condutor manda posição a cada ~3 s e este painel relê a reserva a cada 8 s,
// por isso o estado intermédio perde-se quase sempre — a saída do ponto
// chegaria aqui já como 'em_movimento'.
//
// A comparação é com o ciclo anterior e nunca com o primeiro: entrar no painel
// com a viagem já a decorrer não é uma saída do ponto acabada de acontecer.
function avisarSaidaDoPonto(r) {
  const anterior = reservaVeiculoEstadoAnterior;
  reservaVeiculoEstadoAnterior = r.veiculo_estado || null;

  if (!anterior || !ESTADOS_VEICULO_NO_PONTO.includes(anterior)) return;
  if (!ESTADOS_VEICULO_EM_ROTA.includes(r.veiculo_estado)) return;

  toast(`O veículo saiu do ponto${r.ponto_partida_nome ? ' ' + r.ponto_partida_nome : ''}.`, 'sucesso');
  KGSons.tocarMensagem();
}

async function atualizarReservaAtiva() {
  const json = await kgApiJSON('/api/passageiro/minha_reserva');
  const painel = document.getElementById('reserva-ativa');
  const formWrap = document.getElementById('form-wrap');

  if (!json.reserva) {
    // A reserva desapareceu sem ter sido o passageiro a fechá-la. A bordo, é
    // a chegada: ou o GPS do condutor chegou ao ponto de destino e fechou a
    // viagem sozinho (ver PosicaoController), ou o condutor marcou a chegada.
    // Antes de embarcar, é o lugar que se perdeu — o caso mais comum é o
    // veículo ter saído do ponto (ver Condutor\FilaController), mas o condutor
    // também pode ter recusado o pedido, por isso a frase fica pelo que é
    // verdade nos dois casos.
    //
    // Nenhuma das duas nomeia o ponto: o condutor pode marcar a chegada a
    // meio da rota, no ponto de descida do passageiro. Quem é específico é o
    // aviso por push, que só sai mesmo na saída e na chegada ao ponto.
    if (reservaEstadoAnterior && !reservaFechadaPeloPassageiro) {
      toast(reservaEstadoAnterior === 'a_bordo'
        ? 'Chegou ao destino — viagem concluída.'
        : 'A sua reserva foi libertada — o lugar já não está guardado. Pode reservar outro veículo.',
        reservaEstadoAnterior === 'a_bordo' ? 'sucesso' : 'erro');
      KGSons.tocarMensagem();
    }
    reservaFechadaPeloPassageiro = false;
    aplicarModoABordo(false);
    aplicarViagemComprometida(false);
    reservaAtivaAtual = false;
    reservaAtivaId = null;
    reservaEstadoAnterior = null;
    reservaVeiculoEstadoAnterior = null;
    reservaAtivaDados = null;
    ultimaRotaRecolhaEm = 0; // a próxima reserva desenha a rota logo, sem esperar pelo limite
    // Reserva terminada/cancelada: deixar de seguir o veículo dela.
    salaVeiculoReserva = null;
    sincronizarSalasVeiculos();
    if (meuVeiculoReservaMarcado) { mapa.removeMarker(meuVeiculoReservaMarcado); meuVeiculoReservaMarcado = null; meuVeiculoReservaEstado = null; }
    if (destinoMarkerCriado) { mapa.removeMarker('descida'); destinoMarkerCriado = false; destinoArrastando = false; }
    mapa.clearRoute('rota-recolha');
    mapa.clearRoute('rota-destino');
    painel.style.display = 'none';
    formWrap.style.display = 'block';
    if (json.avaliar_pendente
        && document.getElementById('modal-avaliacao').style.display !== 'flex'
        && !avaliacoesDispensadas.has(json.avaliar_pendente.reserva_id)) {
      abrirModalAvaliacao(json.avaliar_pendente);
    }
    return;
  }

  const r = json.reserva;
  // Guardado para o evento 'veiculo:posicao' poder redesenhar o veículo sem
  // esperar pelo próximo ciclo de 8 s (o evento só traz a coordenada).
  reservaAtivaDados = r;
  reservaAtivaAtual = ['pendente', 'confirmado', 'a_caminho', 'a_bordo'].includes(r.estado);
  reservaAtivaId = r.id;
  painel.style.display = 'block';
  formWrap.style.display = 'none';

  // Pedido de viagem urbana: um condutor está a caminho assim que a reserva
  // passa de pendente a confirmado com veículo já atribuído (tarefa D do
  // relatório "condutor confirma que está a caminho").
  if (r.tipo_viagem === 'urbano' && reservaEstadoAnterior === 'pendente' && r.estado === 'confirmado' && r.condutor_nome) {
    toast(`${r.condutor_nome} está a caminho para o(a) buscar!`, 'sucesso');
  }
  avisarSaidaDoPonto(r);
  reservaEstadoAnterior = r.estado;
  // Embarcado: mapa só com o GPS do veículo, até o condutor (ou o passageiro)
  // marcar a viagem como entregue.
  aplicarModoABordo(r.estado === 'a_bordo');

  const badge = document.getElementById('reserva-badge');
  badge.className = 'vm-badge vm-badge--' + r.estado;
  badge.textContent = { pendente: 'Pendente', confirmado: 'Confirmado', a_caminho: 'Condutor a caminho', a_bordo: 'A bordo', concluido: 'Concluído', recusado: 'Recusado' }[r.estado] || r.estado;

  // Viagem urbana: destino_id é só um placeholder (= ponto_partida_id, para
  // satisfazer a FK — ver migration_20260809_viagem_urbana.sql) e nunca o
  // destino real, por isso destino_nome dá sempre o mesmo nome do ponto de
  // partida ("Estádio da Várzea → Estádio da Várzea"). O destino real é
  // sempre ponto_descida_nome, escrito pelo passageiro — tem de ser usado
  // aqui independentemente de já haver veículo atribuído ou não.
  const nomeDestinoReal = r.tipo_viagem === 'urbano' ? r.ponto_descida_nome : (r.ponto_descida_nome || r.destino_nome);
  document.getElementById('reserva-detalhes').textContent = r.veiculo_id
    ? `${r.tipo_viagem === 'urbano' ? 'A minha localização' : r.ponto_partida_nome} → ${nomeDestinoReal} · ${r.matricula} (${r.tipo}, ${r.cor}) · ${r.preco_final} CVE`
    : `À espera de um condutor disponível · Destino: ${nomeDestinoReal} · ${r.preco_final} CVE (estimado)`;

  const condutorEl = document.getElementById('reserva-condutor');
  if (r.condutor_telefone) {
    const tel = r.condutor_telefone.replace(/\D/g, '');
    condutorEl.innerHTML = `<p class="vm-small">Condutor: ${esc(r.condutor_nome)} —
      <button type="button" class="vm-btn vm-btn--outline vm-btn--sm" id="btn-chamar-condutor">${svgIcone('telefone')} Chamar</button>
      <a href="https://wa.me/${esc(tel)}" target="_blank" class="inline-flex items-center gap-1.5 font-semibold text-emerald-600 hover:underline">${svgIcone('chat')} WhatsApp</a></p>`;
    document.getElementById('btn-chamar-condutor').addEventListener('click', () => {
      KGChamada.iniciar(r.condutor_id, r.condutor_nome);
    });
  } else {
    condutorEl.innerHTML = '';
  }

  if (r.veiculo_lat && r.veiculo_lng) {
    desenharVeiculoReserva(r, parseFloat(r.veiculo_lat), parseFloat(r.veiculo_lng));
  }

  // Marcador e rota do destino: persistidos na reserva (nunca só em memória
  // JS), por isso sobrevivem a atualizar a página — corrige o bug em que a
  // rota e o destino desapareciam ao recarregar (relatório "rota e destino
  // desaparecem"). Visível em qualquer estado da viagem, não só enquanto o
  // veículo ainda não foi atribuído.
  if (r.ponto_descida_lat && r.ponto_descida_lng) {
    const destLat = parseFloat(r.ponto_descida_lat);
    const destLng = parseFloat(r.ponto_descida_lng);
    if (!destinoMarkerCriado) {
      const marcadorDestino = mapa.addMarker('descida', 'descida', destLat, destLng, {
        draggable: true,
        popupHtml: `<strong>${esc(r.ponto_descida_nome || 'Destino')}</strong><br>Arraste para ajustar a localização exata`,
        onDragEnd: async (novaLat, novaLng) => {
          const resp = await fetch('/api/passageiro/atualizar_destino', {
            method: 'POST',
            body: fd({ reserva_id: reservaAtivaId, lat: novaLat, lng: novaLng }),
          });
          const json2 = await resp.json();
          destinoArrastando = false;
          if (!resp.ok) { alert(json2.erro || 'Não foi possível atualizar o destino.'); return; }
          toast('Destino atualizado!', 'sucesso');
          atualizarReservaAtiva();
        },
      });
      if (marcadorDestino) marcadorDestino.on('dragstart', () => { destinoArrastando = true; });
      destinoMarkerCriado = true;
    } else if (!destinoArrastando) {
      mapa.updateMarker('descida', destLat, destLng);
    }

    // Antes de embarcar: rota da minha posição (ou do ponto de partida, sem
    // GPS ainda) até ao destino. Depois de embarcar: rota do veículo (onde
    // estou agora, dentro dele) até ao destino — a mesma que o condutor
    // segue (relatório "a rota deve ser visível para ambos").
    let origemRotaDestino = null;
    if (r.estado === 'a_bordo' && r.veiculo_lat && r.veiculo_lng) {
      origemRotaDestino = { lat: parseFloat(r.veiculo_lat), lng: parseFloat(r.veiculo_lng) };
    } else if (posicaoAtual) {
      origemRotaDestino = { lat: posicaoAtual.coords.latitude, lng: posicaoAtual.coords.longitude };
    } else if (r.ponto_partida_lat && r.ponto_partida_lng) {
      origemRotaDestino = { lat: parseFloat(r.ponto_partida_lat), lng: parseFloat(r.ponto_partida_lng) };
    }
    if (origemRotaDestino) {
      KGMap.tracarRota(mapa, 'rota-destino', origemRotaDestino, { lat: destLat, lng: destLng }, ESTILO_ROTA_DESTINO);
    }
  } else if (destinoMarkerCriado) {
    mapa.removeMarker('descida');
    destinoMarkerCriado = false;
    destinoArrastando = false;
    mapa.clearRoute('rota-destino');
  }

  // Intermunicipal sem ponto de descida (é opcional): o destino é o ponto da
  // carreira. A bordo, é ele que dá sentido ao GPS do veículo — sem isto o
  // mapa ficava com o carro a andar e mais nada.
  if (modoABordo
      && !(r.ponto_descida_lat && r.ponto_descida_lng)
      && r.tipo_viagem !== 'urbano'
      && r.destino_lat && r.destino_lng
      && r.veiculo_lat && r.veiculo_lng) {
    KGMap.tracarRota(
      mapa, 'rota-destino',
      { lat: parseFloat(r.veiculo_lat), lng: parseFloat(r.veiculo_lng) },
      { lat: parseFloat(r.destino_lat), lng: parseFloat(r.destino_lng) },
      ESTILO_ROTA_DESTINO
    );
  }

  document.getElementById('btn-abrir-chat').style.display = ['confirmado', 'a_caminho', 'a_bordo'].includes(r.estado) ? 'block' : 'none';
  // Nenhum passageiro fica refém de um condutor: pode sair enquanto o pedido
  // ainda está pendente e escolher outro veículo (relatório do mapa, tarefa
  // G). Assim que o táxi confirma, a viagem está comprometida: esconder o
  // botão de sair da reserva — e também o "Sair" do topo, para não haver
  // logout a meio de uma viagem já aceite por um condutor.
  document.getElementById('btn-sair-reserva').style.display = (r.estado === 'pendente') ? 'block' : 'none';
  aplicarViagemComprometida(['confirmado', 'a_caminho', 'a_bordo'].includes(r.estado));
  // Se o condutor não clicar "Entregue", o passageiro pode finalizar a
  // viagem por si mesmo assim que já estiver a bordo (relatório "passageiro
  // pode finalizar se o condutor não o fizer").
  document.getElementById('btn-finalizar-viagem').style.display = (r.estado === 'a_bordo') ? 'block' : 'none';
  if (veiculoChatAtivoId !== r.veiculo_id) {
    veiculoChatAtivoId = r.veiculo_id;
    chatUltimoIdVisto = 0;
    document.getElementById('chat-condutor').style.display = 'none';
  }

  // Trocar de veículo (ex: sair de uma reserva e pedir outro táxi) tem de
  // sair da sala do anterior, senão o passageiro continuava a receber — e a
  // aplicar — a posição de um condutor que já não é o dele.
  salaVeiculoReserva = r.veiculo_id ? `veiculo:${r.veiculo_id}` : null;
  sincronizarSalasVeiculos();
}

// Perfil
document.getElementById('btn-perfil').addEventListener('click', async () => {
  const json = await kgApiJSON('/api/passageiro/perfil');
  document.getElementById('perfil-nome').value = json.perfil.nome;
  document.getElementById('perfil-telefone').value = json.perfil.telefone;
  document.getElementById('perfil-nif').value = json.perfil.nif;
  document.getElementById('modal-perfil').style.display = 'flex';
});
document.getElementById('btn-fechar-perfil').addEventListener('click', () => {
  document.getElementById('modal-perfil').style.display = 'none';
});

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
  KGSons.tocarMensagem();
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

document.getElementById('form-perfil').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('_token', CSRF_TOKEN);
  const resp = await fetch('/api/passageiro/perfil', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('perfil-msg');
  if (!resp.ok) { msg.classList.remove('e-sucesso'); msg.textContent = json.erro || 'Erro ao guardar.'; return; }
  msg.classList.add('e-sucesso');
  msg.textContent = json.mensagem;
  setTimeout(() => { document.getElementById('modal-perfil').style.display = 'none'; }, 1200);
});

// Chat (autofalante) com o condutor
let veiculoChatAtivoId = null;
let chatPollTimer = null;
let chatUltimoIdVisto = 0;
async function carregarChat() {
  if (!veiculoChatAtivoId) return;
  const resp = await fetch(`/api/comunicacao/listar?veiculo_id=${veiculoChatAtivoId}`);
  if (!resp.ok) return;
  const json = await resp.json();
  const box = document.getElementById('chat-mensagens');
  box.innerHTML = (json.mensagens || []).map(m => `
    <div class="${esc(m.remetente_id === UTILIZADOR_ID ? 'text-right' : 'text-left')}">
      <span class="vm-small inline-block max-w-[80%] rounded-xl px-2.5 py-1.5 ${esc(m.remetente_id === UTILIZADOR_ID ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200')}">
        ${m.destinatario_id === null ? `<span class="mr-1 inline-flex align-middle text-brand-700" title="Autofalante">${svgIcone('comunicar', 'h-3.5 w-3.5')}</span>` : ''}${esc(m.mensagem)}
      </span>
    </div>`
  ).join('') || '<p class="vm-small">Sem mensagens ainda.</p>';
  box.scrollTop = box.scrollHeight;

  const novasDeOutrem = (json.mensagens || []).filter(m => m.id > chatUltimoIdVisto && m.remetente_id !== UTILIZADOR_ID);
  if (chatUltimoIdVisto > 0 && novasDeOutrem.length > 0) KGSons.tocarMensagem();
  if ((json.mensagens || []).length) chatUltimoIdVisto = Math.max(chatUltimoIdVisto, ...json.mensagens.map(m => m.id));

  (json.mensagens || []).filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    fetch('/api/comunicacao/marcar_lida', { method: 'POST', body: fd({ id: m.id }) });
  });
}
document.getElementById('btn-abrir-chat').addEventListener('click', () => {
  const chat = document.getElementById('chat-condutor');
  const abrir = chat.style.display === 'none';
  chat.style.display = abrir ? 'block' : 'none';
  if (abrir) {
    carregarChat();
    // Sem socket.io a correr não chega nenhum evento 'comunicacao:nova' —
    // enquanto o chat estiver aberto, vai buscar mensagens novas sozinho.
    clearInterval(chatPollTimer);
    chatPollTimer = setInterval(carregarChat, 5000);
  } else {
    clearInterval(chatPollTimer);
    chatPollTimer = null;
  }
});
document.getElementById('btn-sair-reserva').addEventListener('click', async () => {
  if (!reservaAtivaId) return;
  if (!confirm('Sair desta reserva? O seu lugar fica livre e pode escolher outro veículo.')) return;
  const resp = await fetch('/api/passageiro/cancelar_reserva', { method: 'POST', body: fd({ reserva_id: reservaAtivaId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível sair da reserva.'); return; }
  if (window.kgRt && json.veiculo_id) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
  reservaFechadaPeloPassageiro = true;
  atualizarReservaAtiva();
});
document.getElementById('btn-finalizar-viagem').addEventListener('click', async () => {
  if (!reservaAtivaId) return;
  if (!confirm('Confirma que já chegou ao destino? A viagem fica concluída.')) return;
  const resp = await fetch('/api/passageiro/finalizar_viagem', { method: 'POST', body: fd({ reserva_id: reservaAtivaId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível finalizar a viagem.'); return; }
  if (window.kgRt && json.veiculo_id) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
  reservaFechadaPeloPassageiro = true;
  toast('Viagem finalizada — obrigado!', 'sucesso');
  atualizarReservaAtiva();
});
document.getElementById('form-chat').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const input = document.getElementById('chat-input');
  const texto = input.value.trim();
  if (!texto || !veiculoChatAtivoId) return;
  input.value = '';
  const resp = await fetch('/api/comunicacao/enviar', { method: 'POST', body: fd({ veiculo_id: veiculoChatAtivoId, mensagem: texto }) });
  if (resp.ok && window.kgRt) window.kgRt.emit('comunicacao:nova', { veiculoId: veiculoChatAtivoId });
  carregarChat();
});

// Notificações
const estadoBadgeTipoNotif = { alerta: 'pendente', informativo: 'confirmado', urgente: 'recusado' };
let notifUltimaContagem = null;
async function carregarNotificacoes() {
  const json = await kgApiJSON('/api/notificacoes/listar');
  const badge = document.getElementById('badge-notificacoes');
  if (json.nao_lidas > 0) { badge.style.display = 'inline'; badge.textContent = json.nao_lidas; } else { badge.style.display = 'none'; }
  if (notifUltimaContagem !== null && json.nao_lidas > notifUltimaContagem) KGSons.tocarMensagem();
  notifUltimaContagem = json.nao_lidas;

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

// Sugestão / reclamação
let meusCondutoresCarregados = false;
document.getElementById('btn-sugestao').addEventListener('click', async () => {
  if (!meusCondutoresCarregados) {
    const json = await kgApiJSON('/api/passageiro/meus_condutores');
    const sel = document.getElementById('sugestao-condutor');
    (json.condutores || []).forEach(c => sel.add(new Option(c.nome, c.id)));
    meusCondutoresCarregados = true;
  }
  document.getElementById('modal-sugestao').style.display = 'flex';
});
document.getElementById('sugestao-tipo').addEventListener('change', (ev) => {
  document.getElementById('campo-sugestao-condutor').style.display = ev.target.value === 'reclamacao' ? 'block' : 'none';
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

// SOS: numa emergência o pior resultado possível é o silêncio. A versão
// anterior não tinha callback de erro, nem timeout, nem verificava a resposta
// — um GPS que não respondesse fazia o alerta desaparecer sem qualquer sinal
// para o passageiro, que ficava a pensar que tinha pedido ajuda.
async function enviarSos(lat, lng, aviso) {
  try {
    const resp = await fetch('/api/sos', { method: 'POST', body: fd({ lat, lng }) });
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      alert(`Não foi possível enviar o alerta SOS: ${json.erro || 'erro ' + resp.status}. Tente novamente ou ligue diretamente para as autoridades.`);
      return;
    }
    alert(`Alerta SOS enviado. Ajuda a caminho.${aviso ? '\n\n' + aviso : ''}`);
    if (window.kgRt) window.kgRt.emit('sos:ativado', { lat, lng });
  } catch (erro) {
    alert('Sem ligação para enviar o alerta SOS. Verifique a rede e tente novamente.');
  }
}

document.getElementById('btn-sos').addEventListener('click', () => {
  if (!confirm('Ativar alerta SOS? A central de alarmes será notificada com a sua localização.')) return;
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
      if (posicaoAtual) {
        enviarSos(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, 'Atenção: o GPS não respondeu agora — foi enviada a sua última posição conhecida, que pode estar desatualizada.');
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

// Arranque: o mapa aparece de imediato (centro por omissão, Cabo Verde) —
// nunca fica à espera do GPS para desenhar estradas/tiles. A geolocalização
// (abaixo) só entra depois, para centrar e colocar o marcador 'eu'; se
// falhar ou demorar, o resto do painel continua a funcionar na mesma
// (relatório "mapa cinzento": estava tudo dependente de kgEnsureGeolocation
// resolver, incluindo a própria criação do mapa — um GPS lento/negado
// deixava o mapa por criar, daí o fundo cinzento do Leaflet).
// maxZoom 19 (acima dos 15 por omissão do KGMap): o passageiro precisa de
// chegar ao nível da rua — e do edifício — para distinguir o lado da estrada
// onde está a paragem/o veículo. 19 é também o último nível que o servidor de
// tiles do OSM serve com imagem real (ver MAX_ZOOM_TILES em kg-map.js).
mapa = KGMap.create('map', { zoom: 13, maxZoom: 19 });
KGMap.ligarPausaDeAnimacoes(mapa);

// Segue a posição do passageiro automaticamente (relatório "GPS a
// aparecer fora do mapa"): sem isto, o marcador movia-se para a
// coordenada certa mas a vista do mapa nunca ia atrás — bastava andar
// uma rua para o marcador sair do ecrã, sem nenhum aviso. Só para de
// seguir se o próprio utilizador arrastar o mapa (dragstart, nunca
// movestart — senão o nosso próprio seguimento desligava-se a si mesmo); o
// botão de recentrar no mapa retoma o seguimento.
let aSeguirUtilizador = true;
mapa.onDragStart(() => {
  aSeguirUtilizador = false;
  // O seguimento é contínuo (seguirSuave): sem o parar aqui, a interpolação já
  // lançada continuava a puxar a vista de volta enquanto o passageiro
  // arrastava o mapa com o dedo.
  mapa.pararSeguimento();
});
document.getElementById('btn-recentrar').addEventListener('click', () => {
  aSeguirUtilizador = true;
  // A bordo, "a minha localização" é o carro onde vou — é o único GPS no mapa.
  if (modoABordo && reservaAtivaDados && reservaAtivaDados.veiculo_lat && reservaAtivaDados.veiculo_lng) {
    mapa.flyTo(parseFloat(reservaAtivaDados.veiculo_lat), parseFloat(reservaAtivaDados.veiculo_lng), ZOOM_SEGUIMENTO);
    return;
  }
  if (posicaoAtual) mapa.flyTo(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, ZOOM_SEGUIMENTO);
});

// Mesma origem da página (Apache faz proxy de /socket.io/ para o Node em
// 127.0.0.1:3001 — ver public/.htaccess) em vez de ligar direto à porta
// 3001, que não tem certificado TLS próprio e falhava com wss://.
window.kgRt = KGRealtime.connect({
  url: window.location.origin,
  pollingEndpoints: {},
});
window.kgRt.join(`utilizador:${UTILIZADOR_ID}`);
KGChamada.configurar({ rt: window.kgRt, meuId: UTILIZADOR_ID, csrfToken: CSRF_TOKEN });
window.kgRt.on('veiculo:lugares', (payload) => atualizarLugaresNaLista(payload.veiculoId, payload.lugaresLivres));
// O condutor emite a posição a cada ~3 s e o servidor reencaminha-a para a
// sala do veículo. Sem este listener o pacote chegava ao browser e era
// deitado fora: o carro no mapa só se mexia no ciclo de 8 s de
// atualizarReservaAtiva, e os táxis da lista só na pesquisa seguinte.
window.kgRt.on('veiculo:posicao', (payload) => {
  if (!payload || payload.veiculoId == null) return;
  const lat = parseFloat(payload.lat);
  const lng = parseFloat(payload.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

  // Veículo da minha reserva: move o marcador e as rotas que dependem dele.
  if (reservaAtivaDados && String(reservaAtivaDados.veiculo_id) === String(payload.veiculoId)) {
    reservaAtivaDados.veiculo_lat = lat;
    reservaAtivaDados.veiculo_lng = lng;
    desenharVeiculoReserva(reservaAtivaDados, lat, lng);
  }

  // Táxi da lista de disponíveis: só a posição — a cor/badge vem do estado,
  // que continua a ser trazido por atualizarVeiculosNoMapa.
  const idLista = `veiculo-${payload.veiculoId}`;
  if (veiculoMarkerIds.has(idLista)) mapa.moveMarkerSuave(idLista, lat, lng);
});
window.kgRt.on('notificacao:nova', carregarNotificacoes);
window.kgRt.on('comunicacao:nova', (payload) => {
  if (payload.veiculoId === veiculoChatAtivoId && document.getElementById('chat-condutor').style.display !== 'none') carregarChat();
});

carregarPontos().then(atualizarReservaAtiva);
carregarNotificacoes();
setInterval(carregarNotificacoes, 15000);
setInterval(atualizarReservaAtiva, 8000);
// Sem socket.io a correr, o chat só é atualizado com o painel aberto —
// este ciclo garante que o som de "nova mensagem" toca mesmo com o chat
// fechado (ex: o passageiro está a ver o mapa).
setInterval(() => { if (veiculoChatAtivoId) carregarChat(); }, 15000);
setInterval(atualizarVeiculosNoMapa, 10000);

// Envia ao condutor a localização exata do passageiro enquanto houver uma
// reserva ativa (pendente/confirmada/a bordo) — secção 5.3 do relatório do
// mapa. Fica fora do callback do GPS de propósito: a posição é CONSUMIDA
// aqui, por isso uma coordenada só viaja uma vez e o servidor nunca recebe
// uma leitura velha como se fosse a posição atual do passageiro.
async function enviarLocalizacaoPendente() {
  if (!posicaoPorEnviar || !reservaAtivaAtual) return;
  // Se o POST anterior ainda não respondeu, espera-se pelo próximo ciclo em
  // vez de o abortar: abortar não desfaz a escrita já feita no servidor.
  if (envioLocalizacaoEmCurso) return;

  const { lat, lng, accuracy } = posicaoPorEnviar;
  posicaoPorEnviar = null; // consumida: só se envia posição nova, nunca repetida
  envioLocalizacaoEmCurso = true;

  try {
    const resp = await fetch('/api/passageiro/localizacao', {
      method: 'POST',
      body: fd({ lat, lng, accuracy }),
    });
    if (!resp.ok) {
      const json = await resp.json().catch(() => ({}));
      // 422 ("Precisão do GPS insuficiente") é a recusa mais provável e antes
      // passava em silêncio absoluto: com uma leitura sempre acima de 100 m
      // (interior, posicionamento por WiFi) o condutor nunca via o passageiro
      // e nada, em lado nenhum, dizia porquê. Não se repõe a posição na fila
      // — o servidor já disse que aquela leitura não serve.
      console.warn('V-MILLION GPS: localização recusada pelo servidor —', resp.status, json.erro || '');
    }
  } catch (erro) {
    // Falha de rede (≠ recusa do servidor): a leitura continua válida, por
    // isso volta para a fila — a não ser que entretanto já tenha chegado uma
    // mais recente, que tem sempre prioridade.
    if (!posicaoPorEnviar) posicaoPorEnviar = { lat, lng, accuracy };
  } finally {
    envioLocalizacaoEmCurso = false;
  }
}

// Geolocalização: pedida em paralelo com o resto do arranque acima — assim
// que houver uma posição válida, centra o mapa e cria/segue o marcador 'eu'.
kgEnsureGeolocation((pos) => {
  console.log('V-MILLION GPS: posição inicial obtida, precisão =', pos.coords.accuracy, 'm');
  posicaoAtual = pos;
  // A bordo o ponto do GPS do passageiro está fora do mapa de propósito: uma
  // posição nova não o pode trazer de volta antes da entrega.
  if (!modoABordo) {
    mapa.setUserPosition(pos.coords.latitude, pos.coords.longitude);
    mapa.setUserAccuracy(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
    mapa.flyTo(pos.coords.latitude, pos.coords.longitude, ZOOM_SEGUIMENTO);
  }

  // kgEnsureGeolocation pode entregar uma posição mais do que uma vez na
  // mesma sessão (permissão revogada e reposta, botão "Tentar novamente").
  // Sem limpar o que ficou da entrega anterior ficavam dois watchPosition e
  // dois temporizadores vivos a seguir e a enviar o mesmo passageiro em
  // paralelo.
  if (watchIdPassageiro !== null) navigator.geolocation.clearWatch(watchIdPassageiro);
  if (envioLocalizacaoTimer !== null) clearInterval(envioLocalizacaoTimer);
  envioLocalizacaoTimer = setInterval(enviarLocalizacaoPendente, ENVIO_LOCALIZACAO_MS);

  watchIdPassageiro = kgWatchPosition((lat, lng, novaPos, info) => {
    console.log('V-MILLION GPS: atualização, precisão =', novaPos.coords.accuracy, 'm');
    // O ponto do GPS já foi colocado por onLeituraBruta (abaixo), na coordenada
    // exata da última leitura. Aqui trata-se só do que exige uma deslocação
    // mesmo confirmada: seguir o mapa, reportar a posição ao condutor e
    // redesenhar a linha até ao carro.
    // A bordo é o carro que arrasta a vista (desenharVeiculoReserva), não o
    // GPS do telemóvel.
    // Seguimento contínuo, alimentado pela posição filtrada: o mapa desliza
    // com o passageiro em vez de dar um puxão a cada leitura. Parado à espera
    // do táxi — o caso normal aqui — não chega posição filtrada nova, por isso
    // a vista fica quieta em vez de tremer com o ruído do GPS.
    if (aSeguirUtilizador && !modoABordo) mapa.seguirSuave(lat, lng);
    // Reemissão da última posição por perda de sinal: serve para o marcador
    // não desaparecer do mapa, mas não deve alimentar o envio periódico ao
    // condutor — senão o passageiro continua a "reportar-se" numa coordenada
    // que já deixou.
    if (info && info.obsoleta) return;
    posicaoAtual = novaPos;
    // Rede: não é feita aqui. A posição fica pendente e é enviada pelo
    // temporizador acima, no máximo 1x por ENVIO_LOCALIZACAO_MS.
    posicaoPorEnviar = { lat, lng, accuracy: novaPos.coords.accuracy };
    // A linha "eu -> carro" tem duas pontas: a do carro é seguida pelo evento
    // 'veiculo:posicao', esta é a minha — sem isto a linha só acompanhava o
    // passageiro a andar no ciclo de 8 s.
    if (reservaAtivaDados && reservaAtivaDados.veiculo_lat && reservaAtivaDados.veiculo_lng) {
      desenharVeiculoReserva(reservaAtivaDados, parseFloat(reservaAtivaDados.veiculo_lat), parseFloat(reservaAtivaDados.veiculo_lng));
    }
  }, {
    // O ponto do passageiro segue TODAS as leituras aceites, sem passar pelo
    // filtro dos 5 m nem pelo de deriva: com os filtros ficava até 5 m atrás da
    // posição real, e a parado à espera do táxi — que é o caso normal aqui —
    // congelava no erro da primeira leitura, mesmo com o GPS a entregar
    // leituras melhores do mesmo sítio. O mapa só é seguido (seguirSuave) na
    // função de cima, com o sinal já filtrado, senão a vista tremia a cada
    // segundo.
    onLeituraBruta: (lat, lng, pos) => {
      if (modoABordo) return; // sem ponto do passageiro no mapa entre o embarque e a entrega
      // Desliza até à leitura nova em vez de saltar para ela: entre duas
      // leituras (~1 s) o ponto continua a andar, ao ritmo do ecrã.
      mapa.moveMarkerSuave('eu', lat, lng);
      // O círculo acompanha o ponto: o passageiro vê de imediato se está a ver
      // uma posição fiável (círculo pequeno) ou uma estimativa grosseira — é a
      // mesma coordenada que segue para o condutor vir buscá-lo. Só o raio
      // muda aqui: a posição vai a reboque do ponto 'eu', para os dois nunca
      // se separarem a meio de um percurso.
      mapa.setUserAccuracy(lat, lng, pos.coords.accuracy, true);
    },
  });

  // A posição inicial também tem de chegar ao condutor: o watch só volta a
  // emitir depois de o passageiro se afastar mais de 5 m, e quem está parado
  // à espera do táxi pode nunca lá chegar. Se ainda não houver reserva ativa,
  // fica em fila (não é consumida) até haver.
  posicaoPorEnviar = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
});
</script>
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
