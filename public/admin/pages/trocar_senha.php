<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/page_guard.php';

$admin = kg_pagina_exigir_admin();
$csrf = kg_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trocar senha — V-MILLION Admin</title>
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<style>
  body { background: var(--kg-gradient-header); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--sp-6); }
  .kg-auth-card { max-width: 400px; width: 100%; border-top: 5px solid var(--kg-cta); }
</style>
</head>
<body>
<div class="kg-card kg-auth-card">
  <h3 class="kg-h3">Defina uma nova senha</h3>
  <p class="kg-small">Esta é a sua primeira sessão — por segurança, tem de definir uma nova senha antes de continuar.</p>
  <form id="form-nova-senha">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <div class="kg-field"><label class="kg-label">Nova senha</label><input class="kg-input" type="password" name="nova_senha" required minlength="8"></div>
    <div class="kg-field"><label class="kg-label">Confirmar senha</label><input class="kg-input" type="password" name="confirmar_senha" required minlength="8"></div>
    <div id="msg" class="kg-erro-msg"></div>
    <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Guardar e continuar</button>
  </form>
</div>
<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
document.getElementById('form-nova-senha').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  if (dados.get('nova_senha') !== dados.get('confirmar_senha')) {
    document.getElementById('msg').textContent = 'As senhas não coincidem.';
    return;
  }
  const resp = await fetch('/api/admin/trocar_senha.php', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('msg').textContent = json.erro; return; }
  window.location.href = '/admin/painel.php';
});
</script>
</body>
</html>
