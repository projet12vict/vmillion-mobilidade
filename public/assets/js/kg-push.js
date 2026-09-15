/**
 * V-MILLION — Notificações push das chamadas (Web Push).
 *
 * Trata do lado do browser: pede a permissão, subscreve no serviço de push do
 * fabricante e regista o dispositivo no servidor (/api/push/subscrever). O
 * que faz aparecer o aviso já com a app fechada é o Service Worker (sw.js);
 * este ficheiro só existe para lá chegar.
 *
 * Sem isto, um utilizador só era avisado de uma chamada com o painel aberto
 * num separador vivo — o polling de kg-chamada.js e o socket.io morrem com a
 * página.
 *
 * API:
 *   KGPush.configurar({ csrfToken })  -> liga-se em silêncio se a permissão já existir
 *   KGPush.ativar()                   -> pede permissão e subscreve (só a partir de um clique)
 *   KGPush.desativar()                -> cancela a subscrição deste dispositivo
 *   KGPush.estado()                   -> 'indisponivel' | 'inseguro' | 'negado' | 'inativo' | 'ativo'
 *   KGPush.aoMudar(fn)                -> avisa quando o estado muda (para o painel redesenhar)
 */
(function (global) {
  'use strict';

  let csrfToken = '';
  let chavePublica = '';
  let estadoAtual = 'inativo';
  const ouvintes = [];

  function suportado() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  // O Web Push exige contexto seguro. Em http:// simples o
  // navigator.serviceWorker nem existe na maioria dos browsers, mas em alguns
  // existe e é o subscribe() que rebenta — daí distinguir os dois casos, para
  // a mensagem no painel dizer o que realmente se passa.
  function contextoSeguro() {
    return global.isSecureContext === true;
  }

  function definirEstado(novo) {
    if (novo === estadoAtual) return;
    estadoAtual = novo;
    ouvintes.forEach((fn) => { try { fn(novo); } catch (e) { /* um ouvinte partido não pode parar os outros */ } });
  }

  function estado() {
    return estadoAtual;
  }

  function aoMudar(fn) {
    if (typeof fn === 'function') ouvintes.push(fn);
  }

  // A chave pública VAPID vem em base64url e o pushManager quer bytes.
  function chaveEmBytes(base64url) {
    const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const cru = atob(base64);
    const bytes = new Uint8Array(cru.length);
    for (let i = 0; i < cru.length; i++) bytes[i] = cru.charCodeAt(i);
    return bytes;
  }

  function corpo(dados) {
    const form = new FormData();
    form.set('_token', csrfToken);
    Object.keys(dados).forEach((chave) => form.set(chave, dados[chave]));
    return form;
  }

  async function registarNoServidor(subscricao) {
    const json = subscricao.toJSON();
    const resp = await fetch('/api/push/subscrever', {
      method: 'POST',
      credentials: 'same-origin',
      body: corpo({
        endpoint: json.endpoint,
        p256dh: json.keys.p256dh,
        auth: json.keys.auth,
      }),
    });
    return resp.ok;
  }

  // Subscrever é idempotente: se já existir uma subscrição para este browser,
  // reaproveita-se. O registo no servidor é refeito na mesma em cada arranque
  // — é o que repõe a linha depois de uma limpeza da base de dados ou de o
  // servidor ter apagado a subscrição por a ter achado morta.
  // Duas subscrições só são a mesma se tiverem sido feitas com a mesma chave
  // pública do servidor. Reaproveitar uma criada com outra chave dava uma
  // avaria silenciosa: o browser continuava a dizer que estava subscrito e
  // todos os envios morriam com 403 do lado do fabricante.
  function chaveDiferente(subscricao) {
    const atual = subscricao.options && subscricao.options.applicationServerKey;
    if (!atual) return false;

    const guardada = new Uint8Array(atual);
    const esperada = chaveEmBytes(chavePublica);
    if (guardada.length !== esperada.length) return true;

    for (let i = 0; i < esperada.length; i++) {
      if (guardada[i] !== esperada[i]) return true;
    }
    return false;
  }

  async function subscrever() {
    const registo = await navigator.serviceWorker.ready;
    let subscricao = await registo.pushManager.getSubscription();

    if (subscricao && chaveDiferente(subscricao)) {
      await subscricao.unsubscribe();
      subscricao = null;
    }

    if (!subscricao) {
      subscricao = await registo.pushManager.subscribe({
        // Sem isto o Chrome recusa a subscrição: só aceita push que resulte
        // sempre num aviso visível para o utilizador.
        userVisibleOnly: true,
        applicationServerKey: chaveEmBytes(chavePublica),
      });
    }

    const registado = await registarNoServidor(subscricao);
    definirEstado(registado ? 'ativo' : 'inativo');
    return registado;
  }

  /**
   * Arranque silencioso: nunca pede permissão sozinho (um pedido que aparece
   * do nada é recusado quase sempre, e a recusa é definitiva). Só refaz a
   * ligação de quem já tinha dito que sim.
   */
  async function configurar(opts) {
    csrfToken = (opts && opts.csrfToken) || '';

    if (!suportado()) { definirEstado('indisponivel'); return; }
    if (!contextoSeguro()) { definirEstado('inseguro'); return; }

    let json;
    try {
      const resp = await fetch('/api/push/chave', { credentials: 'same-origin' });
      if (!resp.ok) { definirEstado('indisponivel'); return; }
      json = await resp.json();
    } catch (e) {
      definirEstado('indisponivel');
      return;
    }

    // Servidor sem chaves VAPID configuradas: o push está desligado e não há
    // nada a oferecer ao utilizador.
    if (!json.ativo || !json.chave_publica) { definirEstado('indisponivel'); return; }
    chavePublica = json.chave_publica;

    if (Notification.permission === 'denied') { definirEstado('negado'); return; }
    if (Notification.permission !== 'granted') { definirEstado('inativo'); return; }

    try {
      await subscrever();
    } catch (e) {
      definirEstado('inativo');
    }
  }

  /**
   * A partir de um clique do utilizador: o Safari (e o Chrome em iOS) só
   * aceitam Notification.requestPermission() dentro de um gesto real.
   */
  async function ativar() {
    if (!suportado()) { definirEstado('indisponivel'); return false; }
    if (!contextoSeguro()) { definirEstado('inseguro'); return false; }
    if (!chavePublica) { definirEstado('indisponivel'); return false; }

    const permissao = await Notification.requestPermission();

    if (permissao !== 'granted') {
      definirEstado(permissao === 'denied' ? 'negado' : 'inativo');
      return false;
    }

    try {
      return await subscrever();
    } catch (e) {
      definirEstado('inativo');
      return false;
    }
  }

  /**
   * Desligar tem de acontecer dos dois lados: cancelar só no browser deixava
   * o servidor a mandar pushes para um endpoint que já ninguém lê, e apagar
   * só no servidor deixava o browser a achar que continua subscrito (e a
   * nunca voltar a subscrever, porque getSubscription() ainda devolve uma).
   */
  async function desativar() {
    if (!suportado()) return;

    try {
      const registo = await navigator.serviceWorker.ready;
      const subscricao = await registo.pushManager.getSubscription();
      if (!subscricao) { definirEstado('inativo'); return; }

      const endpoint = subscricao.endpoint;
      await subscricao.unsubscribe();
      await fetch('/api/push/cancelar', {
        method: 'POST',
        credentials: 'same-origin',
        body: corpo({ endpoint: endpoint }),
      });
    } catch (e) {
      /* o estado abaixo fica na mesma correto para o utilizador */
    }

    definirEstado('inativo');
  }

  global.KGPush = { configurar, ativar, desativar, estado, aoMudar };
})(window);
