/**
 * esc(valor) — escape de HTML para tudo o que venha do servidor e seja
 * interpolado num template literal que acaba em innerHTML.
 *
 * Os painéis montam as tabelas e as listas com innerHTML. Sem esta função,
 * qualquer campo escrito por um utilizador (o nome do registo, a matrícula de
 * um veículo, o texto de uma sugestão) era HTML executado no browser de quem
 * está a ver a página — no caso do painel de administração, a conta com mais
 * privilégios do sistema.
 *
 * Escapa também aspas simples e duplas porque muitas destas interpolações
 * caem dentro de atributos (data-id="${...}"), onde escapar só < e > não
 * chegava para impedir a saída do atributo.
 *
 * Fica num ficheiro à parte, e não repetida nos três painéis, para que não
 * haja versões diferentes desta função — é o tipo de código que só serve se
 * for exatamente o mesmo em todo o lado.
 */
(function (global) {
  'use strict';

  const MAPA = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

  global.esc = function esc(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (c) => MAPA[c]);
  };
})(window);
