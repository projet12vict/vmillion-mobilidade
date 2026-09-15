<?php
/**
 * V-MILLION — API: lista de passageiros que escolheram um veículo do condutor (secção 9.4).
 * O contacto do passageiro só é revelado depois de o passageiro escolher o veículo (secção 13) — aqui já é visível pois a reserva existe.
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

$veiculoId = filter_input(INPUT_GET, 'veiculo_id', FILTER_VALIDATE_INT);
if (!$veiculoId) {
    http_response_code(422);
    echo json_encode(['erro' => 'Veículo inválido.']);
    exit;
}

$dono = $pdo->prepare("SELECT id, lat, lng FROM veiculos WHERE id = ? AND condutor_id = ?");
$dono->execute([$veiculoId, $condutor['id']]);
$veiculo = $dono->fetch();
if (!$veiculo) {
    http_response_code(403);
    echo json_encode(['erro' => 'Veículo não pertence ao condutor autenticado.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.id, r.estado, r.preco_final, r.tipo_viagem, r.ponto_descida_nome, r.ponto_descida_lat, r.ponto_descida_lng, r.lugares,
            r.passageiro_lat, r.passageiro_lng, r.passageiro_localizacao_em,
            u.id AS passageiro_id, u.nome AS passageiro_nome, u.telefone AS passageiro_telefone,
            pp.nome AS ponto_partida_nome, pp.lat AS ponto_partida_lat, pp.lng AS ponto_partida_lng
     FROM reservas r
     JOIN utilizadores u ON u.id = r.passageiro_id
     JOIN pontos_partida pp ON pp.id = r.ponto_partida_id
     WHERE r.veiculo_id = ? AND r.estado IN ('pendente', 'confirmado', 'a_bordo')
     ORDER BY r.criado_em ASC"
);
$stmt->execute([$veiculoId]);
$passageiros = $stmt->fetchAll();

// Posição a mostrar no mapa: a localização GPS ao vivo do passageiro quando
// disponível (secção 5.3), com fallback para o ponto de partida enquanto o
// telemóvel do passageiro ainda não enviou a primeira posição.
foreach ($passageiros as &$p) {
    $p['mapa_lat'] = $p['passageiro_lat'] ?? $p['ponto_partida_lat'];
    $p['mapa_lng'] = $p['passageiro_lng'] ?? $p['ponto_partida_lng'];
}
unset($p);

// Distância até ao ponto de partida do passageiro (secção 9.4 — para o condutor ir buscá-lo).
$temPosicaoVeiculo = $veiculo['lat'] !== null && $veiculo['lng'] !== null;
foreach ($passageiros as &$p) {
    $p['distancia_m'] = $temPosicaoVeiculo
        ? round(kg_distancia_metros((float) $veiculo['lat'], (float) $veiculo['lng'], (float) $p['ponto_partida_lat'], (float) $p['ponto_partida_lng']))
        : null;
}
unset($p);

if ($temPosicaoVeiculo) {
    usort($passageiros, fn($a, $b) => ($a['distancia_m'] ?? PHP_INT_MAX) <=> ($b['distancia_m'] ?? PHP_INT_MAX));
}

echo json_encode(['passageiros' => $passageiros]);
