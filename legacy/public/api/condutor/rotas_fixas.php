<?php
/**
 * V-MILLION — API: lista de rotas fixas (para o condutor associar ao veículo,
 * secção 3). Apenas leitura — a definição de rotas é feita pelo Super Admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
kg_exigir_utilizador('condutor');

$pdo = kg_db();
$rotas = $pdo->query(
    "SELECT pr.id, pr.preco_fixo_cve, pr.distancia_km, po.nome AS origem_nome, pd.nome AS destino_nome
     FROM precos_rotas pr
     JOIN pontos_partida po ON po.id = pr.ponto_origem_id
     JOIN pontos_partida pd ON pd.id = pr.ponto_destino_id
     ORDER BY po.nome"
)->fetchAll();

echo json_encode(['rotas' => $rotas]);
