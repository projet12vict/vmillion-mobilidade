{{--
    Troca obrigatória da senha temporária, antes da primeira entrada no
    painel. Mantém o cartão compacto com o acento dourado do /admin/login —
    é a mesma porta, o mesmo desenho.
--}}
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#003893">
<meta name="robots" content="noindex, nofollow">
<title>Trocar senha — V-MILLION Admin</title>
@fonts
@vite(['resources/css/app.css'])
</head>
<body class="relative isolate flex min-h-dvh flex-col items-center justify-center overflow-hidden bg-brand-900 px-4 py-10 font-sans text-slate-700 antialiased">

<div class="absolute inset-0 -z-10 bg-linear-135 from-brand-950 via-brand-800 to-brand-700" aria-hidden="true"></div>
<div class="grelha-mapa absolute inset-0 -z-10" aria-hidden="true"></div>

<div class="w-full max-w-md rounded-3xl border-t-4 border-gold-500 bg-white p-6 shadow-2xl shadow-brand-950/30 sm:p-8">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900">Defina uma nova senha</h1>
  <p class="mt-2 text-sm leading-relaxed text-slate-600">
    Esta é a sua primeira sessão — por segurança, tem de definir uma nova senha antes de continuar.
  </p>

  <form id="form-nova-senha" class="mt-6">
    @csrf
    <x-campo-senha nome="nova_senha" id="nova-senha" etiqueta="Nova senha" autocomplete="new-password" minlength="8" :erro="false" />
    <x-campo-senha nome="nova_senha_confirmation" id="confirmar-senha" etiqueta="Confirmar senha" autocomplete="new-password" minlength="8" :erro="false" />
    <div id="msg" class="msg-geral"></div>
    <button type="submit" class="botao-principal">Guardar e continuar</button>
  </form>
</div>

<script src="@kgAsset('assets/js/vm-senha.js')"></script>
<script nonce="{{ $cspNonce }}">
document.getElementById('form-nova-senha').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const dados = new FormData(ev.target);
  if (dados.get('nova_senha') !== dados.get('nova_senha_confirmation')) {
    document.getElementById('msg').textContent = 'As senhas não coincidem.';
    return;
  }
  const resp = await fetch('/api/admin/trocar_senha', { method: 'POST', body: dados });
  const json = await resp.json();
  if (!resp.ok) { document.getElementById('msg').textContent = json.erro; return; }
  window.location.href = '/admin/painel';
});
</script>
</body>
</html>
