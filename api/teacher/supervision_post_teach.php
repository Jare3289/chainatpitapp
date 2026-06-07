<?php
/**
 * api/teacher/supervision_post_teach.php
 * Handles saving the post-teaching reflections for evaluated teachers.
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

    // 2. Validate booking ownership
    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT id, status, teacher_id FROM supervision_bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, status, teacher_id FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$booking_id, $teacher_id]);
    }
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ในการกรอกข้อมูลสำหรับรายการจองนี้']);
        exit;
    }

    // 3. Save post-teaching reflections
    $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET post_teaching_record = ?, status = 'completed' WHERE id = ?");
    $stmt_update->execute([$post_teaching_record, $booking_id]);

    // Send notifications
    try {
        require_once '../../inc/notifications.php';
        
        $stmt_bk = $pdo->prepare("SELECT peer_teacher_id, head_teacher_id, academic_teacher_id, subject_code, subject_name,
            (SELECT user_id FROM teachers WHERE id = b.peer_teacher_id) as peer_user,
            (SELECT user_id FROM teachers WHERE id = b.head_teacher_id) as head_user,
            (SELECT user_id FROM teachers WHERE id = b.academic_teacher_id) as ac_user,
            (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.teacher_id) as t_name
            FROM supervision_bookings b WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk_info = $stmt_bk->fetch();

        if ($bk_info) {
            // Get target evaluatee teacher's user_id
            $stmt_bk_user = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt_bk_user->execute([$booking['teacher_id']]);
            $evaluatee_user_id = $stmt_bk_user->fetchColumn();

            // Notify evaluatee
            $msg_eval = "บันทึกหลังการจัดกิจกรรมการเรียนรู้วิชา " . $bk_info['subject_name'] . " (" . $bk_info['subject_code'] . ") เรียบร้อยแล้ว ขณะนี้กระบวนการนิเทศของท่านเสร็จสิ้นสมบูรณ์ ท่านสามารถดาวน์โหลดเล่มรายงานได้ทันที";
            if ($evaluatee_user_id) {
                if ($is_admin) {
                    cnp_notify($pdo, (int)$evaluatee_user_id, 'บันทึกหลังสอนได้รับการอัปเดต 🎓', "บันทึกหลังแผนการสอนของคุณได้รับการอัปเดตโดยผู้ดูแลระบบ ขณะนี้กระบวนการเสร็จสิ้นแล้ว", 'teacher_supervision.html', 'bi-mortarboard-fill', '#10b981', 'supervision');
                } else {
                    cnp_notify($pdo, (int)$evaluatee_user_id, 'บันทึกหลังสอนเสร็จสิ้น/กระบวนการนิเทศสมบูรณ์ 🎓', $msg_eval, 'teacher_supervision.html', 'bi-mortarboard-fill', '#10b981', 'supervision');
                }
            }

            if ($is_admin) {
                cnp_notify($pdo, (int)$user_id, 'บันทึกหลังสอนเสร็จสิ้น 🎓', "ท่านได้ทำการบันทึกหลังสอนเรียบร้อยแล้ว", 'supervision.html', 'bi-check-circle-fill', '#10b981', 'supervision');
            }

            // Notify committee
            $msg_comm = "อ. " . $bk_info['t_name'] . " ได้กรอกบันทึกหลังการจัดกิจกรรมการเรียนรู้วิชา " . $bk_info['subject_name'] . " แล้ว กระบวนการนิเทศเสร็จสมบูรณ์เรียบร้อย";
            if (!empty($bk_info['peer_user'])) {
                cnp_notify($pdo, (int)$bk_info['peer_user'], 'กระบวนการนิเทศเสร็จสมบูรณ์ 🏁', $msg_comm, 'teacher_supervision.html', 'bi-clipboard-check-fill', '#10b981', 'supervision');
            }
            if (!empty($bk_info['head_user'])) {
                cnp_notify($pdo, (int)$bk_info['head_user'], 'กระบวนการนิเทศเสร็จสมบูรณ์ 🏁', $msg_comm, 'teacher_supervision.html', 'bi-clipboard-check-fill', '#10b981', 'supervision');
            }
            if (!empty($bk_info['ac_user'])) {
                cnp_notify($pdo, (int)$bk_info['ac_user'], 'กระบวนการนิเทศเสร็จสมบูรณ์ 🏁', $msg_comm, 'teacher_supervision.html', 'bi-clipboard-check-fill', '#10b981', 'supervision');
            }
        }
    } catch (Exception $ex) {}

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกหลังการจัดการเรียนรู้เรียบร้อยแล้ว ท่านสามารถเข้าดาวน์โหลด/พิมพ์รูปเล่มเอกสารนิเทศได้ทันที'
    ]);

    // Notify peer + head that post-teach record is complete
    try {
        $ids = supervisionBookingUserIds($pdo, $booking_id);
        $msg = "ครูผู้รับการนิเทศได้ส่งบันทึกหลังการสอน การนิเทศจอง #{$booking_id} เสร็จสมบูรณ์แล้ว";
        supervisionNotify($pdo, [$ids['peer_user_id'], $ids['head_user_id']], 'บันทึกหลังสอนเสร็จสมบูรณ์', $msg, 'supervision_booking.html');
    } catch (Throwable $e_n) {
        error_log('[supervision_post_teach notify] ' . $e_n->getMessage());
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
