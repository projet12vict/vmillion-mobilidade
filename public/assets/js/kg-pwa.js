/**
 * V-MILLION — Regista o Service Worker da PWA (ver sw.js). Incluir com
 * <script src="/assets/js/kg-pwa.js"> em qualquer página pública.
 */
(function () {
  'use strict';
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => { /* sem SW, a página continua a funcionar normalmente */ });
    });
  }
})();
