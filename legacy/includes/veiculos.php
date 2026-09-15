<?php
/**
 * V-MILLION — Helpers de veículos e layout de assentos (secção 7.1).
 * Fila 0 (junto ao condutor): 2 bancos (assentos 1-2).
 * Filas 1 a 4: 3 bancos por fila (assentos 3-14). Total: 14 lugares.
 */

declare(strict_types=1);

/**
 * @return array<int, array{numero:int, fila:int, coluna:int}>
 */
function kg_layout_assentos(): array
{
    $layout = [];
    $numero = 1;

    // Fila 0: 2 lugares (junto ao condutor)
    for ($coluna = 1; $coluna <= 2; $coluna++) {
        $layout[] = ['numero' => $numero++, 'fila' => 0, 'coluna' => $coluna];
    }

    // Filas 1 a 4: 3 lugares cada
    for ($fila = 1; $fila <= 4; $fila++) {
        for ($coluna = 1; $coluna <= 3; $coluna++) {
            $layout[] = ['numero' => $numero++, 'fila' => $fila, 'coluna' => $coluna];
        }
    }

    return $layout; // 14 lugares
}

function kg_criar_assentos_veiculo(PDO $pdo, int $veiculoId): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO assentos_veiculo (veiculo_id, numero, fila, coluna, ocupado) VALUES (?, ?, ?, ?, 0)"
    );
    foreach (kg_layout_assentos() as $lugar) {
        $stmt->execute([$veiculoId, $lugar['numero'], $lugar['fila'], $lugar['coluna']]);
    }
}

function kg_normalizar_matricula(string $matricula): string
{
    return strtoupper(trim($matricula));
}

/**
 * Gate de aprovação + pagamento (secção 11.6): um condutor só vê o mapa e
 * os passageiros se, tendo pelo menos um veículo aprovado, também tiver uma
 * taxa de operação aprovada e ainda válida. Sem veículo aprovado ainda
 * (candidatura em curso), o mapa fica visível — é preciso para registar o
 * primeiro veículo. Partilhado entre a página do condutor e as APIs que
 * mostram procura urbana (api/condutor/passageiros_urbanos.php).
 */
function kg_condutor_pode_ver_mapa(PDO $pdo, int $condutorId): bool
{
    $stmtVeiculo = $pdo->prepare("SELECT 1 FROM veiculos WHERE condutor_id = ? AND aprovado = 1 LIMIT 1");
    $stmtVeiculo->execute([$condutorId]);
    if (!$stmtVeiculo->fetchColumn()) {
        return true;
    }

    $stmtPagamento = $pdo->prepare(
        "SELECT 1 FROM pagamentos_condutores WHERE condutor_id = ? AND status = 'aprovado' AND data_validade >= NOW() LIMIT 1"
    );
    $stmtPagamento->execute([$condutorId]);
    return (bool) $stmtPagamento->fetchColumn();
}
