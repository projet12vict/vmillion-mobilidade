<?php
/**
 * V-MILLION — Bootstrap comum a todas as entradas HTTP (páginas e endpoints API).
 * Aplica headers de segurança, inicia sessão reforçada e carrega config + DB.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/geo_validation.php';

kg_send_security_headers();
kg_session_start();
