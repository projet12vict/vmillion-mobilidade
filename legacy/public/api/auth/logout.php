<?php
/**
 * V-MILLION — Logout: destrói a sessão (utilizador ou admin) e redireciona à landing page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

kg_session_destroy();
header('Location: /index.php');
exit;
