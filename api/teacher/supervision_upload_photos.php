<?php
/**
 * api/teacher/supervision_upload_photos.php
 * Handles uploading photos and saving captions for teaching and supervision.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'teaching' or 'supervision'
$caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';

if ($booking_id <= 0 || !in_array($type, ['teaching', 'supervision'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    // 1. Get teacher id and check ownership
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $curr_user = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_teacher = $stmt->fetch();
    $my_teacher_id = $my_teacher ? $my_teacher['id'] : 0;

    $stmt = $pdo->prepare("SELECT * FROM supervision_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการนิเทศนี้']);
        exit;
    }

    $is_allowed = ($curr_user['role'] === 'admin') || ((int)$booking['teacher_id'] === $my_teacher_id);
    if (!$is_allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์อัปโหลดรูปภาพสำหรับรายการนี้']);
        exit;
    }

    // Load existing post teaching record JSON
    $recordData = [];
    if (!empty($booking['post_teaching_record'])) {
        $parsed = json_decode($booking['post_teaching_record'], true);
        if (is_array($parsed)) {
            $recordData = $parsed;
        }
    }

    // Handle photo file if uploaded
    $db_path = isset($recordData['photo_' . $type]) ? $recordData['photo_' . $type] : '';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed_exts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'อนุญาตเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp) เท่านั้น']);
            exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'รูปภาพต้องมีขนาดไม่เกิน 10MB']);
            exit;
        }

        $upload_dir = '../../uploads/supervision/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = $type . '_' . $booking_id . '_' . time() . '.' . $ext;
        $dest_path = $upload_dir . $new_filename;
        $db_path = 'uploads/supervision/' . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถเซฟรูปภาพลงเซิร์ฟเวอร์ได้']);
            exit;
        }
    }

    // Save/update recordData
    if ($db_path) {
        $recordData['photo_' . $type] = $db_path;
    }
    $recordData['caption_' . $type] = $caption;

    $updated_json = json_encode($recordData, JSON_UNESCAPED_UNICODE);

    $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET post_teaching_record = ? WHERE id = ?");
    $stmt_update->execute([$updated_json, $booking_id]);

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกรูปภาพและคำบรรยายเรียบร้อยแล้ว',
        'photo_path' => $db_path,
        'caption' => $caption
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
