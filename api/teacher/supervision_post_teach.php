<?php
/**
 * api/teacher/supervision_post_teach.php
 * Handles saving the post-teaching reflections for evaluated teachers.
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
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$post_teaching_record = isset($data['post_teaching_record']) ? trim($data['post_teaching_record']) : '';

if ($booking_id <= 0 || empty($post_teaching_record)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกบันทึกหลังการสอนให้ครบถ้วน']);
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

    // 2. Validate booking ownership
    $stmt = $pdo->prepare("SELECT id, status FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$booking_id, $teacher_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ในการกรอกข้อมูลสำหรับรายการจองนี้']);
        exit;
    }

    // 3. Save post-teaching reflections
    $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET post_teaching_record = ?, status = 'completed' WHERE id = ?");
    $stmt_update->execute([$post_teaching_record, $booking_id]);

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกหลังการจัดการเรียนรู้เรียบร้อยแล้ว ท่านสามารถเข้าดาวน์โหลด/พิมพ์รูปเล่มเอกสารนิเทศได้ทันที'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
