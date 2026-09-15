/**
 * V-MILLION — Service Worker da PWA.
 *
 * Só faz cache de ficheiros estáticos (CSS/JS/ícones) — páginas (painéis),
 * API e socket.io passam sempre pela rede, nunca pelo cache. Isto é uma app
 * de transporte em tempo real (posição de veículos, chamadas, reservas):
 * servir dados antigos do cache seria pior do que não servir nada offline.
 */

const CACHE_NAME = 'vmillion-v5';
const ASSETS_ESTATICOS = [
  '/assets/css/kg-design-system.css',
  '/assets/css/kg-map-markers.css',
  '/assets/js/kg-sons.js',
  '/assets/js/kg-chamada.js',
  '/assets/js/kg-push.js',
  '/assets/js/kg-realtime.js',
  '/assets/js/kg-map.js',
  '/assets/js/kg-geolocation.js',
  '/assets/img/logo.svg',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/icon-maskable-192.png',
  '/assets/icons/icon-maskable-512.png',
  '/assets/icons/apple-touch-icon.png',
  '/favicon.svg',
  '/favicon.ico',
  '/manifest.json',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS_ESTATICOS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((chaves) => Promise.all(chaves.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Rede primeiro, cache como reserva — não o contrário. Isto é um projeto em
// desenvolvimento ativo (estes ficheiros mudam com frequência); servir
// sempre do cache primeiro (como estava) escondia silenciosamente todas as
// correções já publicadas em qualquer dispositivo que já tivesse aberto a
// app uma vez, mesmo depois de o servidor já ter o código novo — foi
// exatamente isto que causou "corrigido mas continua a falhar" mais do que
// uma vez. Com internet, é sempre a versão mais recente; o cache só entra
// quando a rede falha (é o caso genuíno de offline).
self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin || !ASSETS_ESTATICOS.includes(url.pathname)) return;

  e.respondWith(
    fetch(e.request)
      .then((res) => {
        const copia = res.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(e.request, copia));
        return res;
      })
      // ignoreSearch: os <script>/<link> dos painéis passaram a levar
      // ?v=<data de modificação> (ver App\Support\Asset) e o cache foi
      // preenchido no install com os caminhos sem versão — sem isto, a
      // reserva offline nunca acertava em nada.
      .catch(() => caches.match(e.request, { ignoreSearch: true }))
  );
});

// ---------------------------------------------------------------------
// Notificações push das chamadas e da viagem
// ---------------------------------------------------------------------
// Isto é o que faz uma chamada chegar com a app fechada ou o telemóvel
// bloqueado: o Service Worker é acordado pelo sistema, sem nenhum separador
// aberto. O polling de kg-chamada.js e o socket.io só existem enquanto há um
// painel vivo, por isso, sem este bloco, uma chamada para quem tivesse
// acabado de arrumar o telemóvel tocava só do lado de quem ligou.
//
// Pela mesma razão passam por aqui os avisos do ponto (o veículo entrou no
// ponto, entrou na fila, saiu, chegou): quem está à espera no ponto tem o
// telemóvel no bolso, não o painel aberto à frente dos olhos.
//
// O conteúdo chega cifrado de ponta a ponta (RFC 8291): o serviço de push do
// fabricante encaminha o pacote sem o conseguir ler.

const TAG_CHAMADA = 'vm-chamada';
const TAG_VIAGEM = 'vm-viagem';
const TAG_PEDIDO = 'vm-pedido';

self.addEventListener('push', (e) => {
  let dados = {};
  try {
    dados = e.data ? e.data.json() : {};
  } catch (err) {
    dados = {};
  }

  // A chamada terminou antes de ser atendida (quem ligou desistiu, ou foi
  // atendida noutro dispositivo do mesmo utilizador): fecha o aviso em vez
  // de o deixar a tocar para uma chamada que já não existe.
  if (dados.tipo === 'chamada_fim') {
    e.waitUntil(
      self.registration.getNotifications({ tag: TAG_CHAMADA })
        .then((abertas) => abertas.forEach((n) => n.close()))
    );
    return;
  }

  // Aviso do ponto: o título e o texto vêm prontos do servidor, que é quem
  // sabe a matrícula e o nome do ponto (ver App\Services\AvisoViagemService).
  if (dados.tipo === 'viagem') {
    e.waitUntil(
      self.registration.showNotification(dados.titulo || 'V-MILLION', {
        body: dados.corpo || '',
        icon: '/assets/icons/icon-192.png',
        badge: '/assets/icons/icon-192.png',
        // Tag própria: partilhar a das chamadas fazia o aviso da viagem
        // fechar-se com o 'chamada_fim' de uma chamada qualquer.
        tag: TAG_VIAGEM,
        renotify: true,
        // Ao contrário de uma chamada, isto não espera decisão nenhuma: é uma
        // informação, e fica-se pela vibração curta do sistema.
        vibrate: [200, 100, 200],
        data: { url: dados.url || '/passageiro/painel' },
      })
    );
    return;
  }

  // Pedido de passageiro para o condutor (ver App\Services\AvisoPedidoService).
  // É o aviso para o condutor distraído, com o telemóvel bloqueado no
  // suporte: vibração longa e fica no ecrã até ele lhe tocar — um pedido
  // perdido de vista é um passageiro deixado à espera.
  if (dados.tipo === 'pedido') {
    e.waitUntil(
      self.registration.showNotification(dados.titulo || 'Novo pedido', {
        body: dados.corpo || '',
        icon: '/assets/icons/icon-192.png',
        badge: '/assets/icons/icon-192.png',
        // Tag própria por pedido: dois passageiros seguidos são dois avisos,
        // e nenhum fecha a chamada ou o aviso da viagem.
        tag: `${TAG_PEDIDO}-${dados.reserva_id || ''}`,
        renotify: true,
        requireInteraction: true,
        vibrate: [400, 150, 400, 150, 400],
        data: { url: dados.url || '/condutor/painel' },
      })
    );
    return;
  }

  if (dados.tipo !== 'chamada') return;

  const quem = dados.de || 'Alguém';

  e.waitUntil(
    self.registration.showNotification('Chamada a receber', {
      body: `${quem} está a ligar.`,
      icon: '/assets/icons/icon-192.png',
      badge: '/assets/icons/icon-192.png',
      // Mesma tag = duas chamadas seguidas não empilham dois avisos;
      // renotify faz o telemóvel voltar a vibrar mesmo assim.
      tag: TAG_CHAMADA,
      renotify: true,
      // Uma chamada não é um aviso que se possa perder de vista: fica no ecrã
      // até haver uma decisão (onde o sistema o permitir).
      requireInteraction: true,
      vibrate: [300, 150, 300, 150, 300],
      data: { url: dados.url || '/', chamadaId: dados.chamada_id || null },
      actions: [{ action: 'atender', title: 'Abrir' }],
    })
  );
});

// Tocar na notificação abre o painel — e reutiliza um separador já aberto em
// vez de abrir mais um, para o utilizador não acabar com a app duplicada e
// duas ligações de tempo real à mesma conta.
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const destino = (e.notification.data && e.notification.data.url) || '/';

  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((janelas) => {
      for (const janela of janelas) {
        if (new URL(janela.url).pathname === destino && 'focus' in janela) return janela.focus();
      }
      return self.clients.openWindow(destino);
    })
  );
});
