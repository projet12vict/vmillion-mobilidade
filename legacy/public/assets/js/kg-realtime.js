/**
 * V-MILLION — Cliente de tempo real: Socket.io com fallback automático para
 * polling a cada 5s se a ligação WebSocket falhar (secção 5.4).
 *
 * Uso:
 *   const rt = KGRealtime.connect({ url: 'http://localhost:3001' });
 *   rt.join('veiculo:12');
 *   rt.on('veiculo:posicao', (payload) => { ... });
 *   rt.emit('veiculo:posicao', { veiculoId: 12, lat, lng });
 *
 * Em modo polling, `on()` é alimentado por pedidos periódicos a
 * `pollingUrlFor(evento)` (definido por endpoint), devolvido pelo próprio
 * consumidor via opts.pollingEndpoints = { 'veiculo:posicao': '/api/...' }.
 */
(function (global) {
  'use strict';

  const POLLING_INTERVAL_MS = 5000;

  function connect(opts) {
    const listeners = new Map();
    const salas = new Set();
    let socket = null;
    let modo = 'websocket';
    let pollingTimers = [];

    function emitLocal(evento, payload) {
      (listeners.get(evento) || []).forEach((cb) => cb(payload));
    }

    function iniciarPolling() {
      modo = 'polling';
      const endpoints = opts.pollingEndpoints || {};
      Object.entries(endpoints).forEach(([evento, url]) => {
        let abortAnterior = null;
        const tick = async () => {
          if (abortAnterior) abortAnterior.abort();
          abortAnterior = new AbortController();
          try {
            const resp = await fetch(url, { credentials: 'same-origin', signal: abortAnterior.signal });
            if (resp.ok) {
              const dados = await resp.json();
              emitLocal(evento, dados);
            }
          } catch (e) { /* silencioso (inclui abortos): tenta novamente no próximo ciclo */ }
        };
        tick();
        pollingTimers.push(setInterval(tick, POLLING_INTERVAL_MS));
      });
    }

    function pararPolling() {
      pollingTimers.forEach(clearInterval);
      pollingTimers = [];
    }

    if (typeof io !== 'undefined' && opts.url) {
      try {
        socket = io(opts.url, { transports: ['websocket', 'polling'], timeout: 5000, reconnectionAttempts: 5 });

        socket.on('connect', () => {
          modo = 'websocket';
          pararPolling();
          salas.forEach((s) => socket.emit('join', s));
        });

        socket.on('connect_error', () => {
          if (modo !== 'polling') iniciarPolling();
        });

        socket.on('disconnect', () => {
          if (modo !== 'polling') iniciarPolling();
        });

        // Reencaminha todos os eventos conhecidos para os listeners locais.
        ['veiculo:posicao', 'veiculo:lugares', 'reserva:nova', 'reserva:atualizada',
         'sos:ativado', 'sos:atualizado', 'comunicacao:nova', 'notificacao:nova',
         'chamada:iniciar', 'chamada:atender', 'chamada:recusar', 'chamada:desligar',
         'urbano:reclamado']
          .forEach((evento) => socket.on(evento, (payload) => emitLocal(evento, payload)));
      } catch (e) {
        iniciarPolling();
      }
    } else {
      iniciarPolling();
    }

    return {
      get modo() { return modo; },
      join(sala) {
        salas.add(sala);
        if (socket && socket.connected) socket.emit('join', sala);
      },
      leave(sala) {
        salas.delete(sala);
        if (socket && socket.connected) socket.emit('leave', sala);
      },
      on(evento, cb) {
        if (!listeners.has(evento)) listeners.set(evento, []);
        listeners.get(evento).push(cb);
      },
      emit(evento, payload) {
        if (socket && socket.connected) socket.emit(evento, payload);
        // Em modo polling, a emissão fica a cargo de um POST direto à API
        // feito pelo consumidor (o polling só cobre a receção de eventos).
      },
      desconectar() {
        pararPolling();
        if (socket) socket.disconnect();
      },
    };
  }

  global.KGRealtime = { connect };
})(window);
