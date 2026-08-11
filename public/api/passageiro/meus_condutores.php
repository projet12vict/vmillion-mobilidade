<?php
/**
 * V-MILLION — API: condutores com quem o passageiro já viajou (para selecionar
 * o alvo de uma reclamação, secção 10 — evita reclamações sobre condutores
 * com quem nunca houve contacto).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$passageiro = kg_exigir_utilizador('passageiro');

$pdo = kg_db();
$stmt = $pdo->prepare(
    "SELECT DISTINCT u.id, u.nome
     FROM reservas r
     JOIN veiculos v ON v.id = r.veiculo_id
     JOIN utilizadores u ON u.id = v.condutor_id
     WHERE r.passageiro_id = ? AND r.estado IN ('confirmado', 'a_bordo', 'concluido')
     ORDER BY u.nome"
);
$stmt->execute([$passageiro['id']]);

echo json_encode(['condutores' => $stmt->fetchAll()]);
