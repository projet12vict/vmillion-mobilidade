<?php
/**
 * V-MILLION — Guardas de acesso para páginas HTML (redirecionam em vez de
 * devolver JSON, ao contrário dos guardas de API em security.php).
 */

declare(strict_types=1);

function kg_pagina_exigir_utilizador(string $tipo): array
{
    $user = kg_utilizador_autenticado();
    if (!$user || $user['tipo'] !== $tipo) {
        header('Location: /login.php');
        exit;
    }
    $stmt = kg_db()->prepare("SELECT status FROM utilizadores WHERE id = ?");
    $stmt->execute([$user['id']]);
    if ($stmt->fetchColumn() === 'suspenso') {
        session_destroy();
        header('Location: /login.php?suspenso=1');
        exit;
    }
    return $user;
}

function kg_pagina_exigir_admin(?string $nivelMinimo = null): array
{
    $admin = kg_admin_autenticado();
    if (!$admin) {
        header('Location: /admin/login.php');
        exit;
    }
    if ($nivelMinimo === 'super' && $admin['nivel'] !== 'super') {
        header('Location: /admin/painel.php');
        exit;
    }
    return $admin;
}
