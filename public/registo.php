<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$tipoInicial = ($_GET['tipo'] ?? '') === 'condutor' ? 'condutor' : 'passageiro';
$csrf = kg_csrf_token();
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
<title>Criar conta — V-MILLION</title>
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<style>
  body { background: var(--kg-gradient-header); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--sp-6); }
  .kg-auth-card { max-width: 440px; width: 100%; position: relative; }
  .kg-auth-close { position: absolute; top: var(--sp-3); right: var(--sp-3); width: 32px; height: 32px; min-height: 0; padding: 0; border-radius: 50%; font-size: 1.125rem; line-height: 1; display: flex; align-items: center; justify-content: center; }
  .kg-tipo-toggle { display: flex; gap: var(--sp-2); margin-bottom: var(--sp-5); background: var(--kg-bg); border-radius: var(--radius-md); padding: 4px; }
  .kg-tipo-toggle button { flex: 1; border: none; background: transparent; padding: var(--sp-3); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; color: var(--kg-text-secondary); }
  .kg-tipo-toggle button.ativo { background: var(--kg-white); color: var(--kg-primary); box-shadow: var(--shadow-sm); }
  .kg-logo { text-align: center; font-weight: 800; font-size: 1.5rem; color: var(--kg-primary); margin-bottom: var(--sp-2); }
  .kg-auth-msg { text-align: center; margin-bottom: var(--sp-4); color: var(--kg-text-secondary); }
</style>
</head>
<body>
<div class="kg-card kg-auth-card">
  <a href="/index.php" class="kg-btn kg-btn--ghost kg-auth-close" id="authClose" aria-label="Fechar e voltar à página inicial">&#10005;</a>
  <div class="kg-logo">V-MILLION</div>
  <p class="kg-auth-msg">Criar a tua conta</p>

  <div class="kg-tipo-toggle">
    <button type="button" data-tipo="passageiro" id="btn-tipo-passageiro">Passageiro</button>
    <button type="button" data-tipo="condutor" id="btn-tipo-condutor">Condutor</button>
  </div>

  <form id="form-registo" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <input type="hidden" name="tipo" id="input-tipo" value="<?= htmlspecialchars($tipoInicial, ENT_QUOTES) ?>">

    <div class="kg-field">
      <label class="kg-label" for="nome">Nome completo</label>
      <input class="kg-input" type="text" id="nome" name="nome" required minlength="3" autocomplete="name">
      <span class="kg-erro-msg" data-erro="nome"></span>
    </div>

    <div class="kg-field">
      <label class="kg-label" for="telefone">Telefone</label>
      <input class="kg-input" type="tel" id="telefone" name="telefone" placeholder="+238 9912345" required autocomplete="tel">
      <span class="kg-erro-msg" data-erro="telefone"></span>
    </div>

    <div class="kg-field">
      <label class="kg-label" for="nif">NIF (9 dígitos)</label>
      <input class="kg-input" type="text" id="nif" name="nif" required pattern="[0-9]{9}" maxlength="9" inputmode="numeric">
      <span class="kg-erro-msg" data-erro="nif"></span>
    </div>

    <div class="kg-field">
      <label class="kg-label" for="senha">Senha</label>
      <input class="kg-input" type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password">
      <span class="kg-erro-msg" data-erro="senha"></span>
    </div>

    <div class="kg-field">
      <label class="kg-label" for="confirmar_senha">Confirmar senha</label>
      <input class="kg-input" type="password" id="confirmar_senha" name="confirmar_senha" required minlength="8" autocomplete="new-password">
      <span class="kg-erro-msg" data-erro="confirmar_senha"></span>
    </div>

    <div id="msg-geral" class="kg-erro-msg" style="margin-bottom: var(--sp-3);"></div>

    <button type="submit" class="kg-btn kg-btn--cta kg-btn--full">Criar conta</button>
  </form>

  <p class="kg-small" style="text-align:center; margin-top: var(--sp-4);">
    Já tens conta? <a href="/login.php" style="color: var(--kg-primary); font-weight:600;">Iniciar sessão</a>
  </p>
</div>

<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
const inputTipo = document.getElementById('input-tipo');
const btnPassageiro = document.getElementById('btn-tipo-passageiro');
const btnCondutor = document.getElementById('btn-tipo-condutor');

function atualizarTipoUI(tipo) {
  inputTipo.value = tipo;
  btnPassageiro.classList.toggle('ativo', tipo === 'passageiro');
  btnCondutor.classList.toggle('ativo', tipo === 'condutor');
}
btnPassageiro.addEventListener('click', () => atualizarTipoUI('passageiro'));
btnCondutor.addEventListener('click', () => atualizarTipoUI('condutor'));
atualizarTipoUI(inputTipo.value);

const REGEX_TELEFONE = /^\+238[ ]?[0-9]{7}$/;
const REGEX_NIF = /^[0-9]{9}$/;

const form = document.getElementById('form-registo');
form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  document.querySelectorAll('.kg-erro-msg').forEach(el => el.textContent = '');
  document.getElementById('msg-geral').textContent = '';

  const dados = new FormData(form);
  const telefone = String(dados.get('telefone') || '').trim();
  const nif = String(dados.get('nif') || '').trim();
  const senha = String(dados.get('senha') || '');
  const confirmar = String(dados.get('confirmar_senha') || '');

  let valido = true;
  if (!REGEX_TELEFONE.test(telefone)) {
    document.querySelector('[data-erro="telefone"]').textContent = 'Formato esperado: +238 9912345';
    valido = false;
  }
  if (!REGEX_NIF.test(nif)) {
    document.querySelector('[data-erro="nif"]').textContent = 'O NIF deve ter exatamente 9 dígitos.';
    valido = false;
  }
  if (senha.length < 8) {
    document.querySelector('[data-erro="senha"]').textContent = 'Mínimo 8 caracteres.';
    valido = false;
  }
  if (senha !== confirmar) {
    document.querySelector('[data-erro="confirmar_senha"]').textContent = 'As senhas não coincidem.';
    valido = false;
  }
  if (!valido) return;

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'A criar conta...';

  try {
    const resp = await fetch('/api/auth/registar.php', { method: 'POST', body: dados });
    const json = await resp.json();
    if (!resp.ok) {
      if (json.campos) {
        for (const [campo, msg] of Object.entries(json.campos)) {
          const el = document.querySelector(`[data-erro="${campo}"]`);
          if (el) el.textContent = msg;
        }
      }
      document.getElementById('msg-geral').textContent = json.erro || 'Ocorreu um erro.';
      return;
    }
    document.getElementById('msg-geral').style.color = 'var(--kg-success)';
    document.getElementById('msg-geral').textContent = json.mensagem;
    setTimeout(() => { window.location.href = '/login.php'; }, 1500);
  } catch (e) {
    document.getElementById('msg-geral').textContent = 'Falha de ligação. Tente novamente.';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Criar conta';
  }
});
</script>
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
