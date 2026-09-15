<?php

declare(strict_types=1);

/**
 * V-MILLION — configuração de domínio.
 *
 * Substitui as constantes KG_* de legacy/config/config.php. Tudo o que muda
 * entre ambientes vem de .env; o que é regra de negócio (limites geográficos
 * de Cabo Verde, layout de assentos, formato do telefone) fica aqui fixo.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Limites geográficos de Cabo Verde
    |--------------------------------------------------------------------------
    | Nada flutua: toda a coordenada aceite pelo sistema é validada contra
    | esta caixa (secção 5.3). Os CHECK constraints das tabelas com lat/lng
    | usam exatamente os mesmos valores.
    */
    'geo' => [
        'lat_min' => 14.7,
        'lat_max' => 17.2,
        'lng_min' => -25.4,
        'lng_max' => -22.7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validação de dados
    |--------------------------------------------------------------------------
    */
    'validacao' => [
        'regex_telefone' => '/^\+238[ ]?[0-9]{7}$/',
        'regex_nif' => '/^[0-9]{9}$/',
        'senha_min_len' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting de login
    |--------------------------------------------------------------------------
    | Duas camadas independentes:
    |
    | 1. Bloqueio por conta (LoginThrottleService): vive na própria linha da
    |    conta, para que um administrador o possa ver e limpar no painel. Cada
    |    bloqueio consecutivo dura o dobro do anterior, até ao teto.
    | 2. Limite por pedido (limitador 'login' em AppServiceProvider): trava o
    |    volume por IP e por identificador antes de chegar ao controller. Sem
    |    ele, o bloqueio por conta não impedia password spraying — uma senha
    |    comum testada contra milhares de telefones nunca chega às 5
    |    tentativas de nenhuma conta.
    |
    | O Super Admin nunca é bloqueado pela camada 1 (ver LoginThrottleService).
    |
    | Os limites por IP são generosos de propósito: em Cabo Verde as redes
    | móveis fazem NAT pesado e vários utilizadores legítimos partilham o
    | mesmo endereço.
    */
    'login' => [
        'max_tentativas' => (int) env('VMILLION_LOGIN_MAX_TENTATIVAS', 5),
        'bloqueio_minutos' => (int) env('VMILLION_LOGIN_BLOQUEIO_MINUTOS', 15),
        'bloqueio_max_minutos' => (int) env('VMILLION_LOGIN_BLOQUEIO_MAX_MINUTOS', 60),

        'throttle' => [
            'ip_por_minuto' => (int) env('VMILLION_LOGIN_THROTTLE_IP_MINUTO', 30),
            'ip_por_hora' => (int) env('VMILLION_LOGIN_THROTTLE_IP_HORA', 200),
            'conta_por_minuto' => (int) env('VMILLION_LOGIN_THROTTLE_CONTA_MINUTO', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting do registo
    |--------------------------------------------------------------------------
    | Sem limite, /api/auth/registar servia para duas coisas de uma vez: criar
    | contas em massa (cada condutor novo cai na fila de aprovação do admin) e
    | usar o 409 de duplicado como oráculo para varrer o espaço de NIFs, que
    | tem apenas nove dígitos.
    |
    | Os valores são mais apertados que os do login porque registar é um ato
    | raro: uma pessoa real fá-lo uma vez, não dez vezes por minuto. Continuam
    | a ser generosos ao nível da hora por causa do NAT das redes móveis de
    | Cabo Verde, onde muitos utilizadores legítimos partilham um IP.
    */
    'registo' => [
        'throttle' => [
            'ip_por_minuto' => (int) env('VMILLION_REGISTO_THROTTLE_IP_MINUTO', 5),
            'ip_por_hora' => (int) env('VMILLION_REGISTO_THROTTLE_IP_HORA', 20),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Veículos
    |--------------------------------------------------------------------------
    | A capacidade depende do tipo de veículo. Hiace: 14 lugares — fila 0
    | (junto ao condutor) com 2 bancos, filas 1 a 4 com 3 bancos cada.
    | Autocarro: 30 lugares — 10 filas de 3, sem lugar ao lado do condutor.
    | Táxi: 4 lugares — o banco de trás com 3 e o lugar ao lado do condutor
    | (fila 0). É o valor com que o formulário "Adicionar veículo" abre; o
    | condutor pode escrever outro, até ao teto do tipo. Um tipo sem entrada em 'lugares_por_tipo' usa
    | 'lugares_total' — ver VeiculoService::lugaresTotal().
    |
    | Essa é a capacidade com que o veículo nasce. Depois, o condutor tira e
    | repõe lugares no botão "Lugares" do painel, entre 1 e o teto do tipo em
    | 'lugares_max_por_tipo'; acima da capacidade de origem, os lugares a mais
    | vão em filas novas atrás — ver VeiculoService::reporLugar().
    */
    'veiculos' => [
        'lugares_total' => 14,
        'lugares_por_tipo' => [
            'hiace' => 14,
            'taxi' => 4,
            'autocarro' => 30,
        ],
        'lugares_max_por_tipo' => [
            'hiace' => 16,
            'taxi' => 7,
            'autocarro' => 60,
        ],
        'lugares_max_por_reserva' => 8,

        // Depois de guardar o ponto de partida/destino, o botão "Guardar" do
        // painel do condutor desaparece durante estas horas e o servidor
        // recusa nova alteração. É o administrador que liberta antes do
        // prazo (ação 'libertar_ponto' em Admin\VeiculoController).
        'bloqueio_ponto_horas' => (int) env('VMILLION_VEICULO_BLOQUEIO_PONTO_HORAS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Motor de rotas (OSRM)
    |--------------------------------------------------------------------------
    | 'url' é a instância própria (tipicamente localhost). 'url_publica' é a
    | instância pública do projeto OSRM, usada só como último recurso quando a
    | própria falha ou está lenta — sem SLA, nunca é a via principal. Ambas
    | são chamadas do lado do servidor, nunca do browser (por isso não entram
    | na CSP).
    */
    'osrm' => [
        'url' => env('VMILLION_OSRM_URL', 'http://localhost:5001'),
        'url_publica' => env('VMILLION_OSRM_PUBLIC_URL', 'https://router.project-osrm.org'),
        'timeout' => (float) env('VMILLION_OSRM_TIMEOUT', 5),
        'cache_ttl' => (int) env('VMILLION_ROTA_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sessão
    |--------------------------------------------------------------------------
    | Timeout de inatividade em minutos, aplicado por TimeoutInatividade.
    */
    'sessao' => [
        'inactivity_timeout' => (int) env('SESSION_INACTIVITY_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificações push (Web Push)
    |--------------------------------------------------------------------------
    | O par de chaves VAPID identifica este servidor perante os serviços de
    | push dos fabricantes (FCM, Mozilla, WNS). Gera-se uma vez, com
    | `php artisan push:chaves`, e vive no .env — a pública também é entregue
    | ao browser, a privada nunca sai daqui.
    |
    | Trocar a chave pública invalida todas as subscrições já feitas: os
    | browsers subscreveram com a antiga e o serviço de push passa a recusar
    | os envios (403). Se alguma vez for preciso rodá-la, a tabela
    | subscricoes_push tem de ser esvaziada no mesmo movimento.
    |
    | 'assunto' entra no JWT (RFC 8292 §2.1) e é o contacto que o fabricante
    | usa se houver algum problema com os envios deste servidor.
    |
    | Sem chaves configuradas o push fica simplesmente desligado (ver
    | WebPushService::ativo()) — o resto da app funciona na mesma.
    */
    'push' => [
        'chave_publica' => env('VMILLION_PUSH_CHAVE_PUBLICA', ''),
        'chave_privada' => env('VMILLION_PUSH_CHAVE_PRIVADA', ''),
        'assunto' => env('VMILLION_PUSH_ASSUNTO', 'mailto:suporte@vmillion.cv'),
        'timeout' => (float) env('VMILLION_PUSH_TIMEOUT', 5),

        // Uma chamada a tocar tem o tempo de vida do próprio toque: passado
        // isto já ninguém está à espera do outro lado (ver
        // vmillion.chamadas.timeout_segundos).
        'ttl_chamada' => (int) env('VMILLION_PUSH_TTL_CHAMADA', 60),

        // "O veículo saiu do ponto" e "o veículo chegou ao ponto" não são
        // avisos que se esgotem no instante: entregues com alguns minutos de
        // atraso (telemóvel sem rede num vale, ecrã desligado) continuam a
        // dizer ao passageiro o que aconteceu à viagem dele. Passada meia
        // hora, já não — a viagem terá acabado entretanto.
        'ttl_viagem' => (int) env('VMILLION_PUSH_TTL_VIAGEM', 1800),

        // Um pedido de passageiro é reclamado em minutos: entregue um quarto
        // de hora depois, o condutor ia buscar alguém que já seguiu noutro
        // carro (ou desistiu).
        'ttl_pedido' => (int) env('VMILLION_PUSH_TTL_PEDIDO', 300),

        // Só são avisados de pedidos urbanos os veículos que mandaram posição
        // há menos disto: um táxi parado desde ontem não está a trabalhar
        // (ver AvisoPedidoService::condutoresUrbanosPara).
        'pedido_urbano_janela_min' => (int) env('VMILLION_PUSH_PEDIDO_URBANO_JANELA_MIN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chamadas
    |--------------------------------------------------------------------------
    | Chamadas 'iniciada' sem resposta ficariam para sempre a bloquear novas
    | chamadas para o mesmo destinatário; são fechadas como perdidas ao fim
    | de 'timeout_segundos', sempre que a tabela é consultada (sem cron).
    |
    | Uma chamada já 'atendida' tem o mesmo problema pela outra ponta: se o
    | browser fechar ou recarregar a meio, o /desligar nunca chega e a linha
    | ficaria ocupada para sempre. Enquanto a chamada está viva, o painel
    | manda um pulso (/api/chamada/pulso) a cada 15s; passados
    | 'pulso_timeout_segundos' sem nenhum pulso, considera-se que já não há
    | ninguém do outro lado e a chamada é fechada. A folga é larga de
    | propósito — só uma chamada mesmo abandonada lá chega.
    */
    'chamadas' => [
        'timeout_segundos' => 60,
        'pulso_timeout_segundos' => 300,
    ],
];
