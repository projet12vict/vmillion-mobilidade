<?php
/**
 * V-MILLION — API: limites de cidade (centro + raio), usados para segmentar
 * troços urbanos/intermunicipais no motor de preços (secção 12.1).
 * Apenas Super Admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$admin = kg_exigir_admin('super');
$pdo = kg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limites = $pdo->query("SELECT id, cidade, lat, lng, raio_km FROM limites_cidades ORDER BY cidade")->fetchAll();
    echo json_encode(['limites' => $limites]);
    exit;
}

kg_csrf_require();
$acao = (string) ($_POST['acao'] ?? '');

if (in_array($acao, ['criar', 'editar'], true)) {
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
    $raioKm = filter_input(INPUT_POST, 'raio_km', FILTER_VALIDATE_FLOAT);

    if ($cidade === '' || $lat === null || $lat === false || $lng === null || $lng === false || $raioKm === null || $raioKm === false || $raioKm <= 0) {
        http_response_code(422);
        echo json_encode(['erro' => 'Dados de limite de cidade inválidos.']);
        exit;
    }
    kg_exigir_coordenadas_validas($lat, $lng);

    if ($acao === 'criar') {
        $pdo->prepare(
            "INSERT INTO limites_cidades (cidade, lat, lng, raio_km, atualizado_por) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), raio_km = VALUES(raio_km), atualizado_por = VALUES(atualizado_por)"
        )->execute([$cidade, $lat, $lng, $raioKm, $admin['id']]);
        kg_log_auditoria($pdo, $admin['id'], 'criou_limite_cidade', 'limites_cidades', null, $cidade);
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
        $pdo->prepare(
            "UPDATE limites_cidades SET cidade = ?, lat = ?, lng = ?, raio_km = ?, atualizado_por = ? WHERE id = ?"
        )->execute([$cidade, $lat, $lng, $raioKm, $admin['id'], $id]);
        kg_log_auditoria($pdo, $admin['id'], 'editou_limite_cidade', 'limites_cidades', $id, $cidade);
    }

    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'eliminar') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(422); echo json_encode(['erro' => 'ID inválido.']); exit; }
    $pdo->prepare("DELETE FROM limites_cidades WHERE id = ?")->execute([$id]);
    kg_log_auditoria($pdo, $admin['id'], 'eliminou_limite_cidade', 'limites_cidades', $id);
    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['erro' => 'Ação inválida.']);
