/**
 * A chamada (estado iniciada/atendida/terminada) e o áudio (WebRTC) são duas
 * camadas independentes: a primeira vive em BD e sobrevive a tudo, a segunda
 * é uma negociação ponto-a-ponto que acontece uma única vez, no arranque.
 * Quando a segunda falha, a primeira continua a mostrar "Em chamada com X" —
 * é por isso que um áudio partido aparece ao utilizador como "a chamada
 * funciona mas a voz não sai", e é por isso que precisa de teste próprio.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

import { criarDocumentoFalso } from './ajuda/dom-falso.mjs';

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

// As duas cópias do script (a app Laravel e o legacy PHP) partilham esta
// lógica de negociação linha a linha, e já divergiram uma da outra por
// distração antes. Correr a mesma bateria nas duas é o que impede uma
// correção de áudio de ser aplicada só a metade.
const COPIAS = [
  ['app', 'public/assets/js/kg-chamada.js'],
  ['legacy', 'legacy/public/assets/js/kg-chamada.js'],
];

function esperarCiclos(n = 6) {
  let p = Promise.resolve();
  for (let i = 0; i < n; i++) p = p.then(() => {});
  return p;
}

/**
 * Carrega o kg-chamada.js real num contexto do Node com o browser
 * falsificado. `microfone` decide como se comporta o getUserMedia: 'ok',
 * 'lento' (só resolve quando o teste mandar) ou 'recusado'.
 */
function carregarChamada(fonte, { microfone = 'ok', semMediaDevices = false } = {}) {
  const documento = criarDocumentoFalso();
  // Relógio controlado pelo teste. Com os timers reais o processo nunca
  // acabava (o polling de 4s corre para sempre) e um teste de uma corrida
  // entre o microfone e a resposta do outro lado ficava dependente de
  // esperar segundos reais para ser fiável.
  const intervalos = new Map();
  let proximoId = 1;
  const sinaisEnviados = [];
  const pedidos = [];
  let libertarMicrofone = null;

  const microfonePronto = new Promise((resolve) => { libertarMicrofone = resolve; });

  const streamFalso = { getTracks: () => [{ stop() {} }], getAudioTracks: () => [{ enabled: true }] };

  class RTCPeerConnectionFalsa {
    constructor() { this.remotas = []; }
    addTrack() {}
    async createOffer() { return { type: 'offer', sdp: 'v=0 oferta' }; }
    async createAnswer() { return { type: 'answer', sdp: 'v=0 resposta' }; }
    async setLocalDescription() {}
    async setRemoteDescription(d) { this.remotas.push(d); }
    async addIceCandidate() {}
    close() {}
  }

  const contexto = {
    console,
    setTimeout: () => 0,
    clearTimeout: () => {},
    setInterval: (fn, ms) => { const id = proximoId++; intervalos.set(id, { fn, ms }); return id; },
    clearInterval: (id) => { intervalos.delete(id); },
    document: documento,
    RTCPeerConnection: RTCPeerConnectionFalsa,
    RTCSessionDescription: function (d) { return d; },
    RTCIceCandidate: function (d) { return d; },
    FormData: class { constructor() { this.dados = {}; } set(k, v) { this.dados[k] = v; } },
    navigator: {
      sendBeacon: () => true,
      mediaDevices: semMediaDevices ? undefined : {
        async getUserMedia() {
          if (microfone === 'recusado') { const e = new Error('nao'); e.name = 'NotAllowedError'; throw e; }
          if (microfone === 'lento') await microfonePronto;
          return streamFalso;
        },
      },
    },
    KGSons: {
      tocarChamar() {}, iniciarToqueIncoming() {}, pararToqueIncoming() {}, tocarEnded() {},
    },
    async fetch(url, opcoes) {
      pedidos.push(url);
      if (String(url).startsWith('/api/chamada/sinais')) {
        return { ok: true, json: async () => ({ sinais: [] }) };
      }
      if (String(url).startsWith('/api/chamada/verificar')) {
        return { ok: true, json: async () => ({ chamada: contexto.__chamadaNaBd }) };
      }
      const corpo = opcoes && opcoes.body ? opcoes.body.dados : {};
      if (String(url).startsWith('/api/chamada/sinalizar')) sinaisEnviados.push(corpo);
      if (String(url).startsWith('/api/chamada/iniciar')) {
        return { ok: true, json: async () => ({ sucesso: true, chamada_id: 7 }) };
      }
      return { ok: true, json: async () => ({ sucesso: true }) };
    },
    __chamadaNaBd: null,
  };
  contexto.window = contexto;
  contexto.global = contexto;
  contexto.addEventListener = () => {}; // o kg-chamada.js liga-se ao 'pagehide'

  vm.createContext(contexto);
  vm.runInContext(fonte, contexto, { filename: 'kg-chamada.js' });

  // Corre os timers registados com este período, como o browser faria.
  const tick = async (ms) => {
    for (const { fn, ms: periodo } of [...intervalos.values()]) {
      if (periodo === ms) await fn();
    }
    await esperarCiclos();
  };

  return { contexto, documento, sinaisEnviados, pedidos, libertarMicrofone, tick };
}

for (const [copia, ficheiro] of COPIAS) {
  const fonte = fs.readFileSync(path.join(RAIZ, ficheiro), 'utf8');

  test(`[${copia}] a oferta é enviada mesmo quando o outro lado atende antes de o microfone abrir`, async () => {
    const { contexto, sinaisEnviados, libertarMicrofone, tick } = carregarChamada(fonte, { microfone: 'lento' });
    contexto.KGChamada.configurar({ rt: null, meuId: 1, csrfToken: 't' });

    // O utilizador clica em "Chamar". O microfone ainda não abriu: na primeira
    // vez o browser mostra um diálogo de permissão que demora segundos.
    contexto.KGChamada.iniciar(2, 'Ana');
    await esperarCiclos();

    // Entretanto o destinatário atende, e o polling geral trá-lo à mesma.
    contexto.__chamadaNaBd = { id: 7, remetente_id: 1, destinatario_id: 2, estado: 'atendida', remetente_nome: 'Eu' };
    await tick(4000); // passa o estado deste lado a 'ativa'

    // Só agora é que a permissão é dada.
    libertarMicrofone();
    await esperarCiclos(12);

    const ofertas = sinaisEnviados.filter((s) => s.tipo === 'offer');
    assert.equal(ofertas.length, 1, 'sem esta oferta a chamada fica ligada e muda dos dois lados');
  });

  test(`[${copia}] microfone recusado escreve o motivo no modal em vez de ficar em silêncio`, async () => {
    const { contexto, documento } = carregarChamada(fonte, { microfone: 'recusado' });
    contexto.KGChamada.configurar({ rt: null, meuId: 1, csrfToken: 't' });

    contexto.KGChamada.iniciar(2, 'Ana');
    await esperarCiclos(10);

    assert.match(documento.__elemento('chamada-subtitulo').textContent, /permissão do microfone recusada/i);
  });

  test(`[${copia}] página fora de https explica que é isso que bloqueia o microfone`, async () => {
    const { contexto, documento } = carregarChamada(fonte, { semMediaDevices: true });
    contexto.KGChamada.configurar({ rt: null, meuId: 1, csrfToken: 't' });

    contexto.KGChamada.iniciar(2, 'Ana');
    await esperarCiclos(10);

    assert.match(documento.__elemento('chamada-subtitulo').textContent, /https/i);
  });
}
