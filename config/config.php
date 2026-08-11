<?php
/**
 * V-MILLION — Configuração global.
 * Carregar sempre no arranque de qualquer script (bootstrap).
 */

declare(strict_types=1);

// ------------------------------------------------------------------
// Ambiente
// ------------------------------------------------------------------
define('KG_ENV', getenv('KG_ENV') ?: 'local'); // 'local' | 'production'
define('KG_IS_PRODUCTION', KG_ENV === 'production');

// ------------------------------------------------------------------
// Base de dados
// ------------------------------------------------------------------
// Por omissão aponta para a instância MySQL do XAMPP usada no piloto
// (porta 3307) e para a base de dados migrada 'kabugo_v2'. Ajustável por
// variáveis de ambiente sem alterar código — ver README, secção "Migração".
define('KG_DB_HOST', getenv('KG_DB_HOST') ?: '127.0.0.1');
define('KG_DB_PORT', getenv('KG_DB_PORT') ?: '3307');
define('KG_DB_NAME', getenv('KG_DB_NAME') ?: 'kabugo_v2');
define('KG_DB_USER', getenv('KG_DB_USER') ?: 'root');
define('KG_DB_PASS', getenv('KG_DB_PASS') ?: '');
define('KG_DB_CHARSET', 'utf8mb4');

// ------------------------------------------------------------------
// Limites de Cabo Verde (validação de coordenadas)
// ------------------------------------------------------------------
define('KG_LAT_MIN', 14.7);
define('KG_LAT_MAX', 17.2);
define('KG_LNG_MIN', -25.4);
define('KG_LNG_MAX', -22.7);

// ------------------------------------------------------------------
// Validação
// ------------------------------------------------------------------
define('KG_REGEX_TELEFONE', '/^\+238[ ]?[0-9]{7}$/');
define('KG_REGEX_NIF', '/^[0-9]{9}$/');
define('KG_SENHA_MIN_LEN', 8);

// ------------------------------------------------------------------
// Rate limiting de login
// ------------------------------------------------------------------
define('KG_LOGIN_MAX_TENTATIVAS', 5);
define('KG_LOGIN_BLOQUEIO_MINUTOS', 15);

// ------------------------------------------------------------------
// Veículos
// ------------------------------------------------------------------
define('KG_LUGARES_TOTAL', 14);

// ------------------------------------------------------------------
// Motor de rotas (OSRM) e cache de rotas (Redis)
// ------------------------------------------------------------------
// Instância própria do OSRM (Cabo Verde), a correr no servidor — ver
// includes/pricing.php (preço/distância) e assets/js/kg-map.js (mapa), que
// caem para linha reta/Haversine se este serviço estiver indisponível.
define('KG_OSRM_URL', getenv('KG_OSRM_URL') ?: 'http://localhost:5001');
define('KG_OSRM_TIMEOUT_S', (float) (getenv('KG_OSRM_TIMEOUT_S') ?: 5));
// Instância pública do projeto OSRM, usada só como último recurso quando a
// instância própria (KG_OSRM_URL) falha ou está lenta — garante rota real
// (por estrada) em vez de linha reta mesmo com o serviço local em baixo.
// Sem SLA/rate limit garantido: nunca é a via principal, só a rede de
// segurança. Chamada sempre do lado do servidor (includes/pricing.php),
// nunca do browser — por isso não entra na CSP (connect-src).
define('KG_OSRM_PUBLIC_URL', getenv('KG_OSRM_PUBLIC_URL') ?: 'https://router.project-osrm.org');

define('KG_REDIS_HOST', getenv('KG_REDIS_HOST') ?: '127.0.0.1');
define('KG_REDIS_PORT', (int) (getenv('KG_REDIS_PORT') ?: 6379));
define('KG_REDIS_ROTA_TTL_S', 86400); // 24 horas

// ------------------------------------------------------------------
// Erros / logging
// ------------------------------------------------------------------
if (KG_IS_PRODUCTION) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');

date_default_timezone_set('Atlantic/Cape_Verde');
