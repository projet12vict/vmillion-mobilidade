<?php
/**
 * V-MILLION — Segurança: sessão, CSRF, rate limiting, headers, auditoria.
 * Ver secção 14 da especificação (nível governamental).
 */

declare(strict_types=1);

// ------------------------------------------------------------------
// Headers de segurança
// ------------------------------------------------------------------
function kg_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function kg_send_security_headers(): void
{
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // microphone=(self): a chamada com voz (WebRTC, ver kg-chamada.js) usa
    // getUserMedia para o áudio — bloqueado por completo (mesmo na própria
    // origem) enquanto isto era microphone=(), o pedido do microfone falhava
    // sempre e a chamada ficava muda sem erro nenhum visível.
    header("Permissions-Policy: geolocation=(self), camera=(), microphone=(self)");
    $nonce = kg_csp_nonce();
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://unpkg.com; " .
        "script-src 'self' 'nonce-{$nonce}' https://unpkg.com https://cdn.socket.io; " .
        "worker-src 'self' blob:; " .
        // O motor de rotas (OSRM) passou a ser chamado só do lado do servidor
        // (public/api/rota.php -> KG_OSRM_URL, tipicamente localhost) — o
        // browser só fala com 'self', por isso router.project-osrm.org saiu
        // daqui; nominatim mantém-se (geocodificação de destino no mapa).
        "connect-src 'self' https://nominatim.openstreetmap.org https://unpkg.com https://cdn.socket.io ws: wss:; " .
        "font-src 'self' https://unpkg.com; " .
        "object-src 'none'; base-uri 'self'; frame-ancestors 'none';"
    );
    if (KG_IS_PRODUCTION) {
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
    }
}

// ------------------------------------------------------------------
// Sessão reforçada
// ------------------------------------------------------------------
function kg_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => KG_IS_PRODUCTION,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    // Timeout de inatividade: 2 horas
    $inactividadeMax = 7200;
    if (isset($_SESSION['kg_ultimo_acesso']) && (time() - $_SESSION['kg_ultimo_acesso']) > $inactividadeMax) {
        kg_session_destroy();
        session_start();
    }
    $_SESSION['kg_ultimo_acesso'] = time();
}

function kg_session_regenerate(): void
{
    session_regenerate_id(true);
}

function kg_session_destroy(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ------------------------------------------------------------------
// CSRF
// ------------------------------------------------------------------
function kg_csrf_token(): string
{
    if (empty($_SESSION['kg_csrf'])) {
        $_SESSION['kg_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['kg_csrf'];
}

function kg_csrf_field(): string
{
    $token = htmlspecialchars(kg_csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

function kg_csrf_verify(?string $token): bool
{
    if (empty($_SESSION['kg_csrf']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['kg_csrf'], $token);
}

function kg_csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!kg_csrf_verify($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Token de segurança inválido. Recarregue a página.']);
        exit;
    }
}

// ------------------------------------------------------------------
// Senhas
// ------------------------------------------------------------------
function kg_password_hash(string $senha): string
{
    return password_hash($senha, PASSWORD_BCRYPT);
}

function kg_password_verify(string $senha, string $hash): bool
{
    return password_verify($senha, $hash);
}

// ------------------------------------------------------------------
// Rate limiting de login (5 tentativas -> bloqueio 15 min; Super Admin nunca bloqueado)
// ------------------------------------------------------------------
function kg_rate_limit_check(PDO $pdo, string $tabela, int $id): void
{
    $stmt = $pdo->prepare("SELECT tentativas_login, bloqueado_ate FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row && $row['bloqueado_ate'] !== null && strtotime((string) $row['bloqueado_ate']) > time()) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        $minutos = (int) ceil((strtotime((string) $row['bloqueado_ate']) - time()) / 60);
        echo json_encode(['erro' => "Demasiadas tentativas. Tente novamente em {$minutos} min."]);
        exit;
    }
}

function kg_rate_limit_registar_falha(PDO $pdo, string $tabela, int $id, bool $isSuperAdmin = false): void
{
    if ($isSuperAdmin) {
        return; // Super Admin nunca bloqueado
    }

    $stmt = $pdo->prepare("SELECT tentativas_login FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $tentativas = (int) ($row['tentativas_login'] ?? 0) + 1;

    if ($tentativas >= KG_LOGIN_MAX_TENTATIVAS) {
        $bloqueadoAte = date('Y-m-d H:i:s', time() + KG_LOGIN_BLOQUEIO_MINUTOS * 60);
        $upd = $pdo->prepare("UPDATE {$tabela} SET tentativas_login = ?, bloqueado_ate = ? WHERE id = ?");
        $upd->execute([$tentativas, $bloqueadoAte, $id]);
    } else {
        $upd = $pdo->prepare("UPDATE {$tabela} SET tentativas_login = ? WHERE id = ?");
        $upd->execute([$tentativas, $id]);
    }
}

function kg_rate_limit_reset(PDO $pdo, string $tabela, int $id): void
{
    $upd = $pdo->prepare("UPDATE {$tabela} SET tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?");
    $upd->execute([$id]);
}

// ------------------------------------------------------------------
// Validação de dados
// ------------------------------------------------------------------
function kg_validar_telefone(string $telefone): bool
{
    return (bool) preg_match(KG_REGEX_TELEFONE, trim($telefone));
}

function kg_normalizar_telefone(string $telefone): string
{
    // Remove espaços internos, mantém formato +238XXXXXXX
    return '+238' . preg_replace('/\D/', '', substr(trim($telefone), 4));
}

function kg_validar_nif(string $nif): bool
{
    return (bool) preg_match(KG_REGEX_NIF, trim($nif));
}

function kg_validar_senha(string $senha): bool
{
    return mb_strlen($senha) >= KG_SENHA_MIN_LEN;
}

// ------------------------------------------------------------------
// Auditoria
// ------------------------------------------------------------------
function kg_log_auditoria(PDO $pdo, ?int $adminId, string $acao, ?string $entidade = null, ?int $entidadeId = null, ?string $detalhes = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO logs_auditoria (admin_id, acao, entidade, entidade_id, detalhes, ip) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $adminId,
        $acao,
        $entidade,
        $entidadeId,
        $detalhes,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

// ------------------------------------------------------------------
// Guarda de autenticação
// ------------------------------------------------------------------
function kg_utilizador_autenticado(): ?array
{
    return $_SESSION['kg_utilizador'] ?? null;
}

function kg_admin_autenticado(): ?array
{
    return $_SESSION['kg_admin'] ?? null;
}

function kg_exigir_utilizador(?string $tipo = null): array
{
    $user = kg_utilizador_autenticado();
    if (!$user || ($tipo !== null && $user['tipo'] !== $tipo)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não autenticado.']);
        exit;
    }
    kg_bloquear_se_suspenso($user['id']);
    // PHP mantém o ficheiro de sessão trancado (exclusivo) até ao fim do
    // pedido — cada painel dispara vários polls em paralelo (passageiros,
    // notificações, chat, chamada a cada 4s/1s, posição GPS, ...), todos com
    // a MESMA sessão, por isso ficavam todos à espera uns dos outros.
    // Nada depois deste ponto escreve em $_SESSION (login/logout, que
    // escrevem, não passam por aqui), por isso é seguro libertar já a
    // trancagem — foi isto que causava "Maximum execution time exceeded"
    // dentro do próprio session_start() sob várias janelas/abas ou vários
    // pedidos simultâneos da mesma sessão.
    session_write_close();
    return $user;
}

// Reconfirma o estado do utilizador a cada pedido — 'status' pode mudar fora
// da sessão (um admin suspende o condutor a meio de uma sessão já aberta) e
// sem isto a sessão antiga continuava a funcionar até expirar sozinha. Só
// bloqueia 'suspenso': 'pendente' continua a passar (um condutor por aprovar
// ainda precisa de aceder ao painel para registar o primeiro veículo — essa
// parte já é controlada à parte pelo gate de aprovação/pagamento).
function kg_bloquear_se_suspenso(int $utilizadorId): void
{
    $stmt = kg_db()->prepare("SELECT status FROM utilizadores WHERE id = ?");
    $stmt->execute([$utilizadorId]);
    if ($stmt->fetchColumn() === 'suspenso') {
        session_destroy();
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'A sua conta está suspensa. Contacte o administrador.']);
        exit;
    }
}

function kg_exigir_admin(?string $nivelMinimo = null): array
{
    $admin = kg_admin_autenticado();
    if (!$admin) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não autenticado.']);
        exit;
    }
    if ($nivelMinimo === 'super' && $admin['nivel'] !== 'super') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Apenas o Super Admin pode executar esta ação.']);
        exit;
    }
    // Mesmo problema de bloqueio de sessão que kg_exigir_utilizador() —
    // painel.php dispara vários pedidos API em paralelo com a mesma sessão de
    // admin, e sem libertar o lock aqui ficam à espera uns dos outros e o
    // write de fecho de sessão falha sob concorrência (session.save_path).
    session_write_close();
    return $admin;
}
