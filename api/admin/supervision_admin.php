<?php
/**
 * api/admin/supervision_admin.php
 * Endpoint for academic committee and admins to view all bookings,
 * approve bookings, assign academic evaluators, or cancel sessions.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

$is_supervision_manager = false;
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        $is_supervision_manager = true;
    } elseif ($_SESSION['role'] === 'teacher') {
        $stmt_check = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt_check->execute([$_SESSION['user_id']]);
        $teacher_id = $stmt_check->fetchColumn();
        if ($teacher_id && (int)$teacher_id === 518) {
            $is_supervision_manager = true;
        }
    }
}

if (!$is_supervision_manager) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

try {
    if ($action === 'get_bookings') {
        // Fetch all bookings for Semester 1/2569
        $semester = 1;
        $year = 2569;

        $stmt = $pdo->prepare("SELECT b.*, 
            t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.photo as t_photo, t.department as t_dept, t.academic_standing as t_standing,
            (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.peer_teacher_id) as peer_name,
            (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.head_teacher_id) as head_name,
            (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.academic_teacher_id) as academic_name
            FROM supervision_bookings b
            JOIN teachers t ON b.teacher_id = t.id
            WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'
            ORDER BY b.booking_date ASC, b.booking_period ASC");
        $stmt->execute([$semester, $year]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format names
        foreach ($bookings as &$b) {
            $b['teacher_name'] = trim(($b['t_prefix'] ?? '') . $b['t_first'] . ' ' . $b['t_last']);
        }
        unset($b);

        // Calculate counts for stats
        $pending_count = 0;
        $upcoming_count = 0; // approved but not completed
        $completed_count = 0; // completed status
        $printed_count = 0; // completed is also printable

        foreach ($bookings as $b) {
            if ($b['status'] === 'pending') {
                $pending_count++;
            } elseif ($b['status'] === 'approved' || $b['status'] === 'doc_submitted') {
                $upcoming_count++;
            } elseif ($b['status'] === 'completed') {
                $completed_count++;
                $printed_count++;
            }
        }

        echo json_encode([
            'success' => true,
            'bookings' => $bookings,
            'stats' => [
                'pending' => $pending_count,
                'upcoming' => $upcoming_count,
                'completed' => $completed_count,
                'printed' => $printed_count
            ]
        ]);

    } elseif ($action === 'approve') {
        // Approve booking and assign academic evaluator
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
        $academic_teacher_id = isset($data['academic_teacher_id']) ? (int)$data['academic_teacher_id'] : 0;

        if ($booking_id <= 0 || $academic_teacher_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'กรุณาระบุข้อมูลการจองและคณะกรรมการวิชาการ']);
            exit;
        }

        // Update booking status
        $stmt = $pdo->prepare("UPDATE supervision_bookings 
            SET status = 'approved', academic_teacher_id = ? 
            WHERE id = ? AND status = 'pending'");
        $stmt->execute([$academic_teacher_id, $booking_id]);

        if ($stmt->rowCount() > 0) {
            // Notify academic evaluator
            try {
                $stmt_user = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                $stmt_user->execute([$academic_teacher_id]);
                $evaluator_user_id = $stmt_user->fetchColumn();

                if ($evaluator_user_id) {
                    $stmt_bk = $pdo->prepare("SELECT booking_date, (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.teacher_id) as t_name FROM supervision_bookings b WHERE b.id = ?");
                    $stmt_bk->execute([$booking_id]);
                    $bk_info = $stmt_bk->fetch();
                    
                    $msg = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็นคณะกรรมการประเมินของ อ. " . $bk_info['t_name'] . " ในวันที่ " . $bk_info['booking_date'];
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, status) VALUES (?, 'งานมอบหมายประเมินนิเทศการสอน', ?, 'unread')")
                        ->execute([$evaluator_user_id, $msg]);
                }
            } catch (Exception $ex) {}

            echo json_encode(['success' => true, 'message' => 'อนุมัติคำร้องและมอบหมายคณะกรรมการวิชาการเรียบร้อยแล้ว']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ไม่พบคำร้องที่รออนุมัติรายการนี้ หรือคำร้องได้รับการอนุมัติไปแล้ว']);
        }

    } elseif ($action === 'reject') {
        // Cancel/Reject booking
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;

        if ($booking_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'ยกเลิกรายการจองเรียบร้อยแล้ว']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถยกเลิกรายการนี้ได้']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action not supported']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
