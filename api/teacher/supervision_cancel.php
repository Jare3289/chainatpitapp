<?php
/**
 * api/teacher/supervision_cancel.php
 * Endpoint for teachers to cancel their own pending supervision booking.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_notify.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
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
    $is_admin = ($_SESSION['role'] === 'admin');
    $teacher_id = 0;
    
    if (!$is_admin) {
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
    }

    // 2. Verify ownership and status (also fetch peer/head for notifications)
    $stmt = $pdo->prepare("SELECT status, peer_teacher_id, head_teacher_id FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$booking_id, $teacher_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง หรือคุณไม่มีสิทธิ์ยกเลิก']);
        exit;
    }

    if ($booking['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'รายการนี้ถูกยกเลิกไปแล้ว']);
        exit;
    }

    // 3. Cancel
    $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);

    // 4. Notify teacher (self) + peer + head
    try {
        $ids = supervisionBookingUserIds($pdo, $booking_id);
        $msg = "การจองคิวนิเทศ #{$booking_id} ถูกยกเลิกโดยครูผู้รับการนิเทศ";
        supervisionNotify($pdo, [$ids['teacher_user_id']], 'ยกเลิกการจองนิเทศสำเร็จ', "คิวนิเทศ #{$booking_id} ของคุณถูกยกเลิกเรียบร้อยแล้ว", 'teacher_supervision.html');
        supervisionNotify($pdo, [$ids['peer_user_id'], $ids['head_user_id']], 'ยกเลิกการจองนิเทศ ❌', $msg, 'teacher_supervision.html');
    } catch (Throwable $e_n) {
        error_log('[supervision_cancel notify] ' . $e_n->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'ยกเลิกการจองคิวสำเร็จ']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
