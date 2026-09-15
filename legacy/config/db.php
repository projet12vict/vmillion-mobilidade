<?php
/**
 * V-MILLION — Ligação PDO à base de dados.
 * Prepared statements obrigatórios em todo o sistema (ver regra de segurança 14).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function kg_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        KG_DB_HOST,
        KG_DB_PORT,
        KG_DB_NAME,
        KG_DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, KG_DB_USER, KG_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[V-MILLION] Falha de ligação à base de dados: ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Serviço indisponível. Tente novamente mais tarde.']);
        exit;
    }

    return $pdo;
}
