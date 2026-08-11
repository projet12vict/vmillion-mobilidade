<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (kg_utilizador_autenticado()) {
    $tipo = kg_utilizador_autenticado()['tipo'];
    header('Location: ' . ($tipo === 'condutor' ? '/condutor/painel.php' : '/passageiro/painel.php'));
    exit;
}

$csrf = kg_csrf_token();
$mensagemSuspenso = isset($_GET['suspenso']) ? 'A sua conta foi suspensa enquanto estava com sessão iniciada. Contacte o administrador.' : '';
$tipo = ($_GET['tipo'] ?? '') === 'condutor' ? 'condutor' : (($_GET['tipo'] ?? '') === 'passageiro' ? 'passageiro' : '');
$linkRegisto = $tipo ? '/registo.php?tipo=' . $tipo : '/registo.php';
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
<title>Iniciar sessão — V-MILLION</title>
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<style>
  body { background: var(--kg-gradient-header); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--sp-6); }
  .kg-auth-card { max-width: 400px; width: 100%; position: relative; }
  .kg-auth-close { position: absolute; top: var(--sp-3); right: var(--sp-3); width: 32px; height: 32px; min-height: 0; padding: 0; border-radius: 50%; font-size: 1.125rem; line-height: 1; display: flex; align-items: center; justify-content: center; }
  .kg-logo { text-align: center; font-weight: 800; font-size: 1.5rem; color: var(--kg-primary); margin-bottom: var(--sp-2); }
  .kg-auth-msg { text-align: center; margin-bottom: var(--sp-5); color: var(--kg-text-secondary); }
</style>
</head>
<body>
<div class="kg-card kg-auth-card">
  <a href="/index.php" class="kg-btn kg-btn--ghost kg-auth-close" id="authClose" aria-label="Fechar e voltar à página inicial">&#10005;</a>
  <div class="kg-logo">V-MILLION</div>
  <p class="kg-auth-msg">Inicia sessão na tua conta</p>
  <?php if ($mensagemSuspenso): ?>
    <p class="kg-erro-msg" style="text-align:center; margin-bottom: var(--sp-4);"><?= htmlspecialchars($mensagemSuspenso, ENT_QUOTES) ?></p>
  <?php endif; ?>

  <form id="form-login" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <div class="kg-field">
      <label class="kg-label" for="identificador">Telefone ou email</label>
      <input class="kg-input" type="text" id="identificador" name="identificador" required autocomplete="username">
    </div>

    <div class="kg-field">
      <label class="kg-label" for="senha">Senha</label>
      <input class="kg-input" type="password" id="senha" name="senha" required autocomplete="current-password">
    </div>

    <div id="msg-geral" class="kg-erro-msg" style="margin-bottom: var(--sp-3);"></div>

    <button type="submit" class="kg-btn kg-btn--primario kg-btn--full">Entrar</button>
  </form>

  <p class="kg-small" style="text-align:center; margin-top: var(--sp-4);">
    Ainda não tens conta? <a href="<?= htmlspecialchars($linkRegisto, ENT_QUOTES) ?>" style="color: var(--kg-primary); font-weight:600;">Criar conta</a>
  </p>
</div>

<script nonce="<?= htmlspecialchars(kg_csp_nonce(), ENT_QUOTES) ?>">
const form = document.getElementById('form-login');
form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const msg = document.getElementById('msg-geral');
  msg.textContent = '';

  const dados = new FormData(form);
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'A entrar...';

  try {
    const resp = await fetch('/api/auth/login.php', { method: 'POST', body: dados });
    const json = await resp.json();
    if (!resp.ok) {
      msg.textContent = json.erro || 'Credenciais inválidas.';
      return;
    }
    window.location.href = json.redirect;
  } catch (e) {
    msg.textContent = 'Falha de ligação. Tente novamente.';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});
</script>
<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
