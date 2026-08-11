<?php
/**
 * V-MILLION — API: polling (3-5s) da chamada mais recente em que este
 * utilizador participa. Camada de confiança independente do socket.io — ver
 * database/migration_20260810_chamadas.sql. O cliente (kg-chamada.js)
 * compara (id, estado) com o que já viu para decidir se há uma transição
 * nova a mostrar; não há "marcar como visto" no servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/chamadas.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = kg_exigir_utilizador();

$pdo = kg_db();
kg_expirar_chamadas_paradas($pdo);

$stmt = $pdo->prepare(
    "SELECT c.id, c.remetente_id, c.destinatario_id, c.estado,
            ur.nome AS remetente_nome, ud.nome AS destinatario_nome
     FROM chamadas c
     JOIN utilizadores ur ON ur.id = c.remetente_id
     JOIN utilizadores ud ON ud.id = c.destinatario_id
     WHERE c.remetente_id = ? OR c.destinatario_id = ?
     ORDER BY c.atualizada_em DESC
     LIMIT 1"
);
$stmt->execute([$utilizador['id'], $utilizador['id']]);
$chamada = $stmt->fetch();

echo json_encode(['chamada' => $chamada ?: null]);
