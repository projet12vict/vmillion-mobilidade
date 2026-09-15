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
  const PRECISAO_BOA_M = 25; // leitura de GPS a sério (não WiFi/rede): aceite de imediato
  const PRECISAO_TIMEOUT_MS = 15000; // não bloqueia para sempre: ao fim disto, usa o que houver
  // Depois da primeira leitura utilizável (<= PRECISAO_MAX_M) mas ainda longe
  // de boa, dá-se esta janela curta para aparecer uma melhor. Sem ela, bastava
  // uma leitura de 99 m para fixar a posição inicial do condutor quando o GPS
  // estava prestes a entregar uma de 10 m.
  const GRACA_PRECISAO_MS = 4000;
  // Deriva do GPS a parado: um "salto" menor do que o raio de erro de uma
  // leitura que piorou não é deslocação real. Limitado a este valor para nunca
  // engolir movimento verdadeiro quando o erro reportado é grande.
  const LIMITE_RUIDO_M = 20;

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
        <button type="button" class="vm-btn vm-btn--primario vm-btn--full" id="kg-geo-retry">Tentar novamente</button>
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
    // A melhor leitura vista até agora, e não a primeira: ao fim do tempo
    // limite entregava-se posInicial mesmo quando entretanto tinham chegado
    // leituras bastante melhores (só ainda não boas o suficiente para passar
    // o teto) — a posição inicial do condutor ficava a ser a pior de todas.
    let melhor = posInicial;
    let gracaId = null;
    const precisaoDe = (pos) => (pos && pos.coords.accuracy != null ? pos.coords.accuracy : Infinity);
    const resolver = (pos) => {
      if (resolvido) return;
      resolvido = true;
      clearTimeout(timeoutId);
      clearTimeout(gracaId);
      navigator.geolocation.clearWatch(watchId);
      hideOverlay();
      onGranted(pos);
    };
    const watchId = navigator.geolocation.watchPosition(
      (pos) => {
        const precisao = pos.coords.accuracy;
        if (precisao == null) { resolver(pos); return; }
        if (precisao < precisaoDe(melhor)) melhor = pos;
        if (precisao <= PRECISAO_BOA_M) { resolver(pos); return; }
        // Já dá para trabalhar, mas ainda não é um fix de GPS a sério: espera
        // GRACA_PRECISAO_MS por algo melhor e segue com o melhor que houver.
        if (precisao <= PRECISAO_MAX_M && gracaId === null) {
          gracaId = setTimeout(() => resolver(melhor), GRACA_PRECISAO_MS);
        }
      },
      () => { /* mantém a tentar — já há 'melhor' como rede de segurança no timeout */ },
      { enableHighAccuracy: true, maximumAge: 0, timeout: PRECISAO_TIMEOUT_MS }
    );
    const timeoutId = setTimeout(() => resolver(melhor), PRECISAO_TIMEOUT_MS);
  }

  // Estado partilhado por toda a página, e nao por chamada: kgEnsureGeolocation
  // e re-chamada pelo botao "Tentar novamente" e pela mudanca de permissao, e
  // cada chamada criava um PermissionStatus novo (com o onchange antigo ainda
  // pendurado) e podia deixar uma cadeia de retry anterior a correr em
  // paralelo — resultado: onGranted a disparar duas vezes e dois
  // watchPosition ativos ao mesmo tempo.
  let permStatus = null;
  let onGrantedAtual = null;
  let retryTimeoutId = null;

  /**
   * Garante que a geolocalização está disponível antes de liberar o painel.
   * @param {(pos: GeolocationPosition) => void} onGranted
   */
  function kgEnsureGeolocation(onGranted) {
    onGrantedAtual = onGranted;
    if (retryTimeoutId !== null) {
      clearTimeout(retryTimeoutId);
      retryTimeoutId = null;
    }

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
          onGrantedAtual(pos);
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
            retryTimeoutId = setTimeout(tryGetPosition, 3000);
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
      const aplicar = (status) => {
        if (status.state === 'denied') {
          showOverlay(() => kgEnsureGeolocation(onGrantedAtual));
          return;
        }
        tryGetPosition();
      };

      // Reutiliza o PermissionStatus ja obtido — o onchange so e registado
      // uma vez, senao cada "Tentar novamente" somava mais um handler a
      // disparar o overlay.
      if (permStatus) {
        aplicar(permStatus);
        return;
      }

      navigator.permissions.query({ name: 'geolocation' }).then((status) => {
        permStatus = status;
        status.onchange = () => {
          if (status.state === 'denied') showOverlay(() => kgEnsureGeolocation(onGrantedAtual));
        };
        aplicar(status);
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
   *
   * O 4.º argumento de onUpdate diz se a posição é uma reemissão da última
   * posição conhecida por perda de sinal ({ obsoleta: true }). Serve para o
   * mapa continuar a mostrar um marcador sem que essa coordenada velha seja
   * enviada ao servidor como se fosse a posição atual do veículo.
   *
   * @param {(lat:number, lng:number, pos:GeolocationPosition, info:{obsoleta:boolean}) => void} onUpdate
   * @param {{onLeituraBruta?: (lat:number, lng:number, pos:GeolocationPosition) => void}} [opts]
   *   'onLeituraBruta' recebe TODAS as leituras que passam o teto de precisão,
   *   sem o filtro dos 5 m nem o de deriva. Serve para desenhar o ponto do GPS
   *   exatamente onde a última leitura o põe: os filtros existem para decidir
   *   (enviar ao servidor, recalcular rota, medir rumo), onde uma leitura a
   *   mais custa caro, mas aplicados também ao desenho deixavam o ponto
   *   parado até 5 m atrás do sítio onde o condutor está mesmo.
   * @returns {number} watchId (usar com navigator.geolocation.clearWatch)
   */
  function kgWatchPosition(onUpdate, opts = {}) {
    const onLeituraBruta = typeof opts.onLeituraBruta === 'function' ? opts.onLeituraBruta : null;
    let ultimaLat = null;
    let ultimaLng = null;
    let ultimaPrecisao = null;
    let ultimaPosConhecida = null;
    let perdaDeSinalEmitida = false;

    return navigator.geolocation.watchPosition(
      (pos) => {
        const precisao = pos.coords.accuracy;
        if (precisao != null && precisao > PRECISAO_MAX_M) return;
        const { latitude, longitude } = pos.coords;
        // Antes de qualquer filtro: a leitura passou o teto de precisão, logo
        // é a melhor ideia disponível de onde o utilizador está agora.
        if (onLeituraBruta) onLeituraBruta(latitude, longitude, pos);
        if (ultimaLat !== null) {
          const dist = haversine(ultimaLat, ultimaLng, latitude, longitude);
          if (dist < MIN_DIST_M) return;
          // Deriva do GPS: uma leitura MENOS precisa do que a anterior que
          // "anda" menos do que o seu próprio raio de erro não descreve
          // deslocação nenhuma — é o marcador a tremer à volta do sítio onde o
          // condutor está mesmo parado. O teto de LIMITE_RUIDO_M garante que
          // isto nunca engole movimento a sério: a 50 km/h percorrem-se ~14 m
          // por segundo, e um erro reportado grande não pode servir de
          // desculpa para ignorar a estrada inteira.
          if (precisao != null && ultimaPrecisao != null && precisao > ultimaPrecisao
              && dist < Math.min(precisao, LIMITE_RUIDO_M)) return;
        }
        ultimaLat = latitude;
        ultimaLng = longitude;
        ultimaPrecisao = precisao;
        ultimaPosConhecida = pos;
        perdaDeSinalEmitida = false;
        onUpdate(latitude, longitude, pos, { obsoleta: false });
      },
      () => {
        // Sinal perdido: o watchPosition volta a chamar isto a cada timeout
        // (10s) enquanto não houver fix. Reemitir a última posição boa serve
        // só para o mapa não ficar sem marcador, por isso é feito UMA vez por
        // perda de sinal e marcado como obsoleto — antes, cada repetição
        // seguia para o servidor e mantinha o veículo "vivo" numa coordenada
        // que ele já tinha deixado há muito.
        if (!ultimaPosConhecida || perdaDeSinalEmitida) return;
        perdaDeSinalEmitida = true;
        onUpdate(ultimaPosConhecida.coords.latitude, ultimaPosConhecida.coords.longitude, ultimaPosConhecida, { obsoleta: true });
      },
      // maximumAge 0 (era 2000): com cache, o browser podia devolver a mesma
      // leitura de há 2 s como se fosse nova — a posição desenhada no mapa
      // ficava sistematicamente atrás da real, que a 50 km/h são ~28 m.
      { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
    );
  }

  global.kgEnsureGeolocation = kgEnsureGeolocation;
  global.kgWatchPosition = kgWatchPosition;
})(window);
