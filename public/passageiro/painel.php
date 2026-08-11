<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/page_guard.php';

$passageiro = kg_pagina_exigir_utilizador('passageiro');
$csrf = kg_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003893">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="V-MILLION">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="icon" href="/assets/icons/icon-192.png">
<title>Painel do Passageiro — V-MILLION</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<link rel="stylesheet" href="/assets/css/kg-map-markers.css">
<style>
  html, body { height: 100%; margin: 0; }
  .kg-p-header {
    height: 60px; background: var(--kg-gradient-header); color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 var(--sp-5); box-shadow: var(--shadow-md); position: relative; z-index: 30;
  }
  .kg-p-header__logo { font-weight: 800; font-size: 1.15rem; }
  .kg-p-header__actions { display: flex; align-items: center; gap: var(--sp-3); }
  .kg-p-header__nome { font-size: 0.875rem; opacity: 0.9; }
  .kg-p-map-wrap { position: relative; height: 58vh; }
  #map { position: absolute; inset: 0; }
  .kg-p-sheet {
    background: var(--kg-white);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    box-shadow: var(--shadow-xl);
    margin-top: -20px; position: relative; z-index: 20;
    padding: var(--sp-5);
    min-height: 42vh;
  }
  .kg-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-3); }
  .kg-veiculos-lista { display: flex; gap: var(--sp-3); overflow-x: auto; padding: var(--sp-2) 0; }
  .kg-veiculo-card { min-width: 220px; flex-shrink: 0; }
  .kg-reserva-status { display: none; }
  @media (max-width: 767px) {
    .kg-form-grid { grid-template-columns: 1fr; }
    .kg-p-map-wrap { height: 48vh; }
    /* Cabeçalho com nome + 4 botões + Sair numa só linha (sem quebrar,
       tem altura fixa de 60px) não cabe num telemóvel estreito — liberta
       espaço escondendo o nome (só decorativo) e encolhendo padding. */
    .kg-p-header { padding: 0 var(--sp-3); }
    .kg-p-header__actions { gap: var(--sp-1); }
    .kg-p-header__nome { display: none; }
  }
</style>
</head>
<body>

<header class="kg-p-header">
  <div class="kg-p-header__logo">V-MILLION</div>
  <div class="kg-p-header__actions">
    <span class="kg-p-header__nome"><?= htmlspecialchars($passageiro['nome'], ENT_QUOTES) ?></span>
    <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff; position:relative;" id="btn-notificacoes">🔔<span id="badge-notificacoes" style="display:none; position:absolute; top:-4px; right:-4px; background:var(--kg-danger); color:#fff; border-radius:50%; font-size:0.65rem; padding:1px 5px;"></span></button>
    <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;" id="btn-sons" title="Preferências de som">🔊</button>
    <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;" id="btn-sugestao">Sugestão/Reclamação</button>
    <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;" id="btn-perfil">Perfil</button>
    <a href="/api/auth/logout.php" class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;">Sair</a>
  </div>
</header>

<div class="kg-p-map-wrap">
  <div id="map"></div>
  <div class="kg-map-controls">
    <button type="button" class="kg-map-btn" id="btn-recentrar" title="A minha localização" aria-label="Recentrar no meu GPS">📍</button>
  </div>
  <button type="button" class="kg-map-sos" id="btn-sos">SOS</button>
  <div class="kg-map-legend">
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--ponto" style="width:14px;height:14px;"></span> Ponto de partida</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--veiculo" style="width:18px;height:18px;"></span> Veículo</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--eu" style="width:12px;height:12px;"></span> A sua localização</div>
    <div class="kg-map-legend__item"><span class="kg-marker kg-marker--descida" style="width:14px;height:14px;"></span> Ponto de descida</div>
  </div>
</div>

<section class="kg-p-sheet">
  <div id="reserva-ativa" class="kg-card kg-reserva-status" style="margin-bottom: var(--sp-4);">
    <div class="kg-flex kg-justify-between kg-items-center">
      <h3 class="kg-h3">A sua viagem</h3>
      <span class="kg-badge" id="reserva-badge"></span>
    </div>
    <p class="kg-small" id="reserva-detalhes"></p>
    <div id="reserva-condutor"></div>
    <button type="button" class="kg-btn kg-btn--outline kg-btn--sm kg-btn--full" id="btn-abrir-chat" style="margin-top:8px; display:none;">💬 Falar com o condutor</button>
    <button type="button" class="kg-btn kg-btn--perigo kg-btn--sm kg-btn--full" id="btn-sair-reserva" style="margin-top:8px; display:none;">Sair desta reserva / escolher outro veículo</button>
    <button type="button" class="kg-btn kg-btn--cta kg-btn--sm kg-btn--full" id="btn-finalizar-viagem" style="margin-top:8px; display:none;">✅ Finalizar viagem (já cheguei)</button>
    <div id="chat-condutor" style="display:none; margin-top:8px;">
      <div id="chat-mensagens" style="max-height:220px; overflow-y:auto; background:var(--kg-bg); border-radius:var(--radius-md); padding:8px; margin-bottom:8px;"></div>
      <form id="form-chat" class="kg-flex kg-gap-2">
        <input class="kg-input" id="chat-input" placeholder="Escreva uma mensagem..." maxlength="500" style="flex:1;">
        <button class="kg-btn kg-btn--cta kg-btn--sm" type="submit">Enviar</button>
      </form>
    </div>
  </div>

  <div id="form-wrap">
    <h3 class="kg-h3">Reservar viagem</h3>
    <div class="kg-flex kg-gap-2" style="margin-bottom: var(--sp-4);">
      <button type="button" class="kg-btn kg-btn--outline kg-btn--full" id="btn-modo-urbana" data-modo="urbana">🏙️ Viagem urbana</button>
      <button type="button" class="kg-btn kg-btn--outline kg-btn--full" id="btn-modo-intermunicipal" data-modo="intermunicipal">🛣️ Viagem intermunicipal</button>
    </div>
    <div class="kg-form-grid" id="form-viagem" style="display:none;">
      <div class="kg-field" id="campo-ponto-partida">
        <label class="kg-label" for="sel-ponto">Ponto de partida</label>
        <select class="kg-select" id="sel-ponto"><option value="">A carregar...</option></select>
      </div>
      <div class="kg-field" id="campo-destino">
        <label class="kg-label" for="sel-destino">Destino</label>
        <select class="kg-select" id="sel-destino"><option value="">A carregar...</option></select>
      </div>
      <div class="kg-field">
        <label class="kg-label" for="sel-lugares">Nº de lugares</label>
        <select class="kg-select" id="sel-lugares">
          <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
        </select>
      </div>
      <div class="kg-field">
        <label class="kg-label" for="sel-motivo">Motivo</label>
        <select class="kg-select" id="sel-motivo">
          <option value="normal">Normal</option>
          <option value="grupo">Grupo</option>
          <option value="passeio">Passeio</option>
        </select>
      </div>
    </div>
    <p class="kg-small" id="info-rota-preview" style="display:none; margin-top:4px;"></p>
    <div class="kg-field" style="position:relative;">
      <label class="kg-label" for="input-descida" id="label-descida">Ponto de descida (opcional)</label>
      <input class="kg-input" type="text" id="input-descida" placeholder="Escreva um local ou clique no mapa" autocomplete="off">
      <div id="descida-sugestoes" style="position:absolute; top:100%; left:0; right:0; background:#fff; border-radius:var(--radius-md); box-shadow:var(--shadow-lg); z-index:40; display:none;"></div>
      <p class="kg-small" id="info-urbano" style="display:none; margin-top:4px;">📍 A sua localização atual será partilhada com os condutores.</p>
    </div>

    <div class="kg-flex kg-gap-2">
      <button class="kg-btn kg-btn--outline" id="btn-buscar" type="button">Ver veículos disponíveis</button>
      <button class="kg-btn kg-btn--ghost" id="btn-ver-taxis-especificos" type="button" style="display:none;">🚕 Ver taxis específicos</button>
    </div>
    <p class="kg-small" id="msg-pedido-urbano" style="margin-top:8px;"></p>

    <div class="kg-veiculos-lista" id="lista-veiculos"></div>
  </div>
</section>

<!-- Modal de escolha de assento -->
<div class="kg-modal-overlay" id="modal-assentos" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Escolha o seu lugar</h3>
    <p class="kg-small">Fila 0 é junto ao condutor.</p>
    <div class="kg-assentos" id="assentos-grid"></div>
    <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
      <button class="kg-btn kg-btn--ghost" id="btn-cancelar-assento" type="button">Cancelar</button>
      <button class="kg-btn kg-btn--cta kg-btn--full" id="btn-confirmar-reserva" type="button" disabled>Reservar lugar</button>
    </div>
  </div>
</div>

<!-- Modal de notificações -->
<div class="kg-modal-overlay" id="modal-notificacoes" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Notificações</h3>
    <div id="lista-notificacoes" style="max-height:60vh; overflow-y:auto;"></div>
    <button type="button" class="kg-btn kg-btn--ghost kg-btn--full" id="btn-fechar-notificacoes" style="margin-top:var(--sp-4);">Fechar</button>
  </div>
</div>

<!-- Modal de notificação urgente -->
<div class="kg-modal-overlay" id="modal-notificacao-urgente" style="display:none;">
  <div class="kg-modal">
    <span class="kg-badge kg-badge--recusado">Urgente</span>
    <h3 class="kg-h3" id="urgente-titulo"></h3>
    <p id="urgente-mensagem"></p>
    <button type="button" class="kg-btn kg-btn--primario kg-btn--full" id="btn-fechar-urgente">Entendido</button>
  </div>
</div>

<!-- Modal de sugestão/reclamação -->
<div class="kg-modal-overlay" id="modal-sugestao" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Sugestão ou reclamação</h3>
    <form id="form-sugestao">
      <div class="kg-field">
        <label class="kg-label">Tipo</label>
        <select class="kg-select" id="sugestao-tipo" name="tipo">
          <option value="sugestao">Sugestão de melhoria</option>
          <option value="reclamacao">Reclamação sobre um condutor</option>
        </select>
      </div>
      <div class="kg-field" id="campo-sugestao-condutor" style="display:none;">
        <label class="kg-label">Condutor</label>
        <select class="kg-select" id="sugestao-condutor" name="condutor_id"></select>
      </div>
      <div class="kg-field"><label class="kg-label">Título</label><input class="kg-input" name="titulo" id="sugestao-titulo" required></div>
      <div class="kg-field"><label class="kg-label">Descrição</label><textarea class="kg-input" name="descricao" id="sugestao-descricao" rows="4" required></textarea></div>
      <div id="sugestao-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
        <button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-sugestao">Cancelar</button>
        <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Enviar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de avaliação do condutor -->
<div class="kg-modal-overlay" id="modal-avaliacao" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Como foi a viagem?</h3>
    <p class="kg-small" id="avaliacao-condutor-nome"></p>
    <div class="kg-flex kg-gap-2" id="avaliacao-estrelas" style="font-size:2rem; justify-content:center; margin:var(--sp-4) 0;">
      <span data-estrela="1" style="cursor:pointer;">☆</span>
      <span data-estrela="2" style="cursor:pointer;">☆</span>
      <span data-estrela="3" style="cursor:pointer;">☆</span>
      <span data-estrela="4" style="cursor:pointer;">☆</span>
      <span data-estrela="5" style="cursor:pointer;">☆</span>
    </div>
    <div class="kg-field"><label class="kg-label">Comentário (opcional)</label><textarea class="kg-input" id="avaliacao-comentario" rows="2"></textarea></div>
    <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
      <button type="button" class="kg-btn kg-btn--ghost" id="btn-saltar-avaliacao">Agora não</button>
      <button type="button" class="kg-btn kg-btn--cta kg-btn--full" id="btn-enviar-avaliacao" disabled>Enviar avaliação</button>
    </div>
  </div>
</div>

<!-- Modal de perfil -->
<div class="kg-modal-overlay" id="modal-perfil" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">O meu perfil</h3>
    <form id="form-perfil">
      <div class="kg-field"><label class="kg-label">Nome</label><input class="kg-input" name="nome" id="perfil-nome" required></div>
      <div class="kg-field"><label class="kg-label">Telefone</label><input class="kg-input" name="telefone" id="perfil-telefone" required></div>
      <div class="kg-field"><label class="kg-label">NIF</label><input class="kg-input" name="nif" id="perfil-nif" required maxlength="9"></div>
      <hr style="border-color: var(--kg-border);">
      <p class="kg-small">Para alterar a senha, preencha os campos abaixo.</p>
      <div class="kg-field"><label class="kg-label">Senha atual</label><input class="kg-input" type="password" name="senha_atual" id="perfil-senha-atual"></div>
      <div class="kg-field"><label class="kg-label">Nova senha</label><input class="kg-input" type="password" name="nova_senha" id="perfil-nova-senha"></div>
      <div id="perfil-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
        <button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-perfil">Fechar</button>
        <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="kg-modal-overlay" id="modal-chamada" style="display:none;">
  <div class="kg-modal" style="text-align:center;">
    <div id="chamada-icone" style="font-size:2.5rem;">📞</div>
    <h3 class="kg-h3" id="chamada-titulo">Chamada</h3>
    <p class="kg-small" id="chamada-subtitulo"></p>
    <div class="kg-flex kg-gap-2" id="chamada-acoes" style="margin-top: var(--sp-4); justify-content:center;"></div>
    <audio id="chamada-audio-remoto" autoplay playsinline></audio>
  </div>
</div>

<div class="kg-modal-overlay" id="modal-sons" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Preferências de som</h3>
    <div class="kg-field kg-flex kg-justify-between kg-items-center">
      <label class="kg-label" for="sons-ativo" style="margin:0;">Sons ativos</label>
      <input type="checkbox" id="sons-ativo">
    </div>
    <div class="kg-field">
      <label class="kg-label" for="sons-volume">Volume</label>
      <input type="range" id="sons-volume" min="0" max="100" step="5" style="width:100%;">
    </div>
    <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
      <button type="button" class="kg-btn kg-btn--outline" id="sons-testar">Testar som</button>
      <button type="button" class="kg-btn kg-btn--primario kg-btn--full" id="btn-fechar-sons">Fechar</button>
    </div>
  </div>
</div>

<div id="toast-container" style="position:fixed; top: var(--sp-5); right: var(--sp-5); z-index:1100; display:flex; flex-direction:column; gap: var(--sp-2);"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="/assets/js/kg-sons.js"></script>
<script src="/assets/js/kg-geolocation.js"></script>
<script src="/assets/js/kg-map.js"></script>
<script src="/assets/js/kg-realtime.js"></script>
<script src="/assets/js/kg-chamada.js"></script>
<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const UTILIZADOR_ID = <?= json_encode((int) $passageiro['id']) ?>;
let mapa = null;
let pontoDescidaEscolhido = null; // {nome, lat, lng}
let veiculoEscolhidoId = null;
let assentoEscolhidoId = null;
let viagemTipo = null; // 'urbana' | 'intermunicipal'
let posicaoAtual = null;
let pontosCache = [];
let veiculoMarkerIds = new Set();
let veiculoMarcadoEstado = new Map();
let reservaAtivaAtual = false;
let reservaEstadoAnterior = null;
let reservaAtivaId = null;
let meuVeiculoReservaMarcado = null;
let meuVeiculoReservaEstado = null;
let destinoMarkerCriado = false;
let destinoArrastando = false;

function kgHeaders() {
  return { 'X-CSRF-Token': CSRF_TOKEN };
}

function toast(mensagem, tipo) {
  const el = document.createElement('div');
  el.className = 'kg-toast' + (tipo === 'erro' ? ' kg-toast--erro' : tipo === 'sucesso' ? ' kg-toast--sucesso' : '');
  el.style.position = 'static';
  el.textContent = mensagem;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

function fd(extra) {
  const d = new FormData();
  d.set('csrf_token', CSRF_TOKEN);
  Object.entries(extra || {}).forEach(([k, v]) => d.set(k, v));
  return d;
}

// Pedido GET com tratamento uniforme de sessão expirada (401): evita que um
// .map()/Object.entries() sobre um JSON de erro rebente com TypeError.
async function kgApiJSON(url, opts) {
  const resp = await fetch(url, opts);
  if (resp.status === 401) {
    window.location.href = '/login.php';
    throw new Error('Sessão expirada, a redirecionar para o login.');
  }
  const json = await resp.json();
  if (!resp.ok) {
    throw new Error(json.erro || `Erro ${resp.status} em ${url}`);
  }
  return json;
}

async function carregarPontos() {
  const json = await kgApiJSON('/api/passageiro/pontos.php');
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
    mapa.addMarker(`ponto-${p.id}`, 'ponto', parseFloat(p.lat), parseFloat(p.lng), {
      popupHtml: `<strong>${p.nome}</strong><br>${p.cidade}`,
    });
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
  document.getElementById('btn-modo-urbana').classList.toggle('kg-btn--cta', modo === 'urbana');
  document.getElementById('btn-modo-urbana').classList.toggle('kg-btn--outline', modo !== 'urbana');
  document.getElementById('btn-modo-intermunicipal').classList.toggle('kg-btn--cta', modo === 'intermunicipal');
  document.getElementById('btn-modo-intermunicipal').classList.toggle('kg-btn--outline', modo !== 'intermunicipal');
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
    btnBuscar.textContent = '🆘 Pedir taxi (qualquer um)';
    document.getElementById('btn-ver-taxis-especificos').style.display = '';
    // Reafirma a localização real do passageiro no mapa (marcador kg-marker--eu
    // já existe desde o arranque, mas volta a centrar/dar destaque ao mudar
    // para modo urbano — relatório "localização real no mapa").
    if (posicaoAtual && mapa) {
      mapa.flyTo(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, 15);
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
    el.textContent = `🛣️ ${dist} · ⏱️ ${dur}`;
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
    { lat: parseFloat(destino.lat), lng: parseFloat(destino.lng) }
  );
  mostrarInfoRota(info);
}
document.getElementById('sel-ponto').addEventListener('change', atualizarRotaPreview);
document.getElementById('sel-destino').addEventListener('change', atualizarRotaPreview);

// Mostra no mapa os veículos disponíveis no ponto (parados ou na fila, com a
// ordem em badge) — para o passageiro ver o carro antes de o escolher.
function atualizarMarcadoresVeiculos(veiculos) {
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
      mapa.updateMarker(id, lat, lng);
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
}

async function buscarVeiculos() {
  const pontoId = document.getElementById('sel-ponto').value;
  const destinoId = document.getElementById('sel-destino').value;
  const lista = document.getElementById('lista-veiculos');
  if (!pontoId) { lista.innerHTML = '<p class="kg-small">Escolha o ponto de partida.</p>'; return; }

  lista.innerHTML = '<div class="kg-skeleton" style="width:220px;height:120px;"></div>';
  const qs = new URLSearchParams({ ponto_id: pontoId });
  if (destinoId) qs.set('destino_id', destinoId);
  if (viagemTipo) qs.set('viagem_tipo', viagemTipo);

  const json = await kgApiJSON(`/api/passageiro/veiculos_disponiveis.php?${qs}`);
  const veiculos = json.veiculos || [];

  atualizarMarcadoresVeiculos(veiculos);

  if (!veiculos.length) {
    lista.innerHTML = '<p class="kg-small">Nenhum veículo disponível neste momento.</p>';
    return;
  }

  lista.innerHTML = '';
  veiculos.forEach(v => {
    const card = document.createElement('div');
    card.className = 'kg-card kg-card--hover kg-veiculo-card';
    card.dataset.veiculoId = v.id;
    const estrelas = v.condutor_avaliacao_total > 0 ? `★ ${v.condutor_avaliacao_media} (${v.condutor_avaliacao_total})` : 'Sem avaliações ainda';
    card.innerHTML = `
      <h4 class="kg-h3" style="margin-bottom:4px;">${v.matricula}</h4>
      <p class="kg-small" style="margin:0 0 4px;">${v.tipo} · ${v.cor} · ${v.condutor_nome}</p>
      ${v.destino_nome ? `<p class="kg-small" style="margin:0 0 4px;">🏁 Destino: <strong>${v.destino_nome}</strong></p>` : ''}
      <p class="kg-small" style="margin:0 0 8px; color:var(--kg-warning);">${estrelas}</p>
      <p class="kg-small kg-veiculo-lugares" style="margin:0 0 8px;">${v.lugares_livres} lugares livres ${v.estado === 'na_fila' ? `· Fila #${v.posicao_fila ?? ''}` : ''}</p>
      <button class="kg-btn kg-btn--primario kg-btn--sm kg-btn--full" type="button">${viagemTipo === 'urbana' ? '📞 Chamar este taxi' : 'Escolher'}</button>`;
    card.querySelector('button').addEventListener('click', () => abrirModalAssentos(v.id));
    lista.appendChild(card);
  });
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

  const resp = await fetch('/api/passageiro/pedir_urbano.php', {
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

// Atualiza a lotação apresentada em tempo real quando um passageiro embarca/desce.
function atualizarLugaresNaLista(veiculoId, lugaresLivres) {
  const card = document.querySelector(`.kg-veiculo-card[data-veiculo-id="${veiculoId}"] .kg-veiculo-lugares`);
  if (card) card.textContent = card.textContent.replace(/^\d+/, String(lugaresLivres));
}

async function abrirModalAssentos(veiculoId) {
  veiculoEscolhidoId = veiculoId;
  assentoEscolhidoId = null;
  document.getElementById('btn-confirmar-reserva').disabled = true;

  const json = await kgApiJSON(`/api/passageiro/assentos.php?veiculo_id=${veiculoId}`);
  const grid = document.getElementById('assentos-grid');
  grid.innerHTML = '';

  const filas = {};
  (json.assentos || []).forEach(a => {
    filas[a.fila] = filas[a.fila] || [];
    filas[a.fila].push(a);
  });

  Object.keys(filas).sort((a, b) => a - b).forEach(filaNum => {
    const filaEl = document.createElement('div');
    filaEl.className = 'kg-assentos__fila' + (filaNum === '0' ? ' kg-assentos__fila--condutor' : '');
    filas[filaNum].sort((a, b) => a.coluna - b.coluna).forEach(a => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'kg-assento ' + (a.ocupado ? 'kg-assento--ocupado' : 'kg-assento--livre');
      btn.textContent = a.numero;
      btn.disabled = !!a.ocupado;
      btn.addEventListener('click', () => {
        grid.querySelectorAll('.kg-assento--selecionado').forEach(el => el.classList.remove('kg-assento--selecionado'));
        btn.classList.add('kg-assento--selecionado');
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
  dados.set('csrf_token', CSRF_TOKEN);
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
    const resp = await fetch('/api/passageiro/reservar.php', { method: 'POST', body: dados });
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
    KGMap.tracarRota(mapa, 'preview-rota', origem, { lat, lng }).then(mostrarInfoRota);
    mapa.setMarkerRotation('eu', KGMap.calcularRumo(origem.lat, origem.lng, lat, lng));
  }
}

function itemSugestaoDescida(texto, icone, onClick) {
  const item = document.createElement('div');
  item.className = 'kg-small';
  item.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--kg-border);';
  item.textContent = `${icone} ${texto}`;
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
      const jsonGravados = await kgApiJSON(`/api/passageiro/destinos_urbanos.php?q=${encodeURIComponent(termo)}`);
      (jsonGravados.destinos || []).forEach(d => {
        box.appendChild(itemSugestaoDescida(d.nome, '⭐', () => escolherPontoDescida(d.nome, parseFloat(d.lat), parseFloat(d.lng))));
        totalSugestoes++;
      });
    } catch (e) { /* silencioso */ }

    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&countrycodes=cv&viewbox=-25.4,17.2,-22.7,14.7&bounded=1&q=${encodeURIComponent(termo)}`;
      const resp = await fetch(url, { headers: { 'Accept-Language': 'pt' } });
      const resultados = await resp.json();
      resultados.slice(0, 5).forEach(r => {
        box.appendChild(itemSugestaoDescida(r.display_name, '📍', () => escolherPontoDescida(r.display_name, parseFloat(r.lat), parseFloat(r.lon))));
        totalSugestoes++;
      });
    } catch (e) { /* silencioso */ }

    box.style.display = totalSugestoes ? 'block' : 'none';
  }, 400);
});

let reservaParaAvaliar = null;
let estrelaEscolhida = 0;

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
  document.querySelectorAll('#avaliacao-estrelas [data-estrela]').forEach(el => {
    el.textContent = Number(el.dataset.estrela) <= n ? '★' : '☆';
  });
}

document.querySelectorAll('#avaliacao-estrelas [data-estrela]').forEach(el => {
  el.addEventListener('click', () => {
    estrelaEscolhida = Number(el.dataset.estrela);
    pintarEstrelas(estrelaEscolhida);
    document.getElementById('btn-enviar-avaliacao').disabled = false;
  });
});
document.getElementById('btn-saltar-avaliacao').addEventListener('click', () => {
  document.getElementById('modal-avaliacao').style.display = 'none';
});
document.getElementById('btn-enviar-avaliacao').addEventListener('click', async () => {
  if (!reservaParaAvaliar || !estrelaEscolhida) return;
  const resp = await fetch('/api/avaliacao/criar.php', {
    method: 'POST',
    body: fd({ reserva_id: reservaParaAvaliar, avaliacao: estrelaEscolhida, comentario: document.getElementById('avaliacao-comentario').value }),
  });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível enviar a avaliação.'); return; }
  document.getElementById('modal-avaliacao').style.display = 'none';
});

async function atualizarReservaAtiva() {
  const json = await kgApiJSON('/api/passageiro/minha_reserva.php');
  const painel = document.getElementById('reserva-ativa');
  const formWrap = document.getElementById('form-wrap');

  if (!json.reserva) {
    reservaAtivaAtual = false;
    reservaAtivaId = null;
    reservaEstadoAnterior = null;
    if (meuVeiculoReservaMarcado) { mapa.removeMarker(meuVeiculoReservaMarcado); meuVeiculoReservaMarcado = null; meuVeiculoReservaEstado = null; }
    if (destinoMarkerCriado) { mapa.removeMarker('descida'); destinoMarkerCriado = false; destinoArrastando = false; }
    mapa.clearRoute('rota-recolha');
    mapa.clearRoute('rota-eu-veiculo');
    mapa.clearRoute('rota-destino');
    painel.style.display = 'none';
    formWrap.style.display = 'block';
    if (json.avaliar_pendente && document.getElementById('modal-avaliacao').style.display !== 'flex') {
      abrirModalAvaliacao(json.avaliar_pendente);
    }
    return;
  }

  const r = json.reserva;
  reservaAtivaAtual = ['pendente', 'confirmado', 'a_bordo'].includes(r.estado);
  reservaAtivaId = r.id;
  painel.style.display = 'block';
  formWrap.style.display = 'none';

  // Pedido de viagem urbana: um condutor está a caminho assim que a reserva
  // passa de pendente a confirmado com veículo já atribuído (tarefa D do
  // relatório "condutor confirma que está a caminho").
  if (r.tipo_viagem === 'urbano' && reservaEstadoAnterior === 'pendente' && r.estado === 'confirmado' && r.condutor_nome) {
    toast(`🚗 ${r.condutor_nome} está a caminho para o(a) buscar!`, 'sucesso');
  }
  reservaEstadoAnterior = r.estado;

  const badge = document.getElementById('reserva-badge');
  badge.className = 'kg-badge kg-badge--' + r.estado;
  badge.textContent = { pendente: 'Pendente', confirmado: 'Confirmado', a_bordo: 'A bordo', concluido: 'Concluído', recusado: 'Recusado' }[r.estado] || r.estado;

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
    condutorEl.innerHTML = `<p class="kg-small">Condutor: ${r.condutor_nome} —
      <button type="button" class="kg-btn kg-btn--outline kg-btn--sm" id="btn-chamar-condutor">📞 Chamar</button>
      <a href="https://wa.me/${tel}" target="_blank" style="color:var(--kg-success);font-weight:600;">💬 WhatsApp</a></p>`;
    document.getElementById('btn-chamar-condutor').addEventListener('click', () => {
      KGChamada.iniciar(r.condutor_id, r.condutor_nome);
    });
  } else {
    condutorEl.innerHTML = '';
  }

  if (r.veiculo_lat && r.veiculo_lng) {
    const idVeiculo = `v-${r.veiculo_id}`;
    const lat = parseFloat(r.veiculo_lat);
    const lng = parseFloat(r.veiculo_lng);
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
      mapa.updateMarker(idVeiculo, lat, lng);
    }

    // Rota do condutor até ao meu ponto de partida, enquanto ainda não embarquei.
    if (r.estado !== 'a_bordo' && r.ponto_partida_lat && r.ponto_partida_lng) {
      KGMap.tracarRota(mapa, 'rota-recolha', { lat, lng }, { lat: parseFloat(r.ponto_partida_lat), lng: parseFloat(r.ponto_partida_lng) });
    } else {
      mapa.clearRoute('rota-recolha');
    }

    // Caminho a percorrer para encontrar o carro: linha entre a minha
    // posição GPS ao vivo e o veículo, enquanto ainda não embarquei
    // (relatório do mapa, tarefa 6). Uma linha simples é mais honesta aqui
    // do que uma rota por estrada (OSRM) — o passageiro anda a pé.
    if (r.estado !== 'a_bordo' && posicaoAtual) {
      mapa.drawRoute('rota-eu-veiculo', [
        [posicaoAtual.coords.latitude, posicaoAtual.coords.longitude],
        [lat, lng],
      ], { color: '#F7D116', weight: 3, opacity: 0.9, dashArray: '6, 8' });
    } else {
      mapa.clearRoute('rota-eu-veiculo');
    }
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
        popupHtml: `<strong>${r.ponto_descida_nome || 'Destino'}</strong><br>Arraste para ajustar a localização exata`,
        onDragEnd: async (novaLat, novaLng) => {
          const resp = await fetch('/api/passageiro/atualizar_destino.php', {
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
      KGMap.tracarRota(mapa, 'rota-destino', origemRotaDestino, { lat: destLat, lng: destLng }, { color: '#00B4D8', weight: 4, opacity: 0.85 });
    }
  } else if (destinoMarkerCriado) {
    mapa.removeMarker('descida');
    destinoMarkerCriado = false;
    destinoArrastando = false;
    mapa.clearRoute('rota-destino');
  }

  document.getElementById('btn-abrir-chat').style.display = (r.estado === 'confirmado' || r.estado === 'a_bordo') ? 'block' : 'none';
  // Nenhum passageiro fica refém de um condutor: pode sair enquanto ainda
  // não embarcou e escolher outro veículo (relatório do mapa, tarefa G).
  document.getElementById('btn-sair-reserva').style.display = (r.estado === 'pendente' || r.estado === 'confirmado') ? 'block' : 'none';
  // Se o condutor não clicar "Entregue", o passageiro pode finalizar a
  // viagem por si mesmo assim que já estiver a bordo (relatório "passageiro
  // pode finalizar se o condutor não o fizer").
  document.getElementById('btn-finalizar-viagem').style.display = (r.estado === 'a_bordo') ? 'block' : 'none';
  if (veiculoChatAtivoId !== r.veiculo_id) {
    veiculoChatAtivoId = r.veiculo_id;
    chatUltimoIdVisto = 0;
    document.getElementById('chat-condutor').style.display = 'none';
  }

  if (window.kgRt) window.kgRt.join(`veiculo:${r.veiculo_id}`);
}

// Perfil
document.getElementById('btn-perfil').addEventListener('click', async () => {
  const json = await kgApiJSON('/api/passageiro/perfil.php');
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
document.getElementById('form-perfil').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/passageiro/perfil.php', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('perfil-msg');
  if (!resp.ok) { msg.textContent = json.erro || 'Erro ao guardar.'; return; }
  msg.style.color = 'var(--kg-success)';
  msg.textContent = json.mensagem;
  setTimeout(() => { document.getElementById('modal-perfil').style.display = 'none'; }, 1200);
});

// Chat (autofalante) com o condutor
let veiculoChatAtivoId = null;
let chatPollTimer = null;
let chatUltimoIdVisto = 0;
async function carregarChat() {
  if (!veiculoChatAtivoId) return;
  const resp = await fetch(`/api/comunicacao/listar.php?veiculo_id=${veiculoChatAtivoId}`);
  if (!resp.ok) return;
  const json = await resp.json();
  const box = document.getElementById('chat-mensagens');
  box.innerHTML = (json.mensagens || []).map(m => `
    <div style="margin-bottom:6px; text-align:${m.remetente_id === UTILIZADOR_ID ? 'right' : 'left'};">
      <span class="kg-small" style="display:inline-block; background:${m.remetente_id === UTILIZADOR_ID ? 'var(--kg-primary)' : '#fff'}; color:${m.remetente_id === UTILIZADOR_ID ? '#fff' : 'inherit'}; border-radius:var(--radius-md); padding:6px 10px; max-width:80%;">
        ${m.destinatario_id === null ? '<strong>📢 </strong>' : ''}${m.mensagem}
      </span>
    </div>`
  ).join('') || '<p class="kg-small">Sem mensagens ainda.</p>';
  box.scrollTop = box.scrollHeight;

  const novasDeOutrem = (json.mensagens || []).filter(m => m.id > chatUltimoIdVisto && m.remetente_id !== UTILIZADOR_ID);
  if (chatUltimoIdVisto > 0 && novasDeOutrem.length > 0) KGSons.tocarMensagem();
  if ((json.mensagens || []).length) chatUltimoIdVisto = Math.max(chatUltimoIdVisto, ...json.mensagens.map(m => m.id));

  (json.mensagens || []).filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    fetch('/api/comunicacao/marcar_lida.php', { method: 'POST', body: fd({ id: m.id }) });
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
  const resp = await fetch('/api/passageiro/cancelar_reserva.php', { method: 'POST', body: fd({ reserva_id: reservaAtivaId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível sair da reserva.'); return; }
  if (window.kgRt && json.veiculo_id) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
  atualizarReservaAtiva();
});
document.getElementById('btn-finalizar-viagem').addEventListener('click', async () => {
  if (!reservaAtivaId) return;
  if (!confirm('Confirma que já chegou ao destino? A viagem fica concluída.')) return;
  const resp = await fetch('/api/passageiro/finalizar_viagem.php', { method: 'POST', body: fd({ reserva_id: reservaAtivaId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro || 'Não foi possível finalizar a viagem.'); return; }
  if (window.kgRt && json.veiculo_id) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
  toast('Viagem finalizada — obrigado!', 'sucesso');
  atualizarReservaAtiva();
});
document.getElementById('form-chat').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const input = document.getElementById('chat-input');
  const texto = input.value.trim();
  if (!texto || !veiculoChatAtivoId) return;
  input.value = '';
  const resp = await fetch('/api/comunicacao/enviar.php', { method: 'POST', body: fd({ veiculo_id: veiculoChatAtivoId, mensagem: texto }) });
  if (resp.ok && window.kgRt) window.kgRt.emit('comunicacao:nova', { veiculoId: veiculoChatAtivoId });
  carregarChat();
});

// Notificações
const estadoBadgeTipoNotif = { alerta: 'pendente', informativo: 'confirmado', urgente: 'recusado' };
let notifUltimaContagem = null;
async function carregarNotificacoes() {
  const json = await kgApiJSON('/api/notificacoes/listar.php');
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
      await fetch('/api/notificacoes/marcar_lida.php', { method: 'POST', body: fd({ id: urgenteNaoLida.id }) });
      document.getElementById('modal-notificacao-urgente').style.display = 'none';
      carregarNotificacoes();
    };
  }

  document.getElementById('lista-notificacoes').innerHTML = (json.notificacoes || []).map(n => `
    <div class="kg-card" style="margin-bottom:8px; ${n.lida ? 'opacity:0.6;' : ''}">
      <div class="kg-flex kg-justify-between kg-items-center">
        <strong>${n.titulo}</strong>
        <span class="kg-badge kg-badge--${estadoBadgeTipoNotif[n.tipo]}">${n.tipo}</span>
      </div>
      <p class="kg-small">${n.mensagem}</p>
      <p class="kg-small" style="opacity:0.6;">${n.criado_em}</p>
      ${!n.lida ? `<button class="kg-btn kg-btn--sm kg-btn--outline" data-marcar-lida="${n.id}">Marcar como lida</button>` : ''}
    </div>`
  ).join('') || '<p class="kg-small">Sem notificações.</p>';

  document.querySelectorAll('[data-marcar-lida]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/notificacoes/marcar_lida.php', { method: 'POST', body: fd({ id: btn.dataset.marcarLida }) });
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
    const json = await kgApiJSON('/api/passageiro/meus_condutores.php');
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/sugestoes/enviar.php', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('sugestao-msg');
  if (!resp.ok) { msg.textContent = json.erro || 'Erro ao enviar.'; return; }
  msg.style.color = 'var(--kg-success)';
  msg.textContent = 'Enviado. Obrigado pelo seu feedback.';
  setTimeout(() => { document.getElementById('modal-sugestao').style.display = 'none'; msg.textContent = ''; ev.target.reset(); }, 1200);
});

// SOS
document.getElementById('btn-sos').addEventListener('click', async () => {
  if (!confirm('Ativar alerta SOS? A central de alarmes será notificada com a sua localização.')) return;
  navigator.geolocation.getCurrentPosition(async (pos) => {
    const dados = new FormData();
    dados.set('csrf_token', CSRF_TOKEN);
    dados.set('lat', pos.coords.latitude);
    dados.set('lng', pos.coords.longitude);
    const resp = await fetch('/api/sos.php', { method: 'POST', body: dados });
    if (resp.ok) {
      alert('Alerta SOS enviado. Ajuda a caminho.');
      if (window.kgRt) window.kgRt.emit('sos:ativado', { lat: pos.coords.latitude, lng: pos.coords.longitude });
    }
  });
});

// Arranque: o mapa aparece de imediato (centro por omissão, Cabo Verde) —
// nunca fica à espera do GPS para desenhar estradas/tiles. A geolocalização
// (abaixo) só entra depois, para centrar e colocar o marcador 'eu'; se
// falhar ou demorar, o resto do painel continua a funcionar na mesma
// (relatório "mapa cinzento": estava tudo dependente de kgEnsureGeolocation
// resolver, incluindo a própria criação do mapa — um GPS lento/negado
// deixava o mapa por criar, daí o fundo cinzento do Leaflet).
mapa = KGMap.create('map', { zoom: 13 });
KGMap.ligarPausaDeAnimacoes(mapa);

// Segue a posição do passageiro automaticamente (relatório "GPS a
// aparecer fora do mapa"): sem isto, o marcador movia-se para a
// coordenada certa mas a vista do mapa nunca ia atrás — bastava andar
// uma rua para o marcador sair do ecrã, sem nenhum aviso. Só para de
// seguir se o próprio utilizador arrastar o mapa (dragstart, nunca
// movestart — senão o nosso próprio panTo desligava-se a si mesmo); o
// botão 📍 no mapa retoma o seguimento.
let aSeguirUtilizador = true;
mapa.onDragStart(() => { aSeguirUtilizador = false; });
document.getElementById('btn-recentrar').addEventListener('click', () => {
  aSeguirUtilizador = true;
  if (posicaoAtual) mapa.flyTo(posicaoAtual.coords.latitude, posicaoAtual.coords.longitude, 15);
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

// Geolocalização: pedida em paralelo com o resto do arranque acima — assim
// que houver uma posição válida, centra o mapa e cria/segue o marcador 'eu'.
kgEnsureGeolocation((pos) => {
  console.log('V-MILLION GPS: posição inicial obtida, precisão =', pos.coords.accuracy, 'm');
  posicaoAtual = pos;
  mapa.setUserPosition(pos.coords.latitude, pos.coords.longitude);
  mapa.flyTo(pos.coords.latitude, pos.coords.longitude, 15);

  kgWatchPosition((lat, lng, novaPos) => {
    console.log('V-MILLION GPS: atualização, precisão =', novaPos.coords.accuracy, 'm');
    mapa.updateMarker('eu', lat, lng);
    if (aSeguirUtilizador) mapa.panTo(lat, lng);
    posicaoAtual = novaPos;
  });

  // Envia a localização exata do passageiro ao condutor enquanto houver uma
  // reserva ativa (pendente/confirmada/a bordo) — secção 5.3 do relatório do mapa.
  let abortLocalizacao = null;
  setInterval(() => {
    if (!reservaAtivaAtual || !posicaoAtual) return;
    if (abortLocalizacao) abortLocalizacao.abort();
    abortLocalizacao = new AbortController();
    fetch('/api/passageiro/localizacao.php', {
      method: 'POST',
      body: fd({ lat: posicaoAtual.coords.latitude, lng: posicaoAtual.coords.longitude, accuracy: posicaoAtual.coords.accuracy }),
      signal: abortLocalizacao.signal,
    }).catch(() => { /* silencioso: tenta novamente no próximo ciclo */ });
  }, 5000);
});
</script>
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
