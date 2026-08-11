<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

if (kg_admin_autenticado()) {
    header('Location: /admin/painel.php');
    exit;
}

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
<title>Acesso administrativo — V-MILLION</title>
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<style>
  body { background: var(--kg-gradient-header); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--sp-6); }
  .kg-auth-card { max-width: 380px; width: 100%; border-top: 5px solid var(--kg-cta); }
  .kg-logo { text-align: center; font-weight: 800; font-size: 1.5rem; color: var(--kg-primary); margin-bottom: var(--sp-4); display: flex; align-items: center; justify-content: center; gap: var(--sp-2); }
  .kg-logo-badge { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: var(--radius-sm); background: var(--kg-cta); font-size: 1.1rem; }
</style>
</head>
<body>
<div class="kg-card kg-auth-card">
  <div class="kg-logo"><span class="kg-logo-badge">🚌</span>V-MILLION Admin</div>
  <form id="form-login-admin" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <div class="kg-field">
      <label class="kg-label" for="email">Email</label>
      <input class="kg-input" type="email" id="email" name="email" required autocomplete="username">
    </div>
    <div class="kg-field">
      <label class="kg-label" for="senha">Senha</label>
      <input class="kg-input" type="password" id="senha" name="senha" required autocomplete="current-password">
    </div>
    <div id="msg-geral" class="kg-erro-msg" style="margin-bottom: var(--sp-3);"></div>
    <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Entrar</button>
  </form>
</div>
<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
document.getElementById('form-login-admin').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const msg = document.getElementById('msg-geral');
  msg.textContent = '';
  const resp = await fetch('/api/auth/login_admin.php', { method: 'POST', body: new FormData(ev.target) });
  const json = await resp.json();
  if (!resp.ok) { msg.textContent = json.erro || 'Credenciais inválidas.'; return; }
  window.location.href = json.redirect;
});
</script>
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
