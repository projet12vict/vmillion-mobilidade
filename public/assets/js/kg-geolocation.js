/**
 * V-MILLION — Geolocalização (secção 5.2) + captura contínua de GPS.
 * O mapa em si já não depende disto (ver painel.php: KGMap.create() corre
 * antes, com um centro por omissão) — só bloqueia com overlay quando a
 * permissão é mesmo negada (err.code 1); GPS lento/sem fix ainda mostra um
 * aviso leve e continua a tentar, nunca trava o resto do painel.
 */
(function (global) {
  'use strict';

  const OVERLAY_ID = 'kg-geo-overlay';
  const MIN_DIST_M = 5; // filtragem de ruído: só emitir posição se moveu > 5m
  const PRECISAO_MAX_M = 100; // acima disto, espera-se por uma leitura melhor antes de confiar
  const PRECISAO_TIMEOUT_MS = 15000; // não bloqueia para sempre: ao fim disto, usa o que houver

  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function overlayEl() {
    let overlay = document.getElementById(OVERLAY_ID);
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = OVERLAY_ID;
      overlay.className = 'kg-geo-overlay';
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  // Reconstrói sempre o conteúdo (não só mostra) — o mesmo elemento é
  // partilhado com showAguardandoPrecisao(), que tem texto/botão diferentes.
  function showOverlay(onRetry) {
    const overlay = overlayEl();
    overlay.innerHTML = `
      <div class="kg-geo-overlay__box">
        <div class="kg-geo-overlay__icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7z"/>
            <circle cx="12" cy="9" r="2.5"/>
          </svg>
        </div>
        <h3 class="kg-geo-overlay__title">Localização necessária</h3>
        <p class="kg-geo-overlay__text">Para usar o V-MILLION, precisa de ativar a localização do seu dispositivo. Vá a Definições &gt; Localização e ative-a.</p>
        <button type="button" class="kg-btn kg-btn--primario kg-btn--full" id="kg-geo-retry">Tentar novamente</button>
      </div>`;
    overlay.style.display = 'flex';
    document.getElementById('kg-geo-retry').addEventListener('click', onRetry);
  }

  // Diferente de showOverlay(): a permissão já foi concedida, só a precisão
  // ainda não chega — não bloqueia com pedido de ação, só avisa que está a
  // afinar (aguardarMelhorPrecisao trata do timeout, nunca fica preso aqui).
  function showAguardandoPrecisao() {
    const overlay = overlayEl();
    overlay.innerHTML = `
      <div class="kg-geo-overlay__box">
        <div class="kg-geo-overlay__icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7z"/>
            <circle cx="12" cy="9" r="2.5"/>
          </svg>
        </div>
        <h3 class="kg-geo-overlay__title">A aguardar localização...</h3>
        <p class="kg-geo-overlay__text">O sinal de GPS ainda está impreciso. A afinar a sua posição — é normal demorar alguns segundos, sobretudo em espaços fechados.</p>
      </div>`;
    overlay.style.display = 'flex';
  }

  function hideOverlay() {
    const overlay = document.getElementById(OVERLAY_ID);
    if (overlay) overlay.style.display = 'none';
  }

  // A primeira leitura veio pouco precisa (>100m, ex: posicionamento por
  // WiFi/IP em vez de GPS de verdade) — em vez de confiar nela às cegas
  // (o passageiro "aparece no mar"), espera por uma leitura melhor, com um
  // limite de tempo para nunca bloquear a app indefinidamente num
  // dispositivo/local onde 100m nunca vai ser possível (indoors, etc.).
  function aguardarMelhorPrecisao(posInicial, onGranted) {
    showAguardandoPrecisao();
    let resolvido = false;
    const resolver = (pos) => {
      if (resolvido) return;
      resolvido = true;
      clearTimeout(timeoutId);
      navigator.geolocation.clearWatch(watchId);
      hideOverlay();
      onGranted(pos);
    };
    const watchId = navigator.geolocation.watchPosition(
      (pos) => {
        if (pos.coords.accuracy == null || pos.coords.accuracy <= PRECISAO_MAX_M) resolver(pos);
      },
      () => { /* mantém a tentar — já há posInicial como rede de segurança no timeout */ },
      { enableHighAccuracy: true, maximumAge: 0, timeout: PRECISAO_TIMEOUT_MS }
    );
    const timeoutId = setTimeout(() => resolver(posInicial), PRECISAO_TIMEOUT_MS);
  }

  /**
   * Garante que a geolocalização está disponível antes de liberar o painel.
   * @param {(pos: GeolocationPosition) => void} onGranted
   */
  function kgEnsureGeolocation(onGranted) {
    if (!('geolocation' in navigator)) {
      console.warn('V-MILLION GPS: navigator.geolocation indisponível neste browser/contexto (ex: página servida sem HTTPS — a API só funciona em contexto seguro).');
      showOverlay(() => kgEnsureGeolocation(onGranted));
      return;
    }

    const tryGetPosition = () => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          console.log('V-MILLION GPS: leitura inicial, precisão =', pos.coords.accuracy, 'm');
          if (pos.coords.accuracy != null && pos.coords.accuracy > PRECISAO_MAX_M) {
            aguardarMelhorPrecisao(pos, onGranted);
            return;
          }
          hideOverlay();
          onGranted(pos);
        },
        (err) => {
          console.warn('V-MILLION GPS: erro ao obter posição, code =', err.code, err.message);
          // code 1 = PERMISSION_DENIED: é mesmo preciso o utilizador agir
          // (ativar a localização/dar permissão) — mantém o overlay bloqueante
          // com essa instrução. code 2/3 (POSITION_UNAVAILABLE/TIMEOUT) só
          // significam que o GPS ainda não obteve o primeiro fix (normal em
          // arranque a frio, sobretudo em espaços fechados) — mostrar aqui a
          // mesma mensagem "vá a Definições" seria enganador (o GPS já está
          // ativo), por isso mostra só o aviso leve e tenta de novo sozinho.
          if (err.code === 1) {
            showOverlay(tryGetPosition);
          } else {
            showAguardandoPrecisao();
            setTimeout(tryGetPosition, 3000);
          }
        },
        // enableHighAccuracy pode demorar bastante a obter o primeiro fix de
        // GPS (não WiFi/rede) num arranque a frio — 10s era demasiado pouco e
        // disparava o erro TIMEOUT com frequência, mesmo com o GPS a
        // funcionar normalmente.
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }
      );
    };

    if (navigator.permissions && navigator.permissions.query) {
      navigator.permissions.query({ name: 'geolocation' }).then((status) => {
        if (status.state === 'denied') {
          showOverlay(() => kgEnsureGeolocation(onGranted));
          return;
        }
        tryGetPosition();
        status.onchange = () => {
          if (status.state === 'denied') showOverlay(() => kgEnsureGeolocation(onGranted));
        };
      }).catch(tryGetPosition);
    } else {
      tryGetPosition();
    }
  }

  /**
   * Segue a posição do utilizador de forma contínua, filtrando ruído (<5m
   * ignorado) e leituras pouco precisas (>100m ignoradas — é isto que
   * evitava o marcador "saltar para o mar": kgEnsureGeolocation só filtrava
   * a primeira leitura, mas era esta função, chamada a cada atualização
   * seguinte, que alimentava o marcador no mapa e o POST para o servidor
   * sem nenhum filtro, deixando passar qualquer leitura má no meio da
   * sessão), e usando a última posição BOA conhecida se o sinal se perder.
   * @param {(lat:number, lng:number, pos:GeolocationPosition) => void} onUpdate
   * @returns {number} watchId (usar com navigator.geolocation.clearWatch)
   */
  function kgWatchPosition(onUpdate) {
    let ultimaLat = null;
    let ultimaLng = null;
    let ultimaPosConhecida = null;

    return navigator.geolocation.watchPosition(
      (pos) => {
        if (pos.coords.accuracy != null && pos.coords.accuracy > PRECISAO_MAX_M) return;
        const { latitude, longitude } = pos.coords;
        if (ultimaLat !== null) {
          const dist = haversine(ultimaLat, ultimaLng, latitude, longitude);
          if (dist < MIN_DIST_M) return;
        }
        ultimaLat = latitude;
        ultimaLng = longitude;
        ultimaPosConhecida = pos;
        onUpdate(latitude, longitude, pos);
      },
      () => {
        if (ultimaPosConhecida) {
          onUpdate(ultimaPosConhecida.coords.latitude, ultimaPosConhecida.coords.longitude, ultimaPosConhecida);
        }
      },
      { enableHighAccuracy: true, maximumAge: 2000, timeout: 10000 }
    );
  }

  global.kgEnsureGeolocation = kgEnsureGeolocation;
  global.kgWatchPosition = kgWatchPosition;
})(window);
