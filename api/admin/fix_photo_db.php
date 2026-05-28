<?php
/**
 * api/admin/fix_photo_db.php
 * สแกนไฟล์รูปใน public/uploads/students/ แล้วอัปเดต field photo ใน DB
 * รันครั้งเดียวเพื่อ link ไฟล์ที่มีอยู่แล้วกับ student records
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

$dir = __DIR__ . '/../../public/uploads/students/';

if (!is_dir($dir)) {
    echo json_encode(['error' => 'ไม่พบโฟลเดอร์ public/uploads/students/']);
    exit;
}

// โหลด student_id ทั้งหมดจาก DB มาใส่ map ก่อน (เร็วกว่า query ทีละรอบ)
$studentMap = [];
$stmt = $pdo->query("SELECT id, student_id FROM students");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $studentMap[$row['student_id']] = $row['id'];
}

$updated  = 0;
$skipped  = 0;
$notFound = 0;
$files    = [];

foreach (scandir($dir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

    $files[] = $file;
    $dbPath  = 'public/uploads/students/' . $file;
    $sid     = null;

    // Pattern 1: student_{student_id}_sync.jpg  (จาก sync script)
    if (preg_match('/^student_(\w+)_sync\.\w+$/i', $file, $m)) {
        $sid = $m[1];
    }
    // Pattern 2: {student_id}.jpg  (ตั้งชื่อตรงๆ)
    elseif (preg_match('/^(\d+)\.\w+$/', $file, $m)) {
        $sid = $m[1];
    }
    // Pattern 3: {student_id}_{anything}.jpg
    elseif (preg_match('/^(\d{4,})[_\-]/', $file, $m)) {
        $sid = $m[1];
    }

    if (!$sid || !isset($studentMap[$sid])) {
        $notFound++;
        continue;
    }

    $internalId = $studentMap[$sid];

    // อัปเดตเฉพาะถ้า photo ยังไม่ตรงกับไฟล์นี้
    $upd = $pdo->prepare(
        "UPDATE students SET photo = ? WHERE id = ? AND (photo IS NULL OR photo = '' OR photo != ?)"
    );
    $upd->execute([$dbPath, $internalId, $dbPath]);

    if ($upd->rowCount() > 0) $updated++;
    else $skipped++;
}

// นับ stats
$totalWithPhoto = $pdo->query("SELECT COUNT(*) FROM students WHERE photo IS NOT NULL AND photo != ''")->fetchColumn();
$totalStudents  = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

echo json_encode([
    'success'         => true,
    'files_found'     => count($files),
    'db_updated'      => $updated,
    'already_correct' => $skipped,
    'no_match'        => $notFound,
    'db_stats'        => [
        'total_students'   => (int)$totalStudents,
        'students_with_photo' => (int)$totalWithPhoto,
        'students_no_photo'   => (int)$totalStudents - (int)$totalWithPhoto,
    ],
    'message' => "อัปเดต $updated รายการ | มีรูปใน DB แล้ว: $totalWithPhoto/$totalStudents คน",
], JSON_UNESCAPED_UNICODE);
