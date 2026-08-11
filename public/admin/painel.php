<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/page_guard.php';

$admin = kg_pagina_exigir_admin();
if (!empty($admin['senha_temporaria'])) {
    // Recarrega da BD para garantir o valor atual (a sessão pode estar desatualizada).
}
$pdo = kg_db();
$stmt = $pdo->prepare("SELECT senha_temporaria FROM administradores WHERE id = ?");
$stmt->execute([$admin['id']]);
if ((bool) $stmt->fetchColumn()) {
    header('Location: /admin/pages/trocar_senha.php');
    exit;
}

$csrf = kg_csrf_token();
$ehSuper = $admin['nivel'] === 'super';
?>
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003893">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="V-MILLION">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="icon" href="/assets/icons/icon-192.png">
<title>Painel Admin — V-MILLION</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<link rel="stylesheet" href="/assets/css/kg-map-markers.css">
<style>
  html, body { height: 100%; margin: 0; overflow: hidden; }
  .kg-a-layout { display: grid; grid-template-columns: 260px 1fr; grid-template-rows: 64px 1fr; height: 100vh; overflow: hidden; }
  .kg-a-sidebar {
    grid-row: 1 / -1; grid-column: 1;
    background: var(--kg-gradient-header); color: #fff; padding: var(--sp-5) var(--sp-3); overflow-y: auto;
    height: 100vh; z-index: 60;
  }
  .kg-a-brand { display: flex; align-items: center; gap: var(--sp-2); font-weight: 800; font-size: 1.1rem; margin-bottom: var(--sp-5); }
  .kg-a-brand-badge {
    display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px;
    border-radius: var(--radius-sm); background: var(--kg-cta); color: var(--kg-text); font-size: 1rem;
  }
  .kg-a-sidebar h4 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.6; margin: var(--sp-5) var(--sp-2) var(--sp-2); color: #fff; }
  .kg-a-sidebar h4:first-child { margin-top: 0; }
  .kg-a-nav-item {
    display: flex; align-items: center; gap: var(--sp-2); padding: 10px 12px; border-radius: var(--radius-sm); color: #fff; text-decoration: none;
    font-size: 0.9rem; font-weight: 600; cursor: pointer; opacity: 0.85;
  }
  .kg-a-nav-item:hover { background: rgba(255,255,255,0.12); opacity: 1; }
  .kg-a-nav-item.ativo { background: #fff; color: var(--kg-primary); opacity: 1; box-shadow: var(--shadow-sm); }
  .kg-a-nav-icon { font-size: 1.05rem; width: 22px; text-align: center; }
  .kg-a-topbar {
    grid-column: 2; grid-row: 1; height: 64px; background: #fff; border-bottom: 1px solid var(--kg-border);
    display: flex; align-items: center; justify-content: space-between; padding: 0 var(--sp-5);
    z-index: 40; box-shadow: var(--shadow-sm);
  }
  .kg-a-content { grid-column: 2; grid-row: 2; padding: var(--sp-6); overflow-y: auto; min-width: 0; min-height: 0; }
  .kg-a-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--sp-4); margin-bottom: var(--sp-6); }
  .kg-a-stat-card { display: flex; align-items: center; gap: var(--sp-3); border-left: 4px solid var(--kg-accent); }
  .kg-a-stat-icon {
    display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex: none;
    border-radius: var(--radius-md); font-size: 1.4rem; background: var(--kg-bg);
  }
  .kg-a-stat-num { font-size: 1.9rem; font-weight: 800; color: var(--kg-text); line-height: 1.1; }
  .kg-a-table-wrap { width: 100%; overflow-x: auto; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
  table.kg-table { width: 100%; min-width: 560px; border-collapse: collapse; background: #fff; }
  table.kg-table th, table.kg-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--kg-border); font-size: 0.875rem; white-space: nowrap; }
  table.kg-table th { background: var(--kg-bg); font-weight: 700; position: sticky; top: 0; }
  table.kg-table tbody tr:hover { background: var(--kg-bg); }
  .kg-a-section { display: none; }
  .kg-a-section.ativa { display: block; }
  #admin-map { height: 420px; border-radius: var(--radius-lg); overflow: hidden; position: relative; margin-bottom: var(--sp-4); }
  .kg-a-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 55; display: none; }
  .kg-a-overlay.aberta { display: block; }
  @media (min-width: 801px) {
    #btn-toggle-sidebar { display: none; }
  }
  @media (max-width: 800px) {
    .kg-a-layout { grid-template-columns: 1fr; }
    .kg-a-sidebar {
      position: fixed; inset: 0 20% 0 0; max-width: 300px; height: 100vh;
      transform: translateX(-100%); transition: transform 0.2s ease; display: block;
    }
    .kg-a-sidebar.aberta { transform: translateX(0); }
    .kg-a-topbar { grid-column: 1; grid-row: 1; }
    .kg-a-content { grid-column: 1; grid-row: 2; }
    table.kg-table { min-width: 480px; }
  }
</style>
</head>
<body>
<div class="kg-a-overlay" id="sidebar-overlay"></div>
<div class="kg-a-layout">
  <aside class="kg-a-sidebar" id="sidebar">
    <div class="kg-a-brand"><span class="kg-a-brand-badge">🚌</span> V-MILLION Admin</div>

    <h4>Operação</h4>
    <a class="kg-a-nav-item ativo" data-secao="dashboard"><span class="kg-a-nav-icon">📊</span><span class="kg-a-nav-label">Dashboard</span></a>
    <a class="kg-a-nav-item" data-secao="utilizadores"><span class="kg-a-nav-icon">👤</span><span class="kg-a-nav-label">Utilizadores</span></a>
    <a class="kg-a-nav-item" data-secao="veiculos"><span class="kg-a-nav-icon">🚐</span><span class="kg-a-nav-label">Veículos</span></a>
    <a class="kg-a-nav-item" data-secao="condutores"><span class="kg-a-nav-icon">🪪</span><span class="kg-a-nav-label">Condutores</span></a>
    <a class="kg-a-nav-item" data-secao="proprietarios"><span class="kg-a-nav-icon">🏢</span><span class="kg-a-nav-label">Proprietários</span></a>

    <h4>Infraestrutura</h4>
    <a class="kg-a-nav-item" data-secao="pontos"><span class="kg-a-nav-icon">📍</span><span class="kg-a-nav-label">Pontos de partida</span></a>
    <a class="kg-a-nav-item" data-secao="parques"><span class="kg-a-nav-icon">🅿️</span><span class="kg-a-nav-label">Parques de estacionamento</span></a>
    <a class="kg-a-nav-item" data-secao="editor-mapa"><span class="kg-a-nav-icon">🗺️</span><span class="kg-a-nav-label">Editor de mapa</span></a>

    <h4>Financeiro</h4>
    <a class="kg-a-nav-item" data-secao="pagamentos"><span class="kg-a-nav-icon">🧾</span><span class="kg-a-nav-label">Pagamentos (taxa por rota)</span></a>
    <?php if ($ehSuper): ?><a class="kg-a-nav-item" data-secao="precos"><span class="kg-a-nav-icon">💵</span><span class="kg-a-nav-label">Preços por rota</span></a><?php endif; ?>

    <h4>Sistema</h4>
    <a class="kg-a-nav-item" data-secao="sugestoes"><span class="kg-a-nav-icon">💬</span><span class="kg-a-nav-label"><?= $ehSuper ? 'Sugestões e reclamações' : 'Reclamações' ?></span></a>
    <?php if ($ehSuper): ?><a class="kg-a-nav-item" data-secao="notificacoes"><span class="kg-a-nav-icon">📢</span><span class="kg-a-nav-label">Comunicar</span></a><?php endif; ?>
    <?php if ($ehSuper): ?><a class="kg-a-nav-item" data-secao="administradores"><span class="kg-a-nav-icon">🛡️</span><span class="kg-a-nav-label">Administradores</span></a><?php endif; ?>
    <a class="kg-a-nav-item" data-secao="logs"><span class="kg-a-nav-icon">📜</span><span class="kg-a-nav-label">Logs de auditoria</span></a>
    <a class="kg-a-nav-item" data-secao="sos"><span class="kg-a-nav-icon">🚨</span><span class="kg-a-nav-label">Central de alarmes (SOS)</span></a>
  </aside>

  <header class="kg-a-topbar">
    <div class="kg-flex kg-items-center kg-gap-2">
      <button class="kg-btn kg-btn--ghost kg-btn--sm" id="btn-toggle-sidebar" aria-label="Abrir menu">☰</button>
      <strong id="titulo-secao">Dashboard</strong>
    </div>
    <div class="kg-flex kg-items-center kg-gap-2">
      <button class="kg-btn kg-btn--sm kg-btn--ghost" style="position:relative;" id="btn-notificacoes-admin" type="button">🔔<span id="badge-notificacoes-admin" style="display:none; position:absolute; top:-4px; right:-4px; background:var(--kg-danger); color:#fff; border-radius:50%; font-size:0.65rem; padding:1px 5px;"></span></button>
      <span class="kg-small"><?= htmlspecialchars($admin['nome'], ENT_QUOTES) ?> (<?= htmlspecialchars($admin['nivel'], ENT_QUOTES) ?>)</span>
      <a href="/api/auth/logout.php" class="kg-btn kg-btn--sm kg-btn--outline">Sair</a>
    </div>
  </header>

  <main class="kg-a-content">

    <section class="kg-a-section ativa" id="secao-dashboard">
      <div class="kg-a-stats" id="dashboard-stats"></div>
      <div class="kg-card">
        <h3 class="kg-h3">Últimas ações</h3>
        <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Admin</th><th>Ação</th><th>Entidade</th><th>Data</th></tr></thead><tbody id="dashboard-logs"></tbody></table></div>
      </div>
    </section>

    <section class="kg-a-section" id="secao-utilizadores">
      <div class="kg-flex kg-gap-2" style="margin-bottom: var(--sp-4);">
        <button class="kg-btn kg-btn--sm kg-btn--outline" data-filtro-tipo="">Todos</button>
        <button class="kg-btn kg-btn--sm kg-btn--outline" data-filtro-tipo="passageiro">Passageiros</button>
        <button class="kg-btn kg-btn--sm kg-btn--outline" data-filtro-tipo="condutor">Condutores</button>
      </div>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Tipo</th><th>Telefone</th><th>NIF</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-utilizadores"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-veiculos">
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Matrícula</th><th>Tipo</th><th>Condutor</th><th>Estado</th><th>Aprovado</th><th>Ações</th></tr></thead><tbody id="tabela-veiculos"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-condutores">
      <p class="kg-small">Condutores pendentes de aprovação (necessitam de um pagamento aprovado e válido em "Pagamentos" — aprovar o pagamento de um condutor novo já ativa a conta automaticamente; este botão serve para casos manuais).</p>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Telefone</th><th>NIF</th><th>Ações</th></tr></thead><tbody id="tabela-condutores-pendentes"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-proprietarios">
      <p class="kg-small">Um proprietário pode também ser condutor — associe-o a uma conta de condutor existente para refletir isso no sistema.</p>
      <button class="kg-btn kg-btn--cta kg-btn--sm" id="btn-novo-proprietario" style="margin-bottom: var(--sp-4);">+ Novo proprietário</button>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Telefone</th><th>NIF</th><th>Também conduz</th><th>Condutores da frota</th><th>Ações</th></tr></thead><tbody id="tabela-proprietarios"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-pontos">
      <button class="kg-btn kg-btn--cta kg-btn--sm" id="btn-novo-ponto" style="margin-bottom: var(--sp-4);">+ Novo ponto</button>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Cidade</th><th>Zona</th><th>Coordenadas</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tabela-pontos"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-parques">
      <button class="kg-btn kg-btn--cta kg-btn--sm" id="btn-novo-parque" style="margin-bottom: var(--sp-4);">+ Novo parque</button>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Cidade</th><th>Vagas</th><th>Ações</th></tr></thead><tbody id="tabela-parques"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-editor-mapa">
      <p class="kg-small">Clique no mapa para criar um novo ponto de partida nessa localização.</p>
      <div id="admin-map"></div>
    </section>

    <section class="kg-a-section" id="secao-pagamentos">
      <p class="kg-small">Pedidos de pagamento dos condutores (pacote diário/semanal/mensal/anual + comprovativo) — inclui os condutores novos a aguardar a primeira aprovação. Ao aprovar, a validade é calculada a partir da duração do pacote, é emitido automaticamente um recibo em PDF e, se for a primeira aprovação do condutor, a conta é ativada de imediato.</p>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Condutor</th><th>Veículo</th><th>Pacote</th><th>Comprovativo</th><th>Valor</th><th>Estado</th><th>Validade</th><th>Aprovado por</th><th>Ações</th></tr></thead><tbody id="tabela-pagamentos"></tbody></table></div>
    </section>

    <?php if ($ehSuper): ?>
    <section class="kg-a-section" id="secao-precos">
      <div class="kg-card" style="margin-bottom: var(--sp-4);">
        <h3 class="kg-h3">Pacotes de pagamento (acesso do condutor)</h3>
        <p class="kg-small">Preço e duração de cada pacote — o condutor escolhe um destes ao submeter o comprovativo de pagamento.</p>
        <form id="form-novo-pacote" class="kg-flex kg-gap-2" style="flex-wrap:wrap; align-items:flex-end;">
          <div class="kg-field" style="width:140px;"><label class="kg-label">Nome</label><input class="kg-input" type="text" id="pacote-nome" maxlength="20" required></div>
          <div class="kg-field" style="width:170px;">
            <label class="kg-label">Tipo de serviço</label>
            <select class="kg-select" id="pacote-tipo-servico" required>
              <option value="urbano">Urbano</option>
              <option value="intermunicipal">Intermunicipal</option>
              <option value="ambos" selected>Urbano + Intermunicipal</option>
            </select>
          </div>
          <div class="kg-field" style="flex:1;min-width:160px;"><label class="kg-label">Descrição</label><input class="kg-input" type="text" id="pacote-descricao"></div>
          <div class="kg-field" style="width:120px;"><label class="kg-label">Preço (CVE)</label><input class="kg-input" type="number" step="1" min="0" id="pacote-preco" required></div>
          <div class="kg-field" style="width:120px;"><label class="kg-label">Duração (dias)</label><input class="kg-input" type="number" step="1" min="1" id="pacote-duracao" required></div>
          <button class="kg-btn kg-btn--primario kg-btn--sm" type="submit">Criar pacote</button>
        </form>
        <div class="kg-a-table-wrap" style="margin-top: var(--sp-4);"><table class="kg-table"><thead><tr><th>Nome</th><th>Tipo de serviço</th><th>Descrição</th><th>Preço (CVE)</th><th>Duração (dias)</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-pacotes"></tbody></table></div>
      </div>
      <div class="kg-card" style="margin-bottom: var(--sp-4);">
        <h3 class="kg-h3">Preço por km</h3>
        <div id="precos-km"></div>
      </div>
      <div class="kg-card" style="margin-bottom: var(--sp-4);">
        <h3 class="kg-h3">Configurações gerais</h3>
        <div class="kg-grid" id="precos-config" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--sp-3);"></div>
      </div>
      <div class="kg-card" style="margin-bottom: var(--sp-4);">
        <h3 class="kg-h3">Preço fixo por rota</h3>
        <p class="kg-small">A distância é calculada automaticamente e usada para fracionar o preço quando um passageiro embarca/desce a meio da rota.</p>
        <form id="form-nova-rota" class="kg-flex kg-gap-2" style="flex-wrap:wrap; align-items:flex-end;">
          <div class="kg-field" style="flex:1;min-width:160px;"><label class="kg-label">Origem</label><select class="kg-select" id="rota-origem"></select></div>
          <div class="kg-field" style="flex:1;min-width:160px;"><label class="kg-label">Destino</label><select class="kg-select" id="rota-destino"></select></div>
          <div class="kg-field" style="width:140px;"><label class="kg-label">Preço (CVE)</label><input class="kg-input" type="number" step="1" min="0" id="rota-preco" required></div>
          <button class="kg-btn kg-btn--primario kg-btn--sm" type="submit">Definir</button>
        </form>
        <div class="kg-a-table-wrap" style="margin-top: var(--sp-4);"><table class="kg-table"><thead><tr><th>Origem</th><th>Destino</th><th>Distância</th><th>Preço fixo</th><th></th></tr></thead><tbody id="tabela-rotas"></tbody></table></div>
      </div>
      <div class="kg-card">
        <h3 class="kg-h3">Limites de cidade</h3>
        <p class="kg-small">Centro (lat/lng) e raio de cada cidade — define onde termina a tarifa urbana e começa a intermunicipal.</p>
        <button class="kg-btn kg-btn--cta kg-btn--sm" id="btn-novo-limite" style="margin-bottom: var(--sp-4);">+ Novo limite de cidade</button>
        <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Cidade</th><th>Coordenadas</th><th>Raio (km)</th><th>Ações</th></tr></thead><tbody id="tabela-limites-cidades"></tbody></table></div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($ehSuper): ?>
    <section class="kg-a-section" id="secao-administradores">
      <form id="form-novo-admin" class="kg-flex kg-gap-2" style="flex-wrap:wrap; align-items:flex-end; margin-bottom: var(--sp-4);">
        <div class="kg-field"><label class="kg-label">Nome</label><input class="kg-input" name="nome" required></div>
        <div class="kg-field"><label class="kg-label">Email</label><input class="kg-input" type="email" name="email" required></div>
        <div class="kg-field">
          <label class="kg-label">Nível</label>
          <select class="kg-select" name="nivel"><option value="gestor">Gestor</option><option value="admin">Admin</option><option value="super">Super</option></select>
        </div>
        <button class="kg-btn kg-btn--primario kg-btn--sm" type="submit">Criar admin</button>
      </form>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Nome</th><th>Email</th><th>Nível</th><th>Estado</th><th>Ações</th></tr></thead><tbody id="tabela-admins"></tbody></table></div>
    </section>
    <?php endif; ?>

    <?php if ($ehSuper): ?>
    <section class="kg-a-section" id="secao-notificacoes">
      <div class="kg-card">
        <h3 class="kg-h3">Enviar notificação</h3>
        <form id="form-notificacao">
          <div class="kg-field">
            <label class="kg-label">Destinatário</label>
            <select class="kg-select" id="notif-destinatario" name="destinatario_tipo">
              <option value="todos">Todos os utilizadores</option>
              <option value="admins">Administradores</option>
              <option value="condutores">Condutores</option>
              <option value="passageiros">Passageiros</option>
              <option value="individual">Utilizador específico (ID)</option>
            </select>
          </div>
          <div class="kg-field" id="campo-notif-id" style="display:none;">
            <label class="kg-label">ID do utilizador</label>
            <input class="kg-input" type="number" name="destinatario_id" id="notif-destinatario-id">
          </div>
          <div class="kg-field">
            <label class="kg-label">Tipo</label>
            <select class="kg-select" name="tipo">
              <option value="informativo">Informativo</option>
              <option value="alerta">Alerta</option>
              <option value="urgente">Urgente (aparece como modal ao entrar)</option>
            </select>
          </div>
          <div class="kg-field"><label class="kg-label">Título</label><input class="kg-input" name="titulo" required></div>
          <div class="kg-field"><label class="kg-label">Mensagem</label><textarea class="kg-input" name="mensagem" rows="4" required></textarea></div>
          <div id="notificacao-msg" class="kg-erro-msg"></div>
          <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Enviar</button>
        </form>
      </div>
    </section>
    <?php endif; ?>

    <section class="kg-a-section" id="secao-sugestoes">
      <p class="kg-small">Sugestões são visíveis apenas ao Super Admin. Reclamações sobre condutores são visíveis a todos os administradores.</p>
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Tipo</th><th>De</th><th>Sobre condutor</th><th>Título</th><th>Estado</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tabela-sugestoes"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-logs">
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Admin</th><th>Ação</th><th>Entidade</th><th>Data</th></tr></thead><tbody id="tabela-logs"></tbody></table></div>
    </section>

    <section class="kg-a-section" id="secao-sos">
      <div class="kg-a-table-wrap"><table class="kg-table"><thead><tr><th>Utilizador</th><th>Tipo</th><th>Localização</th><th>Estado</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tabela-sos"></tbody></table></div>
    </section>

  </main>
</div>

<!-- Modal proprietário -->
<div class="kg-modal-overlay" id="modal-proprietario" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Proprietário</h3>
    <form id="form-proprietario">
      <input type="hidden" name="id" id="proprietario-id">
      <input type="hidden" name="acao" id="proprietario-acao" value="criar">
      <div class="kg-field"><label class="kg-label">Nome</label><input class="kg-input" name="nome" id="proprietario-nome" required></div>
      <div class="kg-field"><label class="kg-label">Telefone</label><input class="kg-input" name="telefone" id="proprietario-telefone" placeholder="+238 9912345" required></div>
      <div class="kg-field"><label class="kg-label">NIF</label><input class="kg-input" name="nif" id="proprietario-nif" maxlength="9" required></div>
      <div class="kg-field">
        <label class="kg-label">Também é condutor (opcional)</label>
        <select class="kg-select" name="utilizador_condutor_id" id="proprietario-condutor"><option value="">Não conduz</option></select>
      </div>
      <div id="proprietario-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2"><button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-proprietario">Cancelar</button><button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal ponto -->
<div class="kg-modal-overlay" id="modal-ponto" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Ponto de partida</h3>
    <form id="form-ponto">
      <input type="hidden" name="id" id="ponto-id">
      <input type="hidden" name="acao" id="ponto-acao" value="criar">
      <div class="kg-field"><label class="kg-label">Nome</label><input class="kg-input" name="nome" id="ponto-nome" required></div>
      <div class="kg-field"><label class="kg-label">Cidade</label><input class="kg-input" name="cidade" id="ponto-cidade" required></div>
      <div class="kg-field"><label class="kg-label">Zona</label><select class="kg-select" name="zona" id="ponto-zona"><option value="urbana">Urbana</option><option value="intermunicipal">Intermunicipal</option></select></div>
      <div class="kg-flex kg-gap-2">
        <div class="kg-field" style="flex:1;"><label class="kg-label">Latitude</label><input class="kg-input" type="number" step="0.000001" name="lat" id="ponto-lat" required></div>
        <div class="kg-field" style="flex:1;"><label class="kg-label">Longitude</label><input class="kg-input" type="number" step="0.000001" name="lng" id="ponto-lng" required></div>
      </div>
      <div id="ponto-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2"><button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-ponto">Cancelar</button><button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal parque -->
<div class="kg-modal-overlay" id="modal-parque" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Parque de estacionamento</h3>
    <form id="form-parque">
      <input type="hidden" name="id" id="parque-id">
      <input type="hidden" name="acao" id="parque-acao" value="criar">
      <div class="kg-field"><label class="kg-label">Nome</label><input class="kg-input" name="nome" id="parque-nome" required></div>
      <div class="kg-field"><label class="kg-label">Morada</label><input class="kg-input" name="morada" id="parque-morada" required></div>
      <div class="kg-field"><label class="kg-label">Cidade</label><input class="kg-input" name="cidade" id="parque-cidade" required></div>
      <div class="kg-flex kg-gap-2">
        <div class="kg-field" style="flex:1;"><label class="kg-label">Latitude</label><input class="kg-input" type="number" step="0.000001" name="lat" id="parque-lat" required></div>
        <div class="kg-field" style="flex:1;"><label class="kg-label">Longitude</label><input class="kg-input" type="number" step="0.000001" name="lng" id="parque-lng" required></div>
      </div>
      <div class="kg-flex kg-gap-2">
        <div class="kg-field" style="flex:1;"><label class="kg-label">Capacidade total</label><input class="kg-input" type="number" min="1" name="capacidade_total" id="parque-capacidade" required></div>
        <div class="kg-field" style="flex:1;"><label class="kg-label">Vagas ocupadas</label><input class="kg-input" type="number" min="0" name="vagas_ocupadas" id="parque-ocupadas" value="0" required></div>
      </div>
      <div id="parque-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2"><button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-parque">Cancelar</button><button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal limite de cidade -->
<div class="kg-modal-overlay" id="modal-limite-cidade" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Limite de cidade</h3>
    <form id="form-limite-cidade">
      <input type="hidden" name="id" id="limite-id">
      <input type="hidden" name="acao" id="limite-acao" value="criar">
      <div class="kg-field"><label class="kg-label">Cidade</label><input class="kg-input" name="cidade" id="limite-cidade" required></div>
      <div class="kg-flex kg-gap-2">
        <div class="kg-field" style="flex:1;"><label class="kg-label">Latitude (centro)</label><input class="kg-input" type="number" step="0.000001" name="lat" id="limite-lat" required></div>
        <div class="kg-field" style="flex:1;"><label class="kg-label">Longitude (centro)</label><input class="kg-input" type="number" step="0.000001" name="lng" id="limite-lng" required></div>
      </div>
      <div class="kg-field"><label class="kg-label">Raio (km)</label><input class="kg-input" type="number" step="0.1" min="0.1" name="raio_km" id="limite-raio" required></div>
      <div id="limite-msg" class="kg-erro-msg"></div>
      <div class="kg-flex kg-gap-2"><button type="button" class="kg-btn kg-btn--ghost" id="btn-fechar-limite">Cancelar</button><button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal senha temporária -->
<div class="kg-modal-overlay" id="modal-senha-admin" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">Admin criado com sucesso</h3>
    <p class="kg-small">Entregue a senha temporária abaixo ao novo administrador por um canal seguro. Só é mostrada uma vez.</p>
    <div class="kg-field">
      <label class="kg-label">Senha temporária</label>
      <input class="kg-input" id="senha-admin-valor" readonly style="font-weight:700; letter-spacing:0.05em;">
    </div>
    <div class="kg-flex kg-gap-2">
      <button type="button" class="kg-btn kg-btn--outline kg-btn--full" id="btn-copiar-senha-admin">Copiar</button>
      <button type="button" class="kg-btn kg-btn--primario kg-btn--full" id="btn-fechar-senha-admin">Concluído</button>
    </div>
  </div>
</div>

<!-- Modal notificações do admin -->
<div class="kg-modal-overlay" id="modal-notificacoes-admin" style="display:none;">
  <div class="kg-modal">
    <h3 class="kg-h3">As minhas notificações</h3>
    <div id="lista-notificacoes-admin" style="max-height:60vh; overflow-y:auto;"></div>
    <button type="button" class="kg-btn kg-btn--ghost kg-btn--full" id="btn-fechar-notificacoes-admin" style="margin-top:var(--sp-4);">Fechar</button>
  </div>
</div>

<!-- Toasts -->
<div id="toast-container" style="position:fixed; top: var(--sp-5); right: var(--sp-5); z-index:1100; display:flex; flex-direction:column; gap: var(--sp-2);"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/kg-map.js"></script>
<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const EH_SUPER = <?= json_encode($ehSuper) ?>;
const TIPO_SERVICO_LABEL = { urbano: 'Urbano', intermunicipal: 'Intermunicipal', ambos: 'Urbano + Intermunicipal' };
let mapaEditor = null;
let pontosCache = [];

function fd(extra) {
  const d = new FormData();
  d.set('csrf_token', CSRF_TOKEN);
  Object.entries(extra || {}).forEach(([k, v]) => d.set(k, v));
  return d;
}

// Toasts
function toast(mensagem, tipo) {
  const el = document.createElement('div');
  el.className = 'kg-toast' + (tipo === 'erro' ? ' kg-toast--erro' : tipo === 'sucesso' ? ' kg-toast--sucesso' : '');
  el.style.position = 'static';
  el.textContent = mensagem;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4500);
}

// Sidebar mobile (hamburger)
const sidebarEl = document.getElementById('sidebar');
const sidebarOverlayEl = document.getElementById('sidebar-overlay');
function abrirSidebar() {
  sidebarEl.classList.add('aberta');
  sidebarOverlayEl.classList.add('aberta');
}
function fecharSidebar() {
  sidebarEl.classList.remove('aberta');
  sidebarOverlayEl.classList.remove('aberta');
}
document.getElementById('btn-toggle-sidebar').addEventListener('click', () => {
  if (sidebarEl.classList.contains('aberta')) fecharSidebar(); else abrirSidebar();
});
sidebarOverlayEl.addEventListener('click', fecharSidebar);

// Navegação
document.querySelectorAll('.kg-a-nav-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.kg-a-nav-item').forEach(i => i.classList.remove('ativo'));
    item.classList.add('ativo');
    document.querySelectorAll('.kg-a-section').forEach(s => s.classList.remove('ativa'));
    const secao = document.getElementById('secao-' + item.dataset.secao);
    if (secao) secao.classList.add('ativa');
    document.getElementById('titulo-secao').textContent = item.querySelector('.kg-a-nav-label').textContent;
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
    window.location.href = '/admin/login.php';
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
  const json = await kgApiJSON('/api/admin/dashboard.php');
  const container = document.getElementById('dashboard-stats');
  const meta = {
    passageiros: { label: 'Passageiros', icone: '👤', cor: 'var(--kg-primary)' },
    condutores: { label: 'Condutores ativos', icone: '🚐', cor: 'var(--kg-accent)' },
    condutores_pendentes: { label: 'Condutores pendentes', icone: '🪪', cor: 'var(--kg-warning)' },
    veiculos: { label: 'Veículos aprovados', icone: '🚌', cor: 'var(--kg-success)' },
    veiculos_pendentes: { label: 'Veículos pendentes', icone: '🚐', cor: 'var(--kg-warning)' },
    pontos: { label: 'Pontos aprovados', icone: '📍', cor: 'var(--kg-primary)' },
    pontos_pendentes: { label: 'Pontos pendentes', icone: '📍', cor: 'var(--kg-warning)' },
    parques: { label: 'Parques', icone: '🅿️', cor: 'var(--kg-secondary)' },
    pagamentos_pendentes: { label: 'Pagamentos pendentes', icone: '💰', cor: 'var(--kg-warning)' },
    faturas_pendentes: { label: 'Faturas pendentes', icone: '🧾', cor: 'var(--kg-warning)' },
    faturas_vencidas: { label: 'Faturas vencidas', icone: '⚠️', cor: 'var(--kg-danger)' },
    administradores_ativos: { label: 'Admins ativos', icone: '🛡️', cor: 'var(--kg-primary)' },
    sos_pendentes: { label: 'SOS pendentes', icone: '🚨', cor: 'var(--kg-danger)' },
  };
  container.innerHTML = Object.entries(json.stats).map(([k, v]) => {
    const m = meta[k] || { label: k, icone: '📌', cor: 'var(--kg-primary)' };
    return `<div class="kg-card kg-a-stat-card" style="border-left-color:${m.cor};">
      <div class="kg-a-stat-icon" style="color:${m.cor};">${m.icone}</div>
      <div><div class="kg-a-stat-num">${v}</div><div class="kg-small">${m.label}</div></div>
    </div>`;
  }).join('');

  document.getElementById('dashboard-logs').innerHTML = json.ultimas_acoes.map(l =>
    `<tr><td>${l.admin_nome || '—'}</td><td>${l.acao}</td><td>${l.entidade || ''} ${l.entidade_id || ''}</td><td>${l.criado_em}</td></tr>`
  ).join('') || '<tr><td colspan="4" class="kg-small">Sem registos.</td></tr>';
}

async function carregarUtilizadores(tipo) {
  const json = await kgApiJSON('/api/admin/utilizadores.php' + (tipo ? `?tipo=${tipo}` : ''));
  document.getElementById('tabela-utilizadores').innerHTML = json.utilizadores.map(u => `
    <tr>
      <td>${u.nome}</td><td>${u.tipo}</td><td>${u.telefone}</td><td>${u.nif}</td>
      <td><span class="kg-badge kg-badge--${u.status === 'ativo' ? 'confirmado' : u.status === 'pendente' ? 'pendente' : 'recusado'}">${u.status}</span></td>
      <td>
        ${u.status === 'ativo' ? `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-suspender-id="${u.id}">Suspender</button>` : `<button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="reativar" data-id="${u.id}">Reativar</button>`}
        <button class="kg-btn kg-btn--sm kg-btn--ghost" data-acao="eliminar" data-id="${u.id}">Eliminar</button>
      </td>
    </tr>`).join('');
  // "Suspender" pede sempre o motivo (fica no registo de auditoria) — por
  // isso usa um botão à parte, fora do ligarAcoesTabela genérico.
  document.querySelectorAll('#tabela-utilizadores [data-suspender-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const motivo = prompt('Motivo da suspensão (fica registado em auditoria):');
      if (motivo === null) return;
      const resp = await fetch('/api/admin/utilizadores.php', { method: 'POST', body: fd({ acao: 'suspender', id: btn.dataset.suspenderId, motivo }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast('Utilizador suspenso.', 'sucesso');
      carregarUtilizadores(tipo);
    });
  });
  ligarAcoesTabela('tabela-utilizadores', '/api/admin/utilizadores.php', () => carregarUtilizadores(tipo));
}
document.querySelectorAll('[data-filtro-tipo]').forEach(btn => btn.addEventListener('click', () => carregarUtilizadores(btn.dataset.filtroTipo)));

async function carregarVeiculos() {
  const json = await kgApiJSON('/api/admin/veiculos.php');
  document.getElementById('tabela-veiculos').innerHTML = json.veiculos.map(v => `
    <tr>
      <td>${v.matricula}</td><td>${v.tipo}</td><td>${v.condutor_nome}</td><td>${v.estado}</td>
      <td>${v.aprovado ? 'Sim' : 'Não'}</td>
      <td>
        ${!v.aprovado ? `<button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="aprovar" data-id="${v.id}">Aprovar</button>` : `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="rejeitar" data-id="${v.id}">Revogar</button>`}
      </td>
    </tr>`).join('');
  ligarAcoesTabela('tabela-veiculos', '/api/admin/veiculos.php', carregarVeiculos);
}

async function carregarCondutoresPendentes() {
  const json = await kgApiJSON('/api/admin/utilizadores.php?tipo=condutor');
  const pendentes = json.utilizadores.filter(u => u.status === 'pendente');
  document.getElementById('tabela-condutores-pendentes').innerHTML = pendentes.map(u => `
    <tr><td>${u.nome}</td><td>${u.telefone}</td><td>${u.nif}</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="aprovar_condutor" data-id="${u.id}">Aprovar</button></td></tr>`
  ).join('') || '<tr><td colspan="4" class="kg-small">Sem condutores pendentes.</td></tr>';
  ligarAcoesTabela('tabela-condutores-pendentes', '/api/admin/utilizadores.php', carregarCondutoresPendentes);
}

function ligarAcoesTabela(tabelaId, endpoint, recarregar) {
  document.querySelectorAll(`#${tabelaId} [data-acao]`).forEach(btn => {
    btn.addEventListener('click', async () => {
      const resp = await fetch(endpoint, { method: 'POST', body: fd({ acao: btn.dataset.acao, id: btn.dataset.id }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      toast('Ação concluída.', 'sucesso');
      recarregar();
    });
  });
}

async function carregarProprietarios() {
  const json = await kgApiJSON('/api/admin/proprietarios.php');
  document.getElementById('tabela-proprietarios').innerHTML = (json.proprietarios || []).map(p => `
    <tr><td>${p.nome}</td><td>${p.telefone}</td><td>${p.nif}</td>
    <td>${p.condutor_nome ? `Sim — ${p.condutor_nome}` : 'Não'}</td>
    <td>${p.total_condutores}</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--outline" data-editar='${JSON.stringify(p)}'>Editar</button>
        <button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="eliminar" data-id="${p.id}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="6" class="kg-small">Sem proprietários registados.</td></tr>';
  document.querySelectorAll('#tabela-proprietarios [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalProprietario(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-proprietarios', '/api/admin/proprietarios.php', carregarProprietarios);
}

async function abrirModalProprietario(p) {
  document.getElementById('proprietario-id').value = p?.id || '';
  document.getElementById('proprietario-acao').value = p ? 'editar' : 'criar';
  document.getElementById('proprietario-nome').value = p?.nome || '';
  document.getElementById('proprietario-telefone').value = p?.telefone || '';
  document.getElementById('proprietario-nif').value = p?.nif || '';

  const selCondutor = document.getElementById('proprietario-condutor');
  const json = await kgApiJSON('/api/admin/utilizadores.php?tipo=condutor');
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/proprietarios.php', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('proprietario-msg').textContent = json.erro; return; }
  document.getElementById('modal-proprietario').style.display = 'none';
  toast('Proprietário guardado.', 'sucesso');
  carregarProprietarios();
});

function badgePontoStatus(status) {
  const classe = status === 'aprovado' ? 'confirmado' : status === 'pendente' ? 'pendente' : 'recusado';
  return `<span class="kg-badge kg-badge--${classe}">${status}</span>`;
}

// Botões de aprovação: pendente tem Aprovar+Recusar; recusado pode voltar a
// ser aprovado; aprovado só tem Recusar (equivalente a despublicar).
function acoesPontoStatus(p) {
  if (p.status === 'pendente') {
    return `<button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="aprovar" data-id="${p.id}">Aprovar</button>
            <button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="recusar" data-id="${p.id}">Recusar</button>`;
  }
  if (p.status === 'recusado') {
    return `<button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="aprovar" data-id="${p.id}">Aprovar</button>`;
  }
  return `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="recusar" data-id="${p.id}">Recusar</button>`;
}

// Ponto único de carregamento: alimenta a tabela (secção "Pontos") e os
// marcadores do editor de mapa (secção "Editor de mapa") a partir dos
// mesmos dados, para nunca ficarem dessincronizados (tarefa 5 do relatório).
async function carregarPontos() {
  const json = await kgApiJSON('/api/admin/pontos.php');
  pontosCache = json.pontos;
  document.getElementById('tabela-pontos').innerHTML = json.pontos.map(p => `
    <tr><td>${p.nome}</td><td>${p.cidade}</td><td>${p.zona}</td><td>${parseFloat(p.lat).toFixed(4)}, ${parseFloat(p.lng).toFixed(4)}</td>
    <td>${badgePontoStatus(p.status)}</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--outline" data-editar='${JSON.stringify(p)}'>Editar</button>
        ${acoesPontoStatus(p)}</td></tr>`
  ).join('') || '<tr><td colspan="6" class="kg-small">Sem pontos de partida registados.</td></tr>';
  document.querySelectorAll('#tabela-pontos [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalPonto(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-pontos', '/api/admin/pontos.php', carregarPontos);
  atualizarMarcadoresPontosEditor();
}

let pontoEditorMarkerIds = new Set();

// Popup do marcador no editor: nome, cidade, coordenadas, status e ações
// (Aprovar/Recusar, "Ativar arrasto", "Zoom" e "Eliminar") — tarefas 2 e 3.
// Usa data-ponto-acao (nunca onclick="...") porque o CSP do site não permite
// atributos de evento inline (script-src sem 'unsafe-inline') — ver o
// listener delegado registado uma única vez logo a seguir.
function popupPontoEditor(p) {
  return `<div class="kg-ponto-popup">
    <strong>${p.nome}</strong><br>
    ${p.cidade} · ${p.zona}<br>
    <span data-coords>${parseFloat(p.lat).toFixed(6)}, ${parseFloat(p.lng).toFixed(6)}</span><br>
    ${badgePontoStatus(p.status)}
    <div class="kg-flex kg-gap-2" style="margin-top:8px; flex-wrap:wrap;">
      ${p.status !== 'aprovado' ? `<button class="kg-btn kg-btn--sm kg-btn--cta" data-ponto-acao="aprovar" data-id="${p.id}">Aprovar</button>` : ''}
      ${p.status !== 'recusado' ? `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-ponto-acao="recusar" data-id="${p.id}">Recusar</button>` : ''}
      <button class="kg-btn kg-btn--sm kg-btn--outline" data-ponto-acao="arrastar" data-id="${p.id}">Ativar arrasto</button>
      <button class="kg-btn kg-btn--sm kg-btn--outline" data-ponto-acao="zoom" data-id="${p.id}">Zoom</button>
      ${EH_SUPER ? `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-ponto-acao="eliminar" data-id="${p.id}">Eliminar</button>` : ''}
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
    const resp = await fetch('/api/admin/pontos.php', { method: 'POST', body: fd({ acao: 'eliminar_definitivo', id }) });
    const json = await resp.json();
    if (!resp.ok) { toast(json.erro || 'Não foi possível eliminar o ponto.', 'erro'); return; }
    toast('Ponto eliminado.', 'sucesso');
    carregarPontos();
    return;
  }
  // aprovar / recusar — espelha os botões da tabela.
  const resp = await fetch('/api/admin/pontos.php', { method: 'POST', body: fd({ acao, id }) });
  const json = await resp.json();
  if (!resp.ok) { toast(json.erro || 'Não foi possível concluir a ação.', 'erro'); return; }
  toast(acao === 'aprovar' ? 'Ponto aprovado.' : 'Ponto recusado.', 'sucesso');
  carregarPontos();
}

async function salvarPosicaoPontoMapa(id, lat, lng) {
  const resp = await fetch('/api/admin/pontos.php', {
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
  pontosCache.forEach(p => {
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/pontos.php', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('ponto-msg').textContent = json.erro; return; }
  document.getElementById('modal-ponto').style.display = 'none';
  toast(criando ? 'Ponto criado — fica pendente até ser aprovado.' : 'Ponto guardado.', 'sucesso');
  carregarPontos();
});

async function carregarParques() {
  const json = await kgApiJSON('/api/admin/parques.php');
  document.getElementById('tabela-parques').innerHTML = json.parques.map(p => `
    <tr><td>${p.nome}</td><td>${p.cidade}</td><td>${p.vagas_ocupadas}/${p.capacidade_total}</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--outline" data-editar='${JSON.stringify(p)}'>Editar</button>
        <button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="eliminar" data-id="${p.id}">Eliminar</button></td></tr>`
  ).join('');
  document.querySelectorAll('#tabela-parques [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalParque(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-parques', '/api/admin/parques.php', carregarParques);
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/parques.php', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('parque-msg').textContent = json.erro; return; }
  document.getElementById('modal-parque').style.display = 'none';
  toast('Parque guardado.', 'sucesso');
  carregarParques();
});

function iniciarEditorMapa() {
  if (mapaEditor) { carregarPontos(); return; }
  // Zoom inicial alto (15) e maxZoom alargado (18) para ajuste preciso de
  // coordenadas — o mapa público fica em 15 (carga gráfica reduzida), mas o
  // editor do admin tem poucos marcadores e beneficia de mais detalhe.
  mapaEditor = KGMap.create('admin-map', { center: { lat: 14.9177, lng: -23.5092 }, zoom: 15, maxZoom: 18 });
  mapaEditor.onClick((lat, lng) => {
    abrirModalPonto(null);
    document.getElementById('ponto-lat').value = lat.toFixed(6);
    document.getElementById('ponto-lng').value = lng.toFixed(6);
  });
  // Busca sempre os pontos aqui (não depende de já se ter visitado a secção
  // "Pontos" antes) — tarefa 2 do relatório.
  carregarPontos();
}

const notifDestinatario = document.getElementById('notif-destinatario');
if (notifDestinatario) notifDestinatario.addEventListener('change', (ev) => {
  document.getElementById('campo-notif-id').style.display = ev.target.value === 'individual' ? 'block' : 'none';
});
const formNotificacao = document.getElementById('form-notificacao');
if (formNotificacao) formNotificacao.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('csrf_token', CSRF_TOKEN);
  dados.set('acao', 'enviar');
  const resp = await fetch('/api/admin/notificacoes.php', { method: 'POST', body: dados });
  const json = await resp.json();
  const msg = document.getElementById('notificacao-msg');
  if (!resp.ok) { msg.textContent = json.erro || 'Erro ao enviar.'; return; }
  msg.style.color = 'var(--kg-success)';
  msg.textContent = `Enviada para ${json.total_destinatarios} destinatário(s).`;
  ev.target.reset();
  toast('Notificação enviada.', 'sucesso');
});

async function carregarSugestoes() {
  const json = await kgApiJSON('/api/admin/sugestoes.php');
  const estadoBadge = { pendente: 'recusado', visto: 'pendente', implementado: 'confirmado', resolvido: 'confirmado' };
  document.getElementById('tabela-sugestoes').innerHTML = (json.sugestoes || []).map(s => `
    <tr>
      <td>${s.tipo === 'sugestao' ? 'Sugestão' : 'Reclamação'}</td>
      <td>${s.utilizador_nome} (${s.utilizador_tipo})</td>
      <td>${s.condutor_nome || '—'}</td>
      <td title="${s.descricao.replace(/"/g, '&quot;')}">${s.titulo}</td>
      <td><span class="kg-badge kg-badge--${estadoBadge[s.status]}">${s.status}</span></td>
      <td>${s.criado_em}</td>
      <td>
        <select class="kg-select" style="display:inline-block;width:auto;" data-sug-status="${s.id}">
          ${['pendente', 'visto', 'implementado', 'resolvido'].map(v => `<option value="${v}" ${v === s.status ? 'selected' : ''}>${v}</option>`).join('')}
        </select>
      </td>
    </tr>`
  ).join('') || '<tr><td colspan="7" class="kg-small">Sem registos.</td></tr>';

  document.querySelectorAll('[data-sug-status]').forEach(sel => {
    sel.addEventListener('change', async () => {
      await fetch('/api/admin/sugestoes.php', { method: 'POST', body: fd({ acao: 'atualizar_status', id: sel.dataset.sugStatus, status: sel.value }) });
      toast('Estado atualizado.', 'sucesso');
    });
  });
}

async function carregarPagamentos() {
  const json = await kgApiJSON('/api/admin/pagamentos.php');
  document.getElementById('tabela-pagamentos').innerHTML = (json.pagamentos || []).map(p => `
    <tr>
      <td>${p.condutor_nome}${p.condutor_status === 'pendente' ? ' <span class="kg-badge kg-badge--pendente" title="Aprovar este pagamento também ativa a conta">conta nova</span>' : ''}</td><td>${p.matricula}</td>
      <td>${p.pacote_nome ? `${p.pacote_nome} (${p.pacote_duracao_dias}d) &mdash; ${TIPO_SERVICO_LABEL[p.pacote_tipo_servico] || p.pacote_tipo_servico}` : (p.origem_nome ? `${p.origem_nome} &rarr; ${p.destino_nome}` : '—')}</td>
      <td>${p.comprovativo_path ? `<a class="kg-btn kg-btn--sm kg-btn--outline" href="/api/admin/comprovativo.php?id=${p.id}" target="_blank">Ver ${p.comprovativo_tipo === 'pdf' ? 'PDF' : 'foto'}</a>` : '—'}</td>
      <td>${p.valor_pago} CVE</td>
      <td><span class="kg-badge kg-badge--${p.status === 'aprovado' ? 'confirmado' : p.status === 'pendente' ? 'pendente' : 'recusado'}">${p.status}</span></td>
      <td>${p.data_validade || '—'}</td>
      <td>${p.aprovado_por_nome || '—'}</td>
      <td>${p.status === 'pendente' ? `
        <button class="kg-btn kg-btn--sm kg-btn--cta" data-pag-acao="aprovar" data-id="${p.id}">Aprovar</button>
        <button class="kg-btn kg-btn--sm kg-btn--perigo" data-pag-acao="recusar" data-id="${p.id}">Recusar</button>`
        : (p.recibo_path ? `<a class="kg-btn kg-btn--sm kg-btn--outline" href="/api/admin/recibo.php?id=${p.id}" target="_blank">Ver recibo</a>` : '')}
        ${p.status === 'aprovado' && EH_SUPER ? `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-pag-acao="reverter" data-id="${p.id}">Reverter</button>` : ''}
      </td>
    </tr>`
  ).join('') || '<tr><td colspan="9" class="kg-small">Sem pedidos de pagamento.</td></tr>';

  document.querySelectorAll('[data-pag-acao]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (btn.dataset.pagAcao === 'reverter' && !confirm('Reverter esta aprovação? O condutor perde acesso ao mapa imediatamente até um novo pagamento ser aprovado.')) return;
      const resp = await fetch('/api/admin/pagamentos.php', { method: 'POST', body: fd({ acao: btn.dataset.pagAcao, id: btn.dataset.id }) });
      const json = await resp.json();
      if (!resp.ok) { toast(json.erro, 'erro'); return; }
      const msgs = { aprovar: 'Pagamento aprovado e recibo emitido.', recusar: 'Pagamento recusado.', reverter: 'Aprovação revertida.' };
      toast(msgs[btn.dataset.pagAcao] || 'Ação concluída.', 'sucesso');
      carregarPagamentos();
    });
  });
}

async function carregarPrecos() {
  const json = await kgApiJSON('/api/admin/precos.php');

  const tipoServicoSelect = (valorAtual) => Object.entries(TIPO_SERVICO_LABEL).map(([v, label]) =>
    `<option value="${v}" ${v === valorAtual ? 'selected' : ''}>${label}</option>`).join('');

  document.getElementById('tabela-pacotes').innerHTML = (json.pacotes || []).map(p => `
    <tr data-pacote-id="${p.id}">
      <td>${p.nome}</td>
      <td><select class="kg-select" data-pacote-campo="tipo_servico" style="min-width:150px;">${tipoServicoSelect(p.tipo_servico)}</select></td>
      <td><input class="kg-input" type="text" value="${p.descricao ?? ''}" data-pacote-campo="descricao" style="min-width:140px;"></td>
      <td><input class="kg-input" type="number" step="1" min="0" value="${p.preco}" data-pacote-campo="preco" style="width:100px;"></td>
      <td><input class="kg-input" type="number" step="1" min="1" value="${p.duracao_dias}" data-pacote-campo="duracao_dias" style="width:90px;"></td>
      <td><span class="kg-badge kg-badge--${p.ativo == 1 ? 'confirmado' : 'recusado'}">${p.ativo == 1 ? 'Ativo' : 'Inativo'}</span></td>
      <td class="kg-flex kg-gap-2">
        <button class="kg-btn kg-btn--sm kg-btn--primario" data-guardar-pacote="${p.id}">Guardar</button>
        <button class="kg-btn kg-btn--sm ${p.ativo == 1 ? 'kg-btn--perigo' : ''}" data-toggle-pacote="${p.id}" data-ativo="${p.ativo}">${p.ativo == 1 ? 'Desativar' : 'Ativar'}</button>
      </td>
    </tr>`
  ).join('') || '<tr><td colspan="7" class="kg-small">Sem pacotes definidos.</td></tr>';

  document.querySelectorAll('[data-guardar-pacote]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const linha = btn.closest('tr');
      const campo = c => linha.querySelector(`[data-pacote-campo="${c}"]`).value;
      const ativoAtual = linha.querySelector('[data-toggle-pacote]').dataset.ativo;
      const resp = await fetch('/api/admin/precos.php', { method: 'POST', body: fd({
        acao: 'guardar_pacote', id: btn.dataset.guardarPacote, ativo: ativoAtual,
        tipo_servico: campo('tipo_servico'), descricao: campo('descricao'), preco: campo('preco'), duracao_dias: campo('duracao_dias'),
      }) });
      const json2 = await resp.json();
      if (!resp.ok) { toast(json2.erro, 'erro'); return; }
      toast('Pacote atualizado.', 'sucesso');
      carregarPrecos();
    });
  });
  document.querySelectorAll('[data-toggle-pacote]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const ativo = btn.dataset.ativo === '1';
      if (ativo) {
        await fetch('/api/admin/precos.php', { method: 'POST', body: fd({ acao: 'remover_pacote', id: btn.dataset.togglePacote }) });
      } else {
        const linha = btn.closest('tr');
        const campo = c => linha.querySelector(`[data-pacote-campo="${c}"]`).value;
        await fetch('/api/admin/precos.php', { method: 'POST', body: fd({
          acao: 'guardar_pacote', id: btn.dataset.togglePacote, ativo: 1,
          tipo_servico: campo('tipo_servico'), descricao: campo('descricao'), preco: campo('preco'), duracao_dias: campo('duracao_dias'),
        }) });
      }
      toast(ativo ? 'Pacote desativado.' : 'Pacote ativado.', 'sucesso');
      carregarPrecos();
    });
  });

  document.getElementById('precos-km').innerHTML = ['urbana', 'intermunicipal'].map(zona => {
    const atual = json.precos_km.find(p => p.zona === zona);
    return `<div class="kg-field"><label class="kg-label">${zona === 'urbana' ? 'Dentro de cidade' : 'Fora de cidade'} (CVE/km)</label>
      <div class="kg-flex kg-gap-2"><input class="kg-input" type="number" step="0.5" min="0" value="${atual ? atual.preco_por_km_cve : 0}" data-zona="${zona}">
      <button class="kg-btn kg-btn--primario kg-btn--sm" data-salvar-km="${zona}">Guardar</button></div></div>`;
  }).join('');

  document.querySelectorAll('[data-salvar-km]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const zona = btn.dataset.salvarKm;
      const valor = document.querySelector(`[data-zona="${zona}"]`).value;
      await fetch('/api/admin/precos.php', { method: 'POST', body: fd({ acao: 'atualizar_km', zona, preco_por_km_cve: valor }) });
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
    <div class="kg-field"><label class="kg-label">${label}</label>
      <div class="kg-flex kg-gap-2"><input class="kg-input" type="number" step="1" min="0" value="${json.config[chave] ?? 0}" data-config="${chave}">
      <button class="kg-btn kg-btn--primario kg-btn--sm" data-salvar-config="${chave}">Guardar</button></div></div>`
  ).join('');
  document.querySelectorAll('[data-salvar-config]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const chave = btn.dataset.salvarConfig;
      const valor = document.querySelector(`[data-config="${chave}"]`).value;
      await fetch('/api/admin/precos.php', { method: 'POST', body: fd({ acao: 'atualizar_config', chave, valor }) });
      toast('Configuração atualizada.', 'sucesso');
    });
  });

  const selOrigem = document.getElementById('rota-origem');
  const selDestino = document.getElementById('rota-destino');
  selOrigem.innerHTML = '';
  selDestino.innerHTML = '';
  const pontosJson = await kgApiJSON('/api/admin/pontos.php');
  pontosJson.pontos.forEach(p => { selOrigem.add(new Option(p.nome, p.id)); selDestino.add(new Option(p.nome, p.id)); });

  document.getElementById('tabela-rotas').innerHTML = json.precos_rotas.map(r => `
    <tr><td>${r.origem_nome}</td><td>${r.destino_nome}</td><td>${r.distancia_km != null ? r.distancia_km + ' km' : '—'}</td><td>${r.preco_fixo_cve} CVE</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--perigo" data-eliminar-rota="${r.id}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="5" class="kg-small">Sem rotas fixas definidas.</td></tr>';
  document.querySelectorAll('[data-eliminar-rota]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/precos.php', { method: 'POST', body: fd({ acao: 'eliminar_rota', id: btn.dataset.eliminarRota }) });
      toast('Rota eliminada.', 'sucesso');
      carregarPrecos();
    });
  });
}

const formNovoPacote = document.getElementById('form-novo-pacote');
if (formNovoPacote) formNovoPacote.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const resp = await fetch('/api/admin/precos.php', { method: 'POST', body: fd({
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
  const resp = await fetch('/api/admin/precos.php', { method: 'POST', body: fd({
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
  const json = await kgApiJSON('/api/admin/limites_cidades.php');
  document.getElementById('tabela-limites-cidades').innerHTML = json.limites.map(l => `
    <tr><td>${l.cidade}</td><td>${parseFloat(l.lat).toFixed(4)}, ${parseFloat(l.lng).toFixed(4)}</td><td>${l.raio_km}</td>
    <td><button class="kg-btn kg-btn--sm kg-btn--outline" data-editar='${JSON.stringify(l)}'>Editar</button>
        <button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="eliminar" data-id="${l.id}">Eliminar</button></td></tr>`
  ).join('') || '<tr><td colspan="4" class="kg-small">Sem limites de cidade definidos.</td></tr>';
  document.querySelectorAll('#tabela-limites-cidades [data-editar]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalLimite(JSON.parse(btn.dataset.editar)));
  });
  ligarAcoesTabela('tabela-limites-cidades', '/api/admin/limites_cidades.php', carregarLimitesCidades);
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
  dados.set('csrf_token', CSRF_TOKEN);
  const resp = await fetch('/api/admin/limites_cidades.php', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('limite-msg').textContent = json.erro; return; }
  document.getElementById('modal-limite-cidade').style.display = 'none';
  toast('Limite de cidade guardado.', 'sucesso');
  carregarLimitesCidades();
});

async function carregarAdmins() {
  const json = await kgApiJSON('/api/admin/administradores.php');
  document.getElementById('tabela-admins').innerHTML = json.administradores.map(a => `
    <tr><td>${a.nome}</td><td>${a.email}</td><td>${a.nivel}</td>
    <td>${a.ativo ? 'Ativo' : 'Inativo'}${a.senha_temporaria ? ' · senha temporária' : ''}</td>
    <td>${a.ativo ? `<button class="kg-btn kg-btn--sm kg-btn--perigo" data-acao="desativar" data-id="${a.id}">Desativar</button>` : `<button class="kg-btn kg-btn--sm kg-btn--cta" data-acao="reativar" data-id="${a.id}">Reativar</button>`}
    <button class="kg-btn kg-btn--sm kg-btn--ghost" data-acao="eliminar" data-id="${a.id}">Eliminar</button></td></tr>`
  ).join('');
  ligarAcoesTabela('tabela-admins', '/api/admin/administradores.php', carregarAdmins);
}
const formNovoAdmin = document.getElementById('form-novo-admin');
if (formNovoAdmin) formNovoAdmin.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  dados.set('csrf_token', CSRF_TOKEN);
  dados.set('acao', 'criar');
  const resp = await fetch('/api/admin/administradores.php', { method: 'POST', body: dados });
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
  const json = await kgApiJSON('/api/admin/logs.php');
  document.getElementById('tabela-logs').innerHTML = json.logs.map(l => `
    <tr><td>${l.admin_nome || '—'}</td><td>${l.acao}</td><td>${l.entidade || ''} ${l.entidade_id || ''}</td><td>${l.criado_em}</td></tr>`
  ).join('');
}

async function carregarSos() {
  const json = await kgApiJSON('/api/admin/sos.php');
  document.getElementById('tabela-sos').innerHTML = json.alarmes.map(a => `
    <tr><td>${a.utilizador_nome} (${a.telefone})</td><td>${a.tipo_utilizador}</td>
    <td>${parseFloat(a.lat).toFixed(5)}, ${parseFloat(a.lng).toFixed(5)}</td>
    <td><span class="kg-badge kg-badge--${a.estado === 'pendente' ? 'recusado' : a.estado === 'em_curso' ? 'pendente' : 'confirmado'}">${a.estado}</span></td>
    <td>${a.criado_em}</td>
    <td>
      ${a.estado === 'pendente' ? `<button class="kg-btn kg-btn--sm kg-btn--outline" data-sos-estado="em_curso" data-id="${a.id}">Em curso</button>` : ''}
      ${a.estado !== 'resolvido' ? `<button class="kg-btn kg-btn--sm kg-btn--cta" data-sos-estado="resolvido" data-id="${a.id}">Resolvido</button>` : ''}
    </td></tr>`
  ).join('') || '<tr><td colspan="6" class="kg-small">Sem alarmes registados.</td></tr>';

  document.querySelectorAll('[data-sos-estado]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/sos.php', { method: 'POST', body: fd({ id: btn.dataset.id, estado: btn.dataset.sosEstado }) });
      carregarSos();
    });
  });
}

async function carregarNotificacoesAdmin() {
  const json = await kgApiJSON('/api/admin/notificacoes.php');
  const badge = document.getElementById('badge-notificacoes-admin');
  if (json.nao_lidas > 0) { badge.style.display = 'inline'; badge.textContent = json.nao_lidas; } else { badge.style.display = 'none'; }

  document.getElementById('lista-notificacoes-admin').innerHTML = (json.recebidas || []).map(n => `
    <div class="kg-card" style="margin-bottom:8px; ${n.lida ? 'opacity:0.6;' : ''}">
      <div class="kg-flex kg-justify-between kg-items-center"><strong>${n.titulo}</strong><span class="kg-badge kg-badge--${n.tipo === 'urgente' ? 'recusado' : n.tipo === 'alerta' ? 'pendente' : 'confirmado'}">${n.tipo}</span></div>
      <p class="kg-small">${n.mensagem}</p>
      <p class="kg-small" style="opacity:0.6;">${n.criado_em}</p>
      ${!n.lida ? `<button class="kg-btn kg-btn--sm kg-btn--outline" data-marcar-lida-admin="${n.id}">Marcar como lida</button>` : ''}
    </div>`
  ).join('') || '<p class="kg-small">Sem notificações.</p>';

  document.querySelectorAll('[data-marcar-lida-admin]').forEach(btn => {
    btn.addEventListener('click', async () => {
      await fetch('/api/admin/notificacoes.php', { method: 'POST', body: fd({ acao: 'marcar_lida', id: btn.dataset.marcarLidaAdmin }) });
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
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
