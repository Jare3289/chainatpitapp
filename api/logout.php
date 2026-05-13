<?php
// api/logout.php
header('Content-Type: application/json');
require_once '../config.php';
session_start();

// Revoke persistent auth tokens for this user (so PWA reopen won't auto-login)
require_once '../inc/security.php';
if (!empty($_SESSION['user_id'])) {
    try { cnp_auth_token_revoke_all($pdo, (int)$_SESSION['user_id']); }
    catch (Exception $e) { error_log('[logout] token revoke: '.$e->getMessage()); }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
    // ลบ remember-me marker ด้วย
    setcookie('cnp_remember', '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

session_destroy();
echo json_encode(['success' => true]);
