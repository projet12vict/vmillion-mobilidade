<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/page_guard.php';
require_once __DIR__ . '/../../includes/veiculos.php';

$condutor = kg_pagina_exigir_utilizador('condutor');
$csrf = kg_csrf_token();

// Gate de pagamento (secção 11.6): condutor com veículo aprovado só vê o
// mapa e os passageiros se tiver uma taxa de operação aprovada e válida.
$pdo = kg_db();
$mostrarMapa = kg_condutor_pode_ver_mapa($pdo, $condutor['id']);

// Papel duplo (secção 8): este condutor também é proprietário de frota?
$stmtProprietario = $pdo->prepare("SELECT nome FROM proprietarios WHERE utilizador_condutor_id = ? LIMIT 1");
$stmtProprietario->execute([$condutor['id']]);
$proprietarioAssociado = $stmtProprietario->fetchColumn();
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
<title>Painel do Condutor — V-MILLION</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<link rel="stylesheet" href="/assets/css/kg-map-markers.css">
<style>
  html, body { height: 100%; margin: 0; }
  .kg-c-layout { display: grid; grid-template-columns: 1fr 380px; height: 100vh; }
  .kg-c-header {
    grid-column: 1 / -1; height: 60px; background: var(--kg-gradient-header); color: #fff;
    display: flex; align-items: center; justify-content: space-between; padding: 0 var(--sp-5);
    box-shadow: var(--shadow-md); position: relative; z-index: 30;
  }
  .kg-c-map-wrap { position: relative; grid-row: 2; }
  #map { position: absolute; inset: 0; }
  .kg-c-sidebar { grid-row: 2; overflow-y: auto; background: var(--kg-bg); padding: var(--sp-4); border-left: 1px solid var(--kg-border); }
  .kg-c-body { display: contents; }
  select.kg-select, input.kg-input { margin-bottom: var(--sp-2); }
  @media (max-width: 900px) {
    .kg-c-layout { grid-template-columns: 1fr; grid-template-rows: 60px 45vh 1fr; }
    .kg-c-map-wrap { grid-row: 2; }
    .kg-c-sidebar { grid-row: 3; border-left: none; border-top: 1px solid var(--kg-border); }
    /* O cabeçalho tem altura fixa (60px) — não pode quebrar como as
       restantes .kg-flex (essas ganham flex-wrap globalmente, ver
       kg-design-system.css). Em vez disso, liberta espaço escondendo o
       nome (só decorativo) e encolhendo o espaçamento/padding. */
    .kg-c-header { padding: 0 var(--sp-3); }
    .kg-c-header .kg-flex { flex-wrap: nowrap; gap: var(--sp-1); }
    .kg-c-header .kg-small { display: none; }
  }
</style>
</head>
<body>
<div class="kg-c-layout">
  <header class="kg-c-header">
    <div style="font-weight:800;">V-MILLION — Condutor<?= $proprietarioAssociado ? ' <span class="kg-badge kg-badge--confirmado" style="margin-left:6px;">Proprietário</span>' : '' ?></div>
    <div class="kg-flex kg-items-center kg-gap-2">
      <span class="kg-small" style="color:#fff;"><?= htmlspecialchars($condutor['nome'], ENT_QUOTES) ?></span>
      <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff; position:relative;" id="btn-notificacoes" type="button">🔔<span id="badge-notificacoes" style="display:none; position:absolute; top:-4px; right:-4px; background:var(--kg-danger); color:#fff; border-radius:50%; font-size:0.65rem; padding:1px 5px;"></span></button>
      <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;" id="btn-sons" type="button" title="Preferências de som">🔊</button>
      <button class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;" id="btn-sugestao" type="button">Sugestão</button>
      <a href="/api/auth/logout.php" class="kg-btn kg-btn--sm kg-btn--ghost" style="color:#fff;">Sair</a>
    </div>
  </header>

  <div class="kg-c-map-wrap">
    <?php if ($mostrarMapa): ?>
    <div id="map"></div>
    <div class="kg-nav-panel" id="nav-panel" style="display:none;">
      <button type="button" class="kg-nav-panel__fechar" id="btn-nav-fechar" aria-label="Fechar navegação">✕</button>
      <div class="kg-nav-panel__titulo"><span id="nav-icone">🧭</span> <span id="nav-titulo"></span></div>
      <div class="kg-nav-panel__info" id="nav-info"></div>
    </div>
    <div class="kg-map-controls">
      <button type="button" class="kg-map-btn" id="btn-recentrar" title="A minha localização" aria-label="Recentrar no meu GPS">📍</button>
    </div>
    <button type="button" class="kg-map-sos" id="btn-sos">SOS</button>
    <?php else: ?>
    <div class="kg-card" style="margin: var(--sp-5);">
      <h3 class="kg-h3">Pagamento pendente</h3>
      <p class="kg-small">O mapa e a lista de passageiros ficam disponíveis depois de o pagamento (pacote diário/semanal/mensal/anual) ser aprovado pelo administrador. Veja o estado em "Pagamento", na barra lateral.</p>
    </div>
    <?php endif; ?>
  </div>

  <aside class="kg-c-sidebar">
    <div class="kg-card" style="margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">Os meus veículos</h3>
      <div id="lista-meus-veiculos"></div>
      <button class="kg-btn kg-btn--outline kg-btn--full kg-btn--sm" id="btn-novo-veiculo" type="button" style="margin-top:8px;">+ Adicionar veículo</button>
    </div>

    <div class="kg-card" style="margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">Pagamento</h3>
      <div id="card-pagamento"><p class="kg-small">A verificar estado...</p></div>
    </div>

    <?php if ($mostrarMapa): ?>
    <div class="kg-card" style="margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">🏙️ Passageiros urbanos</h3>
      <p class="kg-small">Só pedidos urbanos em aberto (sem veículo escolhido ainda) — de qualquer condutor aprovado, esteja ou não num ponto. Pedidos intermunicipais e urbanos já associados a um dos seus veículos aparecem em "Passageiros", mais abaixo. Precisa de ter um veículo selecionado abaixo para poder "Ir buscar".</p>
      <div id="lista-passageiros-urbanos"><p class="kg-small">Nenhum pedido em aberto neste momento.</p></div>
    </div>

    <div class="kg-card" id="card-veiculo-ativo" style="display:none; margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">Veículo ativo</h3>
      <div class="kg-field">
        <label class="kg-label">Ponto de partida</label>
        <select class="kg-select" id="sel-ponto-condutor"></select>
      </div>
      <div class="kg-field">
        <label class="kg-label">Destino</label>
        <select class="kg-select" id="sel-destino-condutor"></select>
      </div>
      <button class="kg-btn kg-btn--primario kg-btn--sm" id="btn-guardar-ponto" type="button">Guardar</button>

      <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
        <button class="kg-btn kg-btn--cta kg-btn--sm" id="btn-entrar-fila" type="button">Entrar na fila</button>
        <button class="kg-btn kg-btn--perigo kg-btn--sm" id="btn-sair-fila" type="button">Sair da fila</button>
      </div>
      <p class="kg-small" id="estado-veiculo-txt" style="margin-top:8px;"></p>
    </div>

    <div class="kg-card" style="margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">Passageiros</h3>
      <div id="lista-passageiros"><p class="kg-small">Selecione um veículo ativo.</p></div>
    </div>

    <div class="kg-card" style="margin-bottom: var(--sp-4);">
      <h3 class="kg-h3">Comunicação</h3>
      <div id="fila-chamadas" style="margin-bottom:8px;"></div>
      <div id="chat-mensagens-condutor" style="max-height:220px; overflow-y:auto; background:var(--kg-bg); border-radius:var(--radius-md); padding:8px; margin-bottom:8px;"></div>
      <form id="form-chat-condutor">
        <select class="kg-select" id="chat-responder-a">
          <option value="">📢 Autofalante (todos os passageiros)</option>
        </select>
        <div class="kg-flex kg-gap-2" style="margin-top:6px;">
          <input class="kg-input" id="chat-input-condutor" placeholder="Escreva uma mensagem..." maxlength="500" style="flex:1;">
          <button class="kg-btn kg-btn--cta kg-btn--sm" type="submit">Enviar</button>
        </div>
      </form>
    </div>

    <div class="kg-card">
      <h3 class="kg-h3">Outros condutores no ponto</h3>
      <div id="lista-outros-condutores"><p class="kg-small">—</p></div>
    </div>
    <?php endif; ?>
  </aside>
</div>

<!-- Modal novo veículo -->
<div class="kg-modal-overlay" id="modal-veiculo" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Registar veículo</h3>
    <form id="form-veiculo">
      <div class="kg-field"><label class="kg-label">Matrícula</label><input class="kg-input" name="matricula" required></div>
      <div class="kg-field">
        <label class="kg-label">Tipo</label>
        <select class="kg-select" name="tipo"><option value="hiace">Hiace</option><option value="taxi">Táxi</option><option value="autocarro">Autocarro</option></select>
      </div>
      <div class="kg-field">
        <label class="kg-label">Tipo de serviço</label>
        <select class="kg-select" name="tipo_servico" id="veiculo-tipo-servico">
          <option value="ambos">Urbano + Intermunicipal</option>
          <option value="urbano">Só urbano (dentro da cidade)</option>
          <option value="intermunicipal">Só intermunicipal (entre cidades)</option>
        </select>
      </div>
      <div class="kg-field" id="campo-rota-fixa" style="display:none;">
        <label class="kg-label">Rota fixa (opcional)</label>
        <select class="kg-select" name="rota_fixa_id" id="veiculo-rota-fixa"><option value="">Nenhuma (preço por km)</option></select>
      </div>
      <div class="kg-field"><label class="kg-label">Cor</label><input class="kg-input" name="cor" required></div>
      <div class="kg-field"><label class="kg-label">Modelo</label><input class="kg-input" name="modelo" required></div>
      <p class="kg-small">Lugares para passageiros: 14 (fixo).</p>
      <div id="veiculo-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
        <button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-veiculo">Cancelar</button>
        <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Registar</button>
      </div>
    </form>
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

<!-- Modal de sugestão -->
<div class="kg-modal-overlay" id="modal-sugestao" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Enviar sugestão</h3>
    <p class="kg-small">Visível apenas para o Super Admin.</p>
    <form id="form-sugestao">
      <input type="hidden" name="tipo" value="sugestao">
      <div class="kg-field"><label class="kg-label">Título</label><input class="kg-input" name="titulo" required></div>
      <div class="kg-field"><label class="kg-label">Descrição</label><textarea class="kg-input" name="descricao" rows="4" required></textarea></div>
      <div id="sugestao-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2" style="margin-top: var(--sp-4);">
        <button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-sugestao">Cancelar</button>
        <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Enviar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal sair da fila -->
<div class="kg-modal-overlay" id="modal-sair-fila" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Sair da fila</h3>
    <div class="kg-field">
      <label class="kg-label">Motivo</label>
      <select class="kg-select" id="sel-motivo-saida">
        <option value="Carro cheio">Carro cheio</option>
        <option value="Problema mecânico">Problema mecânico</option>
        <option value="Fim do turno">Fim do turno</option>
        <option value="Outro">Outro</option>
      </select>
    </div>
    <div class="kg-flex kg-gap-2">
      <button type="button" class="kg-btn kg-btn--ghost" id="btn-cancelar-saida">Cancelar</button>
      <button type="button" class="kg-btn kg-btn--perigo kg-btn--full" id="btn-confirmar-saida">Confirmar saída</button>
    </div>
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="/assets/js/kg-sons.js"></script>
<script src="/assets/js/kg-geolocation.js"></script>
<script src="/assets/js/kg-map.js"></script>
<script src="/assets/js/kg-realtime.js"></script>
<script src="/assets/js/kg-chamada.js"></script>
<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const UTILIZADOR_ID = <?= json_encode((int) $condutor['id']) ?>;
const MOSTRAR_MAPA = <?= json_encode($mostrarMapa) ?>;
let mapa = null;
let pontos = [];
let veiculoAtivoId = null;
let veiculoAtivoData = null;
let watchId = null;
let passageiroMarkerIds = new Set();
let passageiroUrbanoMarkerIds = new Set();
let outroCondutorMarkerIds = new Set();
let outroCondutorMarcadoEstado = new Map();
let pollingAtivo = null;
let meuVeiculoMarcadoId = null;
let meuVeiculoMarcadoEstado = null;
let ultimaLatCondutor = null;
let ultimaLngCondutor = null;
let navegacaoAlvo = null; // { tipo: 'passageiro'|'destino', lat, lng, nome }
let navegacaoTimer = null;
// Deteção de atalho/desvio e ultrapassagem do destino (relatório "rota
// dinâmica"): navMenorDistancia é a menor distância já alcançada ao alvo
// nesta navegação — se a distância voltar a crescer bastante depois de ter
// chegado perto, o condutor ultrapassou o alvo. navUltimaDistancia deteta um
// salto brusco entre dois sinais GPS (saiu da rota por um atalho) para
// recalcular de imediato, sem esperar pelo temporizador de 30s.
let navMenorDistancia = null;
let navUltimaDistancia = null;
let navUltrapassouAvisado = false;
let navUltimoRecalculo = 0;

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

async function carregarPontosDropdowns() {
  const json = await kgApiJSON('/api/passageiro/pontos.php');
  pontos = json.pontos || [];
  const selP = document.getElementById('sel-ponto-condutor');
  const selD = document.getElementById('sel-destino-condutor');
  selP.innerHTML = selD.innerHTML = '';
  pontos.forEach(p => {
    selP.add(new Option(p.nome, p.id));
    selD.add(new Option(p.nome, p.id));
    mapa.addMarker(`ponto-${p.id}`, 'ponto', parseFloat(p.lat), parseFloat(p.lng), { popupHtml: `<strong>${p.nome}</strong>` });
  });
}

async function carregarMeusVeiculos() {
  const json = await kgApiJSON('/api/condutor/veiculos.php');
  const lista = document.getElementById('lista-meus-veiculos');
  lista.innerHTML = '';

  (json.veiculos || []).forEach(v => {
    const div = document.createElement('div');
    div.className = 'kg-flex kg-justify-between kg-items-center';
    div.style.cssText = 'padding:8px 0;border-bottom:1px solid var(--kg-border);';
    const pendentes = v.passageiros_pendentes || 0;
    // Badge de passageiros à espera por veículo (relatório "pedidos
    // intermunicipais invisíveis"): um condutor com mais do que um veículo
    // só via os pedidos do veículo que tinha selecionado como ativo — um
    // pedido preso noutro veículo seu ficava sem qualquer pista visível.
    const badgePendentes = pendentes > 0
      ? ` <span class="kg-badge kg-badge--recusado">${pendentes} à espera</span>`
      : '';
    div.innerHTML = `
      <div>
        <strong>${v.matricula}</strong>
        <span class="kg-badge ${v.aprovado ? 'kg-badge--confirmado' : 'kg-badge--pendente'}">${v.aprovado ? 'Aprovado' : 'Pendente'}</span>${badgePendentes}
        <div class="kg-small">${v.tipo} (${v.tipo_servico}) · ${v.cor} · ${v.lugares_livres}/${v.lugares_total} livres · ${v.estado}</div>
      </div>
      <button class="kg-btn kg-btn--sm kg-btn--outline" type="button">Selecionar</button>`;
    div.querySelector('button').addEventListener('click', () => selecionarVeiculo(v));
    lista.appendChild(div);
  });

  if (!json.veiculos || !json.veiculos.length) {
    lista.innerHTML = '<p class="kg-small">Ainda não tem veículos registados.</p>';
  }

  // Mantém o cartão "Veículo ativo" sincronizado com o estado real depois de
  // ações como Entrar/Sair da fila — sem isto, os botões ficavam-se pelo
  // estado antigo até o condutor voltar a clicar "Selecionar" na lista.
  if (veiculoAtivoId) {
    const atual = (json.veiculos || []).find(v => v.id === veiculoAtivoId);
    if (atual) {
      veiculoAtivoData = atual;
      document.getElementById('estado-veiculo-txt').textContent =
        `Estado: ${atual.estado}${atual.posicao_fila ? ' · posição na fila: ' + atual.posicao_fila : ''}`;
      atualizarBotoesEstadoVeiculo(atual.estado);
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

function pontoPorId(id) {
  return pontos.find(p => String(p.id) === String(id));
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
async function tracarRotaCondutor() {
  if (!mapa) return;
  const partida = pontoPorId(document.getElementById('sel-ponto-condutor').value);
  const destino = pontoPorId(document.getElementById('sel-destino-condutor').value);
  if (!partida || !destino) { mapa.clearRoute('condutor-rota'); return; }
  await KGMap.tracarRota(
    mapa, 'condutor-rota',
    { lat: parseFloat(partida.lat), lng: parseFloat(partida.lng) },
    { lat: parseFloat(destino.lat), lng: parseFloat(destino.lng) }
  );
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

async function iniciarNavegacao(tipo, lat, lng, nome) {
  if (!mapa || isNaN(lat) || isNaN(lng)) return;
  navegacaoAlvo = { tipo, lat, lng, nome };
  navMenorDistancia = null;
  navUltimaDistancia = null;
  navUltrapassouAvisado = false;
  const origem = { lat: ultimaLatCondutor ?? parseFloat(veiculoAtivoData?.lat), lng: ultimaLngCondutor ?? parseFloat(veiculoAtivoData?.lng) };

  document.getElementById('nav-panel').style.display = 'block';
  document.getElementById('nav-icone').textContent = tipo === 'passageiro' ? '📍' : '🏁';
  document.getElementById('nav-titulo').textContent = tipo === 'passageiro' ? `A caminho de ${nome}` : `A caminho do destino: ${nome}`;
  document.getElementById('nav-info').textContent = 'A calcular rota...';

  if (isNaN(origem.lat) || isNaN(origem.lng)) {
    mapa.flyTo(lat, lng, 15);
    document.getElementById('nav-info').textContent = 'Sem posição GPS do veículo ainda — a aguardar sinal.';
    return;
  }

  mapa.fitBounds([[origem.lat, origem.lng], [lat, lng]]);
  const info = await KGMap.tracarRota(mapa, 'rota-navegacao', origem, { lat, lng }, { color: '#00B4D8', weight: 5, opacity: 0.9 });
  if (navegacaoAlvo?.tipo !== tipo || navegacaoAlvo.lat !== lat || navegacaoAlvo.lng !== lng) return; // alvo mudou entretanto
  const dist = formatarDistancia(info.distanciaM);
  const dur = formatarDuracao(info.duracaoS);
  document.getElementById('nav-info').textContent = dist && dur ? `${dist} · ${dur}` : 'Rota traçada (linha direta — sem dados de estrada).';

  clearInterval(navegacaoTimer);
  navegacaoTimer = setInterval(() => atualizarNavegacao(), 30000);
}

async function atualizarNavegacao() {
  if (!navegacaoAlvo || ultimaLatCondutor == null) return;
  const info = await KGMap.tracarRota(
    mapa, 'rota-navegacao',
    { lat: ultimaLatCondutor, lng: ultimaLngCondutor },
    { lat: navegacaoAlvo.lat, lng: navegacaoAlvo.lng },
    { color: '#00B4D8', weight: 5, opacity: 0.9 }
  );
  const dist = formatarDistancia(info.distanciaM);
  const dur = formatarDuracao(info.duracaoS);
  if (dist && dur) document.getElementById('nav-info').textContent = `${dist} · ${dur}`;
}

function pararNavegacao() {
  navegacaoAlvo = null;
  navMenorDistancia = null;
  navUltimaDistancia = null;
  navUltrapassouAvisado = false;
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
    const pontoId = document.getElementById('sel-ponto-condutor').value;
    if (pontoId) carregarOutrosCondutores(pontoId);
  }, 10000);
}

// Os botões "Entrar na fila"/"Sair da fila" estavam sempre visíveis,
// mesmo quando o veículo nunca chegou a entrar na fila — o condutor não
// tinha nenhuma forma óbvia de sinalizar que ia partir diretamente do ponto
// (relatório do mapa, tarefa D: "Iniciar rota"). O endpoint por trás do
// botão (fila_sair.php) já funciona em qualquer estado, o que faltava era
// o botão certo aparecer com o rótulo certo.
function atualizarBotoesEstadoVeiculo(estado) {
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

function selecionarVeiculo(v) {
  if (!MOSTRAR_MAPA) return; // sem pagamento válido, o mapa/passageiros ficam bloqueados
  if (veiculoAtivoId !== v.id && meuVeiculoMarcadoId) {
    mapa.removeMarker(meuVeiculoMarcadoId);
    meuVeiculoMarcadoId = null;
    meuVeiculoMarcadoEstado = null;
    pararNavegacao();
  }
  veiculoAtivoId = v.id;
  veiculoAtivoData = v;
  document.getElementById('card-veiculo-ativo').style.display = 'block';
  if (v.ponto_partida_id) document.getElementById('sel-ponto-condutor').value = v.ponto_partida_id;
  if (v.destino_id) document.getElementById('sel-destino-condutor').value = v.destino_id;
  document.getElementById('estado-veiculo-txt').textContent = `Estado: ${v.estado}${v.posicao_fila ? ' · posição na fila: ' + v.posicao_fila : ''}`;
  atualizarBotoesEstadoVeiculo(v.estado);
  carregarPassageiros();
  carregarChatCondutor();
  tracarRotaCondutor();
  // Elegibilidade para pedidos urbanos depende do veículo selecionado
  // (tipo_servico + ponto definido) — refresca de imediato ao trocar de
  // veículo, sem esperar pelo polling de 10s.
  carregarPassageirosUrbanos();
  if (v.ponto_partida_id) carregarOutrosCondutores(v.ponto_partida_id);
  iniciarPollingCondutor();
  if (window.kgRt) window.kgRt.join(`veiculo:${v.id}`);
}

document.getElementById('btn-guardar-ponto')?.addEventListener('click', async () => {
  if (!veiculoAtivoId) { alert('Selecione um veículo primeiro.'); return; }
  const resp = await fetch('/api/condutor/veiculo_ponto.php', {
    method: 'POST',
    body: fd({
      veiculo_id: veiculoAtivoId,
      ponto_partida_id: document.getElementById('sel-ponto-condutor').value,
      destino_id: document.getElementById('sel-destino-condutor').value,
    }),
  });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro); return; }
  carregarMeusVeiculos();
  carregarOutrosCondutores(document.getElementById('sel-ponto-condutor').value);
  carregarPassageirosUrbanos();
  tracarRotaCondutor();
});

document.getElementById('btn-entrar-fila')?.addEventListener('click', async () => {
  if (!veiculoAtivoId) return;
  const resp = await fetch('/api/condutor/fila_entrar.php', { method: 'POST', body: fd({ veiculo_id: veiculoAtivoId }) });
  const json = await resp.json();
  if (!resp.ok) { alert(json.erro); return; }
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
  const resp = await fetch('/api/condutor/fila_sair.php', { method: 'POST', body: fd({ veiculo_id: veiculoAtivoId, motivo }) });
  const json = await resp.json();
  document.getElementById('modal-sair-fila').style.display = 'none';
  if (!resp.ok) { alert(json.erro); return; }
  carregarMeusVeiculos();
  carregarPassageiros();
});

// Desenha/atualiza no mapa os passageiros associados ao veículo ativo, com a
// localização exata (GPS ao vivo) quando o passageiro já a enviou, ou o
// ponto de partida como fallback (secção 5.3 / 9.4). Visibilidade privada:
// esta lista só contém passageiros com reserva neste veículo (ver
// api/condutor/passageiros.php), nunca de outros veículos.
function atualizarMarcadoresPassageiros(passageiros) {
  const idsAnteriores = passageiroMarkerIds;
  const idsAtuais = new Set();
  passageiros.forEach(p => {
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
        <strong>${p.passageiro_nome}</strong><br>
        <span>${p.estado}${p.passageiro_lat != null ? ' · GPS ao vivo' : ' · ponto de partida'}</span><br>
        <span>🎯 Destino: ${p.ponto_descida_nome || 'destino final'}</span>${distancia ? `<br><span>📏 ${distancia}</span>` : ''}
      </div>`;
      mapa.addMarker(id, 'passageiro', lat, lng, { popupHtml: popup });
    }
  });
  idsAnteriores.forEach(id => { if (!idsAtuais.has(id)) mapa.removeMarker(id); });
  passageiroMarkerIds = idsAtuais;
}

async function carregarPassageiros() {
  if (!veiculoAtivoId) return;
  const json = await kgApiJSON(`/api/condutor/passageiros.php?veiculo_id=${veiculoAtivoId}`);
  const lista = document.getElementById('lista-passageiros');
  lista.innerHTML = '';

  atualizarMarcadoresPassageiros(json.passageiros || []);

  // Restaura a rota de navegação para o destino sempre que há um passageiro
  // a bordo — não só no momento do clique em "Embarcar", mas também aqui,
  // que corre ao carregar o painel e a cada poll (10s). É isto que faz a
  // rota reaparecer depois de um reload ou de um logout/login: sem isto,
  // "navegacaoAlvo" ficava só em memória e esvaziava-se a cada refresh.
  const passageiroABordo = (json.passageiros || []).find(p => p.estado === 'a_bordo');
  if (passageiroABordo) {
    const alvo = destinoDoPassageiro(passageiroABordo);
    const jaANavegarParaAli = navegacaoAlvo?.tipo === 'destino' && alvo && navegacaoAlvo.lat === alvo.lat && navegacaoAlvo.lng === alvo.lng;
    if (alvo && !jaANavegarParaAli) iniciarNavegacao('destino', alvo.lat, alvo.lng, alvo.nome);
  } else if (navegacaoAlvo?.tipo === 'destino') {
    // Já não há ninguém a bordo (chegou/concluído) — a rota de destino
    // deixa de fazer sentido no mapa.
    pararNavegacao();
  }

  if (!json.passageiros || !json.passageiros.length) {
    lista.innerHTML = '<p class="kg-small">Sem passageiros neste momento.</p>';
    return;
  }

  json.passageiros.forEach(p => {
    const tel = (p.passageiro_telefone || '').replace(/\D/g, '');
    const wa = tel;
    const div = document.createElement('div');
    div.className = 'kg-card';
    div.style.marginBottom = '8px';
    const distancia = p.distancia_m != null ? (p.distancia_m >= 1000 ? (p.distancia_m / 1000).toFixed(1) + ' km' : p.distancia_m + ' m') : '—';
    div.innerHTML = `
      <div class="kg-flex kg-justify-between kg-items-center">
        <strong>${p.passageiro_nome}</strong>
        <span class="kg-badge kg-badge--${p.estado}">${p.estado}</span>
      </div>
      <p class="kg-small">${p.tipo_viagem === 'urbano' ? '🏙️ Urbana' : '🛣️ Intermunicipal'} · Embarque: ${p.ponto_partida_nome} (${distancia})</p>
      <p class="kg-small">🎯 Destino: <strong>${p.ponto_descida_nome || 'destino final'}</strong> · ${p.preco_final} CVE</p>
      <div class="kg-flex kg-gap-2">
        <button type="button" class="kg-btn kg-btn--outline kg-btn--sm" data-chamar data-para-id="${p.passageiro_id ?? ''}" data-para-nome="${p.passageiro_nome}">📞 Chamar</button>
        <a href="https://wa.me/${wa}" target="_blank" class="kg-small" style="color:var(--kg-success);font-weight:600;">💬 WhatsApp</a>
      </div>
      <div class="kg-flex kg-gap-2" style="margin-top:8px; flex-wrap:wrap;">
        <button class="kg-btn kg-btn--outline kg-btn--sm" data-buscar data-lat="${p.mapa_lat}" data-lng="${p.mapa_lng}" data-nome="${p.passageiro_nome}">📍 Ir buscar</button>
        ${p.estado === 'pendente' ? `
          <button class="kg-btn kg-btn--cta kg-btn--sm" data-acao="confirmar" data-id="${p.id}">Confirmar</button>
          <button class="kg-btn kg-btn--perigo kg-btn--sm" data-acao="recusar" data-id="${p.id}">Recusar</button>` : ''}
        ${p.estado === 'confirmado' ? `<button class="kg-btn kg-btn--cta kg-btn--sm" data-acao="embarcar" data-id="${p.id}" data-descida-lat="${p.ponto_descida_lat ?? ''}" data-descida-lng="${p.ponto_descida_lng ?? ''}" data-descida-nome="${p.ponto_descida_nome ?? ''}">Embarcar</button>` : ''}
        ${p.estado === 'a_bordo' ? `<button class="kg-btn kg-btn--cta kg-btn--sm" data-acao="chegou" data-id="${p.id}">Chegou</button>` : ''}
      </div>`;
    lista.appendChild(div);
  });

  // "Ir buscar" navega a sério (rota por estrada + distância/tempo), em vez
  // de só centrar o mapa num zoom fixo — relatório do mapa, tarefas B/C.
  lista.querySelectorAll('[data-buscar]').forEach(btn => {
    btn.addEventListener('click', () => {
      const lat = parseFloat(btn.dataset.lat);
      const lng = parseFloat(btn.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) iniciarNavegacao('passageiro', lat, lng, btn.dataset.nome);
    });
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
      const resp = await fetch('/api/condutor/reserva_estado.php', {
        method: 'POST',
        body: fd({ reserva_id: btn.dataset.id, acao }),
      });
      const json = await resp.json();
      if (!resp.ok) { alert(json.erro); return; }
      if (window.kgRt) window.kgRt.emit('reserva:atualizada', { passageiroId: json.passageiro_id });
      if (json.lugares_livres !== null && window.kgRt) window.kgRt.emit('veiculo:lugares', { veiculoId: json.veiculo_id, lugaresLivres: json.lugares_livres });
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
        popupHtml: `<strong>${p.passageiro_nome}</strong><br>🎯 ${p.ponto_descida_nome}`,
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
  const btn = document.querySelector(`#lista-passageiros-urbanos [data-reclamar="${reservaId}"]`);
  if (btn) {
    btn.closest('.kg-card')?.remove();
    const lista = document.getElementById('lista-passageiros-urbanos');
    if (!lista.children.length) {
      lista.innerHTML = '<p class="kg-small">Nenhum pedido em aberto neste momento.</p>';
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
  let json;
  try {
    json = await kgApiJSON(`/api/condutor/passageiros_urbanos.php${qs}`);
  } catch (e) {
    // Antes isto ficava em silêncio e mostrava sempre "Nenhum pedido em
    // aberto" — indistinguível de não haver mesmo procura. A causa mais
    // comum é a conta ainda não ter aprovação/pagamento válido (ver
    // kg_condutor_pode_ver_mapa em includes/veiculos.php); mostrar a
    // mensagem real evita o condutor pensar que não há passageiros quando
    // na verdade é a própria conta que ainda não tem acesso.
    lista.innerHTML = `<p class="kg-small" style="color:var(--kg-danger);">${e.message}</p>`;
    return;
  }
  const passageiros = json.passageiros || [];
  atualizarMarcadoresPassageirosUrbanos(passageiros);

  if (json.elegivel === false) {
    lista.innerHTML = `<p class="kg-small">${json.motivo}</p>`;
    return;
  }

  if (!passageiros.length) {
    lista.innerHTML = '<p class="kg-small">Nenhum pedido em aberto neste momento.</p>';
    return;
  }

  lista.innerHTML = '';
  passageiros.forEach(p => {
    const distancia = p.distancia_m != null ? (p.distancia_m >= 1000 ? (p.distancia_m / 1000).toFixed(1) + ' km' : p.distancia_m + ' m') : '—';
    const div = document.createElement('div');
    div.className = 'kg-card';
    div.style.marginBottom = '8px';
    div.innerHTML = `
      <strong>${p.passageiro_nome}</strong>
      <p class="kg-small">🎯 Destino: <strong>${p.ponto_descida_nome}</strong> · 📏 ${distancia} · ${p.preco_final} CVE</p>
      <button class="kg-btn kg-btn--cta kg-btn--sm kg-btn--full" data-reclamar="${p.id}" data-lat="${p.passageiro_lat}" data-lng="${p.passageiro_lng}" data-nome="${p.passageiro_nome}">📍 Ir buscar</button>`;
    lista.appendChild(div);
  });

  lista.querySelectorAll('[data-reclamar]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!veiculoAtivoId) { alert('Selecione um veículo primeiro.'); return; }
      btn.disabled = true;
      const resp = await fetch('/api/condutor/recolher_urbano.php', {
        method: 'POST',
        body: fd({ reserva_id: btn.dataset.reclamar, veiculo_id: veiculoAtivoId }),
      });
      const json2 = await resp.json();
      if (!resp.ok) { alert(json2.erro); btn.disabled = false; return; }
      if (window.kgRt) {
        window.kgRt.emit('reserva:atualizada', { passageiroId: json2.passageiro_id });
        window.kgRt.emit('urbano:reclamado', { reservaId: btn.dataset.reclamar });
      }
      const lat = parseFloat(btn.dataset.lat), lng = parseFloat(btn.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) iniciarNavegacao('passageiro', lat, lng, btn.dataset.nome);
      carregarPassageirosUrbanos();
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
  const resp = await fetch(`/api/comunicacao/listar.php?veiculo_id=${veiculoAtivoId}`);
  if (!resp.ok) return;
  const json = await resp.json();
  const mensagens = json.mensagens || [];

  const novasDeOutrem = mensagens.filter(m => m.id > chatCondutorUltimoIdVisto && m.remetente_id !== UTILIZADOR_ID);
  if (chatCondutorUltimoIdVisto > 0 && novasDeOutrem.length > 0) KGSons.tocarMensagem();
  if (mensagens.length) chatCondutorUltimoIdVisto = Math.max(chatCondutorUltimoIdVisto, ...mensagens.map(m => m.id));

  const box = document.getElementById('chat-mensagens-condutor');
  box.innerHTML = mensagens.map(m => `
    <div style="margin-bottom:6px; text-align:${m.remetente_id === UTILIZADOR_ID ? 'right' : 'left'};">
      <span class="kg-small" style="display:inline-block; background:${m.remetente_id === UTILIZADOR_ID ? 'var(--kg-primary)' : '#fff'}; color:${m.remetente_id === UTILIZADOR_ID ? '#fff' : 'inherit'}; border-radius:var(--radius-md); padding:6px 10px; max-width:80%;">
        ${m.destinatario_id === null ? '<strong>📢 </strong>' : ''}<strong>${m.remetente_id !== UTILIZADOR_ID ? m.remetente_nome + ': ' : ''}</strong>${m.mensagem}
      </span>
    </div>`
  ).join('') || '<p class="kg-small">Sem mensagens ainda.</p>';
  box.scrollTop = box.scrollHeight;

  // Fila de chamadas: passageiros com mensagens por ler dirigidas ao condutor.
  const pendentes = new Map();
  mensagens.filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    pendentes.set(m.remetente_id, (pendentes.get(m.remetente_id) || { nome: m.remetente_nome, count: 0 }));
    pendentes.get(m.remetente_id).count++;
  });
  const filaEl = document.getElementById('fila-chamadas');
  filaEl.innerHTML = [...pendentes.entries()].map(([id, info]) => `
    <button type="button" class="kg-btn kg-btn--sm kg-btn--perigo" data-responder="${id}" style="margin:2px;">📞 ${info.nome} (${info.count})</button>`
  ).join('');
  filaEl.querySelectorAll('[data-responder]').forEach(btn => {
    btn.addEventListener('click', () => { document.getElementById('chat-responder-a').value = btn.dataset.responder; });
  });

  // Marca como lidas as mensagens recebidas.
  mensagens.filter(m => !m.lida && m.destinatario_id === UTILIZADOR_ID).forEach(m => {
    fetch('/api/comunicacao/marcar_lida.php', { method: 'POST', body: fd({ id: m.id }) });
  });

  // Opções do select "responder a": todos os passageiros que já falaram neste veículo.
  const sel = document.getElementById('chat-responder-a');
  const atual = sel.value;
  const remetentes = new Map();
  mensagens.filter(m => m.remetente_id !== UTILIZADOR_ID).forEach(m => remetentes.set(m.remetente_id, m.remetente_nome));
  sel.innerHTML = '<option value="">📢 Autofalante (todos os passageiros)</option>' +
    [...remetentes.entries()].map(([id, nome]) => `<option value="${id}">${nome}</option>`).join('');
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
  const resp = await fetch('/api/comunicacao/enviar.php', {
    method: 'POST',
    body: fd(destinatarioId ? { veiculo_id: veiculoAtivoId, mensagem: texto, destinatario_id: destinatarioId } : { veiculo_id: veiculoAtivoId, mensagem: texto }),
  });
  if (resp.ok && window.kgRt) window.kgRt.emit('comunicacao:nova', { veiculoId: veiculoAtivoId });
  carregarChatCondutor();
});

// Desenha no mapa os outros veículos aprovados no mesmo ponto (no ponto ou
// na fila, com a ordem da fila em badge) — secção 9.6.
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
      mapa.updateMarker(id, lat, lng);
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

async function carregarOutrosCondutores(pontoId) {
  if (!pontoId) return;
  const qsVeiculo = veiculoAtivoId ? `&veiculo_id=${veiculoAtivoId}` : '';
  const json = await kgApiJSON(`/api/condutor/outros_condutores.php?ponto_id=${pontoId}${qsVeiculo}`);
  const lista = document.getElementById('lista-outros-condutores');
  lista.innerHTML = '';

  atualizarMarcadoresOutrosCondutores(json.condutores || []);

  if (!json.condutores || !json.condutores.length) {
    lista.innerHTML = '<p class="kg-small">Nenhum outro condutor neste ponto.</p>';
    return;
  }
  json.condutores.forEach(c => {
    const wa = (c.condutor_telefone || '').replace(/\D/g, '');
    const div = document.createElement('div');
    div.className = 'kg-small';
    div.style.cssText = 'padding:6px 0;border-bottom:1px solid var(--kg-border);';
    div.innerHTML = `${c.matricula} (${c.condutor_nome})${c.estado === 'na_fila' ? ` · Fila #${c.posicao_fila}` : ''} — <a href="https://wa.me/${wa}" target="_blank" style="color:var(--kg-success);">WhatsApp</a>`;
    lista.appendChild(div);
  });
}

// Novo veículo
let rotasFixasCarregadas = false;
document.getElementById('veiculo-tipo-servico').addEventListener('change', (ev) => {
  document.getElementById('campo-rota-fixa').style.display = ev.target.value === 'urbano' ? 'none' : 'block';
});
document.getElementById('btn-novo-veiculo').addEventListener('click', async () => {
  if (!rotasFixasCarregadas) {
    const json = await kgApiJSON('/api/condutor/rotas_fixas.php');
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/condutor/veiculos.php', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('veiculo-msg');
  if (!resp.ok) { msg.textContent = json.erro || 'Erro ao registar.'; return; }
  msg.style.color = 'var(--kg-success)';
  msg.textContent = json.mensagem;
  carregarMeusVeiculos();
  setTimeout(() => { document.getElementById('modal-veiculo').style.display = 'none'; msg.textContent=''; ev.target.reset(); }, 1500);
});

// Notificações
const estadoBadgeTipoNotif = { alerta: 'pendente', informativo: 'confirmado', urgente: 'recusado' };
async function carregarNotificacoes() {
  const json = await kgApiJSON('/api/notificacoes/listar.php');
  const badge = document.getElementById('badge-notificacoes');
  if (json.nao_lidas > 0) { badge.style.display = 'inline'; badge.textContent = json.nao_lidas; } else { badge.style.display = 'none'; }

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
carregarNotificacoes();
setInterval(carregarNotificacoes, 15000);

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
document.getElementById('btn-sos')?.addEventListener('click', async () => {
  if (!confirm('Ativar alerta SOS?')) return;
  navigator.geolocation.getCurrentPosition(async (pos) => {
    const resp = await fetch('/api/sos.php', { method: 'POST', body: fd({ lat: pos.coords.latitude, lng: pos.coords.longitude }) });
    if (resp.ok) {
      alert('Alerta SOS enviado.');
      if (window.kgRt) window.kgRt.emit('sos:ativado', { lat: pos.coords.latitude, lng: pos.coords.longitude });
    }
  });
});

const PACOTE_LABEL = { diario: 'Diário', semanal: 'Semanal', mensal: 'Mensal', anual: 'Anual' };
const TIPO_SERVICO_LABEL = { urbano: 'Urbano', intermunicipal: 'Intermunicipal', ambos: 'Urbano + Intermunicipal' };

async function carregarPagamento() {
  const [json, veiculosJson] = await Promise.all([
    kgApiJSON('/api/condutor/pagamentos.php'),
    fetch('/api/condutor/veiculos.php').then(r => r.json()),
  ]);
  const card = document.getElementById('card-pagamento');
  const ultimo = (json.pagamentos || [])[0];
  const pacotes = json.pacotes || [];
  const veiculos = veiculosJson.veiculos || [];

  // O status na BD só distingue pendente/aprovado/recusado — "expirado" é
  // um aprovado cuja data_validade já passou (pagamento_valido = false
  // apesar de status === 'aprovado'), por isso é derivado aqui, não vem da API.
  const expirado = !json.pagamento_valido && ultimo && ultimo.status === 'aprovado';

  let html = '';
  if (json.pagamento_valido) {
    html += `<span class="kg-badge kg-badge--confirmado">Pagamento em dia</span> <span class="kg-small">Válido até ${ultimo.data_validade}.</span>`;
  } else if (ultimo && ultimo.status === 'pendente') {
    html += `<span class="kg-badge kg-badge--pendente">Pagamento enviado a aguardar aprovação</span> <span class="kg-small">Enviado em ${ultimo.criado_em}.</span>`;
  } else if (expirado) {
    html += `<span class="kg-badge kg-badge--recusado">Pagamento expirado</span> <span class="kg-small">Expirou em ${ultimo.data_validade}. Escolha um pacote abaixo para renovar.</span>`;
  } else if (ultimo && ultimo.status === 'recusado') {
    html += `<span class="kg-badge kg-badge--recusado">Pagamento recusado</span>`;
  } else {
    html += `<span class="kg-badge kg-badge--recusado">Ainda não tem um pagamento registado</span> <span class="kg-small">Escolha um pacote e envie o comprovativo.</span>`;
  }
  if (ultimo) {
    if (ultimo.status === 'aprovado') {
      html += ` <a class="kg-small" href="/api/condutor/recibo.php?id=${ultimo.id}" target="_blank" style="font-weight:600;">Ver recibo</a>`;
    }
    if (ultimo.status === 'recusado' && ultimo.observacao_admin) {
      html += `<p class="kg-small" style="color:var(--kg-danger);margin-top:6px;">Motivo da recusa: ${ultimo.observacao_admin}. Envie um novo comprovativo.</p>`;
    }
    if (ultimo.comprovativo_path) {
      html += `<p class="kg-small"><a href="/api/condutor/comprovativo.php?id=${ultimo.id}" target="_blank">Ver comprovativo enviado (${PACOTE_LABEL[ultimo.pacote_nome] || ultimo.pacote_nome || '—'})</a></p>`;
    }
  }

  html += `<div style="margin-top:8px;">
    <label class="kg-label">Veículo</label>
    <select class="kg-select" id="sel-veiculo-pagamento"></select>
    <label class="kg-label" style="margin-top:8px;display:block;">Pacote</label>
    <p class="kg-small" id="pacotes-tipo-servico" style="margin:2px 0 4px;"></p>
    <div id="pacotes-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:6px;margin-top:4px;"></div>
    <label class="kg-label" style="margin-top:8px;display:block;">Comprovativo (imagem ou PDF, até 5MB)</label>
    <input class="kg-input" type="file" id="input-comprovativo-pagamento" accept=".jpg,.jpeg,.png,.pdf">
    <button class="kg-btn kg-btn--cta kg-btn--sm kg-btn--full" id="btn-solicitar-pagamento" type="button" style="margin-top:6px;">Solicitar pagamento</button>
  </div>`;
  card.innerHTML = html;

  const selVeiculo = document.getElementById('sel-veiculo-pagamento');
  veiculos.forEach(v => selVeiculo.add(new Option(`${v.matricula}${v.aprovado ? '' : ' (por aprovar)'}`, v.id)));

  // Cada pacote pertence a um tipo de serviço (urbano/intermunicipal/ambos)
  // — só faz sentido mostrar ao condutor os pacotes do mesmo tipo do
  // veículo escolhido, senão ele podia pagar um pacote que não usa.
  function renderPacotesGrid() {
    const veiculo = veiculos.find(v => String(v.id) === String(selVeiculo.value));
    const grid = document.getElementById('pacotes-grid');
    const rotulo = document.getElementById('pacotes-tipo-servico');
    if (!veiculo) {
      grid.innerHTML = '<p class="kg-small">Registe um veículo primeiro.</p>';
      rotulo.textContent = '';
      return;
    }
    rotulo.textContent = `Pacotes disponíveis para o tipo de serviço deste veículo: ${TIPO_SERVICO_LABEL[veiculo.tipo_servico] || veiculo.tipo_servico}.`;
    const pacotesVeiculo = pacotes.filter(p => p.tipo_servico === veiculo.tipo_servico);
    grid.innerHTML = pacotesVeiculo.map(p => `
      <label class="kg-card" style="cursor:pointer;text-align:center;padding:8px;margin:0;">
        <input type="radio" name="pacote-pagamento" value="${p.id}" style="display:block;margin:0 auto 4px;">
        <strong style="font-size:0.85em;">${PACOTE_LABEL[p.nome] || p.nome}</strong>
        <div class="kg-small">${p.preco} CVE</div>
        <div class="kg-small">${p.duracao_dias}d</div>
      </label>`).join('') || '<p class="kg-small">Nenhum pacote disponível para este tipo de serviço — contacte o administrador.</p>';
  }
  selVeiculo.addEventListener('change', renderPacotesGrid);
  renderPacotesGrid();

  document.getElementById('btn-solicitar-pagamento').addEventListener('click', async () => {
    const veiculoId = selVeiculo.value;
    if (!veiculoId) { alert('Registe um veículo primeiro.'); return; }
    const pacoteId = document.querySelector('input[name="pacote-pagamento"]:checked')?.value;
    if (!pacoteId) { alert('Escolha um pacote.'); return; }
    const ficheiro = document.getElementById('input-comprovativo-pagamento').files[0];
    if (!ficheiro) { alert('Anexe o comprovativo (imagem ou PDF).'); return; }
    const dados = fd({ acao: 'solicitar', veiculo_id: veiculoId, pacote_id: pacoteId });
    dados.set('comprovativo', ficheiro);
    const resp = await fetch('/api/condutor/pagamentos.php', { method: 'POST', body: dados });
    const json2 = await resp.json();
    if (!resp.ok) { alert(json2.erro); return; }
    alert(`Pedido enviado (ref. ${json2.referencia}, ${json2.valor} CVE). Aguarde a aprovação do administrador.`);
    carregarPagamento();
  });
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
  mapa = KGMap.create('map', { zoom: 13 });
  KGMap.ligarPausaDeAnimacoes(mapa);

  // Segue a posição do condutor automaticamente (relatório "GPS a
  // aparecer fora do mapa") — só para de seguir se o próprio condutor
  // arrastar o mapa; o botão 📍 retoma. Ver o mesmo mecanismo no painel
  // do passageiro para a explicação completa.
  let aSeguirCondutor = true;
  mapa.onDragStart(() => { aSeguirCondutor = false; });
  document.getElementById('btn-recentrar')?.addEventListener('click', () => {
    aSeguirCondutor = true;
    if (ultimaLatCondutor != null && ultimaLngCondutor != null) mapa.flyTo(ultimaLatCondutor, ultimaLngCondutor, 15);
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

  carregarPontosDropdowns().then(carregarMeusVeiculos);

  // Independente de ter (ou não) um veículo selecionado — um condutor
  // aprovado e em dia vê sempre a procura urbana em aberto (relatório
  // "condutores fora do ponto veem passageiros urbanos").
  carregarPassageirosUrbanos();
  setInterval(carregarPassageirosUrbanos, 10000);

  // Geolocalização: pedida em paralelo com o resto do arranque acima —
  // assim que houver uma posição válida, centra o mapa, cria o marcador
  // 'eu' e só então começa a seguir/enviar a posição do condutor.
  kgEnsureGeolocation((pos) => {
    console.log('V-MILLION GPS: posição inicial obtida, precisão =', pos.coords.accuracy, 'm');
    mapa.setUserPosition(pos.coords.latitude, pos.coords.longitude);
    mapa.flyTo(pos.coords.latitude, pos.coords.longitude, 15);

    watchId = kgWatchPosition(async (lat, lng, pos) => {
      console.log('V-MILLION GPS: atualização, precisão =', pos.coords.accuracy, 'm');
      mapa.updateMarker('eu', lat, lng);
      if (aSeguirCondutor) mapa.panTo(lat, lng);
      if (!veiculoAtivoId) return;
      const idMeuVeiculo = `v-${veiculoAtivoId}`;
      const estadoConhecido = meuVeiculoMarcadoEstado || veiculoAtivoData?.estado || 'no_ponto';

      // Enquanto o veículo tem ponto definido e ainda não "Iniciou rota"
      // (no_ponto/na_fila/chegou_destino), o marcador fica ancorado nas
      // coordenadas do ponto — o GPS do telemóvel do condutor não pode
      // arrastá-lo para fora do ponto (relatório "carro fora do ponto").
      // Veículos sem ponto (urbano a circular livremente) seguem o GPS como
      // sempre, sem qualquer mudança de comportamento.
      const temPontoDefinido = !!veiculoAtivoData?.ponto_partida_id;
      const emRota = !temPontoDefinido || estadoConhecido === 'partiu_da_fila' || estadoConhecido === 'em_movimento';
      const veicLat = emRota ? lat : parseFloat(veiculoAtivoData.lat ?? lat);
      const veicLng = emRota ? lng : parseFloat(veiculoAtivoData.lng ?? lng);

      if (meuVeiculoMarcadoId !== idMeuVeiculo) {
        if (meuVeiculoMarcadoId) mapa.removeMarker(meuVeiculoMarcadoId);
        mapa.addMarker(idMeuVeiculo, 'veiculo', veicLat, veicLng, {
          classes: KGMap.veiculoEstadoClasse(estadoConhecido),
          html: veiculoAtivoData ? KGMap.veiculoIconeHtml(veiculoAtivoData, { comSeta: true }) : '',
          popupHtml: veiculoAtivoData ? KGMap.veiculoPopupHtml(veiculoAtivoData) : 'O meu veículo',
        });
        meuVeiculoMarcadoId = idMeuVeiculo;
        meuVeiculoMarcadoEstado = estadoConhecido;
      } else {
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
      // a reportar posição depois de "Iniciar rota" (posicao.php recusa-o
      // de qualquer forma, mas evitamos o pedido desnecessário).
      if (!emRota) return;

      // Atalhos e desvios (relatório "rota dinâmica"): o condutor pode
      // cortar caminho, mas nunca ultrapassar o alvo — e se sair da rota
      // traçada, ela deve recalcular a partir da posição atual em vez de
      // esperar pelo próximo ciclo de 30s.
      if (navegacaoAlvo) {
        const distAlvo = KGMap.distanciaMetros(lat, lng, navegacaoAlvo.lat, navegacaoAlvo.lng);
        const agora = Date.now();
        if (navMenorDistancia === null || distAlvo < navMenorDistancia) {
          navMenorDistancia = distAlvo;
        } else if (navMenorDistancia < 500 && distAlvo > navMenorDistancia + 150 && !navUltrapassouAvisado) {
          // Já esteve perto do alvo e agora está a afastar-se — ultrapassou.
          navUltrapassouAvisado = true;
          alert('Ultrapassou o destino. Por favor, verifique o percurso.');
          atualizarNavegacao();
          navUltimoRecalculo = agora;
        } else if (navUltimaDistancia !== null && distAlvo > navUltimaDistancia + 80 && agora - navUltimoRecalculo > 8000) {
          // Salto brusco de distância entre dois sinais GPS — saiu da rota
          // traçada (atalho ou desvio); recalcula já, sem esperar o timer.
          atualizarNavegacao();
          navUltimoRecalculo = agora;
        }
        navUltimaDistancia = distAlvo;
      }

      const resp = await fetch('/api/condutor/posicao.php', { method: 'POST', body: fd({ veiculo_id: veiculoAtivoId, lat, lng, accuracy: pos.coords.accuracy }) });
      const json = await resp.json();
      // O estado real (ex: partiu_da_fila -> em_movimento) só é confirmado
      // pelo servidor — se mudou, recria o marcador para refletir a cor/estado
      // correta (posição já está certa, só falta o estilo).
      if (json.estado && json.estado !== meuVeiculoMarcadoEstado) {
        meuVeiculoMarcadoEstado = json.estado;
        if (veiculoAtivoData) veiculoAtivoData.estado = json.estado;
        mapa.removeMarker(idMeuVeiculo);
        mapa.addMarker(idMeuVeiculo, 'veiculo', lat, lng, {
          classes: KGMap.veiculoEstadoClasse(json.estado),
          html: veiculoAtivoData ? KGMap.veiculoIconeHtml(veiculoAtivoData, { comSeta: true }) : '',
          popupHtml: veiculoAtivoData ? KGMap.veiculoPopupHtml(veiculoAtivoData) : 'O meu veículo',
        });
      }
      if (json.chegou_destino) {
        pararNavegacao();
        alert('Chegou ao destino! Todos os passageiros foram marcados como descidos. Ponto de partida e destino foram invertidos.');
        carregarMeusVeiculos();
        const entrarFila = confirm('Deseja entrar na fila no novo ponto? (Cancelar = ficar livre no ponto)');
        if (entrarFila) document.getElementById('btn-entrar-fila').click();
      }
      if (window.kgRt) window.kgRt.emit('veiculo:posicao', { veiculoId: veiculoAtivoId, lat, lng });
    });
  });
} else {
  carregarMeusVeiculos();
}
</script>
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
