<x-layout-auth
  titulo="Criar conta"
  titulo-pagina="Criar conta"
  subtitulo="Menos de um minuto e já podes reservar o teu lugar."
  largura="max-w-lg">

  <div class="mb-6 grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1" role="group" aria-label="Tipo de conta">
    <button type="button" class="tipo-botao" data-tipo="passageiro" id="btn-tipo-passageiro" aria-pressed="false">Passageiro</button>
    <button type="button" class="tipo-botao" data-tipo="condutor" id="btn-tipo-condutor" aria-pressed="false">Condutor</button>
  </div>

  <form id="form-registo" novalidate>
    @csrf
    <input type="hidden" name="tipo" id="input-tipo" value="{{ $tipoInicial }}">

    <div class="campo">
      <label class="campo-etiqueta" for="nome">Nome completo</label>
      <input class="campo-input" type="text" id="nome" name="nome" required minlength="3" autocomplete="name">
      <span class="campo-erro" data-erro="nome"></span>
    </div>

    <x-campo-telefone />

    <div class="campo">
      <label class="campo-etiqueta" for="nif">NIF <span class="font-normal text-slate-400">(9 dígitos)</span></label>
      <input class="campo-input" type="text" id="nif" name="nif" required pattern="[0-9]{9}" maxlength="9" inputmode="numeric">
      <span class="campo-erro" data-erro="nif"></span>
    </div>

    <div class="grid gap-x-4 sm:grid-cols-2">
      <div class="campo">
        <label class="campo-etiqueta" for="ilha">Ilha</label>
        <select class="campo-input" id="ilha" name="ilha" required>
          <option value="" selected disabled>Escolha a ilha</option>
          @foreach (array_keys($municipios) as $ilha)
            <option value="{{ $ilha }}">{{ $ilha }}</option>
          @endforeach
        </select>
        <span class="campo-erro" data-erro="ilha"></span>
      </div>

      <div class="campo">
        <label class="campo-etiqueta" for="municipio">Município</label>
        <select class="campo-input" id="municipio" name="municipio" required disabled>
          <option value="">Escolha primeiro a ilha</option>
        </select>
        <span class="campo-erro" data-erro="municipio"></span>
      </div>
    </div>

    <div class="grid gap-x-4 sm:grid-cols-2">
      <x-campo-senha nome="senha" etiqueta="Senha" autocomplete="new-password" minlength="8" />

      <x-campo-senha nome="confirmar_senha" etiqueta="Confirmar senha" autocomplete="new-password" minlength="8" />
    </div>

    <p id="msg-geral" class="msg-geral" role="alert" aria-live="polite"></p>

    <button type="submit" class="botao-cta">Criar conta</button>
  </form>

  <x-slot:rodape>
    Já tens conta?
    <a href="/login" class="font-semibold text-brand-800 underline-offset-4 transition hover:text-brand-600 hover:underline">Iniciar sessão</a>
  </x-slot:rodape>

  <x-slot:scripts>
    <script src="@kgAsset('assets/js/vm-telefone.js')"></script>
    <script src="@kgAsset('assets/js/vm-senha.js')"></script>
    <script nonce="{{ $cspNonce }}">
    const inputTipo = document.getElementById('input-tipo');
    const btnPassageiro = document.getElementById('btn-tipo-passageiro');
    const btnCondutor = document.getElementById('btn-tipo-condutor');

    function atualizarTipoUI(tipo) {
      inputTipo.value = tipo;
      btnPassageiro.classList.toggle('ativo', tipo === 'passageiro');
      btnCondutor.classList.toggle('ativo', tipo === 'condutor');
      btnPassageiro.setAttribute('aria-pressed', String(tipo === 'passageiro'));
      btnCondutor.setAttribute('aria-pressed', String(tipo === 'condutor'));
    }
    btnPassageiro.addEventListener('click', () => atualizarTipoUI('passageiro'));
    btnCondutor.addEventListener('click', () => atualizarTipoUI('condutor'));
    atualizarTipoUI(inputTipo.value);

    const MUNICIPIOS = @json($municipios);
    const selIlha = document.getElementById('ilha');
    const selMunicipio = document.getElementById('municipio');

    function preencherMunicipios(ilha) {
      const lista = MUNICIPIOS[ilha] || [];
      selMunicipio.replaceChildren();
      // Ilha com um só município (Sal, Boa Vista, São Vicente...): não há nada
      // a escolher, fica logo preenchido.
      if (lista.length !== 1) {
        selMunicipio.add(new Option(lista.length ? 'Escolha o município' : 'Escolha primeiro a ilha', ''));
      }
      lista.forEach(nome => selMunicipio.add(new Option(nome, nome)));
      selMunicipio.disabled = lista.length === 0;
    }
    selIlha.addEventListener('change', () => preencherMunicipios(selIlha.value));
    // O browser pode repor a ilha ao voltar atrás na página.
    preencherMunicipios(selIlha.value);

    const REGEX_TELEFONE = /^\+238[ ]?[0-9]{7}$/;
    const REGEX_NIF = /^[0-9]{9}$/;

    const form = document.getElementById('form-registo');
    const msgGeral = document.getElementById('msg-geral');

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      document.querySelectorAll('[data-erro]').forEach(el => el.textContent = '');
      msgGeral.textContent = '';
      msgGeral.classList.remove('e-sucesso');

      const dados = new FormData(form);
      const telefone = String(dados.get('telefone') || '').trim();
      const nif = String(dados.get('nif') || '').trim();
      const senha = String(dados.get('senha') || '');
      const confirmar = String(dados.get('confirmar_senha') || '');

      let valido = true;
      if (!REGEX_TELEFONE.test(telefone)) {
        document.querySelector('[data-erro="telefone"]').textContent = 'De momento só aceitamos números de Cabo Verde: +238 9912345.';
        valido = false;
      }
      if (!REGEX_NIF.test(nif)) {
        document.querySelector('[data-erro="nif"]').textContent = 'O NIF deve ter exatamente 9 dígitos.';
        valido = false;
      }
      if (!dados.get('ilha')) {
        document.querySelector('[data-erro="ilha"]').textContent = 'Escolha a ilha.';
        valido = false;
      } else if (!dados.get('municipio')) {
        document.querySelector('[data-erro="municipio"]').textContent = 'Escolha o município.';
        valido = false;
      }
      if (senha.length < 8) {
        document.querySelector('[data-erro="senha"]').textContent = 'Mínimo 8 caracteres.';
        valido = false;
      }
      // A verificação contra fugas de dados conhecidas é feita no servidor
      // (ver AppServiceProvider::regraDeSenha); aqui fica só o comprimento,
      // que é o que se pode validar sem um pedido.

      if (senha !== confirmar) {
        document.querySelector('[data-erro="confirmar_senha"]').textContent = 'As senhas não coincidem.';
        valido = false;
      }
      if (!valido) return;

      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'A criar conta...';

      try {
        const resp = await fetch('/api/auth/registar', { method: 'POST', body: dados });
        const json = await resp.json();
        if (!resp.ok) {
          if (json.campos) {
            for (const [campo, msg] of Object.entries(json.campos)) {
              const el = document.querySelector(`[data-erro="${campo}"]`);
              if (el) el.textContent = msg;
            }
          }
          msgGeral.textContent = json.erro || 'Ocorreu um erro.';
          return;
        }
        msgGeral.classList.add('e-sucesso');
        msgGeral.textContent = json.mensagem;
        setTimeout(() => { window.location.href = '/login'; }, 1500);
      } catch (e) {
        msgGeral.textContent = 'Falha de ligação. Tente novamente.';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Criar conta';
      }
    });
    </script>
  </x-slot:scripts>
</x-layout-auth>
