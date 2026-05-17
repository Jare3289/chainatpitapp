<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

$role   = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$importDir = __DIR__ . '/../../public/uploads/import/';
$uploadDir = __DIR__ . '/../../public/uploads/students/';

// Ensure directories exist
if (!is_dir($importDir)) {
    mkdir($importDir, 0755, true);
}
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Scan the import directory for files
$files = scandir($importDir);
$imageFiles = [];
$allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    
    $filePath = $importDir . $file;
    if (is_file($filePath)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts, true)) {
            $imageFiles[] = [
                'filename' => $file,
                'basename' => pathinfo($file, PATHINFO_FILENAME),
                'ext' => $ext,
                'path' => $filePath
            ];
        }
    }
}

if (empty($imageFiles)) {
    echo json_encode([
        'success' => true,
        'empty' => true,
        'message' => 'โฟลเดอร์นำเข้าว่างเปล่า กรุณานำรูปภาพนักเรียนไปใส่ในโฟลเดอร์ public/uploads/import/'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$processed = 0;
$updated = 0;
$notFound = 0;
$details = [];

foreach ($imageFiles as $img) {
    $studentId = trim($img['basename']);
    
    // Check if student exists in database
    $stmt = $pdo->prepare("SELECT id, first_name_th, last_name_th FROM students WHERE student_id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $newFilename = 'student_' . $studentId . '_sync.' . $img['ext'];
        $newPath = $uploadDir . $newFilename;
        $dbPath = 'public/uploads/students/' . $newFilename;
        
        // Copy file to students upload directory and update DB
        if (copy($img['path'], $newPath)) {
            // Update student photo path
            $update = $pdo->prepare("UPDATE students SET photo = ? WHERE id = ?");
            $update->execute([$dbPath, $student['id']]);
            
            // Delete original file from import folder
            unlink($img['path']);
            
            $updated++;
            $details[] = [
                'filename' => $img['filename'],
                'student_id' => $studentId,
                'status' => 'success',
                'name' => $student['first_name_th'] . ' ' . $student['last_name_th'],
                'message' => 'ซิงค์สำเร็จ'
            ];
        } else {
            $details[] = [
                'filename' => $img['filename'],
                'student_id' => $studentId,
                'status' => 'error',
                'name' => $student['first_name_th'] . ' ' . $student['last_name_th'],
                'message' => 'ไม่สามารถคัดลอกไฟล์ได้'
            ];
        }
    } else {
        $notFound++;
        $details[] = [
            'filename' => $img['filename'],
            'student_id' => $studentId,
            'status' => 'not_found',
            'name' => '-',
            'message' => 'ไม่พบรหัสนักเรียนในฐานข้อมูล'
        ];
    }
    $processed++;
}

echo json_encode([
    'success' => true,
    'empty' => false,
    'processed' => $processed,
    'updated' => $updated,
    'not_found' => $notFound,
    'details' => $details
], JSON_UNESCAPED_UNICODE);
