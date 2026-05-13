<?php
/**
 * config.php
 * - โหลดค่าจาก .env (ไม่ commit ขึ้น repo)
 * - ตั้งค่า session cookie ให้ปลอดภัยก่อน session_start() ที่อาจถูกเรียกในไฟล์อื่น
 * - ซ่อน error message ของ DB ในโหมด production
 *
 * .env ที่ต้องมี (ดู .env.example):
 *   APP_ENV=production|development
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS
 *   SECRET_KEY
 */

// ---------- 1) โหลด .env ----------
(static function (): void {
    $path = __DIR__ . '/.env';
    if (!is_file($path)) return;

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;

        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));

        // strip surrounding quotes
        $len = strlen($val);
        if ($len >= 2) {
            $first = $val[0]; $last = $val[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
    }
})();

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

// ---------- 2) Environment / Error reporting ----------
$APP_ENV = env('APP_ENV', 'production');
$IS_PROD = ($APP_ENV !== 'development');

if ($IS_PROD) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ---------- 3) Session security ----------
// ตั้งค่าก่อน session_start() ที่ endpoint แต่ละไฟล์เรียก
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    // GC สูงสุด 60 วัน (รองรับ remember-me 30 วัน + buffer)
    ini_set('session.gc_maxlifetime', '5184000');

    // อ่านสัญญาณ "remember me" จาก cookie ตัวรอง (ไม่ใช่ session cookie เอง)
    // ก่อน session_start() เพื่อกำหนด cookie_lifetime ให้ session_start() Set-Cookie ถูกต้อง
    if (!empty($_COOKIE['cnp_remember']) && $_COOKIE['cnp_remember'] === '1') {
        ini_set('session.cookie_lifetime', '2592000');  // 30 days
    } else {
        ini_set('session.cookie_lifetime', '0');         // browser-session
    }
}

// ---------- 4) Database ----------
$host    = env('DB_HOST', '127.0.0.1');
$db      = env('DB_NAME', 'cnpapp_system');
$user    = env('DB_USER', 'root');
$pass    = env('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('[config] DB connect failed: ' . $e->getMessage());
    http_response_code(503);
    if ($IS_PROD) {
        die('ระบบขัดข้องชั่วคราว: ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    } else {
        die('Database Error (Local): ' . $e->getMessage());
    }
}
