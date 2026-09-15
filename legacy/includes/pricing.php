<?php
/**
 * V-MILLION — Motor de preços dinâmicos (secção 12 da especificação).
 *
 * Regras:
 *  - Preço fixo por rota (ponto A -> ponto B), definido pelo Super Admin.
 *  - Preço por km dentro de cidade (zona 'urbana').
 *  - Preço por km fora de cidade (zona 'intermunicipal').
 *  - Se a rota percorrida pelo passageiro atravessar as duas zonas, o preço
 *    é calculado proporcionalmente (km urbanos * preço urbano + km
 *    intermunicipais * preço intermunicipal).
 *  - Se existir preço fixo para a rota completa, este é usado como teto
 *    máximo: o passageiro paga o menor valor entre o proporcional e o fixo.
 */

declare(strict_types=1);

/**
 * Consulta os preços por km atualmente configurados.
 * @return array{urbana: float, intermunicipal: float}
 */
function kg_obter_precos_km(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT zona, preco_por_km_cve FROM precos_km");
    $precos = ['urbana' => 0.0, 'intermunicipal' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        $precos[$row['zona']] = (float) $row['preco_por_km_cve'];
    }
    return $precos;
}

/**
 * Procura um preço fixo definido para a rota entre dois pontos (nas duas direções).
 */
function kg_obter_preco_fixo_rota(PDO $pdo, int $pontoOrigemId, int $pontoDestinoId): ?float
{
    $stmt = $pdo->prepare(
        "SELECT preco_fixo_cve FROM precos_rotas
         WHERE (ponto_origem_id = ? AND ponto_destino_id = ?)
            OR (ponto_origem_id = ? AND ponto_destino_id = ?)
         LIMIT 1"
    );
    $stmt->execute([$pontoOrigemId, $pontoDestinoId, $pontoDestinoId, $pontoOrigemId]);
    $row = $stmt->fetch();
    return $row ? (float) $row['preco_fixo_cve'] : null;
}

/**
 * Calcula o preço proporcional dado o número de km percorridos em cada zona
 * até ao ponto de descida do passageiro (não a rota completa).
 */
function kg_calcular_preco_proporcional(float $kmUrbano, float $kmIntermunicipal, array $precosKm): float
{
    $kmUrbano = max(0.0, $kmUrbano);
    $kmIntermunicipal = max(0.0, $kmIntermunicipal);

    return round(
        $kmUrbano * $precosKm['urbana'] + $kmIntermunicipal * $precosKm['intermunicipal'],
        2
    );
}

/**
 * Calcula o preço final da reserva.
 *
 * @param float $kmUrbanoAteDescida        Km percorridos em zona urbana até ao ponto de descida.
 * @param float $kmIntermunicipalAteDescida Km percorridos em zona intermunicipal até ao ponto de descida.
 */
function kg_calcular_preco_reserva(
    PDO $pdo,
    int $pontoOrigemId,
    int $pontoDestinoId,
    float $kmUrbanoAteDescida,
    float $kmIntermunicipalAteDescida
): float {
    $precosKm = kg_obter_precos_km($pdo);
    $proporcional = kg_calcular_preco_proporcional($kmUrbanoAteDescida, $kmIntermunicipalAteDescida, $precosKm);

    $fixo = kg_obter_preco_fixo_rota($pdo, $pontoOrigemId, $pontoDestinoId);
    if ($fixo !== null) {
        // O preço fixo da rota completa funciona como teto máximo.
        return min($proporcional, $fixo);
    }

    return $proporcional;
}

/**
 * Preço de uma viagem urbana (secção A do relatório "viagem urbana vs
 * intermunicipal"): o destino não é um ponto da lista mas um local escrito/
 * geocodificado pelo passageiro (ponto de descida), por isso não há
 * segmentação de zonas a fazer — é sempre tarifa urbana, distância direta
 * entre a posição de partida e o ponto de descida.
 * @return array{preco: float, metodo: string}
 */
function kg_calcular_preco_urbano(PDO $pdo, float $distanciaKm): array
{
    $config = kg_obter_config_precos($pdo);
    $precosKm = kg_obter_precos_km($pdo);
    $preco = round(max(0.0, $distanciaKm) * $precosKm['urbana'], 2);
    return ['preco' => kg_aplicar_limites_preco($preco, $config), 'metodo' => 'urbano_direto'];
}

/**
 * Grava (ou reforça, se o mesmo nome já existir) um destino escrito por um
 * passageiro numa viagem urbana, para poder ser sugerido a outros
 * passageiros (secção "destinos criados por passageiros são gravados e
 * reutilizáveis"). Nunca bloqueia a criação da reserva por causa disto.
 */
function kg_guardar_destino_urbano(PDO $pdo, string $nome, float $lat, float $lng, int $criadoPor): void
{
    try {
        $pdo->prepare(
            "INSERT INTO destinos_urbanos (nome, lat, lng, criado_por, usos) VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE usos = usos + 1, atualizado_em = CURRENT_TIMESTAMP"
        )->execute([$nome, $lat, $lng, $criadoPor]);
    } catch (Throwable $e) {
        error_log('[V-MILLION] Falha ao gravar destino urbano: ' . $e->getMessage());
    }
}

/**
 * Consulta as configurações gerais de preço (valor mínimo/máximo, taxa de operação por rota).
 * @return array{valor_minimo: float, valor_maximo: float, taxa_operacao_rota: float}
 */
function kg_obter_config_precos(PDO $pdo): array
{
    $config = ['valor_minimo' => 0.0, 'valor_maximo' => 0.0, 'taxa_operacao_rota' => 0.0];
    $stmt = $pdo->query("SELECT chave, valor FROM config_precos");
    foreach ($stmt->fetchAll() as $row) {
        $config[$row['chave']] = (float) $row['valor'];
    }
    return $config;
}

/**
 * Aplica o piso e o teto configurados (secção 12.1 — "Valor mínimo e máximo").
 * Um valor_maximo de 0 é tratado como "sem teto".
 */
function kg_aplicar_limites_preco(float $preco, array $config): float
{
    $min = $config['valor_minimo'] ?? 0.0;
    $max = $config['valor_maximo'] ?? 0.0;
    if ($min > 0 && $preco < $min) {
        $preco = $min;
    }
    if ($max > 0 && $preco > $max) {
        $preco = $max;
    }
    return round($preco, 2);
}

/**
 * Limite (centro + raio) configurado para uma cidade, usado para separar o
 * troço urbano do troço intermunicipal de uma viagem entre cidades.
 * @return array{lat: float, lng: float, raio_km: float}|null
 */
function kg_obter_limite_cidade(PDO $pdo, string $cidade): ?array
{
    $stmt = $pdo->prepare("SELECT lat, lng, raio_km FROM limites_cidades WHERE cidade = ? LIMIT 1");
    $stmt->execute([$cidade]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return ['lat' => (float) $row['lat'], 'lng' => (float) $row['lng'], 'raio_km' => (float) $row['raio_km']];
}

/**
 * Projeção plana local (equirretangular) em km, válida para as pequenas
 * distâncias de uma única rota dentro do arquipélago — suficiente para
 * resolver a interseção reta/círculo abaixo sem precisar de geometria esférica exata.
 * @return array{x: float, y: float}
 */
function kg_latlng_para_xy_km(float $lat, float $lng, float $latReferencia): array
{
    return [
        'x' => $lng * 111.320 * cos(deg2rad($latReferencia)),
        'y' => $lat * 110.574,
    ];
}

/**
 * Resolve a interseção entre o segmento O->D (coordenadas já projetadas em km)
 * e a circunferência de centro C e raio r. Devolve os dois parâmetros t
 * (ordenados) tais que o ponto O + t*(D-O) está sobre a circunferência, ou
 * null se a reta não intersectar o círculo.
 * @return array{0: float, 1: float}|null
 */
function kg_raizes_intersecao_circulo(array $o, array $d, array $c, float $raio): ?array
{
    $ax = $d['x'] - $o['x'];
    $ay = $d['y'] - $o['y'];
    $relx = $o['x'] - $c['x'];
    $rely = $o['y'] - $c['y'];

    $a = $ax * $ax + $ay * $ay;
    if ($a < 1e-9) {
        return null;
    }
    $b = 2 * ($relx * $ax + $rely * $ay);
    $cc = $relx * $relx + $rely * $rely - $raio * $raio;

    $disc = $b * $b - 4 * $a * $cc;
    if ($disc < 0) {
        return null;
    }
    $sqrtDisc = sqrt($disc);
    $t1 = (-$b - $sqrtDisc) / (2 * $a);
    $t2 = (-$b + $sqrtDisc) / (2 * $a);

    return $t1 <= $t2 ? [$t1, $t2] : [$t2, $t1];
}

/**
 * Segmenta a rota origem->destino em três troços (secção 12.1):
 *  - do ponto de partida até sair do limite da cidade de origem (urbano);
 *  - da saída da cidade de origem até entrar no limite da cidade de destino (intermunicipal);
 *  - da entrada na cidade de destino até ao destino (urbano, tarifa universal).
 * Devolve as distâncias (em km, a partir da origem) onde a rota sai da
 * cidade de origem ("saida_km") e onde entra na cidade de destino ("entrada_km").
 * Se não houver limites de cidade configurados, aproxima por metade da rota
 * (comportamento de recurso, nunca bloqueia o cálculo do preço).
 * @return array{saida_km: float, entrada_km: float}
 */
function kg_segmentar_zona_urbana(PDO $pdo, array $pontoPartida, array $destino, float $distanciaTotalKm): array
{
    if ($distanciaTotalKm <= 0.0001) {
        return ['saida_km' => 0.0, 'entrada_km' => 0.0];
    }

    if ($pontoPartida['cidade'] === $destino['cidade']) {
        // Toda a rota é urbana: saída = entrada = distância total (ver kg_zonas_ate_distancia).
        return ['saida_km' => $distanciaTotalKm, 'entrada_km' => $distanciaTotalKm];
    }

    $limiteOrigem = kg_obter_limite_cidade($pdo, (string) $pontoPartida['cidade']);
    $limiteDestino = kg_obter_limite_cidade($pdo, (string) $destino['cidade']);

    if (!$limiteOrigem || !$limiteDestino) {
        $metade = $distanciaTotalKm / 2;
        return ['saida_km' => $metade, 'entrada_km' => $metade];
    }

    $latRef = ((float) $pontoPartida['lat'] + (float) $destino['lat']) / 2;
    $o = kg_latlng_para_xy_km((float) $pontoPartida['lat'], (float) $pontoPartida['lng'], $latRef);
    $d = kg_latlng_para_xy_km((float) $destino['lat'], (float) $destino['lng'], $latRef);
    $co = kg_latlng_para_xy_km($limiteOrigem['lat'], $limiteOrigem['lng'], $latRef);
    $cd = kg_latlng_para_xy_km($limiteDestino['lat'], $limiteDestino['lng'], $latRef);

    // Ponto onde a rota sai do círculo da cidade de origem (assumindo que o
    // ponto de partida está dentro do seu próprio limite de cidade).
    $saidaT = 0.0;
    $distOrigemCentro = sqrt(($o['x'] - $co['x']) ** 2 + ($o['y'] - $co['y']) ** 2);
    if ($distOrigemCentro <= $limiteOrigem['raio_km']) {
        $raizes = kg_raizes_intersecao_circulo($o, $d, $co, $limiteOrigem['raio_km']);
        $saidaT = 1.0;
        if ($raizes !== null) {
            foreach ($raizes as $t) {
                if ($t > 1e-6) {
                    $saidaT = min(1.0, $t);
                    break;
                }
            }
        }
    }

    // Ponto onde a rota entra no círculo da cidade de destino.
    $entradaT = 1.0;
    $distDestinoCentro = sqrt(($d['x'] - $cd['x']) ** 2 + ($d['y'] - $cd['y']) ** 2);
    if ($distDestinoCentro <= $limiteDestino['raio_km']) {
        $raizes = kg_raizes_intersecao_circulo($o, $d, $cd, $limiteDestino['raio_km']);
        $entradaT = 0.0;
        if ($raizes !== null) {
            $entradaT = max(0.0, min(1.0, $raizes[0]));
        }
    }

    $saidaKm = max(0.0, min($distanciaTotalKm, $saidaT * $distanciaTotalKm));
    $entradaKm = max(0.0, min($distanciaTotalKm, $entradaT * $distanciaTotalKm));
    if ($entradaKm < $saidaKm) {
        // Geometria degenerada (círculos sobrepostos): sem troço intermunicipal.
        $entradaKm = $saidaKm;
    }

    return ['saida_km' => $saidaKm, 'entrada_km' => $entradaKm];
}

/**
 * Dada a segmentação da rota completa (kg_segmentar_zona_urbana) e a
 * distância até ao ponto de descida de um passageiro, devolve [kmUrbano, kmIntermunicipal]
 * para esse troço específico. Um passageiro que desce dentro do limite da
 * cidade de origem (secção 12.1, "destino na periferia") paga só tarifa urbana.
 * @return array{0: float, 1: float}
 */
function kg_zonas_ate_distancia(array $segmentos, float $distanciaAteDescidaKm): array
{
    $d = max(0.0, $distanciaAteDescidaKm);
    $saida = $segmentos['saida_km'];
    $entrada = $segmentos['entrada_km'];

    $kmUrbano = min($d, $saida) + max(0.0, $d - $entrada);
    $kmIntermunicipal = max(0.0, min($d, $entrada) - $saida);

    return [$kmUrbano, $kmIntermunicipal];
}

/**
 * Fraciona o preço fixo de uma rota (secção 12.1, exemplo Praia -> Calheta)
 * proporcionalmente à distância que o passageiro percorre dentro dessa rota,
 * usando a distância de cada ponto (embarque/desembarque) até à origem da rota.
 * Devolve null se a rota não tiver distância registada (admin ainda não a
 * definiu) — o chamador deve então cair no cálculo por km.
 */
function kg_calcular_preco_rota_fixa_fracionado(PDO $pdo, int $rotaId, array $pontoEmbarque, array $pontoDesembarque): ?float
{
    $stmt = $pdo->prepare(
        "SELECT pr.preco_fixo_cve, pr.distancia_km, po.lat AS origem_lat, po.lng AS origem_lng
         FROM precos_rotas pr JOIN pontos_partida po ON po.id = pr.ponto_origem_id
         WHERE pr.id = ?"
    );
    $stmt->execute([$rotaId]);
    $rota = $stmt->fetch();

    if (!$rota || $rota['distancia_km'] === null || (float) $rota['distancia_km'] <= 0) {
        return null;
    }

    $distanciaTotal = (float) $rota['distancia_km'];
    $origemLat = (float) $rota['origem_lat'];
    $origemLng = (float) $rota['origem_lng'];

    $distanciaAteEmbarque = kg_distancia_metros($origemLat, $origemLng, (float) $pontoEmbarque['lat'], (float) $pontoEmbarque['lng']) / 1000;
    $distanciaAteDesembarque = kg_distancia_metros($origemLat, $origemLng, (float) $pontoDesembarque['lat'], (float) $pontoDesembarque['lng']) / 1000;

    $fracao = ($distanciaAteDesembarque - $distanciaAteEmbarque) / $distanciaTotal;
    $fracao = max(0.0, min(1.0, $fracao));

    return round($fracao * (float) $rota['preco_fixo_cve'], 2);
}

/**
 * Ponto de entrada único do motor de preços (secção 12). Ordem de decisão:
 *  1) Se o veículo tem uma rota fixa associada e essa rota tem distância
 *     registada, fraciona o preço fixo proporcionalmente ao troço do passageiro.
 *  2) Caso contrário, calcula por km usando a segmentação real urbano/intermunicipal
 *     (limites de cidade), com o preço fixo da rota (se existir) como teto.
 * Em ambos os casos aplica o valor mínimo/máximo configurado.
 * @return array{preco: float, metodo: string}
 */
function kg_calcular_preco_viagem(
    PDO $pdo,
    ?array $veiculo,
    array $pontoPartida,
    array $destino,
    float $distanciaTotalKm,
    float $distanciaAteDescidaKm,
    ?array $pontoDescidaCoord = null
): array {
    $config = kg_obter_config_precos($pdo);

    if ($veiculo && !empty($veiculo['rota_fixa_id'])) {
        $pontoChegada = $pontoDescidaCoord ?? ['lat' => $destino['lat'], 'lng' => $destino['lng']];
        $precoFracionado = kg_calcular_preco_rota_fixa_fracionado($pdo, (int) $veiculo['rota_fixa_id'], $pontoPartida, $pontoChegada);
        if ($precoFracionado !== null) {
            return ['preco' => kg_aplicar_limites_preco($precoFracionado, $config), 'metodo' => 'rota_fixa_fracionada'];
        }
    }

    $segmentos = kg_segmentar_zona_urbana($pdo, $pontoPartida, $destino, $distanciaTotalKm);
    [$kmUrbano, $kmIntermunicipal] = kg_zonas_ate_distancia($segmentos, $distanciaAteDescidaKm);

    $precosKm = kg_obter_precos_km($pdo);
    $proporcional = kg_calcular_preco_proporcional($kmUrbano, $kmIntermunicipal, $precosKm);

    $fixo = kg_obter_preco_fixo_rota($pdo, (int) $pontoPartida['id'], (int) $destino['id']);
    $preco = $fixo !== null ? min($proporcional, $fixo) : $proporcional;

    return [
        'preco' => kg_aplicar_limites_preco($preco, $config),
        'metodo' => $fixo !== null ? 'rota_fixa_teto' : 'proporcional',
    ];
}

/**
 * Obtém a rota (geometria + distância) via OSRM (instância própria, definida
 * em KG_OSRM_URL). Lança exceção em caso de falha — o chamador deve tratar
 * (ex.: usar apenas distância em linha reta como aproximação de recurso,
 * nunca bloquear a reserva).
 *
 * @return array{distancia_m: float, duracao_s: float, geometria: array}
 */
function kg_osrm_calcular_rota(float $origLat, float $origLng, float $destLat, float $destLng, string $baseUrl = KG_OSRM_URL): array
{
    $url = sprintf(
        '%s/route/v1/driving/%F,%F;%F,%F?overview=full&geometries=geojson',
        rtrim($baseUrl, '/'),
        $origLng,
        $origLat,
        $destLng,
        $destLat
    );

    $ctx = stream_context_create(['http' => ['timeout' => KG_OSRM_TIMEOUT_S]]);
    $resposta = @file_get_contents($url, false, $ctx);
    if ($resposta === false) {
        throw new RuntimeException("OSRM indisponível ({$baseUrl})");
    }

    $dados = json_decode($resposta, true);
    if (!isset($dados['routes'][0])) {
        throw new RuntimeException("OSRM não devolveu rota válida ({$baseUrl})");
    }

    $rota = $dados['routes'][0];
    return [
        'distancia_m' => (float) $rota['distance'],
        'duracao_s'   => (float) $rota['duration'],
        'geometria'   => $rota['geometry']['coordinates'] ?? [],
    ];
}

/**
 * Igual a kg_osrm_calcular_rota, mas com rede de segurança: se a instância
 * própria (KG_OSRM_URL, tipicamente localhost) falhar ou demorar demasiado,
 * tenta a instância pública do projeto OSRM (KG_OSRM_PUBLIC_URL) antes de
 * desistir — é isto que evita cair para linha reta só porque o serviço local
 * está temporariamente indisponível/lento (ex.: container Docker sob carga).
 * Só lança RuntimeException se ambas falharem.
 */
function kg_osrm_calcular_rota_com_fallback(float $origLat, float $origLng, float $destLat, float $destLng): array
{
    try {
        return kg_osrm_calcular_rota($origLat, $origLng, $destLat, $destLng, KG_OSRM_URL);
    } catch (Throwable $e) {
        error_log('[V-MILLION] OSRM local falhou, a tentar instância pública: ' . $e->getMessage());
        return kg_osrm_calcular_rota($origLat, $origLng, $destLat, $destLng, KG_OSRM_PUBLIC_URL);
    }
}

/**
 * Liga (uma vez por pedido) ao Redis usado para cache de rotas (KG_REDIS_HOST/
 * KG_REDIS_PORT). Devolve null — nunca lança — se a extensão phpredis não
 * estiver instalada ou o servidor estiver em baixo: o cache é sempre um
 * atalho de performance, nunca uma dependência para a rota/preço funcionar.
 */
function kg_redis(): ?Redis
{
    static $redis = null;
    static $tentado = false;

    if ($redis !== null) {
        return $redis;
    }
    if ($tentado || !extension_loaded('redis')) {
        return null;
    }
    $tentado = true;

    try {
        $ligacao = new Redis();
        if (!$ligacao->connect(KG_REDIS_HOST, KG_REDIS_PORT, 1.0)) {
            return null;
        }
        $redis = $ligacao;
        return $redis;
    } catch (Throwable $e) {
        error_log('[V-MILLION] Redis indisponível: ' . $e->getMessage());
        return null;
    }
}

/**
 * Igual a kg_osrm_calcular_rota, mas com cache em Redis (24h — KG_REDIS_ROTA_TTL_S)
 * para reduzir a carga no servidor OSRM em rotas repetidas (mesmos pontos de
 * partida/destino, comuns porque a maioria das viagens usa os pontos fixos da
 * lista). Sem Redis disponível, comporta-se exatamente como kg_osrm_calcular_rota
 * (chama sempre o OSRM); continua a lançar RuntimeException se o OSRM falhar.
 */
function kg_osrm_calcular_rota_cache(float $origLat, float $origLng, float $destLat, float $destLng): array
{
    // 5 casas decimais (~1m de precisão) para maximizar acertos de cache sem
    // misturar pontos distintos.
    $chave = sprintf('rota_%.5F_%.5F_%.5F_%.5F', $origLat, $origLng, $destLat, $destLng);
    $redis = kg_redis();

    if ($redis) {
        try {
            $emCache = $redis->get($chave);
            if ($emCache !== false) {
                $dados = json_decode((string) $emCache, true);
                if (is_array($dados) && isset($dados['distancia_m'], $dados['duracao_s'], $dados['geometria'])) {
                    return $dados;
                }
            }
        } catch (Throwable $e) {
            error_log('[V-MILLION] Falha ao ler cache de rota no Redis: ' . $e->getMessage());
        }
    }

    $rota = kg_osrm_calcular_rota_com_fallback($origLat, $origLng, $destLat, $destLng);

    if ($redis) {
        try {
            $redis->setex($chave, KG_REDIS_ROTA_TTL_S, json_encode($rota));
        } catch (Throwable $e) {
            error_log('[V-MILLION] Falha ao gravar cache de rota no Redis: ' . $e->getMessage());
        }
    }

    return $rota;
}
