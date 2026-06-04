<?php
/**
 * api/teacher/supervision_upload.php
 * Handles lesson plan document uploading for evaluatees.
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

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
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
    $allowed_exts = ['pdf', 'doc', 'docx'];

    if (!in_array($ext, $allowed_exts)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'อนุญาตให้เฉพาะไฟล์เอกสารสกุล PDF, DOC, หรือ DOCX เท่านั้น']);
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
    $new_filename = 'lesson_plan_' . $booking_id . '_' . time() . '.' . $ext;
    $dest_path = $upload_dir . $new_filename;
    $db_path = 'uploads/supervision/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        // Update database booking path and set status to 'doc_submitted'
        $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET lesson_plan_doc = ?, status = 'doc_submitted' WHERE id = ?");
        $stmt_update->execute([$db_path, $booking_id]);

        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดเอกสารประกอบการนิเทศเรียบร้อยแล้ว คณะกรรมการสามารถตรวจประเมินได้ทันที',
            'doc_path' => $db_path
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถคัดลอกไฟล์ไปยังโฟลเดอร์เซิร์ฟเวอร์ได้']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
