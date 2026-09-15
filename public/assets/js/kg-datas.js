/**
 * formatarData(valor) — datas da API prontas para leitura nos painéis.
 *
 * As datas chegam da API em UTC — a base de dados guarda hora de Cabo Verde
 * e o Laravel serializa em Z (23:01 local sai como 00:01Z). Formatamos
 * sempre em Atlantic/Cape_Verde e não no fuso de quem está a ver: isto é uma
 * operação em Santiago, e a mesma linha tem de dizer a mesma hora a quem a
 * abre de outro país.
 *
 * Sem isto, os painéis interpolavam a string crua da API e o condutor lia
 * "válido até 2026-09-27T00:00:00.000000Z".
 *
 * Fica num ficheiro à parte, como o kg-esc.js, para que não haja versões
 * diferentes desta função nos três painéis — datas mostradas de maneiras
 * diferentes na mesma operação são um problema de confiança, não de estilo.
 */
(function (global) {
  'use strict';

  const FORMATO = new Intl.DateTimeFormat('pt-PT', {
    timeZone: 'Atlantic/Cape_Verde',
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false,
  });

  // Um valor por formatar é devolvido tal como veio: é preferível mostrar a
  // string estranha do que esconder um campo que a API mudou de formato.
  global.formatarData = function formatarData(valor) {
    if (!valor) return '—';
    const d = new Date(valor);
    if (isNaN(d.getTime())) return String(valor);
    return FORMATO.format(d).replace(',', '');
  };
})(window);
