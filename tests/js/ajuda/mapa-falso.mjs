/**
 * Mapa falso com a mesma API que KGMap.create() devolve, mas que em vez de
 * desenhar guarda o estado de cada marcador. É assim que um teste consegue
 * perguntar "o ponto do passageiro ainda está no mapa?" e "o que diz o
 * detalhe do condutor?" sem browser nem Leaflet.
 */
export function criarMapaFalso() {
  const marcadores = new Map();
  const rotas = new Map();

  return {
    engine: 'falso',
    marcadores,
    rotas,

    addMarker(id, tipo, lat, lng, opcoes = {}) {
      marcadores.set(id, {
        id, tipo, lat, lng,
        classes: opcoes.classes || '',
        html: opcoes.html || '',
        popupHtml: opcoes.popupHtml || '',
        badge: opcoes.badge ?? null,
        zIndexOffset: opcoes.zIndexOffset || 0,
      });
      return marcadores.get(id);
    },
    updateMarker(id, lat, lng) {
      const m = marcadores.get(id);
      if (m) { m.lat = lat; m.lng = lng; }
    },
    // O movimento contínuo é uma questão de desenho (interpolação quadro a
    // quadro até à posição nova); para os testes conta o sítio onde o
    // marcador fica, por isso aqui a posição é aplicada de uma vez.
    moveMarkerSuave(id, lat, lng) { this.updateMarker(id, lat, lng); },
    removeMarker(id) { marcadores.delete(id); },
    setMarkerIcone(id, html) {
      const m = marcadores.get(id);
      if (m) m.html = html;
    },
    setMarkerPopup(id, html) {
      const m = marcadores.get(id);
      if (m) m.popupHtml = html;
    },
    setMarkerRotation() {},
    setMarkerDraggable() {},
    drawRoute(id, coords) { rotas.set(id, coords); },
    clearRoute(id) { rotas.delete(id); },
    setUserPosition(lat, lng) { this.addMarker('eu', 'eu', lat, lng, {}); },
    // Círculo do raio de erro do GPS: guardado como estado (e não desenhado)
    // para um teste poder confirmar que acompanha a posição.
    // 'suave' (4.º argumento) só muda quem posiciona o círculo no mapa real —
    // fica a reboque do ponto do GPS, que acaba na mesma coordenada.
    setUserAccuracy(lat, lng, raioM) {
      if (!(raioM > 0)) { this.precisao = null; return; }
      this.precisao = { lat, lng, raioM };
    },
    clearUserAccuracy() { this.precisao = null; },
    precisao: null,
    // Delimitação por município: o traçado vem de um GeoJSON pedido por rede,
    // que aqui não existe. Guarda-se só se a camada está ligada — é o que os
    // testes precisam de saber; quem está a ser exercitado é o painel, não o
    // desenho das fronteiras.
    municipios: false,
    mostrarMunicipios() { this.municipios = true; return Promise.resolve([]); },
    esconderMunicipios() { this.municipios = false; },
    municipiosVisiveis() { return this.municipios; },
    fitCaboVerde() {},
    fitBounds() {},
    flyTo() {},
    panTo() {},
    seguirSuave() {},
    pararSeguimento() {},
    onMoveStart() {},
    onMoveEnd() {},
    onDragStart() {},
    onClick() {},
  };
}
