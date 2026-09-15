/**
 * V-MILLION — botão de mostrar/esconder senha (x-campo-senha).
 *
 * Delegado no document: funciona para qualquer [data-senha] da página,
 * incluindo os que apareçam depois (formulários carregados por JS).
 *
 * O botão nasce com 'hidden' no HTML e é revelado aqui — sem JS ninguém
 * fica com um olho que não faz nada.
 */
(function () {
  'use strict';

  function revelarBotoes(raiz) {
    (raiz || document).querySelectorAll('[data-senha] [data-senha-olho][hidden]')
      .forEach(function (botao) { botao.removeAttribute('hidden'); });
  }

  function alternar(botao, focar) {
    const campo = botao.closest('[data-senha]');
    const input = campo && campo.querySelector('[data-senha-input]');
    if (!input) return;

    const visivel = input.type === 'text';

    // Trocar o type limpa a seleção em vários browsers; guardamos e repomos
    // o cursor para não atirar quem está a meio de escrever para o fim (ou
    // para o princípio) do que já escreveu.
    const inicio = input.selectionStart;
    const fim = input.selectionEnd;

    input.type = visivel ? 'password' : 'text';

    botao.setAttribute('aria-pressed', String(!visivel));
    botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Esconder senha');

    // toggleAttribute e nao .hidden: a propriedade 'hidden' so existe em
    // HTMLElement — nestes <svg> escrever icone.hidden nao mexia no atributo
    // (ficava uma propriedade solta) e os dois icones apareciam sobrepostos.
    const icones = botao.querySelectorAll('[data-senha-icone]');
    icones.forEach(function (icone) {
      const mostrarEste = icone.dataset.senhaIcone === (visivel ? 'mostrar' : 'esconder');
      icone.toggleAttribute('hidden', !mostrarEste);
    });

    if (focar === false) return;

    input.focus();
    if (inicio !== null && typeof input.setSelectionRange === 'function') {
      try { input.setSelectionRange(inicio, fim); } catch (e) { /* type=email e afins */ }
    }
  }

  document.addEventListener('click', function (ev) {
    if (!(ev.target instanceof Element)) return;
    const botao = ev.target.closest('[data-senha-olho]');
    if (botao) alternar(botao, true);
  });

  // Submeter com a senha à vista deixaria o valor visível no ecrã enquanto a
  // página navega; e se o formulário falhar, a senha fica exposta na página.
  document.addEventListener('submit', function (ev) {
    ev.target.querySelectorAll('[data-senha-input][type="text"]').forEach(function (input) {
      const botao = input.closest('[data-senha]').querySelector('[data-senha-olho]');
      // focar: false — quem submeteu está no botão de entrar, não no campo.
      if (botao) alternar(botao, false);
    });
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { revelarBotoes(); });
  } else {
    revelarBotoes();
  }
})();
