/**
 * V-MILLION — Service Worker da PWA.
 *
 * Só faz cache de ficheiros estáticos (CSS/JS/ícones) — páginas (painéis),
 * API e socket.io passam sempre pela rede, nunca pelo cache. Isto é uma app
 * de transporte em tempo real (posição de veículos, chamadas, reservas):
 * servir dados antigos do cache seria pior do que não servir nada offline.
 */

const CACHE_NAME = 'vmillion-v1';
const ASSETS_ESTATICOS = [
  '/assets/css/kg-design-system.css',
  '/assets/css/kg-map-markers.css',
  '/assets/js/kg-sons.js',
  '/assets/js/kg-chamada.js',
  '/assets/js/kg-realtime.js',
  '/assets/js/kg-map.js',
  '/assets/js/kg-geolocation.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
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
      .catch(() => caches.match(e.request))
  );
});
