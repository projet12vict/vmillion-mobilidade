/**
 * V-MILLION — Sons de notificação e de chamada, sintetizados em tempo real com
 * a Web Audio API nativa. Sem bibliotecas externas, sem ficheiros de áudio.
 *
 * Nota: as bibliotecas "earcons" e "sonix" pedidas não existem tal como
 * descritas (não encontradas em pesquisa), e "sound-bakery" é uma
 * ferramenta C/C++ de middleware de áudio para jogos, não um pacote JS de
 * <1KB — nenhuma delas foi usada. Esta implementação faz exactamente o que
 * essas bibliotecas prometiam (osciladores + envelope de ganho), sem
 * depender de um pacote não verificado.
 *
 * API:
 *   KGSons.tocarMensagem()   -> nova mensagem no autofalante
 *   KGSons.tocarChamar()     -> feedback ao clicar em "Chamar" (tel:)
 *   KGSons.tocarSucesso()    -> ex: condutor a caminho, pedido aceite
 *   KGSons.tocarErro()
 *   KGSons.estaAtivo() / definirAtivo(bool)
 *   KGSons.obterVolume() / definirVolume(0-100)
 */
(function (global) {
  'use strict';

  const CHAVE_ATIVO = 'kg_sons_ativo';
  const CHAVE_VOLUME = 'kg_sons_volume';

  let ctx = null;
  function obterContexto() {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!ctx) ctx = new AC();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function prefereReduzido() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function estaAtivo() {
    return localStorage.getItem(CHAVE_ATIVO) !== '0';
  }
  function definirAtivo(ativo) {
    localStorage.setItem(CHAVE_ATIVO, ativo ? '1' : '0');
  }
  function obterVolume() {
    const v = parseInt(localStorage.getItem(CHAVE_VOLUME), 10);
    return isNaN(v) ? 70 : Math.max(0, Math.min(100, v));
  }
  function definirVolume(v) {
    localStorage.setItem(CHAVE_VOLUME, String(Math.max(0, Math.min(100, v))));
  }

  function podeTocar() {
    return estaAtivo() && !prefereReduzido();
  }

  // Um "beep": oscilador + envelope de ganho (ataque rápido, saída
  // exponencial) — a técnica que qualquer biblioteca de "sons sintetizados"
  // faz por dentro, sem precisar de nenhuma dependência.
  function beep(contexto, { freq, duracao = 0.15, tipo = 'sine', atraso = 0, volumeRelativo = 1, sweepPara = null }) {
    const osc = contexto.createOscillator();
    const gain = contexto.createGain();
    const agora = contexto.currentTime + atraso;
    const volumeBase = (obterVolume() / 100) * 0.5 * volumeRelativo; // 0.5 = teto, nunca fica estridente

    osc.type = tipo;
    osc.frequency.setValueAtTime(freq, agora);
    if (sweepPara) osc.frequency.exponentialRampToValueAtTime(sweepPara, agora + duracao);

    gain.gain.setValueAtTime(0.0001, agora);
    gain.gain.exponentialRampToValueAtTime(Math.max(0.0001, volumeBase), agora + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, agora + duracao);

    osc.connect(gain).connect(contexto.destination);
    osc.start(agora);
    osc.stop(agora + duracao + 0.05);
  }

  function tocarMensagem() {
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    beep(c, { freq: 880, duracao: 0.12 });
    beep(c, { freq: 1320, duracao: 0.14, atraso: 0.1 });
  }

  // Ruído branco filtrado — a "estática" de rádio. Gerado por amostra a
  // amostra num buffer, sem ficheiro nenhum.
  function estatica(contexto, { duracao = 0.12, atraso = 0, volumeRelativo = 0.35 }) {
    const amostras = Math.max(1, Math.floor(contexto.sampleRate * duracao));
    const buffer = contexto.createBuffer(1, amostras, contexto.sampleRate);
    const dados = buffer.getChannelData(0);
    for (let i = 0; i < amostras; i++) dados[i] = Math.random() * 2 - 1;

    const fonte = contexto.createBufferSource();
    fonte.buffer = buffer;

    const filtro = contexto.createBiquadFilter();
    filtro.type = 'bandpass';
    filtro.frequency.value = 1800;
    filtro.Q.value = 0.7;

    const gain = contexto.createGain();
    const agora = contexto.currentTime + atraso;
    const volumeBase = (obterVolume() / 100) * 0.5 * volumeRelativo;
    gain.gain.setValueAtTime(volumeBase, agora);
    gain.gain.exponentialRampToValueAtTime(0.0001, agora + duracao);

    fonte.connect(filtro).connect(gain).connect(contexto.destination);
    fonte.start(agora);
    fonte.stop(agora + duracao + 0.02);
  }

  // A app não sabe quando uma chamada tel: toca/atende/termina — isso
  // acontece fora do browser, na aplicação de telefone nativa. Este é só o
  // som de "a iniciar a chamada" (squelch de rádio + beep), não um ciclo de
  // vida completo simulado.
  function tocarChamar() {
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    estatica(c, { duracao: 0.1 });
    beep(c, { freq: 660, duracao: 0.18, tipo: 'square', volumeRelativo: 0.6, sweepPara: 880, atraso: 0.08 });
  }

  // Toque de chamada a receber — repete até KGSons.pararToqueIncoming() ser
  // chamado (o utilizador atende ou recusa; nunca para sozinho).
  let toqueIncomingTimer = null;
  function padraoToque(c) {
    beep(c, { freq: 587, duracao: 0.28, tipo: 'sine' });
    beep(c, { freq: 587, duracao: 0.28, tipo: 'sine', atraso: 0.36 });
  }
  function iniciarToqueIncoming() {
    pararToqueIncoming();
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    padraoToque(c);
    toqueIncomingTimer = setInterval(() => {
      const ctxAtual = obterContexto();
      if (ctxAtual) padraoToque(ctxAtual);
    }, 1600);
  }
  function pararToqueIncoming() {
    if (toqueIncomingTimer) {
      clearInterval(toqueIncomingTimer);
      toqueIncomingTimer = null;
    }
  }

  function tocarEnded() {
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    beep(c, { freq: 480, duracao: 0.18, tipo: 'sine', sweepPara: 220, volumeRelativo: 0.55 });
  }

  function tocarSucesso() {
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    beep(c, { freq: 660, duracao: 0.1 });
    beep(c, { freq: 990, duracao: 0.16, atraso: 0.08 });
  }

  function tocarErro() {
    if (!podeTocar()) return;
    const c = obterContexto();
    if (!c) return;
    beep(c, { freq: 300, duracao: 0.22, tipo: 'sawtooth', volumeRelativo: 0.7, sweepPara: 180 });
  }

  global.KGSons = {
    tocarMensagem, tocarChamar, tocarSucesso, tocarErro,
    iniciarToqueIncoming, pararToqueIncoming, tocarEnded,
    estaAtivo, definirAtivo, obterVolume, definirVolume,
  };
})(window);
