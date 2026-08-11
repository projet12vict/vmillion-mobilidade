/**
 * V-MILLION — Chamada com voz real (WebRTC) entre passageiro e condutor.
 *
 * O estado (iniciada/atendida/recusada/terminada) fica persistido em BD via
 * api/chamada/*.php (ver database/migration_20260810_chamadas.sql) — não só
 * em memória JS — porque o socket.io (realtime/server.js) nem sempre está a
 * correr neste ambiente (mesmo problema que já tinha acontecido com o
 * autofalante). Um polling a cada 4s é a fonte de verdade; o socket.io, se
 * estiver ligado, só acelera isso (avisa "verifica agora" em vez de esperar
 * pelo próximo tick) — nunca substitui o polling.
 *
 * O áudio é WebRTC ponto-a-ponto (RTCPeerConnection): o servidor nunca vê
 * nem guarda som, só a sinalização de texto (oferta/resposta SDP + ICE
 * candidates) via api/chamada/sinalizar.php e sinais.php — ver
 * database/migration_20260810_sinalizacao_chamada.sql. Essa troca usa um
 * polling próprio, mais rápido (1s), só enquanto há uma ligação a
 * negociar/ativa — não o polling geral de 4s, que é lento de mais para ICE.
 * Se o microfone falhar (permissão recusada, sem hardware), a chamada
 * visual continua a funcionar na mesma — só fica sem áudio.
 *
 * Uso (uma vez, no arranque do painel, depois de window.kgRt existir):
 *   KGChamada.configurar({ rt: window.kgRt, meuId: UTILIZADOR_ID, csrfToken: CSRF_TOKEN });
 * csrfToken tem de vir passado explicitamente (não window.CSRF_TOKEN): uma
 * `const` declarada no <script> da página não fica pendurada em `window`,
 * só `var`/`function` o fazem.
 * No botão "Chamar":
 *   KGChamada.iniciar(paraUtilizadorId, paraNome);
 *
 * Espera este HTML na página (ver modal-sons para o mesmo padrão):
 *   #modal-chamada, #chamada-icone, #chamada-titulo, #chamada-subtitulo,
 *   #chamada-acoes, #chamada-audio-remoto (elemento <audio>)
 */
(function (global) {
  'use strict';

  const INTERVALO_POLL_MS = 4000;
  const INTERVALO_SINAIS_MS = 1000;
  const ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302' }];

  let rt = null;
  let meuId = null;
  let csrfToken = null;
  let pollTimer = null;
  let sinalPollTimer = null;

  // inativa | a_chamar | a_receber | ativa | terminada
  let estado = 'inativa';
  let chamadaAtualId = null;
  let outroId = null;
  let outroNome = '';
  let mensagemTerminada = '';
  let fechoAutomaticoTimer = null;

  let pc = null;
  let localStream = null;
  let ultimoSinalId = 0;
  let microfoneSilenciado = false;

  function els() {
    return {
      modal: document.getElementById('modal-chamada'),
      icone: document.getElementById('chamada-icone'),
      titulo: document.getElementById('chamada-titulo'),
      subtitulo: document.getElementById('chamada-subtitulo'),
      acoes: document.getElementById('chamada-acoes'),
    };
  }

  function botao(texto, classe, aoClicar) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `kg-btn ${classe}`;
    btn.textContent = texto;
    btn.addEventListener('click', aoClicar);
    return btn;
  }

  function renderizar() {
    const e = els();
    if (!e.modal) return;
    clearTimeout(fechoAutomaticoTimer);

    if (estado === 'inativa') {
      e.modal.style.display = 'none';
      e.acoes.innerHTML = '';
      return;
    }

    e.modal.style.display = 'flex';
    e.acoes.innerHTML = '';

    if (estado === 'a_chamar') {
      e.icone.textContent = '📞';
      e.titulo.textContent = `A chamar ${outroNome}...`;
      e.subtitulo.textContent = 'À espera que atenda.';
      e.acoes.appendChild(botao('Desligar', 'kg-btn--perigo', desligar));
    } else if (estado === 'a_receber') {
      e.icone.textContent = '📲';
      e.titulo.textContent = `Chamada de ${outroNome}`;
      e.subtitulo.textContent = '';
      e.acoes.appendChild(botao('Recusar', 'kg-btn--perigo', recusar));
      e.acoes.appendChild(botao('Atender', 'kg-btn--cta', atender));
    } else if (estado === 'ativa') {
      e.icone.textContent = '🎧';
      e.titulo.textContent = `Em chamada com ${outroNome}`;
      e.subtitulo.textContent = localStream ? '' : 'Sem microfone — só o outro lado te ouve, se tiver.';
      e.acoes.appendChild(botao(microfoneSilenciado ? '🔇 Ativar microfone' : '🎤 Silenciar', 'kg-btn--outline', alternarMicrofone));
      e.acoes.appendChild(botao('Desligar', 'kg-btn--perigo', desligar));
    } else if (estado === 'terminada') {
      e.icone.textContent = '📴';
      e.titulo.textContent = mensagemTerminada || 'Chamada terminada';
      e.subtitulo.textContent = '';
      e.acoes.appendChild(botao('Fechar', 'kg-btn--outline', reiniciar));
      fechoAutomaticoTimer = setTimeout(reiniciar, 2500);
    }
  }

  function sinalizarEstado(evento) {
    if (rt && outroId != null) rt.emit(evento, { paraUtilizadorId: outroId });
  }

  function pedir(url, corpo) {
    const dados = new FormData();
    dados.set('csrf_token', csrfToken);
    Object.entries(corpo || {}).forEach(([k, v]) => dados.set(k, v));
    return fetch(url, { method: 'POST', body: dados }).then(async (resp) => {
      const json = await resp.json().catch(() => ({}));
      return { ok: resp.ok, json };
    });
  }

  // ---- Áudio (WebRTC) ----------------------------------------------

  function criarPeerConnection() {
    const conexao = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    conexao.onicecandidate = (ev) => {
      if (ev.candidate) enviarSinal('ice', JSON.stringify(ev.candidate));
    };
    conexao.ontrack = (ev) => {
      const audioEl = document.getElementById('chamada-audio-remoto');
      if (!audioEl) return;
      audioEl.srcObject = ev.streams[0];
      audioEl.play().catch(() => { /* autoplay bloqueado — o utilizador já interagiu ao atender/ligar, tenta-se só */ });
    };
    return conexao;
  }

  function enviarSinal(tipo, payload) {
    if (chamadaAtualId == null) return;
    pedir('/api/chamada/sinalizar.php', { chamada_id: chamadaAtualId, tipo, payload });
  }

  function iniciarPollSinais() {
    clearInterval(sinalPollTimer);
    sinalPollTimer = setInterval(processarSinais, INTERVALO_SINAIS_MS);
    processarSinais();
  }

  async function processarSinais() {
    if (chamadaAtualId == null || !pc) return;
    let resp;
    try {
      resp = await fetch(`/api/chamada/sinais.php?chamada_id=${chamadaAtualId}&desde_id=${ultimoSinalId}`, { credentials: 'same-origin' });
    } catch (e) { return; }
    if (!resp.ok) return;
    const json = await resp.json().catch(() => null);
    const sinais = (json && json.sinais) || [];
    for (const sinal of sinais) {
      ultimoSinalId = Math.max(ultimoSinalId, sinal.id);
      if (!pc) break; // a chamada pode ter sido desligada a meio deste ciclo
      try {
        const dados = JSON.parse(sinal.payload);
        if (sinal.tipo === 'offer') {
          await pc.setRemoteDescription(new RTCSessionDescription(dados));
          const answer = await pc.createAnswer();
          await pc.setLocalDescription(answer);
          enviarSinal('answer', JSON.stringify(answer));
        } else if (sinal.tipo === 'answer') {
          await pc.setRemoteDescription(new RTCSessionDescription(dados));
        } else if (sinal.tipo === 'ice') {
          await pc.addIceCandidate(new RTCIceCandidate(dados));
        }
      } catch (e) { /* sinal fora de ordem ou inválido — ignora-se, o próximo tick continua */ }
    }
  }

  async function iniciarAudio() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      return; // sem microfone/permissão — chamada visual continua sem áudio
    }
    if (estado !== 'a_chamar' || chamadaAtualId == null) { pararAudio(); return; } // desligou entretanto
    pc = criarPeerConnection();
    localStream.getTracks().forEach((t) => pc.addTrack(t, localStream));
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    enviarSinal('offer', JSON.stringify(offer));
    iniciarPollSinais();
    renderizar();
  }

  async function atenderAudio() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      return;
    }
    if (estado !== 'ativa' || chamadaAtualId == null) { pararAudio(); return; }
    pc = criarPeerConnection();
    localStream.getTracks().forEach((t) => pc.addTrack(t, localStream));
    iniciarPollSinais(); // a oferta do outro lado chega por aqui e é respondida em processarSinais()
    renderizar();
  }

  function alternarMicrofone() {
    if (!localStream) return;
    microfoneSilenciado = !microfoneSilenciado;
    localStream.getAudioTracks().forEach((t) => { t.enabled = !microfoneSilenciado; });
    renderizar();
  }

  function pararAudio() {
    clearInterval(sinalPollTimer);
    sinalPollTimer = null;
    ultimoSinalId = 0;
    microfoneSilenciado = false;
    if (localStream) {
      localStream.getTracks().forEach((t) => t.stop());
      localStream = null;
    }
    if (pc) {
      pc.close();
      pc = null;
    }
    const audioEl = document.getElementById('chamada-audio-remoto');
    if (audioEl) audioEl.srcObject = null;
  }

  // ---- Estado da chamada ---------------------------------------------

  function reiniciar() {
    clearTimeout(fechoAutomaticoTimer);
    KGSons.pararToqueIncoming();
    pararAudio();
    estado = 'inativa';
    chamadaAtualId = null;
    outroId = null;
    outroNome = '';
    mensagemTerminada = '';
    renderizar();
  }

  function mostrarTerminada(mensagem) {
    KGSons.pararToqueIncoming();
    KGSons.tocarEnded();
    pararAudio();
    estado = 'terminada';
    mensagemTerminada = mensagem;
    chamadaAtualId = null;
    renderizar();
  }

  async function iniciar(paraId, paraNome) {
    if (meuId == null || paraId == null || estado !== 'inativa') return;
    const { ok, json } = await pedir('/api/chamada/iniciar.php', { destinatario_id: paraId });
    if (!ok) {
      alert(json.erro === 'ocupado' ? `${paraNome} está ocupado(a) noutra chamada.` : (json.erro || 'Não foi possível ligar.'));
      return;
    }
    chamadaAtualId = json.chamada_id;
    outroId = paraId;
    outroNome = paraNome || 'utilizador';
    estado = 'a_chamar';
    KGSons.tocarChamar();
    renderizar();
    sinalizarEstado('chamada:iniciar');
    iniciarAudio();
  }

  async function atender() {
    if (estado !== 'a_receber' || chamadaAtualId == null) return;
    const { ok } = await pedir('/api/chamada/responder.php', { chamada_id: chamadaAtualId, acao: 'atender' });
    if (!ok) return;
    KGSons.pararToqueIncoming();
    estado = 'ativa';
    renderizar();
    sinalizarEstado('chamada:atender');
    atenderAudio();
  }

  async function recusar() {
    if (estado !== 'a_receber' || chamadaAtualId == null) return;
    await pedir('/api/chamada/responder.php', { chamada_id: chamadaAtualId, acao: 'recusar' });
    sinalizarEstado('chamada:recusar');
    reiniciar();
  }

  async function desligar() {
    if ((estado !== 'a_chamar' && estado !== 'ativa') || chamadaAtualId == null) return;
    await pedir('/api/chamada/desligar.php', { chamada_id: chamadaAtualId });
    sinalizarEstado('chamada:desligar');
    reiniciar();
  }

  // Fonte de verdade: o que está na BD agora para este utilizador. Chamado
  // a cada tick do polling e, como atalho, sempre que o socket.io avisa que
  // algo mudou do outro lado — nos dois casos cai aqui, nunca há dois
  // caminhos de estado divergentes.
  async function verificarAgora() {
    if (meuId == null) return;
    let resp;
    try {
      resp = await fetch('/api/chamada/verificar.php', { credentials: 'same-origin' });
    } catch (e) { return; }
    if (!resp.ok) return;
    const json = await resp.json().catch(() => null);
    const chamada = json && json.chamada;
    if (!chamada) return;

    if (chamada.id === chamadaAtualId) {
      if (chamada.estado === 'atendida' && estado === 'a_chamar') {
        estado = 'ativa';
        renderizar();
      } else if (chamada.estado === 'recusada' && estado === 'a_chamar') {
        mostrarTerminada(`Chamada recusada por ${outroNome}`);
      } else if (chamada.estado === 'terminada' && estado !== 'inativa' && estado !== 'terminada') {
        mostrarTerminada(estado === 'a_receber' ? 'Chamada perdida' : 'Chamada terminada');
      }
      return;
    }

    if (estado === 'inativa' && chamada.estado === 'iniciada' && chamada.destinatario_id === meuId) {
      chamadaAtualId = chamada.id;
      outroId = chamada.remetente_id;
      outroNome = chamada.remetente_nome;
      estado = 'a_receber';
      KGSons.iniciarToqueIncoming();
      renderizar();
    }
  }

  function configurar(opts) {
    rt = opts.rt || null;
    meuId = opts.meuId;
    csrfToken = opts.csrfToken;
    if (meuId == null) return;

    verificarAgora();
    clearInterval(pollTimer);
    pollTimer = setInterval(verificarAgora, INTERVALO_POLL_MS);

    if (rt) {
      ['chamada:iniciar', 'chamada:atender', 'chamada:recusar', 'chamada:desligar'].forEach((evento) => {
        rt.on(evento, (payload) => {
          if (payload && payload.paraUtilizadorId === meuId) verificarAgora();
        });
      });
    }
  }

  global.KGChamada = { configurar, iniciar };
})(window);
