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
    $stmt = $pdo->prepare("SELECT status, subject_code, subject_name, booking_date, peer_teacher_id, head_teacher_id, academic_teacher_id,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.teacher_id) as t_name,
        (SELECT user_id FROM teachers WHERE id = b.peer_teacher_id) as peer_user,
        (SELECT user_id FROM teachers WHERE id = b.head_teacher_id) as head_user,
        (SELECT user_id FROM teachers WHERE id = b.academic_teacher_id) as ac_user
        FROM supervision_bookings b WHERE b.id = ? AND b.teacher_id = ?");
    $stmt->execute([$booking_id, $teacher_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง หรือคุณไม่มีสิทธิ์ยกเลิก']);
        exit;
    }

    if ($booking['status'] !== 'pending' && $booking['status'] !== 'approved') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถยกเลิกได้ เนื่องจากคิวถูกดำเนินการไปแล้ว']);
        exit;
    }

    // 3. Cancel
    $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);

    // Send notifications
    try {
        require_once '../../inc/notifications.php';
        
        $parts = explode('-', $booking['booking_date']);
        $thai_date = $booking['booking_date'];
        if (count($parts) === 3) {
            $y = (int)$parts[0] + 543;
            $m = (int)$parts[1];
            $d = (int)$parts[2];
            $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $thai_date = "$d {$months[$m]} $y";
        }

        // Notify evaluatee (myself)
        $msg_eval = "การยกเลิกจองคิวนิเทศรายวิชา " . $booking['subject_name'] . " (" . $booking['subject_code'] . ") ในวันที่ $thai_date สำเร็จแล้ว";
        cnp_notify($pdo, (int)$user_id, 'ยกเลิกคิวนิเทศสำเร็จ ❌', $msg_eval, 'teacher_supervision.html', 'bi-x-circle-fill', '#ef4444', 'supervision');

        // Notify committee
        $msg_comm = "อ. " . $booking['t_name'] . " ได้ยกเลิกจองคิวนิเทศในวันที่ $thai_date วิชา " . $booking['subject_name'];
        if (!empty($booking['peer_user'])) {
            cnp_notify($pdo, (int)$booking['peer_user'], 'คิวนิเทศถูกยกเลิก ⚠️', $msg_comm, 'teacher_supervision.html', 'bi-x-circle', '#ef4444', 'supervision');
        }
        if (!empty($booking['head_user'])) {
            cnp_notify($pdo, (int)$booking['head_user'], 'คิวนิเทศถูกยกเลิก ⚠️', $msg_comm, 'teacher_supervision.html', 'bi-x-circle', '#ef4444', 'supervision');
        }
        if (!empty($booking['ac_user'])) {
            cnp_notify($pdo, (int)$booking['ac_user'], 'คิวนิเทศถูกยกเลิก ⚠️', $msg_comm, 'teacher_supervision.html', 'bi-x-circle', '#ef4444', 'supervision');
        }
    } catch (Exception $ex) {}

    echo json_encode(['success' => true, 'message' => 'ยกเลิกการจองคิวสำเร็จ']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
