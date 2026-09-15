<?php
/**
 * V-MILLION — API: pedidos de viagem urbana em aberto, visíveis a qualquer
 * condutor aprovado e em dia — mesmo fora de um ponto de partida (secção
 * "condutores aprovados fora do ponto veem passageiros urbanos"). O
 * contacto do passageiro só é revelado depois de reclamado (privacidade —
 * ver api/condutor/recolher_urbano.php e api/condutor/passageiros.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/veiculos.php';

header('Content-Type: application/json; charset=utf-8');
$condutor = kg_exigir_utilizador('condutor');
$pdo = kg_db();

if (!kg_condutor_pode_ver_mapa($pdo, $condutor['id'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'A sua conta ainda não tem acesso ao mapa (aprovação ou pagamento pendente).']);
    exit;
}

$condutorLat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT) ?: null;
$condutorLng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT) ?: null;

// Um veículo 'intermunicipal' nunca vê pedidos urbanos; um 'ambos' só os vê
// enquanto NÃO tiver ponto de partida definido (com ponto definido está a
// operar como intermunicipal — vê antes os passageiros desse ponto/destino
// em api/condutor/passageiros.php). Sem veiculo_id (nenhum veículo
// selecionado ainda), mantém-se o comportamento anterior de mostrar sempre
// a procura urbana em aberto.
$veiculoId = filter_input(INPUT_GET, 'veiculo_id', FILTER_VALIDATE_INT);
if ($veiculoId) {
    $veiculoStmt = $pdo->prepare("SELECT tipo_servico, ponto_partida_id FROM veiculos WHERE id = ? AND condutor_id = ?");
    $veiculoStmt->execute([$veiculoId, $condutor['id']]);
    $veiculo = $veiculoStmt->fetch();
    if (!$veiculo) {
        http_response_code(404);
        echo json_encode(['erro' => 'Veículo não encontrado.']);
        exit;
    }
    $elegivel = $veiculo['tipo_servico'] === 'urbano'
        || ($veiculo['tipo_servico'] === 'ambos' && $veiculo['ponto_partida_id'] === null);
    if (!$elegivel) {
        echo json_encode([
            'passageiros' => [],
            'elegivel' => false,
            'motivo' => $veiculo['tipo_servico'] === 'intermunicipal'
                ? 'Este veículo é só intermunicipal — não recebe pedidos urbanos.'
                : 'Este veículo tem um ponto de partida definido — a operar como intermunicipal. Remova o ponto para voltar a ver pedidos urbanos.',
        ]);
        exit;
    }
}

$stmt = $pdo->prepare(
    "SELECT r.id, r.ponto_descida_nome, r.ponto_descida_lat, r.ponto_descida_lng,
            r.passageiro_lat, r.passageiro_lng, r.lugares, r.preco_final, r.criado_em,
            u.nome AS passageiro_nome
     FROM reservas r
     JOIN utilizadores u ON u.id = r.passageiro_id
     WHERE r.tipo_viagem = 'urbano' AND r.veiculo_id IS NULL AND r.estado = 'pendente'
     ORDER BY r.criado_em ASC LIMIT 100"
);
$stmt->execute();
$passageiros = $stmt->fetchAll();

if ($condutorLat !== null && $condutorLng !== null) {
    foreach ($passageiros as &$p) {
        $p['distancia_m'] = round(kg_distancia_metros($condutorLat, $condutorLng, (float) $p['passageiro_lat'], (float) $p['passageiro_lng']));
    }
    unset($p);
    usort($passageiros, fn($a, $b) => $a['distancia_m'] <=> $b['distancia_m']);
} else {
    foreach ($passageiros as &$p) {
        $p['distancia_m'] = null;
    }
    unset($p);
}

echo json_encode(['passageiros' => $passageiros, 'elegivel' => true]);
