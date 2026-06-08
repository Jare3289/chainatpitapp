<?php
/**
 * api/admin/supervision_booking_manage.php
 * Endpoint for managing supervision queue, assigning evaluators,
 * and checking teacher availability.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_notify.php';
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
    if ($action === 'list') {
        $semester = 1;
        $year = 2569;

        // Fetch all bookings
        $stmt = $pdo->prepare("SELECT b.*,
            t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.department as t_dept, t.academic_standing as t_standing,
            tp.prefix as p_prefix, tp.first_name_th as p_first, tp.last_name_th as p_last, tp.department as p_dept,
            th.prefix as h_prefix, th.first_name_th as h_first, th.last_name_th as h_last, th.department as h_dept,
            ta.prefix as a_prefix, ta.first_name_th as a_first, ta.last_name_th as a_last, ta.department as a_dept
            FROM supervision_bookings b
            JOIN teachers t ON b.teacher_id = t.id
            LEFT JOIN teachers tp ON b.peer_teacher_id = tp.id
            LEFT JOIN teachers th ON b.head_teacher_id = th.id
            LEFT JOIN teachers ta ON b.academic_teacher_id = ta.id
            WHERE b.semester = ? AND b.year = ?
            ORDER BY b.booking_date DESC, b.booking_period ASC");
        $stmt->execute([$semester, $year]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format names and classroom info
        foreach ($bookings as &$b) {
            // ชื่อผู้รับประเมิน: ชื่อแรกเท่านั้น (first name only)
            $b['teacher_name'] = trim($b['t_first'] ?? '');
            $b['peer_name'] = $b['peer_teacher_id'] ? trim($b['p_first'] ?? '') : '-';
            $b['head_name'] = $b['head_teacher_id'] ? trim($b['h_first'] ?? '') : '-';
            $b['academic_name'] = $b['academic_teacher_id'] ? trim($b['a_first'] ?? '') : '-';

            // Parse classroom code: "205" → grade=2, class=05
            $classroom = trim($b['classroom'] ?? '');
            if (preg_match('/^([1-6])(\d{2})$/', $classroom, $m)) {
                $b['grade_level'] = 'ม.' . $m[1];
                $b['class_name'] = (int)$m[2];
            } else {
                $b['grade_level'] = $classroom ?: 'ไม่ระบุ';
                $b['class_name'] = '';
            }
            $b['room_location'] = trim($b['room_number'] ?? '');
        }
        unset($b);

        echo json_encode([
            'success' => true,
            'bookings' => $bookings
        ]);

    } elseif ($action === 'get_available_evaluators') {
        $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
        $date = trim($_GET['date'] ?? '');
        $period = isset($_GET['period']) ? (int)$_GET['period'] : -1;

        if ($booking_id > 0) {
            $stmt_bk = $pdo->prepare("SELECT booking_date, booking_period, teacher_id FROM supervision_bookings WHERE id = ?");
            $stmt_bk->execute([$booking_id]);
            $bk = $stmt_bk->fetch();
            if ($bk) {
                $date = $bk['booking_date'];
                $period = (int)$bk['booking_period'];
                $evaluatee_teacher_id = (int)$bk['teacher_id'];
            }
        }

        if (empty($date) || $period < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'พารามิเตอร์ไม่ครบถ้วน']);
            exit;
        }

        // Get day of week (1 = Monday, 7 = Sunday)
        $day_of_week = date('N', strtotime($date));

        // Check if this period is a "meeting period" (has subject like "ประชุม", "ชุมนุม", etc.)
        $stmt_check_meeting = $pdo->prepare("
            SELECT COUNT(*) FROM timetable
            WHERE day_of_week = ? AND period = ? AND subject_name LIKE ?
        ");
        $stmt_check_meeting->execute([$day_of_week, $period, '%ประชุม%']);
        $isMeetingPeriod = $stmt_check_meeting->fetchColumn() > 0;

        // 1. Fetch busy teachers from timetable
        $stmt_busy = $pdo->prepare("SELECT DISTINCT teacher_id FROM timetable WHERE day_of_week = ? AND period = ?");
        $stmt_busy->execute([$day_of_week, $period]);
        $busy_timetable_teacher_ids = $stmt_busy->fetchAll(PDO::FETCH_COLUMN);

        // 2. Fetch busy teachers from active supervision bookings
        // (If they are evaluatee, peer, head, or academic evaluator in another active booking on this date and period)
        $stmt_conflicts = $pdo->prepare("
            SELECT b.id, b.teacher_id, b.peer_teacher_id, b.head_teacher_id, b.academic_teacher_id,
            t.prefix, t.first_name_th, t.last_name_th
            FROM supervision_bookings b
            JOIN teachers t ON b.teacher_id = t.id
            WHERE b.booking_date = ? AND b.booking_period = ? AND b.status != 'cancelled' AND b.id != ?
        ");
        $stmt_conflicts->execute([$date, $period, $booking_id]);
        $conflicts = $stmt_conflicts->fetchAll(PDO::FETCH_ASSOC);

        $conflict_map = [];
        foreach ($conflicts as $c) {
            $tname = trim(($c['prefix'] ?? '') . $c['first_name_th'] . ' ' . $c['last_name_th']);
            if ($c['teacher_id']) {
                $conflict_map[$c['teacher_id']] = "เป็นผู้รับประเมิน (สอนวิชาของ อ. {$tname})";
            }
            if ($c['peer_teacher_id']) {
                $conflict_map[$c['peer_teacher_id']] = "เป็นกรรมการคู่หูของ อ. {$tname}";
            }
            if ($c['head_teacher_id']) {
                $conflict_map[$c['head_teacher_id']] = "เป็นกรรมการหัวหน้าของ อ. {$tname}";
            }
            if ($c['academic_teacher_id']) {
                $conflict_map[$c['academic_teacher_id']] = "เป็นกรรมการฝ่ายวิชาการของ อ. {$tname}";
            }
        }

        // 3. Fetch all teachers in school (for peer/head evaluator selection)
        $stmt_teachers = $pdo->query("SELECT id, prefix, first_name_th, last_name_th, department, sub_department, department_position, position, academic_standing, nationality FROM teachers ORDER BY first_name_th ASC");
        $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

        $all_teachers = [];
        $academic_teachers = []; // กรรมการคนลำดับที่ 3: ตัวแทนคณะกรรมการวิชาการ (15 คน: 11 ครู + 4 รองผู้อำนวยการ)

        // List of 15 eligible academic evaluators (11 teachers + 4 deputy directors)
        // ต้องระบุคนโดยชื่อเต็มหรือ teacher_id
        $allowed_academic_names = [
            // 11 ครู
            'ครูวิลาวรรณ',
            'ครูเพ็ญประภา',
            'ครูปวีณ์นุช',
            'ครูจิตรดา',
            'ครูสุภัค',
            'ครูปณิตา',
            'ครูสุชาดา',
            'ครูอังคณา',
            'ครูสันธินี',
            'ครูสาธิต',
            // 4 รองผู้อำนวยการ
            'รองผู้อำนวยการ'
        ];

        foreach ($teachers as $t) {
            $t_id = (int)$t['id'];
            $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);

            $is_busy_timetable = in_array($t_id, $busy_timetable_teacher_ids);
            $is_busy_booking = isset($conflict_map[$t_id]);

            $t['is_busy'] = ($is_busy_timetable || $is_busy_booking);

            $reasons = [];
            if ($is_busy_timetable) {
                $reasons[] = "มีสอนตามตารางสอนในคาบนี้";
            }
            if ($is_busy_booking) {
                $reasons[] = $conflict_map[$t_id];
            }

            $t['busy_reason'] = implode(' และ ', $reasons);

            $all_teachers[] = $t;

            // --- กรองสำหรับกรรมการวิชาการ: เฉพาะคนในรายชื่อที่กำหนด + ว่าง ---
            $pos = trim($t['position'] ?? '');
            $is_in_allowed_list = false;

            // Check if in allowed list by position (รองผู้อำนวยการ)
            if (stripos($pos, 'รองผู้อำนวยการ') !== false) {
                $is_in_allowed_list = true;
            }
            // Check if in allowed list by first name (11 ครู)
            $fname = trim($t['first_name_th'] ?? '');
            foreach ($allowed_academic_names as $name) {
                if (stripos($fname, trim(str_replace('ครู', '', $name))) !== false) {
                    $is_in_allowed_list = true;
                    break;
                }
            }

            // ต้องว่างในคาบนั้น
            $is_available = !$t['is_busy'];
            // ต้องไม่เป็นครูที่รับประเมินเอง
            $is_evaluatee = isset($evaluatee_teacher_id) && ($t_id === (int)$evaluatee_teacher_id);

            // Add to academic_teachers ถ้า: ในรายชื่อ + ว่าง + ไม่ใช่ผู้รับประเมิน
            if ($is_in_allowed_list && $is_available && !$is_evaluatee) {
                $academic_teachers[] = $t;
            }
        }

        echo json_encode([
            'success' => true,
            'teachers' => $all_teachers,
            'academic_teachers' => $academic_teachers,
            'is_meeting_period' => $isMeetingPeriod
        ]);

    } elseif ($action === 'assign') {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
        $peer_teacher_id = isset($data['peer_teacher_id']) ? (int)$data['peer_teacher_id'] : 0;
        $head_teacher_id = isset($data['head_teacher_id']) ? (int)$data['head_teacher_id'] : 0;
        $academic_teacher_id = isset($data['academic_teacher_id']) ? (int)$data['academic_teacher_id'] : 0;

        if ($booking_id <= 0 || $peer_teacher_id <= 0 || $head_teacher_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'กรุณาระบุข้อมูลการจอง เพื่อนครูร่วมประเมิน และหัวหน้ากลุ่มสาระฯ ให้ครบถ้วน']);
            exit;
        }

        // Get current booking details and verify evaluatee is not assigned as evaluator
        $stmt_bk = $pdo->prepare("SELECT b.*, t.prefix, t.first_name_th, t.last_name_th FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk = $stmt_bk->fetch(PDO::FETCH_ASSOC);

        if (!$bk) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
            exit;
        }

        $evaluatee_teacher_id = (int)$bk['teacher_id'];
        $evaluatee_name = trim(($bk['prefix'] ?? '') . $bk['first_name_th'] . ' ' . $bk['last_name_th']);

        if ($peer_teacher_id === $evaluatee_teacher_id || $head_teacher_id === $evaluatee_teacher_id || $academic_teacher_id === $evaluatee_teacher_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ครูผู้รับประเมินไม่สามารถรับหน้าที่เป็นกรรมการนิเทศคิวของตนเองได้']);
            exit;
        }

        // Fetch old assignments to check who has been updated/added
        $old_peer = (int)$bk['peer_teacher_id'];
        $old_head = (int)$bk['head_teacher_id'];
        $old_academic = (int)$bk['academic_teacher_id'];

        $new_status = ($bk['status'] === 'pending') ? 'approved' : $bk['status'];

        $stmt_update = $pdo->prepare("
            UPDATE supervision_bookings 
            SET peer_teacher_id = ?, head_teacher_id = ?, academic_teacher_id = ?, status = ? 
            WHERE id = ?
        ");
        $stmt_update->execute([$peer_teacher_id, $head_teacher_id, ($academic_teacher_id > 0 ? $academic_teacher_id : null), $new_status, $booking_id]);

        // Create notification list
        // Fetch user IDs
        $teachers_to_notify = []; // [user_id => message]
        
        $msg_date = thDate($bk['booking_date']);
        $msg_period = $bk['booking_period'];

        // Helper function to get user_id of teacher
        $getUser = function($pdo, $teacher_id) {
            if (!$teacher_id) return null;
            $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            return $stmt->fetchColumn();
        };

        // Notify Peer if assigned or changed
        if ($peer_teacher_id !== $old_peer) {
            $uid = $getUser($pdo, $peer_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'กรรมการร่วมประเมิน (ครูคู่หู)' ของ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Notify Head if assigned or changed
        if ($head_teacher_id !== $old_head) {
            $uid = $getUser($pdo, $head_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'กรรมการประเมิน (หัวหน้า/รองกลุ่มสาระฯ)' ของ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Notify Academic if assigned or changed
        if ($academic_teacher_id > 0 && $academic_teacher_id !== $old_academic) {
            $uid = $getUser($pdo, $academic_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'คณะกรรมการฝ่ายวิชาการ' ประเมินนิเทศให้กับ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Send notifications
        foreach ($teachers_to_notify as $uid => $msg) {
            supervisionNotify($pdo, [$uid], 'งานมอบหมายเป็นกรรมการนิเทศ', $msg, 'supervision_evaluate.html');
        }

        // Also notify the evaluatee if status changed from pending to approved
        if ($bk['status'] === 'pending' && $new_status === 'approved') {
            $evaluatee_uid = $getUser($pdo, $evaluatee_teacher_id);
            if ($evaluatee_uid) {
                supervisionNotify($pdo, [$evaluatee_uid], 'คำร้องจองคิวนิเทศได้รับการอนุมัติ', "คำร้องการจองคิวนิเทศของคุณในวันที่ {$msg_date} ได้รับอนุมัติและตั้งกรรมการเรียบร้อยแล้ว", 'supervision_booking.html');
            }
        }

        echo json_encode(['success' => true, 'message' => 'บันทึกการมอบหมายกรรมการและอัปเดตสถานะคิวนิเทศเรียบร้อยแล้ว']);

    } elseif ($action === 'cancel') {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;

        if ($booking_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
            exit;
        }

        // Get details before cancelling to notify
        $stmt_bk = $pdo->prepare("SELECT b.*, t.prefix, t.first_name_th, t.last_name_th FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk = $stmt_bk->fetch(PDO::FETCH_ASSOC);

        if (!$bk) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);

        if ($stmt->rowCount() > 0) {
            // Send cancellation notifications
            $ids = supervisionBookingUserIds($pdo, $booking_id);
            $msg_date = thDate($bk['booking_date']);
            $msg_period = $bk['booking_period'];
            $teacher_name = trim(($bk['prefix'] ?? '') . $bk['first_name_th'] . ' ' . $bk['last_name_th']);

            $notif_msg = "ฝ่ายวิชาการได้ยกเลิกคิวนิเทศการสอนของ อ. {$teacher_name} ในวันที่ {$msg_date} คาบ {$msg_period} เรียบร้อยแล้ว";
            
            $uids = array_filter([$ids['teacher_user_id'], $ids['peer_user_id'], $ids['head_user_id']]);
            // Add academic evaluator user id
            if ($bk['academic_teacher_id']) {
                $stmt_acad = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                $stmt_acad->execute([$bk['academic_teacher_id']]);
                $acad_uid = $stmt_acad->fetchColumn();
                if ($acad_uid) {
                    $uids[] = $acad_uid;
                }
            }

            supervisionNotify($pdo, $uids, 'ยกเลิกคิวนิเทศการสอน', $notif_msg, 'supervision_booking.html');

            echo json_encode(['success' => true, 'message' => 'ยกเลิกรายการคิวนิเทศการสอนเรียบร้อยแล้ว']);
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
