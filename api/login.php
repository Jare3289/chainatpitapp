<?php
// api/login.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();

cnp_verify_origin();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username'], $data['password'], $data['role'])) {
    cnp_json_error(400, 'ข้อมูลไม่ครบถ้วน');
}

$username = trim((string)$data['username']);
$password = (string)$data['password'];
$role     = (string)$data['role'];
$remember = !empty($data['remember']);

$allowedRoles = ['admin', 'teacher', 'student'];
if (!in_array($role, $allowedRoles, true) || $username === '' || $password === '') {
    cnp_json_error(400, 'ข้อมูลไม่ถูกต้อง');
}

// Rate limit: 5 attempts / 15 min per (IP + username)
$ip      = cnp_client_ip();
$rateKey = 'login:' . $ip . ':' . $username;
if (!cnp_rate_limit_check($rateKey, 5, 900)) {
    cnp_json_error(429, 'พยายามเข้าระบบบ่อยเกินไป กรุณารอ 15 นาทีแล้วลองใหม่');
}

try {
    $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name
                           FROM users u
                           JOIN roles r ON u.role_id = r.id
                           WHERE u.username = ? AND r.name = ?
                           LIMIT 1");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $valid = false;
    if ($user) {
        $stored   = (string)$user['password'];
        $info     = password_get_info($stored);
        $isHashed = !empty($info['algo']);

        if ($isHashed) {
            $valid = password_verify($password, $stored);
            if ($valid && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$newHash, $user['id']]);
            }
        } else {
            // Legacy plaintext: timing-safe compare then upgrade to hash
            if (hash_equals($stored, $password)) {
                $valid = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$newHash, $user['id']]);
            }
        }
    }

    if (!$valid) {
        cnp_rate_limit_record($rateKey, 900);
        // small delay to slow down brute force
        usleep(random_int(100000, 300000));
        cnp_json_error(401, 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้องสำหรับบทบาทที่เลือก');
    }

    // success — clear failed attempts and prevent fixation
    cnp_rate_limit_clear($rateKey);
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role_name'];
    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'role'     => $user['role_name'],
    ];
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // fresh per login
    $_SESSION['remember']   = $remember;

    // Persist login: set both PHPSESSID cookie + cnp_remember marker.
    // The marker is read by config.php BEFORE session_start() to make subsequent
    // session_start() calls Set-Cookie with the long lifetime as well — otherwise
    // PHP would override our 30-day cookie with a browser-session one.
    if ($remember) {
        $cp = session_get_cookie_params();
        $longExpire = time() + 30 * 24 * 3600;
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        );

        setcookie(session_name(), session_id(), [
            'expires'  => $longExpire,
            'path'     => '/',
            'domain'   => $cp['domain'],
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('cnp_remember', '1', [
            'expires'  => $longExpire,
            'path'     => '/',
            'domain'   => $cp['domain'],
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Issue persistent auth token (90 days) — works even if PHPSESSID is purged
        try { cnp_auth_token_issue($pdo, (int)$user['id']); } catch (Exception $e) { error_log('[login] token issue: '.$e->getMessage()); }
    } else {
        // ผู้ใช้เลิกติ๊ก remember — ลบ marker ถ้าเคยมี
        if (!empty($_COOKIE['cnp_remember'])) {
            setcookie('cnp_remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    $redirectMap = [
        'admin'   => 'views/admin_dashboard.html',
        'teacher' => 'views/teacher_dashboard.html',
        'student' => 'views/student_dashboard.html',
    ];
    $redirect = $redirectMap[$user['role_name']] ?? 'views/index.html';

    echo json_encode([
        'success'    => true,
        'redirect'   => $redirect,
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (PDOException $e) {
    error_log('[login] DB error: ' . $e->getMessage());
    cnp_json_error(500, 'ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง');
}
