<?php

declare(strict_types=1);

/*
| Ícones do painel
|--------------------------------------------------------------------------
| Só o interior do <svg>: a moldura (viewBox, stroke, largura do traço) é
| posta pelo componente <x-icone>. Todos desenham numa grelha de 24x24 com
| traço de 2 e nenhum tem cor própria — herdam a do texto por currentColor,
| que é o que lhes permite acompanhar o estado do menu (branco quando
| inativo, azul quando ativo).
|
| Isto vive em config/ e não no componente porque os cartões do dashboard
| são desenhados pelo JS: a mesma lista é passada à página com @json e
| serve os dois lados, para não haver dois conjuntos a divergir.
*/

return [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',

    'utilizadores' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',

    // Autocarro de perfil. Chegou a ser também o logótipo; hoje o logótipo é
    // a marca "M" com a estrada (public/assets/img/logo.svg) e esta carrinha
    // ficou só como ícone de menu, onde a 20px ainda se lê.
    'veiculo' => '<path d="M4 6h13a2 2 0 0 1 1.9 1.4l1.4 4.6c.1.4.2.8.2 1.2V18h-3"/><path d="M2 18V8a2 2 0 0 1 2-2"/><path d="M2 18h3"/><path d="M8 6v6M15 6v6"/><path d="M2 12h19"/><circle cx="7" cy="18" r="2"/><circle cx="16" cy="18" r="2"/><path d="M9 18h5"/>',

    'condutor' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.2 16.4c.6-1.2 1.9-2 3.3-2s2.7.8 3.3 2"/><path d="M15 10h4M15 14h3"/>',

    'proprietario' => '<path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16"/><path d="M15 9h3a2 2 0 0 1 2 2v10"/><path d="M2 21h20"/><path d="M8 7h3M8 11h3M8 15h3"/>',

    'ponto' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',

    'parque' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9.5 17V7.5h3.2a3 3 0 0 1 0 6H9.5"/>',

    'mapa' => '<path d="m9 4-6 2.5v14L9 18l6 2.5 6-2.5v-14L15 6.5 9 4Z"/><path d="M9 4v14M15 6.5v14"/>',

    'pagamento' => '<rect x="2" y="6" width="20" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.5"/><path d="M5.5 12h.01M18.5 12h.01"/>',

    'preco' => '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.8 8.8a2 2 0 0 0 2.8 0l7.2-7.2a2 2 0 0 0 0-2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',

    'sugestao' => '<path d="M21 14a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/><path d="M8 8h8M8 12h5"/>',

    'comunicar' => '<path d="M3 10.5v3a1.5 1.5 0 0 0 1.5 1.5H6l2.5 5.5h2.2L8.5 15 19 19V5L8.5 9h-4A1.5 1.5 0 0 0 3 10.5Z"/><path d="M21.5 9.5a3.5 3.5 0 0 1 0 5"/>',

    'administrador' => '<path d="M12 22s8-4 8-10V5.5L12 2 4 5.5V12c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',

    'log' => '<rect x="9" y="2" width="6" height="4" rx="1.5"/><path d="M15 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h4"/>',

    'sos' => '<path d="M7 18.5V12a5 5 0 0 1 10 0v6.5"/><path d="M4 18.5h16"/><path d="M12 2v1.5M4.9 5.4l1.1 1M19.1 5.4l-1.1 1"/>',

    'sino' => '<path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-3 8.5-3 8.5h18s-3-2-3-8.5"/><path d="M13.7 20.5a2 2 0 0 1-3.4 0"/>',

    'fatura' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',

    /* ---------- Painel do condutor ---------- */

    'som' => '<path d="M11 5 6.5 9H3v6h3.5L11 19V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>',

    'fechar' => '<path d="M6 6l12 12M18 6 6 18"/>',

    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',

    'seta-baixo' => '<path d="m6 9 6 6 6-6"/>',

    'bussola' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5.5-5.5 2 2-5.5 5.5-2Z"/>',

    'localizar' => '<circle cx="12" cy="12" r="3.5"/><circle cx="12" cy="12" r="8"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22"/>',

    'destino' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',

    // Uma régua com marcas vira ruído a 20px; a seta de duplo sentido entre
    // dois batentes lê-se como "distância entre dois pontos" a qualquer tamanho.
    'distancia' => '<path d="M4 6v12M20 6v12"/><path d="M7 12h10"/><path d="m9.5 9.5-2.5 2.5 2.5 2.5M14.5 9.5l2.5 2.5-2.5 2.5"/>',

    'bandeira' => '<path d="M4 21V4"/><path d="M4 5h11l-1.5 3.5L15 12H4"/>',

    'telefone' => '<path d="M6.5 3h-2A1.5 1.5 0 0 0 3 4.6C3 13.1 10.9 21 19.4 21a1.5 1.5 0 0 0 1.6-1.5v-2a1.5 1.5 0 0 0-1.3-1.5l-2.6-.4a1.5 1.5 0 0 0-1.4.6l-.9 1.2a13 13 0 0 1-5.8-5.8l1.2-.9a1.5 1.5 0 0 0 .6-1.4l-.4-2.6A1.5 1.5 0 0 0 6.5 3Z"/>',

    'chat' => '<path d="M20.5 11.5a8 8 0 0 1-11.7 7.1L3.5 20.5l1.9-5.3a8 8 0 1 1 15.1-3.7Z"/>',

    'cidade' => '<path d="M3 21V9l6-3v15"/><path d="M9 12h5.5a1.5 1.5 0 0 1 1.5 1.5V21"/><path d="M16 15h3.5a1.5 1.5 0 0 1 1.5 1.5V21"/><path d="M2 21h20"/><path d="M6 12h.01M6 16h.01"/>',

    'estrada' => '<path d="m7 3-3 18M17 3l3 18"/><path d="M12 4v3M12 10.5v3M12 17v3"/>',

    'telefone_a_receber' => '<path d="M6.5 3h-2A1.5 1.5 0 0 0 3 4.6C3 13.1 10.9 21 19.4 21a1.5 1.5 0 0 0 1.6-1.5v-2a1.5 1.5 0 0 0-1.3-1.5l-2.6-.4a1.5 1.5 0 0 0-1.4.6l-.9 1.2a13 13 0 0 1-5.8-5.8l1.2-.9a1.5 1.5 0 0 0 .6-1.4l-.4-2.6A1.5 1.5 0 0 0 6.5 3Z"/><path d="M21 3l-5.5 5.5"/><path d="M15.5 3.5v5h5"/>',

    'auscultadores' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M4 13.5h1.5A1.5 1.5 0 0 1 7 15v3a1.5 1.5 0 0 1-1.5 1.5H4Z"/><path d="M20 13.5h-1.5A1.5 1.5 0 0 0 17 15v3a1.5 1.5 0 0 0 1.5 1.5H20Z"/>',

    'telefone_desligado' => '<path d="M9.7 4.2A9 9 0 0 1 12 4c1.4 0 2.5.2 3 .4M4.4 8.6C3.5 10.5 3 12.4 3 14v2h5l1-3 3 1M21 16v-2c0-.9-.2-1.9-.5-2.9"/><path d="m3 3 18 18"/>',

    /* Estrela de avaliação. Vai sempre com fill="none" do componente; quem
       precisa dela cheia acrescenta a classe fill-current, que ganha ao
       atributo (CSS vence atributos de apresentação). */
    'estrela' => '<path d="m12 3.2 2.7 5.5 6.1.9-4.4 4.3 1 6-5.4-2.8-5.4 2.8 1-6L3.2 9.6l6.1-.9L12 3.2Z"/>',

    'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 1.9"/>',

    'verificado' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.5 2.5 4.5-5"/>',

    'alerta' => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17.5h.01"/>',
];
