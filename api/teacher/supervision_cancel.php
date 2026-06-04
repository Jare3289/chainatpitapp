<?php
/**
 * api/teacher/supervision_cancel.php
 * Endpoint for teachers to cancel their own pending supervision booking.
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

// Get booking_id from GET param or JSON body
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data && isset($data['booking_id'])) {
        $booking_id = (int)$data['booking_id'];
    }
}

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

try {
    // 1. Get teacher id
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

    // 2. Verify ownership and status
    $stmt = $pdo->prepare("SELECT status FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$booking_id, $teacher_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง หรือคุณไม่มีสิทธิ์ยกเลิก']);
        exit;
    }

    if ($booking['status'] !== 'pending') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถยกเลิกได้ เนื่องจากคิวถูกดำเนินการไปแล้ว']);
        exit;
    }

    // 3. Cancel
    $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);

    echo json_encode(['success' => true, 'message' => 'ยกเลิกการจองคิวสำเร็จ']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
