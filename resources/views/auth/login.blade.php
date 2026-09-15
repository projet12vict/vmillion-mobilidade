@php
    $contexto = match ($tipo) {
        'condutor' => 'Entra na tua conta de condutor para publicar rotas e receber reservas.',
        'passageiro' => 'Entra na tua conta de passageiro para reservares o teu lugar.',
        default => 'Entra na tua conta para continuares a tua viagem.',
    };
@endphp

<x-layout-auth titulo="Iniciar sessão" titulo-pagina="Iniciar sessão" :subtitulo="$contexto">

  @if ($mensagemSuspenso)
    <p class="msg-geral">{{ $mensagemSuspenso }}</p>
  @endif

  <form id="form-login" novalidate>
    @csrf

    {{--
        O que segue no pedido continua a chamar-se 'identificador' (o
        LoginController ainda distingue telefone de email pelo '@'), mas o
        formulário só oferece telefone — o mesmo campo com seletor de
        indicativo que existe no registo.
    --}}
    <x-campo-telefone nome="identificador" id="identificador" autofocus />

    <x-campo-senha nome="senha" id="senha" autocomplete="current-password" />

    <p id="msg-geral" class="msg-geral" role="alert" aria-live="polite"></p>

    <button type="submit" class="botao-principal">Entrar</button>
  </form>

  <x-slot:rodape>
    Ainda não tens conta?
    <a href="{{ $linkRegisto }}" class="font-semibold text-brand-800 underline-offset-4 transition hover:text-brand-600 hover:underline">Criar conta</a>
  </x-slot:rodape>

  <x-slot:scripts>
    <script src="@kgAsset('assets/js/vm-telefone.js')"></script>
    <script src="@kgAsset('assets/js/vm-senha.js')"></script>
    <script nonce="{{ $cspNonce }}">
    const REGEX_TELEFONE = /^\+238[ ]?[0-9]{7}$/;

    const form = document.getElementById('form-login');
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const msg = document.getElementById('msg-geral');
      msg.textContent = '';
      document.querySelector('[data-erro="identificador"]').textContent = '';

      const dados = new FormData(form);

      // O mesmo pré-teste do registo: sem ele, um número de outro país só
      // dava 'Credenciais inválidas.' depois de ir ao servidor.
      if (!REGEX_TELEFONE.test(String(dados.get('identificador') || '').trim())) {
        document.querySelector('[data-erro="identificador"]').textContent =
          'De momento só aceitamos números de Cabo Verde: +238 9912345.';
        return;
      }

      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'A entrar...';

      try {
        const resp = await fetch('/api/auth/login', { method: 'POST', body: dados });
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
  </x-slot:scripts>
</x-layout-auth>
