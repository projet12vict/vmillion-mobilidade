/**
 * Carrega o script do painel do condutor (que vive dentro do Blade) num
 * contexto isolado do Node, com o DOM, a rede e o mapa falsificados — o
 * KGMap real, esse, é carregado tal e qual, porque é ele que produz o HTML
 * do detalhe do veículo que os testes leem.
 *
 * As diretivas Blade do script são substituídas pelos valores que o servidor
 * lá poria; o resto do ficheiro é executado sem uma linha alterada, para que
 * o teste falhe se o painel mudar de comportamento.
 */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

import { criarDocumentoFalso, ElementoFalso } from './dom-falso.mjs';
import { criarMapaFalso } from './mapa-falso.mjs';

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

// Valores que o Blade injeta no script. Os ícones ficam vazios de propósito:
// svgIcone devolve '' para um nome desconhecido, e nenhum teste do mapa
// depende do desenho de um ícone.
const VALORES_BLADE = {
  "@json(csrf_token())": '"teste-csrf-token"',
  "@json((int) $condutor->id)": '1',
  "@json($mostrarMapa)": 'true',
  "@json(config('icones'))": '{}',
  "@json(config('vmillion.veiculos.lugares_por_tipo'))": '{"hiace":14,"taxi":4,"autocarro":30}',
  "@json(config('vmillion.veiculos.lugares_max_por_tipo'))": '{"hiace":16,"taxi":7,"autocarro":60}',
  "@json((int) config('vmillion.veiculos.lugares_total'))": '14',
};

function scriptDoPainel() {
  const blade = fs.readFileSync(path.join(RAIZ, 'resources/views/condutor/painel.blade.php'), 'utf8');
  const bloco = blade.match(/<script nonce="\{\{ \$cspNonce \}\}">\n([\s\S]*?)\n<\/script>/);
  if (!bloco) throw new Error('Bloco <script> do painel do condutor não encontrado.');

  let js = bloco[1];
  for (const [directiva, valor] of Object.entries(VALORES_BLADE)) {
    if (!js.includes(directiva)) {
      throw new Error(`A diretiva ${directiva} deixou de existir no painel — atualize VALORES_BLADE.`);
    }
    js = js.split(directiva).join(valor);
  }

  const porSubstituir = js.match(/@json\(|\{\{/);
  if (porSubstituir) {
    throw new Error(`Ficou uma expressão Blade por substituir no script do painel: ${porSubstituir[0]}`);
  }

  return js;
}

/**
 * @param {object} opcoes
 * @param {(url: string, init?: object) => object} opcoes.responder
 *   devolve o corpo JSON para cada pedido; lança se o URL não for esperado.
 */
export function carregarPainelCondutor({ responder }) {
  const documento = criarDocumentoFalso();
  const mapa = criarMapaFalso();
  const pedidos = [];

  let aoObterPosicao = null;   // callback de kgEnsureGeolocation
  let aoMoverPosicao = null;   // callback de kgWatchPosition (sinal filtrado)
  let aoLerGpsBruto = null;    // opts.onLeituraBruta de kgWatchPosition

  const contexto = {
    // O painel regista o GPS e as recusas do servidor na consola; num teste
    // isso é só ruído. Os erros continuam a aparecer.
    console: { log: () => {}, warn: () => {}, info: () => {}, error: console.error },
    setTimeout, clearTimeout,
    // Os temporizadores do painel (polling de 10s, envio de posição de 3s)
    // não podem ficar a correr num teste: o que interessa é o que acontece
    // quando o painel é chamado, não quando o relógio dispara.
    setInterval: () => 0,
    clearInterval: () => {},
    document: documento,
    navigator: { geolocation: { clearWatch: () => {}, getCurrentPosition: () => {} } },
    Event: class Event {
      constructor(tipo) { this.type = tipo; }
    },
    Option: class Option {
      constructor(texto, valor) { this.text = texto; this.value = String(valor ?? ''); }
    },
    FormData: class FormData {
      constructor() { this._d = new Map(); }
      set(k, v) { this._d.set(k, v); }
      get(k) { return this._d.get(k); }
    },
    URLSearchParams,
    Intl, Date, Math, JSON, Promise, Set, Map, Array, Object, String, Number, Boolean, RegExp, Error,
    alert: () => {},
    confirm: () => false,
    async fetch(url, init) {
      pedidos.push({ url, init });
      const corpo = responder(String(url), init);
      return {
        ok: true,
        status: 200,
        json: async () => corpo,
      };
    },
    kgEnsureGeolocation: (cb) => { aoObterPosicao = cb; },
    kgWatchPosition: (cb, opts = {}) => { aoMoverPosicao = cb; aoLerGpsBruto = opts.onLeituraBruta || null; return 1; },
    KGSons: { tocarMensagem: () => {}, tocarSucesso: () => {}, estaAtivo: () => false, obterVolume: () => 50, definirAtivo: () => {}, definirVolume: () => {} },
    KGChamada: { configurar: () => {}, iniciar: () => {} },
    // configurar() é assíncrono no kg-push.js verdadeiro e o painel encadeia
    // um .then() para desenhar o estado do botão.
    KGPush: {
      configurar: () => Promise.resolve(),
      ativar: () => Promise.resolve(false),
      desativar: () => Promise.resolve(),
      estado: () => 'inativo',
      aoMudar: () => {},
    },
    KGRealtime: { connect: () => ({ join: () => {}, on: () => {}, emit: () => {} }) },
  };
  contexto.window = contexto;
  contexto.globalThis = contexto;
  contexto.location = { origin: 'http://localhost', href: '' };

  vm.createContext(contexto);

  // Os helpers partilhados entram tal como no browser, pela mesma ordem.
  for (const ficheiro of ['kg-esc.js', 'kg-datas.js', 'kg-map.js']) {
    vm.runInContext(fs.readFileSync(path.join(RAIZ, 'public/assets/js', ficheiro), 'utf8'), contexto, { filename: ficheiro });
  }

  // O mapa é o único ponto do KGMap trocado: tudo o resto (o HTML do ícone,
  // o HTML do popup, as contas de rota) é o código verdadeiro.
  contexto.KGMap.create = () => mapa;
  contexto.KGMap.ligarPausaDeAnimacoes = () => {};

  // O epílogo corre no mesmo âmbito do painel e é a única forma de lhe
  // chegar às funções e variáveis, que são todas locais ao script.
  const epilogo = `
    ;globalThis.__painel = {
      carregarPassageiros,
      atualizarMarcadoresPassageiros,
      retirarPassageiroDoMapa,
      aplicarOcupacao,
      textoEstadoVeiculo,
      estado: () => ({
        veiculoAtivoId,
        ocupacaoVeiculo,
        meuVeiculoMarcadoId,
        passageiroMarkerIds: new Set(passageiroMarkerIds),
      }),
    };`;

  vm.runInContext(scriptDoPainel() + epilogo, contexto, { filename: 'painel-condutor.js' });

  return {
    contexto,
    mapa,
    documento,
    pedidos,
    painel: () => contexto.__painel,
    /** Entrega a primeira posição de GPS e liga o seguimento, como o browser faria. */
    darPosicaoInicial(lat, lng) {
      if (!aoObterPosicao) throw new Error('O painel não pediu geolocalização.');
      aoObterPosicao({ coords: { latitude: lat, longitude: lng, accuracy: 8 } });
    },
    /** Uma atualização de GPS seguinte (sinal já filtrado, como no browser). */
    moverGps(lat, lng) {
      if (!aoMoverPosicao) throw new Error('O painel não está a seguir o GPS.');
      if (aoLerGpsBruto) aoLerGpsBruto(lat, lng, { coords: { latitude: lat, longitude: lng, accuracy: 8 } });
      aoMoverPosicao(lat, lng, { coords: { latitude: lat, longitude: lng, accuracy: 8 } }, { obsoleta: false });
    },
    /**
     * Uma leitura que o filtro de kg-geolocation.js NÃO deixaria passar ao
     * painel (deslocação de poucos metros, deriva): só o ponto do GPS a ela
     * reage — é o que garante que o marcador fica no sítio exato.
     */
    lerGpsBruto(lat, lng, precisaoM = 8) {
      if (!aoLerGpsBruto) throw new Error('O painel não pediu as leituras cruas do GPS.');
      aoLerGpsBruto(lat, lng, { coords: { latitude: lat, longitude: lng, accuracy: precisaoM } });
    },
  };
}

export { ElementoFalso };
