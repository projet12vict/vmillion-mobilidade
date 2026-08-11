/**
 * V-MILLION — Mapa unificado: Leaflet puro (Canvas 2D), sem WebGL/3D.
 * Sem dependência de GPU, sem perda de contexto, sem terreno/elevação.
 *
 * API devolvida por KGMap.create():
 *   engine                         -> '2d'
 *   addMarker(id, tipo, lat, lng, {classes, popupHtml, badge, html, draggable, onDragEnd})
 *   updateMarker(id, lat, lng)
 *   setMarkerDraggable(id, draggable)
 *   removeMarker(id)
 *   drawRoute(id, coordsLatLng, estilo)  -> [[lat,lng], ...], estilo opcional (color, dashArray, weight, ...)
 *   clearRoute(id)
 *   setUserPosition(lat, lng)
 *   fitCaboVerde()
 *   fitBounds(latlngs, opts)
 *   flyTo(lat, lng, zoom)
 *   onMoveStart(cb) / onMoveEnd(cb)
 *
 * KGMap.create(containerId, { center, zoom, maxZoom }) — maxZoom por omissão
 * é 15 (carga gráfica reduzida); o editor de mapa do admin pode pedir mais.
 *
 * Helpers de veículo (KGMap.*, independentes da instância):
 *   veiculoIconeHtml(v)  -> HTML a passar em addMarker(..., { html }): emoji do
 *                           tipo, pastilha com a cor real e badge de lugares livres.
 *   veiculoPopupHtml(v)  -> HTML a passar em addMarker(..., { popupHtml }): matrícula,
 *                           tipo, cor, condutor e telefone (link WhatsApp) quando disponíveis.
 *                           v = { matricula, tipo, cor, condutor_nome, condutor_telefone,
 *                                 lugares_livres, posicao_fila } — campos em falta são omitidos.
 *   veiculoEstadoClasse(estado) -> classe CSS (na-fila/em-movimento/partiu/chegou) a passar
 *                           em addMarker(..., { classes }), a partir de veiculos.estado real.
 */
(function (global) {
  'use strict';

  const CABO_VERDE_BOUNDS = { latMin: 14.7, latMax: 17.2, lngMin: -25.4, lngMax: -22.7 };
  // tile.openfreemap.org só serve tiles vetoriais (pbf/mvt) — não responde
  // a pedidos raster {z}/{x}/{y}.png (confirmado: liga falha, HTTP 000),
  // o que deixava o mapa cinzento. O tile server público padrão do
  // OpenStreetMap serve PNG raster diretamente, compatível com L.tileLayer.
  const OSM_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const OSM_SUBDOMAINS = ['a', 'b', 'c'];
  const MAX_ZOOM = 15;

  function coordenadasValidas(lat, lng) {
    return lat >= CABO_VERDE_BOUNDS.latMin && lat <= CABO_VERDE_BOUNDS.latMax
      && lng >= CABO_VERDE_BOUNDS.lngMin && lng <= CABO_VERDE_BOUNDS.lngMax;
  }

  const VEICULO_EMOJI = { hiace: '🚐', taxi: '🚖', autocarro: '🚌' };
  const VEICULO_TIPO_LABEL = { hiace: 'Hiace', taxi: 'Táxi', autocarro: 'Autocarro' };
  const VEICULO_COR_HEX = {
    branco: '#f8fafc', preto: '#111827', cinza: '#9ca3af', cinzento: '#9ca3af',
    prata: '#cbd5e1', azul: '#2563eb', vermelho: '#dc2626', verde: '#16a34a',
    amarelo: '#eab308', laranja: '#f97316', castanho: '#78350f', roxo: '#7c3aed',
    dourado: '#ca8a04', bege: '#d6c9a8', vinho: '#7f1d1d', 'azul escuro': '#1e3a8a',
  };
  // Classe CSS por estado real do veículo (veiculos.estado) — usada em todos
  // os pontos do código que desenham um marcador de veículo, para nunca
  // mostrar um estado inventado/hardcoded (relatório "posição real").
  const VEICULO_ESTADO_CLASSE = {
    no_ponto: '', na_fila: 'na-fila', em_movimento: 'em-movimento',
    partiu_da_fila: 'partiu', chegou_destino: 'chegou',
  };

  function veiculoEstadoClasse(estado) {
    return VEICULO_ESTADO_CLASSE[estado] ?? '';
  }

  function escaparHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function corVeiculoParaHex(cor) {
    if (!cor) return '#94a3b8';
    const chave = cor.trim().toLowerCase();
    return VEICULO_COR_HEX[chave] || '#94a3b8';
  }

  // HTML injetado dentro do marcador do veículo: emoji do tipo, pastilha com a
  // cor real do carro, badge de lugares livres (secção C do relatório), e
  // opcionalmente uma seta de navegação (opts.comSeta) — só faz sentido no
  // próprio veículo do condutor em navegação, não em veículos só listados.
  function veiculoIconeHtml(v, opts = {}) {
    const emoji = VEICULO_EMOJI[v.tipo] || '🚗';
    const hex = corVeiculoParaHex(v.cor);
    const lugares = v.lugares_livres != null ? `<div class="lugares">${escaparHtml(v.lugares_livres)}</div>` : '';
    const seta = opts.comSeta ? '<span class="kg-marker-seta">▲</span>' : '';
    return `${seta}<span class="kg-marker-emoji">${emoji}</span><span class="kg-marker-cor" style="background:${hex}"></span>${lugares}`;
  }

  // Popup completo do veículo: matrícula, tipo, cor, condutor e telefone
  // (com link WhatsApp) quando disponível — nunca inventa dados em falta.
  function veiculoPopupHtml(v) {
    const emoji = VEICULO_EMOJI[v.tipo] || '🚗';
    const tipoLabel = VEICULO_TIPO_LABEL[v.tipo] || v.tipo || '';
    const linhas = [];
    if (v.matricula) linhas.push(`<strong>${escaparHtml(v.matricula)}</strong>`);
    const detalhe = [[emoji, tipoLabel].filter(Boolean).join(' '), v.cor].filter(Boolean).join(' · ');
    if (detalhe) linhas.push(`<span>${escaparHtml(detalhe)}</span>`);
    if (v.condutor_nome) linhas.push(`<span>👤 ${escaparHtml(v.condutor_nome)}</span>`);
    if (v.destino_nome) linhas.push(`<span>🏁 Destino: ${escaparHtml(v.destino_nome)}</span>`);
    if (v.condutor_telefone) {
      const wa = v.condutor_telefone.replace(/\D/g, '');
      linhas.push(`<a href="https://wa.me/${escaparHtml(wa)}" target="_blank" rel="noopener">📱 ${escaparHtml(v.condutor_telefone)}</a>`);
    }
    if (v.lugares_livres != null) {
      linhas.push(`<span>📍 ${escaparHtml(v.lugares_livres)} lugares livres${v.posicao_fila ? ' · Fila #' + escaparHtml(v.posicao_fila) : ''}</span>`);
    }
    return `<div class="kg-veiculo-popup">${linhas.join('<br>')}</div>`;
  }

  function criarElementoMarcador(tipo, classesExtra, badgeTexto) {
    const el = document.createElement('div');
    el.className = `kg-marker kg-marker--${tipo}${classesExtra ? ' ' + classesExtra : ''}`;
    if (badgeTexto) {
      const badge = document.createElement('div');
      badge.className = 'kg-marker-badge';
      badge.textContent = badgeTexto;
      el.appendChild(badge);
    }
    return el;
  }

  // ------------------------------------------------------------------
  // Motor 2D — Leaflet
  // ------------------------------------------------------------------
  function criarMapa2D(containerId, opts) {
    // maxZoom por omissão fica em MAX_ZOOM (carga gráfica reduzida nos mapas
    // públicos — passageiro/condutor); o editor de mapa do admin pode pedir
    // um maxZoom maior (opts.maxZoom) para ajuste preciso de coordenadas.
    const maxZoom = opts.maxZoom || MAX_ZOOM;
    const map = L.map(containerId, {
      center: [opts.center.lat, opts.center.lng],
      zoom: opts.zoom || 12,
      maxZoom,
      maxBounds: [
        [CABO_VERDE_BOUNDS.latMin, CABO_VERDE_BOUNDS.lngMin],
        [CABO_VERDE_BOUNDS.latMax, CABO_VERDE_BOUNDS.lngMax],
      ],
      zoomControl: false,
      preferCanvas: true,
    });

    L.tileLayer(OSM_TILES, {
      attribution: '&copy; OpenStreetMap contributors',
      subdomains: OSM_SUBDOMAINS,
      maxZoom,
    }).addTo(map);

    L.control.zoom({ position: 'topright' }).addTo(map);

    const markers = new Map();
    const routes = new Map();

    return {
      engine: '2d',
      raw: map,
      addMarker(id, tipo, lat, lng, options = {}) {
        if (!coordenadasValidas(lat, lng)) return null;
        const el = criarElementoMarcador(tipo, options.classes, options.badge);
        if (options.html) el.innerHTML += options.html;
        const icon = L.divIcon({ className: '', html: el.outerHTML, iconSize: [42, 42], iconAnchor: [21, 21] });
        const marker = L.marker([lat, lng], { icon, draggable: !!options.draggable }).addTo(map);
        if (options.popupHtml) marker.bindPopup(options.popupHtml);
        if (options.onDragEnd) {
          marker.on('dragend', () => {
            const pos = marker.getLatLng();
            if (!coordenadasValidas(pos.lat, pos.lng)) { marker.setLatLng([lat, lng]); return; }
            options.onDragEnd(pos.lat, pos.lng, marker);
          });
        }
        markers.set(id, marker);
        return marker;
      },
      updateMarker(id, lat, lng) {
        const m = markers.get(id);
        if (m && coordenadasValidas(lat, lng)) m.setLatLng([lat, lng]);
      },
      // Roda só a seta (.kg-marker-seta) dentro do marcador do veículo, sem
      // recriar o marcador inteiro — chamado a cada posição GPS nova, seria
      // caro (e piscava) reconstruir o ícone completo todas as vezes.
      setMarkerRotation(id, graus) {
        const m = markers.get(id);
        const el = m && m.getElement && m.getElement();
        const seta = el && el.querySelector('.kg-marker-seta');
        if (seta) {
          seta.style.transform = `rotate(${graus}deg)`;
          seta.classList.remove('kg-marker-seta--oculta');
        }
      },
      // Ativa/desativa o arrasto de um marcador já criado (ex: botão
      // "Ativar arrasto" no popup, para evitar mover pontos por engano).
      setMarkerDraggable(id, draggable) {
        const m = markers.get(id);
        if (!m || !m.dragging) return;
        if (draggable) m.dragging.enable(); else m.dragging.disable();
      },
      removeMarker(id) {
        const m = markers.get(id);
        if (m) { map.removeLayer(m); markers.delete(id); }
      },
      drawRoute(id, coordsLatLng, estilo = {}) {
        this.clearRoute(id);
        const linha = L.polyline(coordsLatLng, Object.assign({ color: '#003893', weight: 4, opacity: 0.85 }, estilo)).addTo(map);
        routes.set(id, linha);
      },
      clearRoute(id) {
        const linha = routes.get(id);
        if (linha) { map.removeLayer(linha); routes.delete(id); }
      },
      setUserPosition(lat, lng) {
        // Inclui sempre a seta (mesmo sem rota ainda) para setMarkerRotation
        // conseguir rodá-la mais tarde sem recriar o marcador — chamado só
        // uma vez no arranque; as posições seguintes usam updateMarker.
        this.addMarker('eu', 'eu', lat, lng, { html: '<span class="kg-marker-seta kg-marker-seta--oculta">▲</span>' });
        this.updateMarker('eu', lat, lng);
      },
      fitCaboVerde() {
        map.fitBounds([
          [CABO_VERDE_BOUNDS.latMin, CABO_VERDE_BOUNDS.lngMin],
          [CABO_VERDE_BOUNDS.latMax, CABO_VERDE_BOUNDS.lngMax],
        ]);
      },
      // Ajusta o mapa para mostrar todos os pontos dados (ex: editor de mapa
      // do admin, ao carregar — evita zoom fixo esconder pontos distantes).
      fitBounds(latlngs, opts = {}) {
        if (!latlngs || !latlngs.length) return;
        map.fitBounds(latlngs, Object.assign({ maxZoom: opts.maxZoom || 16, padding: [40, 40] }, opts));
      },
      flyTo(lat, lng, zoom) {
        map.flyTo([lat, lng], zoom || map.getZoom());
      },
      // Recentragem "leve" para seguir o GPS em tempo real (chamado a cada
      // leitura, potencialmente a cada poucos segundos) — sem alterar o
      // zoom, ao contrário de flyTo, que é para saltos ocasionais e
      // deliberados (botão "recentrar", mudança de modo).
      panTo(lat, lng) {
        map.panTo([lat, lng], { animate: true, duration: 0.5 });
      },
      onMoveStart(cb) { map.on('movestart', cb); },
      onMoveEnd(cb) { map.on('moveend', cb); },
      // Ao contrário de onMoveStart (dispara também para movimentos
      // programáticos, como o nosso próprio panTo/flyTo — usá-lo aqui
      // criaria um ciclo em que o próprio seguimento automático se desligava
      // a si mesmo), dragstart só dispara quando é o utilizador a arrastar
      // o mapa com o dedo/rato — é o sinal certo para "parou de seguir".
      onDragStart(cb) { map.on('dragstart', cb); },
      onClick(cb) { map.on('click', (e) => cb(e.latlng.lat, e.latlng.lng)); },
    };
  }

  const KGMap = {
    create(containerId, opts) {
      const options = Object.assign({ center: { lat: 14.9177, lng: -23.5092 }, zoom: 13 }, opts);
      return criarMapa2D(containerId, options);
    },
    coordenadasValidas,
  };

  // Pausar animações de pulso durante o arrastar do mapa (secção 17.2)
  function ligarPausaDeAnimacoes(mapaInstancia) {
    mapaInstancia.onMoveStart(() => document.body.classList.add('map-dragging'));
    mapaInstancia.onMoveEnd(() => document.body.classList.remove('map-dragging'));
  }

  // Proxy do backend (public/api/rota.php) para a instância própria do OSRM
  // (KG_OSRM_URL, só acessível a partir do servidor — não é chamada
  // diretamente do browser) — devolve também os dados em cache (Redis),
  // reduzindo tanto a carga no OSRM como a latência sentida no mapa.
  const ROTA_API_URL = '/api/rota.php';

  /**
   * Traça a rota real (por estrada) entre dois pontos, via OSRM (proxy do
   * backend), e desenha-a no mapa com mapa.drawRoute(id, ...).
   * Se o serviço falhar (rede, OSRM em baixo, timeout, etc.), cai
   * silenciosamente para uma linha reta entre origem e destino — nunca
   * deixa a rota em branco (o utilizador nunca vê mensagens de erro, secção 5.1).
   * @param {object} mapaInstancia — instância devolvida por KGMap.create()
   * @param {string} id — identificador da rota (para poder limpar/substituir)
   * @param {{lat:number,lng:number}} origem
   * @param {{lat:number,lng:number}} destino
   * @param {object} estilo — passado a drawRoute (cor, dashArray, ...)
   * @returns {Promise<{sucesso:boolean, distanciaM:?number, duracaoS:?number}>}
   *   distanciaM/duracaoS ficam null quando caiu no fallback de linha reta
   *   (nesse caso não há distância real de estrada para mostrar).
   */
  async function tracarRota(mapaInstancia, id, origem, destino, estilo) {
    if (!origem || !destino || !coordenadasValidas(origem.lat, origem.lng) || !coordenadasValidas(destino.lat, destino.lng)) {
      return { sucesso: false, distanciaM: null, duracaoS: null };
    }
    const linhaReta = () => {
      mapaInstancia.drawRoute(id, [[origem.lat, origem.lng], [destino.lat, destino.lng]], estilo);
      return { sucesso: false, distanciaM: null, duracaoS: null };
    };
    try {
      const params = new URLSearchParams({
        origem_lat: origem.lat, origem_lng: origem.lng,
        destino_lat: destino.lat, destino_lng: destino.lng,
      });
      const resp = await fetch(`${ROTA_API_URL}?${params}`);
      if (!resp.ok) return linhaReta();
      const json = await resp.json();
      const coords = json?.geometria;
      if (!json?.sucesso || !Array.isArray(coords) || !coords.length) return linhaReta();
      mapaInstancia.drawRoute(id, coords.map(([lng, lat]) => [lat, lng]), estilo);
      return { sucesso: true, distanciaM: json.distancia_m ?? null, duracaoS: json.duracao_s ?? null };
    } catch (e) {
      return linhaReta();
    }
  }

  // Distância haversine em metros entre duas coordenadas — usada para
  // detetar desvio de rota/ultrapassagem do destino (relatório do mapa,
  // "atalhos e rota dinâmica").
  function distanciaMetros(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  // Rumo (bearing) de um ponto para outro, em graus (0=Norte, 90=Este) —
  // usado para rodar a seta do marcador do veículo na direção do movimento
  // real (calculado entre duas posições GPS sucessivas) ou, sem movimento
  // ainda detetado, na direção do alvo de navegação (relatório do mapa,
  // tarefa D — "se o condutor estiver parado, a seta aponta para o alvo").
  function calcularRumo(lat1, lng1, lat2, lng2) {
    const toRad = (d) => (d * Math.PI) / 180;
    const toDeg = (r) => (r * 180) / Math.PI;
    const dLng = toRad(lng2 - lng1);
    const y = Math.sin(dLng) * Math.cos(toRad(lat2));
    const x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) - Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(dLng);
    return (toDeg(Math.atan2(y, x)) + 360) % 360;
  }

  KGMap.ligarPausaDeAnimacoes = ligarPausaDeAnimacoes;
  KGMap.tracarRota = tracarRota;
  KGMap.calcularRumo = calcularRumo;
  KGMap.distanciaMetros = distanciaMetros;
  KGMap.veiculoIconeHtml = veiculoIconeHtml;
  KGMap.veiculoPopupHtml = veiculoPopupHtml;
  KGMap.veiculoEstadoClasse = veiculoEstadoClasse;

  global.KGMap = KGMap;
})(window);
