{{--
    Acesso administrativo.

    Ao contrário do /login e do /registo, este ecrã não usa o <x-layout-auth>:
    aquele shell é o funil público (painel com as vantagens para passageiros,
    ligação para criar conta) e nada disso faz sentido para um administrador.
    Mantém-se o cartão compacto com o acento dourado do desenho anterior — é
    o sinal visual de que esta é outra porta — mas já com o tema Tailwind.
--}}
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
<title>Acesso administrativo — V-MILLION</title>
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="relative isolate flex min-h-dvh flex-col items-center justify-center overflow-hidden bg-brand-900 px-4 py-10 font-sans text-slate-700 antialiased">

<div class="absolute inset-0 -z-10 bg-linear-135 from-brand-950 via-brand-800 to-brand-700" aria-hidden="true"></div>
<div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>
<div class="absolute -left-32 -top-24 -z-10 h-96 w-96 rounded-full bg-accent-500/15 blur-3xl" aria-hidden="true"></div>
<div class="absolute -bottom-32 -right-24 -z-10 h-96 w-96 rounded-full bg-gold-500/10 blur-3xl" aria-hidden="true"></div>

<main class="w-full max-w-sm">
  <div class="flex items-center justify-center gap-2.5 text-white">
    <x-logo caixa="h-10 w-10" class="shadow-lg shadow-brand-950/25" />
    <span class="text-xl font-bold tracking-tight">V-MILLION</span>
    <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-gold-300 ring-1 ring-white/20">Admin</span>
  </div>

  <div class="mt-8 overflow-hidden rounded-3xl bg-white shadow-2xl shadow-brand-950/40">
    <div class="h-1.5 bg-gold-500" aria-hidden="true"></div>

    <div class="p-6 sm:p-8">
      <h1 class="text-xl font-bold text-slate-900">Acesso administrativo</h1>
      <p class="mt-1.5 mb-6 flex items-center gap-1.5 text-sm text-slate-500">
        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        </svg>
        Área reservada à administração.
      </p>

      <form id="form-login-admin" novalidate>
        @csrf

        <div class="campo">
          <label class="campo-etiqueta" for="email">Email</label>
          <input class="campo-input" type="email" id="email" name="email" required autocomplete="username" autofocus>
        </div>

        <x-campo-senha nome="senha" autocomplete="current-password" />

        <p id="msg-geral" class="msg-geral" role="alert" aria-live="polite"></p>

        <button type="submit" class="botao-principal">Entrar</button>
      </form>
    </div>
  </div>

  <p class="mt-6 text-center text-sm">
    <a href="/" class="text-blue-200/70 underline-offset-4 transition hover:text-white hover:underline">Voltar à página inicial</a>
  </p>
</main>

<script src="@kgAsset('assets/js/vm-senha.js')"></script>
<script nonce="{{ $cspNonce }}">
const form = document.getElementById('form-login-admin');
form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const msg = document.getElementById('msg-geral');
  msg.textContent = '';

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'A entrar...';

  try {
    const resp = await fetch('/api/auth/login_admin', { method: 'POST', body: new FormData(form) });
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
<script src="@kgAsset('assets/js/kg-pwa.js')"></script>
</body>
</html>
