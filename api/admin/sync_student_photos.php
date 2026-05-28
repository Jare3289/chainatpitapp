<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

$role   = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$importDir = realpath(__DIR__ . '/../../public/uploads/import') . DIRECTORY_SEPARATOR;
$uploadDir = realpath(__DIR__ . '/../../public/uploads/students');

// Ensure upload dir exists
if (!$uploadDir) {
    $path = __DIR__ . '/../../public/uploads/students';
    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        echo json_encode(['error' => 'ไม่สามารถสร้างโฟลเดอร์รูปภาพได้: ' . $path], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $uploadDir = realpath($path);
}
$uploadDir .= DIRECTORY_SEPARATOR;

// Ensure import dir exists
if (!$importDir) {
    $path = __DIR__ . '/../../public/uploads/import';
    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        echo json_encode(['error' => 'ไม่สามารถสร้างโฟลเดอร์ import ได้'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $importDir = realpath($path) . DIRECTORY_SEPARATOR;
}

// Scan import directory for image files
$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
$imageFiles  = [];

foreach (scandir($importDir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) continue;
    if (!is_file($importDir . $file)) continue;
    $imageFiles[] = [
        'filename' => $file,
        'basename' => pathinfo($file, PATHINFO_FILENAME),
        'ext'      => $ext,
        'path'     => $importDir . $file,
    ];
}

if (empty($imageFiles)) {
    echo json_encode([
        'success' => true,
        'empty'   => true,
        'message' => 'โฟลเดอร์นำเข้าว่างเปล่า กรุณานำรูปภาพที่ตั้งชื่อเป็นรหัสนักเรียนไปวางใน public/uploads/import/',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Pre-fetch all students indexed by student_id (1 query)
$studentMap = [];
foreach ($pdo->query("SELECT id, student_id, first_name_th, last_name_th FROM students")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $studentMap[trim($row['student_id'])] = $row;
}

$processed = 0;
$updated   = 0;
$notFound  = 0;
$details   = [];

foreach ($imageFiles as $img) {
    $studentId = trim($img['basename']);
    $student   = $studentMap[$studentId] ?? null;
    $processed++;

    if (!$student) {
        $notFound++;
        $details[] = [
            'filename'   => $img['filename'],
            'student_id' => $studentId,
            'status'     => 'not_found',
            'name'       => '-',
            'message'    => 'ไม่พบรหัสนักเรียนในฐานข้อมูล',
        ];
        continue;
    }

    $newFilename = 'student_' . $studentId . '_' . time() . '.' . $img['ext'];
    $destPath    = $uploadDir . $newFilename;
    $dbPath      = 'public/uploads/students/' . $newFilename;

    // Try copy first; fall back to rename if copy fails (same filesystem)
    $moved = @copy($img['path'], $destPath);
    if (!$moved) {
        $moved = @rename($img['path'], $destPath);
        if (!$moved) {
            $details[] = [
                'filename'   => $img['filename'],
                'student_id' => $studentId,
                'status'     => 'error',
                'name'       => $student['first_name_th'] . ' ' . $student['last_name_th'],
                'message'    => 'คัดลอกไฟล์ไม่ได้ (ตรวจสิทธิ์โฟลเดอร์ public/uploads/)',
            ];
            continue;
        }
    }

    // Update DB
    $pdo->prepare("UPDATE students SET photo = ? WHERE id = ?")
        ->execute([$dbPath, $student['id']]);

    // Remove source (if copy succeeded rename already moved it)
    if (file_exists($img['path'])) @unlink($img['path']);

    $updated++;
    $details[] = [
        'filename'   => $img['filename'],
        'student_id' => $studentId,
        'status'     => 'success',
        'name'       => $student['first_name_th'] . ' ' . $student['last_name_th'],
        'message'    => 'ซิงค์สำเร็จ',
    ];
}

echo json_encode([
    'success'   => true,
    'empty'     => false,
    'processed' => $processed,
    'updated'   => $updated,
    'not_found' => $notFound,
    'details'   => $details,
], JSON_UNESCAPED_UNICODE);
