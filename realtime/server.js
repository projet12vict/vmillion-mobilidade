/**
 * V-MILLION — Serviço de tempo real (Socket.io).
 * PHP/Apache serve o site; este processo Node à parte trata apenas dos
 * eventos em tempo real (posição de veículos, reservas, SOS) — secção 5.4.
 *
 * Arranque: node realtime/server.js
 * Variáveis de ambiente: KG_REALTIME_PORT (default 3001), KG_CORS_ORIGIN
 */

'use strict';

const { Server } = require('socket.io');

const PORT = process.env.KG_REALTIME_PORT || 3001;
// Em produção/piloto o Node só recebe pedidos via proxy interno do Apache
// (ver public/.htaccess), nunca diretamente do browser — por isso o Origin
// visto aqui é sempre o da própria página (http ou https, porta variável
// consoante o setup local: XAMPP, `php -S`, etc.), não um domínio de
// terceiros. Por omissão aceita qualquer origem localhost/127.0.0.1;
// KG_CORS_ORIGIN (lista separada por vírgulas) substitui isto para o
// domínio real do piloto.
const CORS_ORIGIN = process.env.KG_CORS_ORIGIN
  ? process.env.KG_CORS_ORIGIN.split(',').map((s) => s.trim())
  : [/^https?:\/\/localhost(:\d+)?$/, /^https?:\/\/127\.0\.0\.1(:\d+)?$/];

const io = new Server(PORT, {
  cors: { origin: CORS_ORIGIN, methods: ['GET', 'POST'] },
  pingInterval: 10000,
  pingTimeout: 5000,
});

// Salas: 'ponto:<id>' (passageiros/condutores num ponto), 'veiculo:<id>',
// 'passageiro:<id>', 'admin:sos' (central de alarmes).
io.on('connection', (socket) => {
  socket.on('join', (sala) => {
    if (typeof sala === 'string' && sala.length < 64) socket.join(sala);
  });

  socket.on('leave', (sala) => {
    if (typeof sala === 'string') socket.leave(sala);
  });

  // Condutor atualiza posição -> notifica passageiros do veículo + admins.
  socket.on('veiculo:posicao', (payload) => {
    if (!payload || typeof payload.veiculoId === 'undefined') return;
    io.to(`veiculo:${payload.veiculoId}`).emit('veiculo:posicao', payload);
  });

  // Lotação do veículo mudou (passageiro embarcou/desceu) -> notifica todos
  // os que seguem este veículo (passageiro a bordo + outros à espera).
  socket.on('veiculo:lugares', (payload) => {
    if (!payload || typeof payload.veiculoId === 'undefined') return;
    io.to(`veiculo:${payload.veiculoId}`).emit('veiculo:lugares', payload);
  });

  // Nova mensagem de comunicação (autofalante) passageiro <-> condutor.
  socket.on('comunicacao:nova', (payload) => {
    if (!payload || typeof payload.veiculoId === 'undefined') return;
    io.to(`veiculo:${payload.veiculoId}`).emit('comunicacao:nova', payload);
  });

  // Notificação do Super Admin para um utilizador específico ou broadcast.
  // Cada utilizador autenticado entra na sala 'utilizador:<id>' ao carregar o painel.
  socket.on('notificacao:nova', (payload) => {
    if (!payload) return;
    if (payload.destinatarioId) {
      io.to(`utilizador:${payload.destinatarioId}`).emit('notificacao:nova', payload);
    } else {
      io.emit('notificacao:nova', payload);
    }
  });

  // Passageiro reserva lugar -> notifica o condutor.
  socket.on('reserva:nova', (payload) => {
    if (!payload || typeof payload.veiculoId === 'undefined') return;
    io.to(`veiculo:${payload.veiculoId}`).emit('reserva:nova', payload);
  });

  // Condutor confirma/recusa -> notifica o passageiro.
  socket.on('reserva:atualizada', (payload) => {
    if (!payload || typeof payload.passageiroId === 'undefined') return;
    io.to(`passageiro:${payload.passageiroId}`).emit('reserva:atualizada', payload);
  });

  // Condutor reclama ("Ir buscar") um pedido urbano em aberto -> avisa todos
  // os outros condutores em tempo real para o removerem já da lista/mapa,
  // em vez de só desaparecer no próximo poll (até 10s depois — tempo
  // suficiente para dois condutores se dirigirem ao mesmo passageiro).
  // Sem sala própria (qualquer condutor pode estar a ver o pedido): broadcast
  // simples, tal como notificacao:nova sem destinatarioId.
  socket.on('urbano:reclamado', (payload) => {
    if (!payload || typeof payload.reservaId === 'undefined') return;
    io.emit('urbano:reclamado', payload);
  });

  // SOS -> notifica a central de alarmes (admin) em tempo real.
  socket.on('sos:ativado', (payload) => {
    io.to('admin:sos').emit('sos:ativado', payload);
  });

  socket.on('sos:atualizado', (payload) => {
    io.emit('sos:atualizado', payload);
  });

  // Chamada simulada (visual + sonora) entre passageiro e condutor — sem
  // áudio real (não há WebRTC aqui), só o sinal de estado (a chamar,
  // recebida, atendida, recusada, desligada) para a app do outro lado.
  ['chamada:iniciar', 'chamada:atender', 'chamada:recusar', 'chamada:desligar'].forEach((evento) => {
    socket.on(evento, (payload) => {
      if (!payload || typeof payload.paraUtilizadorId === 'undefined') return;
      io.to(`utilizador:${payload.paraUtilizadorId}`).emit(evento, payload);
    });
  });
});

console.log(`[V-MILLION] Serviço de tempo real ligado na porta ${PORT}`);
