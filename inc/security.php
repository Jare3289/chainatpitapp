<?php
/**
 * inc/security.php
 * Helpers ที่ทุก endpoint เรียกใช้ได้: CSRF, rate limit, origin check, auth check
 * โหลดหลังจาก require config.php + session_start()
 */

if (!function_exists('cnp_json_error')) {
    function cnp_json_error(int $status, string $msg): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ─────────────────── Auth ─────────────────── */

function cnp_require_auth(?array $allowed_roles = null): array {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        cnp_json_error(401, 'Unauthorized');
    }
    if ($allowed_roles && !in_array($_SESSION['role'], $allowed_roles, true)) {
        cnp_json_error(403, 'Forbidden');
    }
    cnp_slide_session();
    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => (string)($_SESSION['username'] ?? ''),
        'role'     => (string)$_SESSION['role'],
    ];
}

/**
 * Sliding session for "remember me" — refreshes cookie expiry on each authenticated
 * request so the user stays logged in for 30 days from last activity.
 *
 * Throttle: only re-set cookie at most once per hour to avoid Set-Cookie spam.
 */
function cnp_slide_session(): void {
    if (empty($_SESSION['remember'])) return;

    $lastSlide = $_SESSION['_last_slide'] ?? 0;
    if (time() - $lastSlide < 3600) return; // throttle to 1/hour

    $_SESSION['_last_slide'] = time();
    $cp     = session_get_cookie_params();
    $expire = time() + 30 * 24 * 3600;
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    // Slide both PHPSESSID and the cnp_remember marker
    // Use explicit '/' so the cookie covers all paths regardless of server config
    setcookie(session_name(), session_id(), [
        'expires'  => $expire,
        'path'     => '/',
        'domain'   => $cp['domain'],
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    setcookie('cnp_remember', '1', [
        'expires'  => $expire,
        'path'     => '/',
        'domain'   => $cp['domain'],
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ─────────────────── CSRF ─────────────────── */

function cnp_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function cnp_csrf_verify(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if ($sent === '' || $stored === '' || !hash_equals($stored, $sent)) {
        cnp_json_error(419, 'CSRF token invalid or missing');
    }
}

/* ─────────────────── Origin / Referer check ─────────────────── */

function cnp_verify_origin(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return;

    // Strip port from HTTP_HOST if present (e.g. localhost:8000 -> localhost)
    $host = preg_replace('/:\d+$/', '', $host);

    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($source === '') return; // some clients (mobile apps) won't send these

    $parsed = parse_url($source);
    $sourceHost = strtolower($parsed['host'] ?? '');

    if ($sourceHost !== '' && $sourceHost !== $host) {
        cnp_json_error(403, 'Cross-origin request rejected');
    }
}

/* ─────────────────── Rate limiting (login) ─────────────────── */
/*
 * Simple file-based rate limiter — fine for low/medium traffic
 * For high traffic move to Redis/Memcache later.
 */

function cnp_rate_limit_check(string $key, int $maxAttempts = 5, int $windowSec = 900): bool {
    $dir = sys_get_temp_dir() . '/cnp_ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . hash('sha256', $key) . '.json';

    $now = time();
    $attempts = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $attempts = $decoded;
        }
    }
    // drop stale
    $attempts = array_filter($attempts, fn($t) => ($now - (int)$t) < $windowSec);
    return count($attempts) < $maxAttempts;
}

function cnp_rate_limit_record(string $key, int $windowSec = 900): void {
    $dir = sys_get_temp_dir() . '/cnp_ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . hash('sha256', $key) . '.json';

    $now = time();
    $attempts = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $attempts = $decoded;
        }
    }
    $attempts = array_filter($attempts, fn($t) => ($now - (int)$t) < $windowSec);
    $attempts[] = $now;
    @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
}

function cnp_rate_limit_clear(string $key): void {
    $file = sys_get_temp_dir() . '/cnp_ratelimit/' . hash('sha256', $key) . '.json';
    if (is_file($file)) @unlink($file);
}

function cnp_client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/* ─────────────────── Persistent auth token (cnp_auth cookie) ─────────────────── */
/*
 * Two-part token: "selector.validator"
 * - selector is the public lookup key (stored plain)
 * - validator is the secret (only its sha256 is stored)
 *
 * Cookie 'cnp_auth' = "{selector}.{validator}"
 * Valid for 90 days. Auto-rotates on use to limit replay window.
 */

function cnp_auth_token_issue(PDO $pdo, int $userId, int $days = 90): void {
    $selector  = bin2hex(random_bytes(8));    // 16 hex chars
    $validator = bin2hex(random_bytes(32));   // 64 hex chars
    $hash      = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', time() + $days * 86400);
    $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $pdo->prepare("
        INSERT INTO auth_tokens (selector, token_hash, user_id, expires_at, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$selector, $hash, $userId, $expiresAt, $ua]);

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    setcookie('cnp_auth', $selector . '.' . $validator, [
        'expires'  => time() + $days * 86400,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Check the cnp_auth cookie. If valid, populate $_SESSION and rotate token.
 * Returns user_id on success, null on failure.
 * Call this BEFORE checking $_SESSION['user_id'] in endpoints that allow auto-relogin.
 */
function cnp_auth_token_check(PDO $pdo): ?int {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id']; // already logged in
    $cookie = $_COOKIE['cnp_auth'] ?? '';
    if (!preg_match('/^([a-f0-9]+)\.([a-f0-9]+)$/', $cookie, $m)) return null;

    [$_, $selector, $validator] = $m;

    $stmt = $pdo->prepare("
        SELECT id, user_id, token_hash, expires_at
        FROM auth_tokens
        WHERE selector = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $hash = hash('sha256', $validator);
    if (!hash_equals($row['token_hash'], $hash)) {
        // Suspicious — possible token theft. Revoke this token.
        $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
        return null;
    }

    // Valid — populate session
    $userStmt = $pdo->prepare("
        SELECT u.id, u.username, r.name AS role
        FROM users u JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? LIMIT 1
    ");
    $userStmt->execute([$row['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
        return null;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['user']     = ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']];
    $_SESSION['remember'] = true;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Rotate: issue new token first, then revoke old one
    // (ถ้า issue ล้มเหลว token เดิมยังใช้งานได้ — ไม่สูญเสีย session)
    cnp_auth_token_issue($pdo, (int)$user['id']);
    $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);

    // Refresh cnp_remember marker so config.php keeps setting 30-day PHPSESSID lifetime
    // (หากคุกกี้นี้หมดอายุก่อน cnp_auth ระบบจะ auto-login ได้แต่ session อาจหมดเร็ว)
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    setcookie('cnp_remember', '1', [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Refresh the PHPSESSID cookie too so it doesn't expire before the next slide
    setcookie(session_name(), session_id(), [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return (int)$user['id'];
}

function cnp_auth_token_revoke_all(PDO $pdo, int $userId): void {
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
    setcookie('cnp_auth', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
