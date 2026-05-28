<?php
/**
 * api/admin/migrate_photos.php
 * คัดลอกรูปภาพจาก /cnpapp/public/uploads/ → /public/uploads/
 * รันครั้งเดียวหลัง deploy ใหม่ที่ root domain
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ── Path resolution ────────────────────────────────────────────────────────
// Script lives at: <root>/api/admin/migrate_photos.php
// Root is:         <root>/
$root     = rtrim(dirname(dirname(__DIR__)), '/\\');          // <root>
$newBase  = $root . '/public/uploads/';
$oldBase  = $root . '/cnpapp/public/uploads/';

$dirs = ['students', 'teachers'];
$copied = 0; $skipped = 0; $errors = [];

foreach ($dirs as $dir) {
    $src  = $oldBase . $dir . '/';
    $dest = $newBase . $dir . '/';

    if (!is_dir($src)) {
        $errors[] = "ไม่พบโฟลเดอร์ต้นทาง: $src";
        continue;
    }
    if (!is_dir($dest)) {
        if (!@mkdir($dest, 0755, true)) {
            $errors[] = "ไม่สามารถสร้างโฟลเดอร์: $dest";
            continue;
        }
    }

    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;
        $from = $src . $file;
        $to   = $dest . $file;
        if (!is_file($from)) continue;
        if (file_exists($to)) { $skipped++; continue; }   // ไม่ทับของเดิม
        if (@copy($from, $to)) {
            $copied++;
        } else {
            $errors[] = "copy ไม่ได้: $file";
        }
    }
}

echo json_encode([
    'success' => true,
    'copied'  => $copied,
    'skipped' => $skipped,
    'errors'  => $errors,
    'message' => "คัดลอกสำเร็จ $copied ไฟล์, ข้าม $skipped ไฟล์ (มีอยู่แล้ว)",
], JSON_UNESCAPED_UNICODE);
