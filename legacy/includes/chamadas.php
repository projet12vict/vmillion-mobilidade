<?php
/**
 * V-MILLION — Chamada simulada: quem pode chamar quem.
 * Mesma regra de relação já usada em api/comunicacao/enviar.php (secção 6):
 * só entre condutor e passageiro com reserva ativa no mesmo veículo — nunca
 * entre dois utilizadores sem nenhuma viagem em comum.
 */

declare(strict_types=1);

function kg_pode_chamar(PDO $pdo, array $remetente, int $destinatarioId): bool
{
    if ($remetente['id'] === $destinatarioId) {
        return false;
    }

    if ($remetente['tipo'] === 'condutor') {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM reservas r
             JOIN veiculos v ON v.id = r.veiculo_id
             WHERE v.condutor_id = ? AND r.passageiro_id = ? AND r.estado IN ('pendente', 'confirmado', 'a_bordo')
             LIMIT 1"
        );
        $stmt->execute([$remetente['id'], $destinatarioId]);
        return (bool) $stmt->fetchColumn();
    }

    if ($remetente['tipo'] === 'passageiro') {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM reservas r
             JOIN veiculos v ON v.id = r.veiculo_id
             WHERE r.passageiro_id = ? AND v.condutor_id = ? AND r.estado IN ('pendente', 'confirmado', 'a_bordo')
             LIMIT 1"
        );
        $stmt->execute([$remetente['id'], $destinatarioId]);
        return (bool) $stmt->fetchColumn();
    }

    return false;
}

// Chamadas "iniciada" a que ninguém respondeu ficam para sempre a bloquear
// novas chamadas para o mesmo destinatário — isto fecha-as como perdidas ao
// fim de 1 minuto, sempre que a tabela é consultada (sem precisar de cron).
function kg_expirar_chamadas_paradas(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE chamadas SET estado = 'terminada', terminada_em = NOW()
         WHERE estado = 'iniciada' AND iniciada_em < (NOW() - INTERVAL 60 SECOND)"
    );
}

// Devolve a chamada só se $utilizadorId for um dos dois participantes —
// usado pela sinalização WebRTC (sinalizar.php/sinais.php) para garantir
// que ninguém lê/escreve ofertas, respostas ou ICE candidates de uma
// chamada alheia.
function kg_chamada_participante(PDO $pdo, int $chamadaId, int $utilizadorId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM chamadas WHERE id = ? AND (remetente_id = ? OR destinatario_id = ?) LIMIT 1");
    $stmt->execute([$chamadaId, $utilizadorId, $utilizadorId]);
    return $stmt->fetch() ?: null;
}
