/**
 * V-MILLION — campo de telefone com seletor de país.
 *
 * Liga o comportamento ao markup de <x-campo-telefone>: escolha do país,
 * pesquisa, teclado, e a composição do valor que é realmente submetido
 * (indicativo + número nacional) no input escondido.
 *
 * Fica num ficheiro servido de /assets/js e não num <script> inline porque a
 * CSP só deixa passar inline com nonce (ver SecurityHeaders) — e este código
 * é o mesmo em qualquer formulário que use o componente.
 */
(function (global) {
  'use strict';

  const document = global.document;

  /**
   * Bandeira a partir do código ISO: 'CV' -> os dois indicadores regionais
   * correspondentes. Assim não há emojis guardados no repositório nem uma
   * folha de sprites para carregar. Em sistemas sem tipo de letra de
   * bandeiras (Windows), o browser desenha as duas letras — que continuam a
   * identificar o país.
   */
  function bandeira(iso) {
    const codigo = String(iso || '').toUpperCase();
    if (!/^[A-Z]{2}$/.test(codigo)) return codigo;
    return String.fromCodePoint(
      0x1f1e6 + codigo.charCodeAt(0) - 65,
      0x1f1e6 + codigo.charCodeAt(1) - 65
    );
  }

  /** Pesquisa sem acentos: 'polonia' encontra 'Polónia'. */
  function normalizar(texto) {
    return String(texto || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function apenasDigitos(texto) {
    return String(texto || '').replace(/\D/g, '');
  }

  function iniciar(raiz) {
    const botao = raiz.querySelector('[data-tel-botao]');
    const bandeiraBotao = botao.querySelector('[data-tel-bandeira]');
    const indicativoBotao = botao.querySelector('[data-tel-indicativo]');
    const numero = raiz.querySelector('[data-tel-numero]');
    const valor = raiz.querySelector('[data-tel-valor]');
    const painel = raiz.querySelector('[data-tel-painel]');
    const pesquisa = raiz.querySelector('[data-tel-pesquisa]');
    const lista = raiz.querySelector('[data-tel-lista]');
    const vazio = raiz.querySelector('[data-tel-vazio]');
    const opcoes = Array.from(raiz.querySelectorAll('[data-tel-opcao]'));

    let selecionada = opcoes.find((o) => o.getAttribute('aria-selected') === 'true') || opcoes[0];
    let ativa = selecionada;

    opcoes.forEach((opcao) => {
      opcao.querySelector('[data-tel-bandeira]').textContent = bandeira(opcao.dataset.iso);
      opcao.dataset.procura = normalizar(opcao.dataset.nome) + ' ' + opcao.dataset.iso.toLowerCase() +
        ' ' + opcao.dataset.indicativo + ' ' + apenasDigitos(opcao.dataset.indicativo);
    });

    // --- valor submetido ---------------------------------------------------

    function compor() {
      const digitos = apenasDigitos(numero.value);
      valor.value = digitos ? selecionada.dataset.indicativo + ' ' + digitos : '';
    }

    function selecionar(opcao, focarNumero) {
      selecionada.setAttribute('aria-selected', 'false');
      selecionada = opcao;
      selecionada.setAttribute('aria-selected', 'true');

      bandeiraBotao.textContent = bandeira(opcao.dataset.iso);
      indicativoBotao.textContent = opcao.dataset.indicativo;
      botao.setAttribute('aria-label',
        'País do indicativo: ' + opcao.dataset.nome + ' (' + opcao.dataset.indicativo + ')');
      numero.placeholder = opcao.dataset.exemplo || '';

      compor();
      if (focarNumero) numero.focus();
    }

    /** Indicativo mais longo que casa com o número — '+1264' antes de '+1'. */
    function porIndicativo(texto) {
      const digitos = apenasDigitos(texto);
      let melhor = null;
      opcoes.forEach((opcao) => {
        const dial = apenasDigitos(opcao.dataset.indicativo);
        if (!digitos.startsWith(dial)) return;
        if (!melhor || dial.length > apenasDigitos(melhor.dataset.indicativo).length) melhor = opcao;
      });
      return melhor;
    }

    // --- painel ------------------------------------------------------------

    function marcarAtiva(opcao) {
      if (ativa) ativa.classList.remove('ativa');
      ativa = opcao;
      if (!ativa) {
        pesquisa.removeAttribute('aria-activedescendant');
        return;
      }
      ativa.classList.add('ativa');
      pesquisa.setAttribute('aria-activedescendant', ativa.id);
      ativa.scrollIntoView({ block: 'nearest' });
    }

    function visiveis() {
      return opcoes.filter((o) => !o.hidden);
    }

    function filtrar() {
      const termo = normalizar(pesquisa.value).trim();
      const termoDigitos = apenasDigitos(termo);
      let visto = 0;

      opcoes.forEach((opcao) => {
        const casa = !termo ||
          opcao.dataset.procura.includes(termo) ||
          (termoDigitos !== '' && opcao.dataset.procura.includes(termoDigitos));
        opcao.hidden = !casa;
        if (casa) visto += 1;
      });

      vazio.hidden = visto > 0;
      marcarAtiva(visiveis()[0] || null);
    }

    function abrir() {
      painel.hidden = false;
      botao.setAttribute('aria-expanded', 'true');
      pesquisa.value = '';
      filtrar();
      marcarAtiva(selecionada);
      pesquisa.focus();
    }

    function fechar(focarBotao) {
      if (painel.hidden) return;
      painel.hidden = true;
      botao.setAttribute('aria-expanded', 'false');
      if (focarBotao) botao.focus();
    }

    botao.addEventListener('click', () => {
      if (painel.hidden) abrir();
      else fechar(true);
    });

    lista.addEventListener('click', (ev) => {
      const opcao = ev.target.closest('[data-tel-opcao]');
      if (!opcao) return;
      selecionar(opcao, true);
      fechar(false);
    });

    pesquisa.addEventListener('input', filtrar);

    pesquisa.addEventListener('keydown', (ev) => {
      const atuais = visiveis();
      const indice = ativa ? atuais.indexOf(ativa) : -1;

      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (atuais.length === 0) return;
        const passo = ev.key === 'ArrowDown' ? 1 : -1;
        const seguinte = (indice + passo + atuais.length) % atuais.length;
        marcarAtiva(atuais[seguinte]);
        return;
      }
      if (ev.key === 'Home' || ev.key === 'End') {
        ev.preventDefault();
        if (atuais.length === 0) return;
        marcarAtiva(ev.key === 'Home' ? atuais[0] : atuais[atuais.length - 1]);
        return;
      }
      if (ev.key === 'Enter') {
        ev.preventDefault();
        if (!ativa) return;
        selecionar(ativa, true);
        fechar(false);
        return;
      }
      if (ev.key === 'Escape') {
        ev.preventDefault();
        fechar(true);
      }
    });

    // O clique fora e o Escape fecham; o focusout sozinho não chega porque o
    // rato pode carregar numa opção antes de o foco sair do campo de pesquisa.
    document.addEventListener('click', (ev) => {
      if (!raiz.contains(ev.target)) fechar(false);
    });

    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !painel.hidden) fechar(true);
    });

    // --- número nacional ---------------------------------------------------

    numero.addEventListener('input', () => {
      // Colar um número internacional completo ('+351 912345678') muda o país
      // em vez de ficar um '+' que a validação depois rejeita.
      if (numero.value.trim().startsWith('+')) {
        const opcao = porIndicativo(numero.value);
        if (opcao) {
          const resto = apenasDigitos(numero.value).slice(apenasDigitos(opcao.dataset.indicativo).length);
          selecionar(opcao, false);
          numero.value = resto;
        }
      }

      // Reatribuir o valor põe o cursor no fim, por isso só quando há mesmo
      // caracteres a tirar — escrever no meio do número tem de continuar a dar.
      const limpo = numero.value.replace(/[^\d ]/g, '');
      if (limpo !== numero.value) numero.value = limpo;
      compor();
    });

    numero.addEventListener('blur', compor);

    // --- estado inicial ----------------------------------------------------

    const inicial = raiz.dataset.valorInicial || '';
    const opcaoInicial = inicial ? (porIndicativo(inicial) || selecionada) : selecionada;

    if (inicial) {
      numero.value = apenasDigitos(inicial).slice(apenasDigitos(opcaoInicial.dataset.indicativo).length);
    }
    selecionar(opcaoInicial, false);
  }

  function iniciarTodos() {
    document.querySelectorAll('[data-tel]').forEach(iniciar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
  } else {
    iniciarTodos();
  }
})(window);
