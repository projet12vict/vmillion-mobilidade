/**
 * V-MILLION — Mapa unificado: Leaflet puro (Canvas 2D), sem WebGL/3D.
 * Sem dependência de GPU, sem perda de contexto, sem terreno/elevação.
 *
 * API devolvida por KGMap.create():
 *   engine                         -> '2d'
 *   addMarker(id, tipo, lat, lng, {classes, popupHtml, badge, html, draggable, onDragEnd, zIndexOffset})
 *   updateMarker(id, lat, lng)          -> coloca o marcador (salta para lá)
 *   moveMarkerSuave(id, lat, lng, duracaoMs)  -> desliza até lá, sem saltos
 *   setMarkerDraggable(id, draggable)
 *   removeMarker(id)
 *   drawRoute(id, coordsLatLng, estilo)  -> [[lat,lng], ...], estilo opcional (color, dashArray, weight, ...)
 *   clearRoute(id)
 *   setUserPosition(lat, lng)
 *   setMarkerIcone(id, html, classe)
 *   setMarkerPopup(id, html)       -> troca/tira o popup sem recriar o marcador
 *   fitCaboVerde()
 *   fitBounds(latlngs, opts)
 *   flyTo(lat, lng, zoom)
 *   panTo(lat, lng)                     -> recentragem pontual (animação do Leaflet)
 *   seguirSuave(lat, lng, duracaoMs)    -> seguimento contínuo, sem parar entre leituras
 *   pararSeguimento()
 *   onMoveStart(cb) / onMoveEnd(cb)
 *   mostrarMunicipios(opts) / esconderMunicipios() / municipiosVisiveis()
 *                                  -> delimitação do país por concelho (fronteiras + nomes)
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
 *                                 lugares_livres, posicao_fila, ocupacao } — campos em falta
 *                           são omitidos. Com 'ocupacao' (só o condutor a tem, do próprio
 *                           veículo) o popup mostra o detalhe do lugar e a lista de quem vai
 *                           a bordo, e a pastilha do ícone passa a "a bordo/livres".
 *   veiculoChipHtml(v)   -> o mesmo emoji e pastilha de cor, mas em tamanho de ícone e sem
 *                           badge de lugares, para identificar um veículo fora do mapa
 *                           (cartão "Veículo ativo" do painel do condutor).
 *   ocupacaoPopupHtml(o) -> só o bloco de detalhe do lugar, para reaproveitar noutro popup.
 *                           o = { lugares_total, lugares_livres, a_bordo, reservados,
 *                                 passageiros: [{ nome, destino, assento, lugares }],
 *                                 aguardam: [{ nome, estado, destino, assento, lugares }] }
 *
 * Municípios (KGMap.*, independentes da instância):
 *   carregarMunicipios()      -> Promise<Array<{nome, ilha, area_km2, centro, geometria}>>,
 *                                os 22 concelhos de Cabo Verde (docs/Municipio.pdf).
 *   municipioEm(lat, lng)     -> o concelho que contém a coordenada, ou null se estiver
 *                                fora do território (só depois de carregarMunicipios()).
 *
 * Rota dinâmica (KGMap.*, independentes da instância):
 *   projetarNaRota(coords, lat, lng) -> ponto da rota mais próximo da posição, com a
 *                           distância a ela: > limiar significa que o condutor saiu da rota.
 *   rotaAFrente(coords, lat, lng)    -> só o troço que falta percorrer (posição atual ->
 *                           destino), para desenhar a rota sem o rasto já percorrido.
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
  // Zoom máximo que o servidor de tiles do OSM serve mesmo (z19 = nível de
  // edifício). Declarado como maxNativeZoom para que um maxZoom maior pedido
  // pela página (condutor/passageiro) continue a mostrar imagem — sem isto, o
  // Leaflet pedia z19+ ao servidor e recebia 404, ficando com buracos cinzentos.
  const MAX_ZOOM_TILES = 19;

  // Delimitação administrativa do país: os 22 concelhos de docs/Municipio.pdf.
  // O ficheiro é servido da própria origem (a CSP tem connect-src 'self') e o
  // traçado vem do geoBoundaries/OpenStreetMap, simplificado para ~180 KB.
  // O ?v= é o que torna seguro o cache de um ano que o .htaccess dá a este
  // ficheiro: um traçado novo entra com outro número e chega a toda a gente.
  const MUNICIPIOS_URL = '/assets/geo/cv-municipios.geojson?v=1';
  // Acima deste zoom a etiqueta com o nome do concelho é ruído: está pousada
  // no interior do concelho e, ao nível da rua, fica sempre fora do ecrã. As
  // linhas da fronteira ficam sempre — é quando o condutor está em cima de uma
  // que elas lhe dizem mais.
  const MUNICIPIOS_ZOOM_ETIQUETAS = 12;

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
    // Com ocupação conhecida (só o condutor a tem, do próprio veículo) a
    // pastilha passa a "a bordo/livres" — a mesma informação que o popup
    // detalha, visível sem precisar de abrir nada. Sem ocupação continua a
    // ser só lugares livres, como em todos os outros veículos do mapa.
    const ocupacao = v.ocupacao;
    const texto = ocupacao
      ? `${ocupacao.a_bordo}/${ocupacao.lugares_livres}`
      : (v.lugares_livres != null ? String(v.lugares_livres) : null);
    // O título diz o que os dois números são: "2/9" sozinho não se
    // percebe, e é a mesma contagem que o popup detalha ao clicar.
    const titulo = ocupacao ? ' title="a bordo / lugares livres"' : ' title="lugares livres"';
    const lugares = texto != null
      ? `<div class="lugares${ocupacao ? ' lugares--ocupacao' : ''}"${titulo}>${escaparHtml(texto)}</div>`
      : '';
    const seta = opts.comSeta ? '<span class="kg-marker-seta">▲</span>' : '';
    return `${seta}<span class="kg-marker-emoji">${emoji}</span><span class="kg-marker-cor" style="background:${hex}"></span>${lugares}`;
  }

  // Mesma leitura do marcador (emoji do tipo + pastilha com a cor real), mas
  // para usar fora do mapa: o condutor com mais do que um veículo reconhece à
  // vista qual deles está selecionado, sem ter de ler a matrícula. Sem badge
  // de lugares — onde este chip é usado esse número já está escrito por extenso.
  function veiculoChipHtml(v) {
    if (!v) return '';
    const emoji = VEICULO_EMOJI[v.tipo] || '🚗';
    const hex = corVeiculoParaHex(v.cor);
    const titulo = [VEICULO_TIPO_LABEL[v.tipo] || v.tipo, v.cor, v.matricula].filter(Boolean).join(' · ');
    return `<span class="kg-veiculo-chip ${veiculoEstadoClasse(v.estado)}" title="${escaparHtml(titulo)}">`
      + `<span class="kg-marker-emoji">${emoji}</span>`
      + `<span class="kg-marker-cor" style="background:${hex}"></span></span>`;
  }

  // Uma linha de passageiro no popup do veículo: nome, destino escolhido e
  // número do assento. O condutor abre o popup para saber quem leva, para
  // onde e em que lugar — só o nome (como era antes) respondia a um terço da
  // pergunta. Campos em falta são omitidos, nunca inventados.
  function passageiroLinhaHtml(p, etiquetaEstado) {
    const partes = [`<span class="kg-passageiro-nome">👤 ${escaparHtml(p.nome)}</span>`];
    if (p.destino) partes.push(`<span class="kg-passageiro-destino">🏁 ${escaparHtml(p.destino)}</span>`);
    // 'lugares' só aparece quando a reserva vale por mais do que uma pessoa —
    // escrever "1 lugar" em todas as linhas era ruído em cima do assento.
    if (p.lugares > 1) partes.push(`<span class="kg-passageiro-lugar">${escaparHtml(p.lugares)} lugares</span>`);
    if (p.assento != null) partes.push(`<span class="kg-passageiro-lugar">Lugar ${escaparHtml(p.assento)}</span>`);
    if (etiquetaEstado) partes.push(`<span class="kg-passageiro-estado">${escaparHtml(etiquetaEstado)}</span>`);
    return `<li>${partes.join(' ')}</li>`;
  }

  const RESERVA_ESTADO_CURTO = { confirmado: 'confirmado', a_caminho: 'a caminho' };

  // Detalhe do lugar para o popup do veículo: capacidade, quem vai a bordo
  // (com destino e assento de cada um), quem já reservou mas ainda não
  // embarcou e lugares livres. Sem ninguém a bordo mostra na mesma a
  // capacidade e os livres — o "estado vazio" é uma resposta, não um popup
  // em branco.
  function ocupacaoPopupHtml(ocupacao) {
    if (!ocupacao) return '';
    const aBordo = ocupacao.passageiros || [];
    const aguardam = ocupacao.aguardam || [];
    const linhas = [
      `<span>💺 ${escaparHtml(ocupacao.lugares_total)} lugares · ${escaparHtml(ocupacao.a_bordo)} a bordo · ${escaparHtml(ocupacao.lugares_livres)} livres</span>`,
    ];
    linhas.push(aBordo.length
      ? `<ul class="kg-veiculo-popup-lista">${aBordo.map((p) => passageiroLinhaHtml(p)).join('')}</ul>`
      : '<span class="kg-veiculo-popup-vazio">Sem passageiros a bordo.</span>');

    if (ocupacao.reservados > 0 || aguardam.length) {
      linhas.push(`<span>🕓 ${escaparHtml(ocupacao.reservados)} reservados (ainda por embarcar)</span>`);
    }
    // A lista de quem aguarda só entra quando existe mesmo: 'reservados' é
    // uma contagem derivada dos lugares e pode ser maior do que as reservas
    // conhecidas (dados antigos), por isso as duas coisas não se substituem.
    if (aguardam.length) {
      linhas.push(`<ul class="kg-veiculo-popup-lista kg-veiculo-popup-lista--aguardam">${
        aguardam.map((p) => passageiroLinhaHtml(p, RESERVA_ESTADO_CURTO[p.estado] || p.estado)).join('')}</ul>`);
    }

    return linhas.join('<br>');
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
    // Com ocupação conhecida, o detalhe do lugar substitui a linha simples de
    // "lugares livres": diz o mesmo e mais, e repetir os dois dava duas
    // contagens lado a lado a responderem à mesma pergunta.
    if (v.ocupacao) {
      if (v.posicao_fila) linhas.push(`<span>🚏 Fila #${escaparHtml(v.posicao_fila)}</span>`);
      linhas.push(ocupacaoPopupHtml(v.ocupacao));
    } else if (v.lugares_livres != null) {
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
  // Municípios — delimitação administrativa (docs/Municipio.pdf)
  // ------------------------------------------------------------------
  // O GeoJSON é pedido uma só vez por página, mesmo que haja mais do que um
  // mapa ou que a camada seja ligada e desligada várias vezes: a promessa
  // fica guardada e é ela que é devolvida a todos os pedidos seguintes.
  let municipiosPromessa = null;
  let municipiosLista = null;

  function carregarMunicipios() {
    if (municipiosPromessa) return municipiosPromessa;
    municipiosPromessa = fetch(MUNICIPIOS_URL, { credentials: 'same-origin' })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((colecao) => {
        municipiosLista = colecao.features.map((f) => ({
          nome: f.properties.nome,
          ilha: f.properties.ilha,
          area_km2: f.properties.area_km2,
          // [lng, lat] no GeoJSON; a etiqueta e o Leaflet querem [lat, lng].
          centro: [f.properties.centro[1], f.properties.centro[0]],
          geometria: f.geometry,
        }));
        return municipiosLista;
      })
      .catch((erro) => {
        // Sem delimitação o mapa continua a servir para conduzir: o painel
        // nunca mostra erros ao condutor (secção 5.1). Fica só no console, e a
        // promessa é limpa para uma próxima tentativa poder correr melhor.
        console.warn('V-MILLION mapa: municípios indisponíveis —', erro);
        municipiosPromessa = null;
        municipiosLista = [];
        return municipiosLista;
      });
    return municipiosPromessa;
  }

  // Ray casting sobre um anel [[lng, lat], ...] já fechado.
  function dentroDoAnel(anel, lng, lat) {
    let dentro = false;
    for (let i = 0; i < anel.length - 1; i++) {
      const [x1, y1] = anel[i];
      const [x2, y2] = anel[i + 1];
      if ((y1 > lat) !== (y2 > lat) && lng < x1 + ((lat - y1) / (y2 - y1)) * (x2 - x1)) dentro = !dentro;
    }
    return dentro;
  }

  // Um polígono é [exterior, buraco, buraco, ...]: estar dentro do exterior
  // não chega, tem de estar fora de todos os buracos (Santa Catarina do Fogo,
  // por exemplo, é um recorte dentro de São Filipe).
  function dentroDoPoligono(poligono, lng, lat) {
    if (!dentroDoAnel(poligono[0], lng, lat)) return false;
    for (let i = 1; i < poligono.length; i++) {
      if (dentroDoAnel(poligono[i], lng, lat)) return false;
    }
    return true;
  }

  /**
   * O concelho que contém a coordenada, ou null quando ela cai fora do
   * território (mar, ou outro país). Só responde depois de carregarMunicipios()
   * ter resolvido — antes disso devolve null, e não fica à espera: é chamado a
   * cada leitura do GPS e não pode bloquear o seguimento do condutor.
   */
  function municipioEm(lat, lng) {
    if (!municipiosLista || !coordenadasValidas(lat, lng)) return null;
    for (const m of municipiosLista) {
      const polis = m.geometria.type === 'Polygon' ? [m.geometria.coordinates] : m.geometria.coordinates;
      for (const poli of polis) {
        if (dentroDoPoligono(poli, lng, lat)) return m;
      }
    }
    return null;
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
      maxNativeZoom: MAX_ZOOM_TILES,
    }).addTo(map);

    L.control.zoom({ position: 'topright' }).addTo(map);

    const markers = new Map();
    const routes = new Map();
    // Raio de erro do GPS à volta do marcador 'eu' (setUserAccuracy). Fica à
    // parte dos markers porque não é um marcador: é um círculo em metros, que
    // muda de tamanho no ecrã com o zoom.
    let circuloPrecisao = null;

    // Delimitação por município: as linhas ficam sempre, as etiquetas com o
    // nome só até MUNICIPIOS_ZOOM_ETIQUETAS. São dois grupos separados por
    // isso mesmo — o do zoom entra e sai do mapa sem mexer nas fronteiras.
    let municipiosLinhas = null;
    let municipiosEtiquetas = null;
    let municipiosAoZoom = null;

    // --- Movimento contínuo (requestAnimationFrame) -----------------------
    // O GPS entrega ~1 leitura por segundo. Aplicada de uma vez (setLatLng),
    // cada leitura faz o marcador dar um salto e ficar parado até à seguinte:
    // era isto que o condutor via como o veículo a andar aos solavancos. Aqui
    // cada alvo novo é percorrido em interpolação linear ao ritmo do ecrã, e
    // um alvo que chega a meio do percurso arranca da posição desenhada nesse
    // instante — nunca há corte, recuo nem travagem entre leituras.
    const animacoes = new Map();     // chave -> percurso em curso
    const ultimoAlvoEm = new Map();  // chave -> instante do último alvo
    let quadroId = null;
    // A interpolação é linear de propósito (velocidade constante): com uma
    // curva de aceleração/travagem, cada leitura de GPS acabaria a travar
    // antes da seguinte — a "interrupção" que se quer eliminar.
    const DURACAO_MIN_MS = 300;
    const DURACAO_MAX_MS = 2500;
    // Acima disto não é deslocação, é reposicionamento (primeiro fix do GPS,
    // salto de leitura, troca de veículo): percorrer isso a passo constante
    // mostrava o marcador a atravessar a ilha, por isso vai direto.
    const SALTO_DIRETO_M = 500;
    // Movimentos do mapa feitos por nós (o seguimento contínuo abaixo) não são
    // "o utilizador mexeu no mapa" — ver onMoveStart/onMoveEnd.
    let movimentoInterno = false;

    function correrQuadro(agora) {
      quadroId = null;
      animacoes.forEach((anim, chave) => {
        const t = Math.min(1, (agora - anim.inicio) / anim.duracao);
        anim.aplicar(
          anim.deLat + (anim.paraLat - anim.deLat) * t,
          anim.deLng + (anim.paraLng - anim.deLng) * t
        );
        if (t >= 1) animacoes.delete(chave);
      });
      if (animacoes.size) quadroId = requestAnimationFrame(correrQuadro);
    }

    // Sem duração dada, usa o intervalo real medido entre os dois últimos
    // alvos: a animação acaba mesmo quando a leitura seguinte chega, sem
    // parar antes (buraco no movimento) nem ficar sistematicamente atrás.
    function duracaoDoPercurso(chave, duracaoMs, agora) {
      const anterior = ultimoAlvoEm.get(chave);
      ultimoAlvoEm.set(chave, agora);
      const base = duracaoMs != null
        ? duracaoMs
        : (anterior != null ? agora - anterior : DURACAO_MIN_MS);
      return Math.max(DURACAO_MIN_MS, Math.min(DURACAO_MAX_MS, base));
    }

    function animarPara(chave, deLat, deLng, paraLat, paraLng, duracaoMs, aplicar) {
      // Mesmo alvo que o percurso já a decorrer: deixa-o acabar em vez de o
      // recomeçar. Quem redesenha (o painel do passageiro repete a posição do
      // carro a cada leitura do seu próprio GPS, para refazer a linha até ele)
      // cortava assim o movimento a meio, uma e outra vez.
      const emCurso = animacoes.get(chave);
      if (emCurso && emCurso.paraLat === paraLat && emCurso.paraLng === paraLng) return;
      const agora = performance.now();
      const duracao = duracaoDoPercurso(chave, duracaoMs, agora);
      const distancia = coordenadasValidas(deLat, deLng)
        ? distanciaMetros(deLat, deLng, paraLat, paraLng)
        : Infinity;
      // Nada para percorrer (parado, ou reposicionamento em vez de deslocação
      // — primeiro fix, salto de leitura, troca de veículo): vai direto, sem
      // gastar quadros a interpolar o mesmo ponto nem a atravessar a ilha a
      // passo constante.
      if (distancia < 0.05 || distancia > SALTO_DIRETO_M) {
        animacoes.delete(chave);
        aplicar(paraLat, paraLng);
        return;
      }
      animacoes.set(chave, { deLat, deLng, paraLat, paraLng, inicio: agora, duracao, aplicar });
      if (quadroId === null) quadroId = requestAnimationFrame(correrQuadro);
    }

    function pararAnimacao(chave) { animacoes.delete(chave); }

    return {
      engine: '2d',
      raw: map,
      addMarker(id, tipo, lat, lng, options = {}) {
        if (!coordenadasValidas(lat, lng)) return null;
        const el = criarElementoMarcador(tipo, options.classes, options.badge);
        if (options.html) el.innerHTML += options.html;
        // 'kg-marker-icone' é o invólucro de 42x42 que o Leaflet cria e
        // posiciona: o CSS centra lá dentro o marcador real (que pode ser
        // bem mais pequeno — o ponto do GPS tem 22px) e passa-lhe a área de
        // clique. Sem isso, o marcador encostava ao canto do invólucro e a
        // caixa de 42px sobrava para os lados a apanhar os cliques dos
        // marcadores vizinhos — era assim que o ponto do GPS engolia os
        // cliques no marcador do veículo, que fica na mesma coordenada.
        const icon = L.divIcon({ className: 'kg-marker-icone', html: el.outerHTML, iconSize: [42, 42], iconAnchor: [21, 21] });
        // zIndexOffset: quando dois marcadores caem na mesma coordenada, o
        // Leaflet empilha-os por latitude e o empate resolve-se ao acaso.
        // Quem tem detalhe para mostrar (o veículo do condutor, por cima do
        // ponto do GPS) precisa de ficar por cima e receber o clique.
        const marker = L.marker([lat, lng], {
          icon,
          draggable: !!options.draggable,
          zIndexOffset: options.zIndexOffset || 0,
        }).addTo(map);
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
        if (!m || !coordenadasValidas(lat, lng)) return;
        // Uma colocação direta manda sempre sobre um percurso a decorrer: sem
        // isto, a animação anterior continuava a puxar o marcador de volta ao
        // alvo antigo, quadro a quadro, por cima da posição acabada de fixar.
        pararAnimacao(`marcador:${id}`);
        m.setLatLng([lat, lng]);
      },
      // Como updateMarker, mas percorrendo o caminho até à posição nova em vez
      // de saltar para lá — é o que faz o veículo andar de forma contínua no
      // mapa entre duas leituras de GPS, em vez de aparecer de sítio em sítio.
      // 'duracaoMs' é opcional: por omissão o percurso dura o intervalo medido
      // entre as leituras, para acabar quando a seguinte chega.
      moveMarkerSuave(id, lat, lng, duracaoMs) {
        const m = markers.get(id);
        if (!m || !coordenadasValidas(lat, lng)) return;
        const de = m.getLatLng();
        animarPara(`marcador:${id}`, de.lat, de.lng, lat, lng, duracaoMs, (a, b) => {
          m.setLatLng([a, b]);
          // O círculo de precisão do GPS vive colado ao ponto 'eu': se o ponto
          // desliza e o círculo salta, o par separa-se durante um segundo.
          if (id === 'eu' && circuloPrecisao) circuloPrecisao.setLatLng([a, b]);
        });
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
      // Troca o conteúdo de um marcador já criado sem o recriar: é assim que
      // o ponto do GPS ('eu') passa a mostrar o veículo selecionado pelo
      // condutor. Recriar o marcador reiniciava a animação de pulso e perdia
      // a rotação já aplicada à seta, por isso a seta é reaproveitada tal
      // como está. A classe opcional liga-se com conteúdo e desliga-se sem
      // ele (o ponto do GPS muda de forma quando mostra um veículo).
      setMarkerIcone(id, html, classe) {
        const m = markers.get(id);
        const el = m && m.getElement && m.getElement();
        const alvo = el && (el.classList.contains('kg-marker') ? el : el.querySelector('.kg-marker'));
        if (!alvo) return;
        const seta = alvo.querySelector('.kg-marker-seta');
        alvo.innerHTML = html || '';
        if (seta) alvo.appendChild(seta);
        if (classe) alvo.classList.toggle(classe, !!html);
      },
      // Troca (ou tira) o popup de um marcador já criado. Ao contrário de
      // recriar o marcador, um popup aberto continua aberto e muda no lugar —
      // é o que deixa a contagem de lugares mudar debaixo dos olhos do
      // condutor, em vez de lhe fechar o detalhe na cara a cada embarque.
      setMarkerPopup(id, html) {
        const m = markers.get(id);
        if (!m) return;
        if (!html) { m.unbindPopup(); return; }
        if (m.getPopup()) m.setPopupContent(html); else m.bindPopup(html);
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
        // Sem parar o percurso, os quadros seguintes ainda mexiam num marcador
        // já fora do mapa (e mantinham o ciclo de animação vivo à toa).
        pararAnimacao(`marcador:${id}`);
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
      // Mostra a incerteza da leitura do GPS em vez de a esconder: com um erro
      // de 40 m, o ponto azul sozinho dá a entender uma exatidão que não
      // existe. O círculo não é clicável (interactive: false) para não roubar
      // os cliques aos marcadores que ficam por cima dele.
      // 'suave': o círculo não é reposicionado aqui — só muda de raio e deixa a
      // posição ao percurso do ponto 'eu' (moveMarkerSuave), para os dois
      // andarem juntos. Sem isso, o círculo saltava a cada leitura por cima de
      // um ponto que estava a deslizar.
      setUserAccuracy(lat, lng, raioM, suave) {
        // Sem raio utilizável (o browser nem sempre reporta 'accuracy'), o
        // círculo é retirado em vez de ficar parado onde estava — um círculo
        // desalinhado do ponto mentiria mais do que não mostrar nenhum.
        if (!coordenadasValidas(lat, lng) || !(raioM > 0)) { this.clearUserAccuracy(); return; }
        if (circuloPrecisao) {
          circuloPrecisao.setRadius(raioM);
          if (!suave) circuloPrecisao.setLatLng([lat, lng]);
          return;
        }
        circuloPrecisao = L.circle([lat, lng], {
          radius: raioM,
          interactive: false,
          color: '#003893',
          weight: 1,
          opacity: 0.35,
          fillColor: '#003893',
          fillOpacity: 0.08,
        }).addTo(map);
      },
      clearUserAccuracy() {
        if (circuloPrecisao) { map.removeLayer(circuloPrecisao); circuloPrecisao = null; }
      },
      /**
       * Desenha a delimitação do país por município (os 22 concelhos de
       * docs/Municipio.pdf). A linha leva um contorno branco por baixo: sobre
       * os tiles do OSM, uma linha fina sozinha desaparece por cima de uma
       * estrada ou de uma mancha urbana. O tracejado e a cor neutra são para
       * não ser confundida com a rota do condutor, que é azul e contínua.
       *
       * A camada não tem preenchimento de propósito: assim o clique atravessa
       * o interior do concelho e continua a chegar ao mapa e aos marcadores
       * que lá estão — só a própria linha da fronteira abre o detalhe.
       *
       * @param {{cor?:string, popup?:boolean}} opts
       * @returns {Promise<Array>} a lista de concelhos carregada
       */
      mostrarMunicipios(opts = {}) {
        return carregarMunicipios().then((lista) => {
          if (!lista.length || municipiosLinhas) return lista;

          const cor = opts.cor || '#334155';
          // Canvas próprio para a delimitação, por duas razões. A tolerância:
          // no canvas, a área de toque de uma linha é metade da sua espessura
          // — 0,75px numa linha de 1,5px, que ninguém acerta com o dedo dentro
          // de um carro; 10px dá-lhe um alvo real. E a separação: ligar e
          // desligar a camada deixa de obrigar a redesenhar as rotas, que
          // vivem no canvas por omissão do mapa.
          const desenho = L.canvas({ tolerance: 10 });
          // Cada anel vira uma polilinha em vez de se entregar o GeoJSON ao
          // L.geoJSON: com o motor de canvas (preferCanvas), o tracejado só
          // aparece quando 'dashArray' vem no construtor da linha — o
          // L.geoJSON aplica o 'style' depois de a construir e o tracejado
          // perdia-se, ficando uma linha contínua igual a uma rota.
          const contornos = [];
          const fronteiras = [];
          for (const m of lista) {
            const popup = opts.popup === false ? null
              : `<div class="kg-municipio-popup"><strong>${escaparHtml(m.nome)}</strong>`
                + `<div>Ilha ${escaparHtml(m.ilha)}</div>`
                + `<div>${String(m.area_km2).replace('.', ',')} km&sup2;</div></div>`;
            const polis = m.geometria.type === 'Polygon' ? [m.geometria.coordinates] : m.geometria.coordinates;
            for (const poli of polis) {
              for (const anel of poli) {
                const pontos = anel.map((c) => [c[1], c[0]]);
                contornos.push(L.polyline(pontos, {
                  renderer: desenho, color: '#ffffff', weight: 4, opacity: 0.5, interactive: false,
                }));
                const linha = L.polyline(pontos, {
                  renderer: desenho, color: cor, weight: 1.5, opacity: 0.85, dashArray: '6 4',
                  interactive: popup != null,
                });
                if (popup) linha.bindPopup(popup);
                fronteiras.push(linha);
              }
            }
          }
          // O branco vai todo primeiro: no canvas a ordem de desenho é a ordem
          // de adição, e um contorno desenhado depois apagava a linha vizinha.
          municipiosLinhas = L.layerGroup(contornos.concat(fronteiras));
          // A licença dos contornos (ODbL, via geoBoundaries) obriga a creditar
          // a fonte; o Leaflet junta isto à atribuição dos tiles.
          map.attributionControl?.addAttribution('Limites: geoBoundaries');
          municipiosLinhas.addTo(map);

          municipiosEtiquetas = L.layerGroup(lista.map((m) => L.tooltip({
            permanent: true,
            direction: 'center',
            className: 'kg-municipio-etiqueta',
            interactive: false,
          }).setLatLng(m.centro).setContent(escaparHtml(m.nome))));

          municipiosAoZoom = () => {
            if (!municipiosEtiquetas) return;
            const mostrar = map.getZoom() <= MUNICIPIOS_ZOOM_ETIQUETAS;
            if (mostrar === map.hasLayer(municipiosEtiquetas)) return;
            if (mostrar) municipiosEtiquetas.addTo(map); else map.removeLayer(municipiosEtiquetas);
          };
          map.on('zoomend', municipiosAoZoom);
          municipiosAoZoom();

          return lista;
        });
      },
      esconderMunicipios() {
        if (municipiosAoZoom) { map.off('zoomend', municipiosAoZoom); municipiosAoZoom = null; }
        if (municipiosLinhas) {
          map.removeLayer(municipiosLinhas);
          map.attributionControl?.removeAttribution('Limites: geoBoundaries');
          municipiosLinhas = null;
        }
        if (municipiosEtiquetas) { map.removeLayer(municipiosEtiquetas); municipiosEtiquetas = null; }
      },
      municipiosVisiveis() { return municipiosLinhas != null; },
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
        pararAnimacao('mapa');
        map.flyTo([lat, lng], zoom || map.getZoom());
      },
      // Recentragem "leve" para seguir o GPS em tempo real (chamado a cada
      // leitura, potencialmente a cada poucos segundos) — sem alterar o
      // zoom, ao contrário de flyTo, que é para saltos ocasionais e
      // deliberados (botão "recentrar", mudança de modo).
      panTo(lat, lng) {
        // Recentragem pontual: manda sobre um seguimento contínuo a decorrer,
        // que senão continuava a puxar a vista para o alvo antigo por cima
        // desta.
        pararAnimacao('mapa');
        map.panTo([lat, lng], { animate: true, duration: 0.5 });
      },
      // Seguimento contínuo: o mapa acompanha o condutor sem parar entre
      // leituras. panTo() não servia para isto — cada chamada arranca uma
      // animação própria do Leaflet, que acelera e trava em meio segundo e é
      // cortada pela chamada seguinte, e era isso que dava o efeito de "anda,
      // pára, anda". Aqui o centro é interpolado no mesmo relógio dos
      // marcadores, a velocidade constante, e cada quadro só desloca o painel
      // do mapa (animate: false), sem animação nem reposição da vista.
      //
      // Deve ser alimentado com a posição JÁ FILTRADA: com as leituras cruas,
      // o condutor parado veria a vista a tremer ao ritmo do ruído do GPS.
      seguirSuave(lat, lng, duracaoMs) {
        if (!coordenadasValidas(lat, lng)) return;
        const centro = map.getCenter();
        animarPara('mapa', centro.lat, centro.lng, lat, lng, duracaoMs, (a, b) => {
          movimentoInterno = true;
          map.panTo([a, b], { animate: false });
          movimentoInterno = false;
        });
      },
      // O condutor pegou no mapa: o seguimento em curso morre já, senão a
      // animação continuava a puxar-lhe a vista de volta durante um segundo.
      pararSeguimento() { pararAnimacao('mapa'); },
      // Os movimentos do seguimento contínuo são filtrados: chegam a cada
      // quadro e, sem isto, ligavam e desligavam a pausa das animações
      // (body.map-dragging) 60 vezes por segundo enquanto o condutor anda.
      onMoveStart(cb) { map.on('movestart', () => { if (!movimentoInterno) cb(); }); },
      onMoveEnd(cb) { map.on('moveend', () => { if (!movimentoInterno) cb(); }); },
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

  // Proxy do backend (routes/api.php → RotaController) para a instância própria do OSRM
  // (VMILLION_OSRM_URL, só acessível a partir do servidor — não é chamada
  // diretamente do browser) — devolve também os dados em cache (Redis),
  // reduzindo tanto a carga no OSRM como a latência sentida no mapa.
  const ROTA_API_URL = '/api/rota';

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
   * @returns {Promise<{sucesso:boolean, distanciaM:?number, duracaoS:?number, geometria:?Array}>}
   *   distanciaM/duracaoS ficam null quando caiu no fallback de linha reta
   *   (nesse caso não há distância real de estrada para mostrar). 'geometria'
   *   é o traçado desenhado, em [[lat,lng], ...] — quem segue a rota ao vivo
   *   (rotaAFrente/projetarNaRota) precisa de o guardar para recortar a parte
   *   já percorrida sem voltar a pedir a rota ao servidor.
   */
  async function tracarRota(mapaInstancia, id, origem, destino, estilo) {
    if (!origem || !destino || !coordenadasValidas(origem.lat, origem.lng) || !coordenadasValidas(destino.lat, destino.lng)) {
      return { sucesso: false, distanciaM: null, duracaoS: null, geometria: null };
    }
    const linhaReta = () => {
      const geometria = [[origem.lat, origem.lng], [destino.lat, destino.lng]];
      mapaInstancia.drawRoute(id, geometria, estilo);
      return { sucesso: false, distanciaM: null, duracaoS: null, geometria };
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
      const geometria = coords.map(([lng, lat]) => [lat, lng]);
      mapaInstancia.drawRoute(id, geometria, estilo);
      return { sucesso: true, distanciaM: json.distancia_m ?? null, duracaoS: json.duracao_s ?? null, geometria };
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

  // Projeção plana local (equirretangular) em metros. Sobre alguns
  // quilómetros de estrada — a escala de uma viagem urbana em Cabo Verde — o
  // erro é irrelevante, e permite fazer contas de geometria (distância a um
  // segmento) que em graus de latitude/longitude não fecham.
  function paraMetros(lat, lng, latRef) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    return { x: toRad(lng) * Math.cos(toRad(latRef)) * R, y: toRad(lat) * R };
  }

  /**
   * Ponto da rota mais próximo da posição dada — a base da rota dinâmica: diz
   * ao mesmo tempo onde o veículo está no traçado (para recortar o que já
   * ficou para trás) e a que distância está dele (para saber se saiu da rota).
   * @param {Array<[number,number]>} coords traçado em [[lat,lng], ...]
   * @param {number} [indiceMinimo] segmento a partir do qual procurar. Numa
   *   rota que passa duas vezes pelo mesmo sítio (volta atrás, rotunda, ida e
   *   volta pela mesma rua), o ponto mais próximo em absoluto pode ser o da
   *   outra passagem — e o traçado saltava para lá, cortando meia viagem.
   *   Quem segue a rota ao vivo passa aqui o progresso já feito.
   * @returns {?{distanciaM:number, indice:number, ponto:[number,number]}}
   *   'indice' é o segmento [indice, indice+1] onde a projeção caiu.
   */
  function projetarNaRota(coords, lat, lng, indiceMinimo) {
    if (!Array.isArray(coords) || coords.length < 2) return null;
    const p = paraMetros(lat, lng, lat);
    const inicio = Math.max(0, Math.min(indiceMinimo || 0, coords.length - 2));
    let melhor = null;

    for (let i = inicio; i < coords.length - 1; i++) {
      const a = paraMetros(coords[i][0], coords[i][1], lat);
      const b = paraMetros(coords[i + 1][0], coords[i + 1][1], lat);
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const comprimento2 = dx * dx + dy * dy;
      // Segmento degenerado (dois pontos iguais no traçado do OSRM): a
      // projeção é o próprio ponto, sem divisão por zero.
      const t = comprimento2 === 0
        ? 0
        : Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / comprimento2));
      const distancia = Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));

      if (!melhor || distancia < melhor.distanciaM) {
        melhor = {
          distanciaM: distancia,
          indice: i,
          ponto: [
            coords[i][0] + t * (coords[i + 1][0] - coords[i][0]),
            coords[i][1] + t * (coords[i + 1][1] - coords[i][1]),
          ],
        };
      }
    }

    return melhor;
  }

  /**
   * A parte da rota que ainda falta percorrer: começa na posição atual do
   * veículo e segue até ao destino, sem o rasto do que já ficou para trás
   * (requisito "a trajetória mostra o percurso atual e à frente, sem linha
   * atrás"). Devolve também a distância que falta, calculada sobre o próprio
   * traçado — assim o painel atualiza os quilómetros a cada sinal de GPS sem
   * pedir a rota outra vez ao servidor.
   * @param {number} [indiceMinimo] ver projetarNaRota — o segmento já
   *   alcançado, para a rota não saltar para uma passagem posterior.
   * @param {{encostarM?:number}} [opts] com 'encostarM', uma posição a essa
   *   distância ou menos do traçado é tratada como erro de leitura do GPS e a
   *   linha arranca no ponto projetado sobre a estrada, em vez de arrancar na
   *   coordenada crua. É o que tira o "gancho" que ligava o veículo à estrada
   *   a cada sinal — um desvio que o condutor nunca fez.
   * @returns {?{coords:Array<[number,number]>, projecao:object, distanciaRestanteM:number}}
   */
  function rotaAFrente(coords, lat, lng, indiceMinimo, opts = {}) {
    const proj = projetarNaRota(coords, lat, lng, indiceMinimo);
    if (!proj) return null;

    const encostar = opts.encostarM != null && proj.distanciaM <= opts.encostarM;
    const frente = encostar ? [] : [[lat, lng]];
    // A projeção só entra quando está mesmo à frente do veículo: colada à
    // posição atual (< 2 m) daria um segmento de comprimento zero, e o
    // primeiro ponto do resto do traçado trata do resto. Com 'encostar' ela é
    // o próprio início da linha, por isso entra sempre.
    if (encostar || distanciaMetros(lat, lng, proj.ponto[0], proj.ponto[1]) > 2) frente.push(proj.ponto);
    for (let i = proj.indice + 1; i < coords.length; i++) frente.push(coords[i]);

    let restante = 0;
    for (let i = 0; i < frente.length - 1; i++) {
      restante += distanciaMetros(frente[i][0], frente[i][1], frente[i + 1][0], frente[i + 1][1]);
    }

    return { coords: frente, projecao: proj, distanciaRestanteM: restante };
  }

  KGMap.ligarPausaDeAnimacoes = ligarPausaDeAnimacoes;
  KGMap.tracarRota = tracarRota;
  KGMap.calcularRumo = calcularRumo;
  KGMap.distanciaMetros = distanciaMetros;
  KGMap.projetarNaRota = projetarNaRota;
  KGMap.rotaAFrente = rotaAFrente;
  KGMap.veiculoIconeHtml = veiculoIconeHtml;
  KGMap.veiculoChipHtml = veiculoChipHtml;
  KGMap.veiculoPopupHtml = veiculoPopupHtml;
  KGMap.ocupacaoPopupHtml = ocupacaoPopupHtml;
  KGMap.veiculoEstadoClasse = veiculoEstadoClasse;
  KGMap.carregarMunicipios = carregarMunicipios;
  KGMap.municipioEm = municipioEm;

  global.KGMap = KGMap;
})(window);
