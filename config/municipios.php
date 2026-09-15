<?php

declare(strict_types=1);

/**
 * Ilhas habitadas de Cabo Verde e os seus municípios (concelhos).
 *
 * Alimenta os seletores de ilha e município do registo e a validação desses
 * campos (RegistoRequest). Os nomes são exatamente os do traçado que o mapa
 * desenha e que o MunicipioService lê (public/assets/geo/cv-municipios.geojson):
 * um município guardado numa conta pode assim ser comparado diretamente com
 * o concelho onde uma coordenada cai. O teste
 * RegistoIlhaMunicipioTest::test_a_lista_e_a_mesma_do_mapa falha se as duas
 * listas se afastarem.
 *
 * Ilhas de norte para sul (Barlavento e depois Sotavento); municípios por
 * ordem alfabética.
 */
return [
    'Santo Antão' => ['Paúl', 'Porto Novo', 'Ribeira Grande'],
    'São Vicente' => ['São Vicente'],
    'São Nicolau' => ['Ribeira Brava', 'Tarrafal de São Nicolau'],
    'Sal' => ['Sal'],
    'Boa Vista' => ['Boa Vista'],
    'Maio' => ['Maio'],
    'Santiago' => [
        'Praia',
        'Ribeira Grande de Santiago',
        'Santa Catarina',
        'Santa Cruz',
        'São Domingos',
        'São Lourenço dos Órgãos',
        'São Miguel',
        'São Salvador do Mundo',
        'Tarrafal',
    ],
    'Fogo' => ['Mosteiros', 'Santa Catarina do Fogo', 'São Filipe'],
    'Brava' => ['Brava'],
];
