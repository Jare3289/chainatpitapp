<?php
/**
 * api/teacher/supervision_upload_docs.php
 * Handles uploading the 4 mandatory PDFs for supervision.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$doc_type = isset($_POST['doc_type']) ? trim($_POST['doc_type']) : '';

$allowed_doc_types = [
    'doc_subject_structure',
    'doc_unit_structure',
    'doc_unit_plan',
    'doc_lesson_plan'
];

if ($booking_id <= 0 || !in_array($doc_type, $allowed_doc_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    // 1. Get my teacher id
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$me) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Teacher record not found']);
        exit;
    }

    $teacher_id = $me['id'];

    // 2. Validate booking ownership and status
    $stmt = $pdo->prepare("SELECT id, status FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$booking_id, $teacher_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ในการแก้ไขรายการจองนี้']);
        exit;
    }

    if ($booking['status'] !== 'approved' && $booking['status'] !== 'doc_submitted') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'สถานะคำร้องยังไม่ผ่านการอนุมัติหรือไม่สามารถอัปโหลดได้ในสถานะนี้']);
        exit;
    }

    // 3. Handle File Upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
        exit;
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'อนุญาตให้เฉพาะไฟล์เอกสารสกุล PDF เท่านั้น']);
        exit;
    }

    // Max 15MB file size
    if ($file['size'] > 15 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ไฟล์ต้องมีขนาดไม่เกิน 15MB']);
        exit;
    }

    // Ensure upload directory exists
    $upload_dir = '../../uploads/supervision/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique name
    $new_filename = $doc_type . '_' . $booking_id . '_' . time() . '.' . $ext;
    $dest_path = $upload_dir . $new_filename;
    $db_path = 'uploads/supervision/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        // Upsert into supervision_docs
        $stmt_check = $pdo->prepare("SELECT booking_id FROM supervision_docs WHERE booking_id = ?");
        $stmt_check->execute([$booking_id]);
        $exists = $stmt_check->fetch();

        if ($exists) {
            $stmt_update = $pdo->prepare("UPDATE supervision_docs SET $doc_type = ? WHERE booking_id = ?");
            $stmt_update->execute([$db_path, $booking_id]);
        } else {
            $stmt_insert = $pdo->prepare("INSERT INTO supervision_docs (booking_id, $doc_type) VALUES (?, ?)");
            $stmt_insert->execute([$booking_id, $db_path]);
        }

        // Also check if all 4 docs are uploaded
        $stmt_all = $pdo->prepare("SELECT doc_subject_structure, doc_unit_structure, doc_unit_plan, doc_lesson_plan FROM supervision_docs WHERE booking_id = ?");
        $stmt_all->execute([$booking_id]);
        $docs = $stmt_all->fetch(PDO::FETCH_ASSOC);

        $all_uploaded = true;
        foreach ($docs as $k => $v) {
            if (empty($v)) {
                $all_uploaded = false;
                break;
            }
        }

        if ($all_uploaded) {
            $stmt_status = $pdo->prepare("UPDATE supervision_bookings SET status = 'doc_submitted' WHERE id = ? AND status = 'approved'");
            $stmt_status->execute([$booking_id]);
        }

        // Auto-read receipt logic (clear read receipts for this document when uploaded)
        // Whenever a teacher uploads a NEW version of a doc, evaluators must read it AGAIN.
        if (in_array($doc_type, ['doc_subject_structure', 'doc_unit_structure', 'doc_unit_plan', 'doc_lesson_plan'])) {
            $read_column = str_replace('doc_', 'read_', $doc_type);
            $stmt_clear_reads = $pdo->prepare("UPDATE supervision_doc_reads SET $read_column = NULL WHERE booking_id = ?");
            $stmt_clear_reads->execute([$booking_id]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดเอกสารสำเร็จ',
            'doc_path' => $db_path,
            'all_uploaded' => $all_uploaded
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถคัดลอกไฟล์ไปยังโฟลเดอร์เซิร์ฟเวอร์ได้']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
