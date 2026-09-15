/**
 * DOM mínimo para correr o script de um painel Blade fora do browser.
 *
 * Não é um jsdom nem quer ser: o painel do condutor toca no DOM para
 * pendurar handlers e escrever listas, e nada disso é o que está a ser
 * testado. O que interessa é o que o painel manda para o mapa, e para lá
 * chegar basta que nenhuma destas chamadas rebente. Cada elemento é
 * memorizado por id, para que o teste possa ler o que o painel lá escreveu
 * (ex: o texto do estado do veículo).
 */

class ElementoFalso {
  constructor(nome = 'div') {
    this.nome = nome;
    this.style = {};
    this.dataset = {};
    this.innerHTML = '';
    this.textContent = '';
    this.value = '';
    this.checked = false;
    this.disabled = false;
    this.files = [];
    this.children = [];
    this.scrollTop = 0;
    this.scrollHeight = 0;
    this.className = '';
    this.options = [];
    this.classList = {
      add: () => {}, remove: () => {}, toggle: () => {}, contains: () => false,
    };
  }

  addEventListener() {}
  removeEventListener() {}
  dispatchEvent() { return true; }
  appendChild(filho) { this.children.push(filho); return filho; }
  removeChild() {}
  remove() {}
  scrollIntoView() {}
  closest() { return null; }
  // Devolve sempre um elemento (memorizado por seletor) em vez de null: o
  // painel monta cartões com innerHTML e vai logo buscar os botões lá dentro
  // para lhes pendurar handlers. Devolver null fazia rebentar código que no
  // browser funciona — e o que está a ser testado não é o DOM.
  querySelector(seletor) {
    if (!this._porSeletor) this._porSeletor = new Map();
    if (!this._porSeletor.has(seletor)) this._porSeletor.set(seletor, new ElementoFalso(seletor));
    return this._porSeletor.get(seletor);
  }
  querySelectorAll() { return []; }
  add(opcao) { this.options.push(opcao); }
  getBoundingClientRect() { return { top: 0, left: 0, width: 0, height: 0 }; }
}

export function criarDocumentoFalso() {
  const porId = new Map();

  const documento = {
    getElementById(id) {
      if (!porId.has(id)) porId.set(id, new ElementoFalso(id));
      return porId.get(id);
    },
    createElement: (nome) => new ElementoFalso(nome),
    querySelector: () => null,
    querySelectorAll: () => [],
    addEventListener: () => {},
    body: new ElementoFalso('body'),
  };

  // O teste lê por aqui o que o painel escreveu no DOM.
  documento.__elemento = (id) => documento.getElementById(id);

  return documento;
}

export { ElementoFalso };
