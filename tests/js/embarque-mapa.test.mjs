/**
 * Embarque visto do mapa do condutor (/condutor/painel).
 *
 * A regra: enquanto o condutor vai a caminho, o passageiro tem ponto próprio
 * no mapa; assim que entra no carro, esse ponto desaparece e a pessoa passa a
 * contar dentro do detalhe do condutor — no badge do marcador (a bordo /
 * lugares livres) e no popup que se abre ao clicar nele.
 *
 * O teste corre o script verdadeiro do painel (extraído do Blade) contra um
 * mapa e uma rede falsos, e é o KGMap real que produz o HTML do detalhe. O
 * que o servidor responde nestes cenários é o que a suite PHP já fixa em
 * tests/Feature/EmbarqueTest.php (no_mapa e ocupacao) — aqui testa-se o que o
 * painel faz com essa resposta.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { carregarPainelCondutor } from './ajuda/carregar-painel.mjs';

const VEICULO = {
  id: 1, matricula: 'ST-01-AA', tipo: 'hiace', cor: 'branco', modelo: 'Hiace',
  tipo_servico: 'urbano', aprovado: true, estado: 'em_movimento',
  lugares_total: 14, lugares_livres: 13, lat: null, lng: null,
  ponto_partida_id: null, destino_id: null, passageiros_pendentes: 1,
};

const RESERVA_BASE = {
  id: 7, passageiro_id: 42, passageiro_nome: 'Ana Lopes', passageiro_telefone: '+2389912345',
  tipo_viagem: 'urbano', preco_final: 250, ponto_partida_nome: 'Plateau',
  // Sem ponto de descida com coordenadas de propósito: o que está a ser
  // testado é o mapa dos passageiros, não a rota de navegação.
  ponto_descida_nome: 'Palmarejo', ponto_descida_lat: null, ponto_descida_lng: null,
  mapa_lat: 14.918, mapa_lng: -23.508, passageiro_lat: 14.918, passageiro_lng: -23.508,
  distancia_m: 320, pode_ir_buscar: false, motivo_bloqueio: null,
};

const A_CAMINHO = {
  passageiros: [{ ...RESERVA_BASE, estado: 'a_caminho', no_mapa: true, pode_embarcar: true }],
  ocupacao: {
    lugares_total: 14, lugares_livres: 13, a_bordo: 0, reservados: 1,
    passageiros: [],
    aguardam: [{ reserva_id: 7, nome: 'Ana Lopes', estado: 'a_caminho', destino: 'Palmarejo', assento: 1, lugares: 1 }],
  },
};

const A_BORDO = {
  passageiros: [{ ...RESERVA_BASE, estado: 'a_bordo', no_mapa: false, pode_embarcar: false }],
  ocupacao: {
    lugares_total: 14, lugares_livres: 13, a_bordo: 1, reservados: 0,
    passageiros: [{ reserva_id: 7, passageiro_id: 42, nome: 'Ana Lopes', destino: 'Palmarejo', assento: 1, lugares: 1 }],
    aguardam: [],
  },
};

/** Painel arrancado, com veículo ativo e uma posição de GPS já entregue. */
async function painelPronto() {
  let passageiros = A_CAMINHO;

  const painel = carregarPainelCondutor({
    responder(url) {
      if (url.startsWith('/api/condutor/passageiros?')) return passageiros;
      if (url.startsWith('/api/condutor/passageiros_urbanos')) return { passageiros: [], elegivel: true };
      if (url.startsWith('/api/condutor/veiculos')) return { veiculos: [VEICULO] };
      if (url.startsWith('/api/passageiro/pontos')) return { pontos: [] };
      if (url.startsWith('/api/comunicacao/listar')) return { mensagens: [] };
      if (url.startsWith('/api/condutor/pagamentos')) return { pagamentos: [], pacotes: [], veiculos_validos: ['1'] };
      if (url.startsWith('/api/notificacoes/listar')) return { notificacoes: [], nao_lidas: 0 };
      throw new Error(`Pedido inesperado no teste: ${url}`);
    },
  });

  // Deixa o arranque assíncrono do painel assentar (veículos, passageiros).
  await new Promise((r) => setImmediate(r));
  await new Promise((r) => setImmediate(r));

  // O GPS chega depois do resto do painel, como no browser: é isto que cria o
  // marcador do próprio veículo do condutor.
  painel.darPosicaoInicial(14.9177, -23.5092);
  painel.moverGps(14.9177, -23.5092);

  return {
    ...painel,
    definirPassageiros(novos) { passageiros = novos; },
  };
}

const marcadorDoPassageiro = (mapa) => mapa.marcadores.get('passageiro-7');
const marcadorDoCondutor = (mapa) => mapa.marcadores.get('v-1');

test('a caminho: o passageiro tem ponto próprio e ainda não conta a bordo', async () => {
  const { mapa } = await painelPronto();

  const ponto = marcadorDoPassageiro(mapa);
  assert.ok(ponto, 'o passageiro a caminho tem de ter ponto no mapa');
  assert.equal(ponto.tipo, 'passageiro');
  assert.equal(ponto.lat, 14.918);
  assert.match(ponto.popupHtml, /Ana Lopes/);

  const condutor = marcadorDoCondutor(mapa);
  assert.ok(condutor, 'o veículo do condutor tem de estar no mapa');
  assert.match(condutor.html, /0\/13/, 'o badge conta 0 a bordo e 13 livres');
  assert.match(condutor.popupHtml, /0 a bordo/);
  assert.match(condutor.popupHtml, /Sem passageiros a bordo/,
    'quem ainda não embarcou não pode aparecer na lista de bordo');
  assert.match(condutor.popupHtml, /1 reservados/, 'mas conta como lugar reservado');
  assert.match(condutor.popupHtml, /kg-veiculo-popup-lista--aguardam[\s\S]*Ana Lopes/,
    'e aparece à parte, na lista de quem falta embarcar');
});

test('ao embarcar, o ponto do passageiro sai do mapa e passa a contar no detalhe do condutor', async () => {
  const { mapa, painel, definirPassageiros } = await painelPronto();

  assert.ok(marcadorDoPassageiro(mapa), 'pré-condição: o ponto existe antes do embarque');

  definirPassageiros(A_BORDO);
  await painel().carregarPassageiros();

  assert.equal(marcadorDoPassageiro(mapa), undefined,
    'o ponto de GPS do passageiro tem de desaparecer do mapa depois de embarcar');
  assert.equal(painel().estado().passageiroMarkerIds.has('passageiro-7'), false,
    'e o painel não pode continuar a dar o marcador por desenhado');

  const condutor = marcadorDoCondutor(mapa);
  assert.match(condutor.html, /1\/13/, 'o badge do condutor passa a 1 a bordo');
  assert.match(condutor.popupHtml, /1 a bordo/);
  assert.match(condutor.popupHtml, /Ana Lopes/, 'e o detalhe passa a listar quem vai a bordo');
  assert.match(condutor.popupHtml, /Palmarejo/, 'com o destino dele');
  assert.match(condutor.popupHtml, /Lugar 1/, 'e o lugar que ocupa');
  assert.doesNotMatch(condutor.popupHtml, /reservados/,
    'já não há lugares presos por embarcar');
});

test('o cartão "Veículo ativo" acompanha a contagem', async () => {
  const { documento, painel, definirPassageiros } = await painelPronto();

  definirPassageiros(A_BORDO);
  await painel().carregarPassageiros();

  assert.match(
    documento.getElementById('estado-veiculo-txt').textContent,
    /1 a bordo · 13\/14 lugares livres/,
  );
});

test('o embarque tira o ponto do mapa sem esperar pela resposta seguinte', async () => {
  const { mapa, painel } = await painelPronto();

  assert.ok(marcadorDoPassageiro(mapa), 'pré-condição: o ponto existe');

  // É o que o clique em "Embarcar" faz, mal o servidor confirma — antes de
  // qualquer novo carregarPassageiros().
  assert.equal(painel().retirarPassageiroDoMapa(7), true);
  assert.equal(marcadorDoPassageiro(mapa), undefined);

  // Idempotente: um segundo clique (ou o poll a seguir) não rebenta.
  assert.equal(painel().retirarPassageiroDoMapa(7), false);
});

test('quem desce devolve o lugar e sai do detalhe do condutor', async () => {
  const { mapa, painel, definirPassageiros } = await painelPronto();

  definirPassageiros(A_BORDO);
  await painel().carregarPassageiros();
  assert.match(marcadorDoCondutor(mapa).html, /1\/13/);

  // "Entregue": a reserva sai dos estados ativos e o assento é libertado.
  definirPassageiros({
    passageiros: [],
    ocupacao: { lugares_total: 14, lugares_livres: 14, a_bordo: 0, reservados: 0, passageiros: [], aguardam: [] },
  });
  await painel().carregarPassageiros();

  const condutor = marcadorDoCondutor(mapa);
  assert.match(condutor.html, /0\/14/, 'o lugar volta a estar livre');
  assert.match(condutor.popupHtml, /Sem passageiros a bordo/);
  assert.equal(marcadorDoPassageiro(mapa), undefined);
});

test('o ponto do GPS fica na coordenada exata da última leitura, sem filtro', async () => {
  const { mapa, lerGpsBruto } = await painelPronto();

  const antes = mapa.marcadores.get('eu');
  assert.ok(antes, 'o ponto do GPS tem de existir depois da posição inicial');

  // Deslocação de ~2 m: o filtro dos 5 m de kg-geolocation.js não a entrega ao
  // painel, mas o ponto azul tem de ir lá na mesma — é o sítio onde o condutor
  // está mesmo.
  lerGpsBruto(14.91772, -23.50922, 6);

  const depois = mapa.marcadores.get('eu');
  assert.equal(depois.lat, 14.91772);
  assert.equal(depois.lng, -23.50922);
  assert.deepEqual(mapa.precisao, { lat: 14.91772, lng: -23.50922, raioM: 6 },
    'o círculo de precisão acompanha o ponto, com o raio de erro da leitura');
});

test('em rota, o veículo acompanha todas as leituras do GPS, não só as filtradas', async () => {
  const { mapa, lerGpsBruto } = await painelPronto();

  assert.ok(marcadorDoCondutor(mapa), 'pré-condição: o veículo já está no mapa');

  // Mesma deslocação curta do teste anterior: o filtro dos 5 m não a entrega
  // ao painel. O veículo tem de ir lá na mesma — está na mesma coordenada do
  // ponto do GPS (o condutor vai dentro dele) e, à espera de deslocações
  // confirmadas, ficava atrás do próprio ponto azul e andava aos saltos.
  lerGpsBruto(14.91772, -23.50922, 6);

  const veiculo = marcadorDoCondutor(mapa);
  assert.equal(veiculo.lat, 14.91772);
  assert.equal(veiculo.lng, -23.50922);
  assert.deepEqual(
    [veiculo.lat, veiculo.lng],
    [mapa.marcadores.get('eu').lat, mapa.marcadores.get('eu').lng],
    'o veículo e o ponto do GPS nunca se separam',
  );
});
