<?php
/**
 * api/teacher/supervision_book.php
 * Handles booking requests with strict date validation and daily period limits.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_schedule.php';
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

$booking_id         = !empty($data['booking_id']) ? (int)$data['booking_id'] : 0;
$subject_code       = trim($data['subject_code'] ?? '');
$subject_name       = trim($data['subject_name'] ?? '');
$classroom          = trim($data['classroom'] ?? '');
$room_number        = trim($data['room_number'] ?? '');
$lesson_topic       = trim($data['lesson_topic'] ?? '');
$booking_date       = trim($data['booking_date'] ?? '');
$booking_period     = isset($data['booking_period']) ? (int)$data['booking_period'] : -1;
$peer_teacher_id    = isset($data['peer_teacher_id']) ? (int)$data['peer_teacher_id'] : 0;
$head_teacher_id    = isset($data['head_teacher_id']) ? (int)$data['head_teacher_id'] : 0;
$teacher_position   = trim($data['teacher_position'] ?? 'ครู');
$academic_standing  = trim($data['academic_standing'] ?? 'ไม่มีวิทยฐานะ');
$evaluation_purpose = trim($data['evaluation_purpose'] ?? 'ไม่มีวิทยฐานะ');
$semester           = 1;
$year               = 2569;

if (empty($subject_name) || empty($lesson_topic) || empty($booking_date) || $booking_period < 0 || $peer_teacher_id <= 0 || $head_teacher_id <= 0 || empty($teacher_position) || empty($academic_standing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน (ขาดข้อมูลสำคัญ)']);
    exit;
}

try {
    $is_admin = ($_SESSION['role'] === 'admin');
    $me = null;
    $teacher_id = 0;
    $my_dept = '';
    $my_sub_dept = '';

    if ($is_admin) {
        if ($booking_id > 0) {
            $stmt = $pdo->prepare("SELECT teacher_id FROM supervision_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $teacher_id = (int)$stmt->fetchColumn();
        } else {
            $teacher_id = isset($data['teacher_id']) ? (int)$data['teacher_id'] : 0;
        }

        if ($teacher_id > 0) {
            $stmt_t = $pdo->prepare("SELECT department, sub_department FROM teachers WHERE id = ?");
            $stmt_t->execute([$teacher_id]);
            $t_rec = $stmt_t->fetch(PDO::FETCH_ASSOC);
            if ($t_rec) {
                $my_dept = $t_rec['department'];
                $my_sub_dept = $t_rec['sub_department'];
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$me) {
            $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE teacher_id = ? OR email = ?");
            $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
            $me = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$me) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลอาจารย์ในระบบ']);
            exit;
        }

        $teacher_id = $me['id'];
        $my_dept = $me['department'];
        $my_sub_dept = $me['sub_department'];
    }

    // 2. Check if already booked for this semester
    $sql_check_dup = "SELECT id FROM supervision_bookings WHERE teacher_id = ? AND semester = ? AND year = ? AND status != 'cancelled'";
    $params_check_dup = [$teacher_id, $semester, $year];
    
    if ($booking_id > 0) {
        $sql_check_dup .= " AND id != ?";
        $params_check_dup[] = $booking_id;
    }

    $stmt = $pdo->prepare($sql_check_dup);
    $stmt->execute($params_check_dup);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ท่านได้ทำการจองคิวนิเทศในภาคเรียนนี้ไว้แล้ว']);
        exit;
    }

    // 3. Define allowed date windows for departments (now loaded from inc/supervision_schedule.php)

    // Determine user's schedule key
    $schedule_key = '';
    if ($my_sub_dept === 'คอมพิวเตอร์และเทคโนโลยี') {
        $schedule_key = 'คอมพิวเตอร์และเทคโนโลยี';
    } else {
        $schedule_key = $my_dept;
    }

    if (!isset($supervision_schedules[$schedule_key])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'กลุ่มสาระการเรียนรู้ของท่านยังไม่มีตารางกำหนดการนิเทศในภาคเรียนนี้']);
        exit;
    }

    $allowed_dates = $supervision_schedules[$schedule_key]['dates'];
    if (!in_array($booking_date, $allowed_dates) && !$is_admin) {
        // Format Thai Dates for error message
        $formatted_dates = array_map(function($d) {
            $parts = explode('-', $d);
            $y = (int)$parts[0] + 543;
            $m = (int)$parts[1];
            $day = (int)$parts[2];
            $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            return "$day {$months[$m]} " . substr($y, 2);
        }, $allowed_dates);

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'วันที่ระบุไม่ตรงตามกำหนดการกลุ่มสาระของท่าน ช่วงที่จองได้คือ: ' . implode(', ', $formatted_dates)]);
        exit;
    }

    // 4. Check school daily limit (max 5 bookings total across the school)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM supervision_bookings WHERE booking_date = ? AND status != 'cancelled' AND id != ?");
    $stmt->execute([$booking_date, $booking_id]);
    $daily_count = $stmt->fetchColumn();

    if ($daily_count >= 5 && !$is_admin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถจองได้ เนื่องจากคิวการนิเทศในวันที่เลือกเต็มแล้ว']);
        exit;
    }

    if ($booking_id > 0) {
        if ($is_admin) {
            $stmt_check = $pdo->prepare("SELECT id, status FROM supervision_bookings WHERE id = ?");
            $stmt_check->execute([$booking_id]);
        } else {
            $stmt_check = $pdo->prepare("SELECT id, status FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
            $stmt_check->execute([$booking_id, $teacher_id]);
        }
        $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง หรือคุณไม่มีสิทธิ์แก้ไข']);
            exit;
        }

        if ($existing['status'] !== 'pending' && $existing['status'] !== 'approved' && !$is_admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถแก้ไขได้เนื่องจากคิวถูกดำเนินการไปแล้ว']);
            exit;
        }

        $stmt_update = $pdo->prepare("UPDATE supervision_bookings 
            SET teacher_position = ?, academic_standing = ?, evaluation_purpose = ?, 
                subject_code = ?, subject_name = ?, classroom = ?, room_number = ?, 
                lesson_topic = ?, booking_date = ?, booking_period = ?, 
                peer_teacher_id = ?, head_teacher_id = ?, updated_at = NOW() 
            WHERE id = ?");
        $stmt_update->execute([
            $teacher_position, $academic_standing, $evaluation_purpose,
            $subject_code, $subject_name, $classroom, $room_number,
            $lesson_topic, $booking_date, $booking_period,
            $peer_teacher_id, $head_teacher_id,
            $booking_id
        ]);

        $success_message = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
    } else {
        $stmt = $pdo->prepare("INSERT INTO supervision_bookings 
            (teacher_id, teacher_position, academic_standing, evaluation_purpose, 
            semester, year, subject_code, subject_name, classroom, room_number, lesson_topic, 
            booking_date, booking_period, peer_teacher_id, head_teacher_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
        
        $stmt->execute([
            $teacher_id, $teacher_position, $academic_standing, $evaluation_purpose,
            $semester, $year, $subject_code, $subject_name, $classroom, $room_number, $lesson_topic,
            $booking_date, $booking_period, $peer_teacher_id, $head_teacher_id
        ]);

        $booking_id = $pdo->lastInsertId();
        $success_message = 'จองคิวนิเทศเรียบร้อยแล้ว (สามารถอัปโหลดเอกสารได้ทันที)';
    }

    // Create notifications
    try {
        require_once '../../inc/notifications.php';
        
        $stmt_me_name = $pdo->prepare("SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = ?");
        $stmt_me_name->execute([$teacher_id]);
        $my_full_name = $stmt_me_name->fetchColumn() ?: $_SESSION['username'];

        $parts = explode('-', $booking_date);
        $thai_date = $booking_date;
        if (count($parts) === 3) {
            $y = (int)$parts[0] + 543;
            $m = (int)$parts[1];
            $d = (int)$parts[2];
            $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $thai_date = "$d {$months[$m]} $y";
        }

        $stmt_user = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
        
        // Notify peer
        $stmt_user->execute([$peer_teacher_id]);
        $peer_user_id = $stmt_user->fetchColumn();
        if ($peer_user_id) {
            $msg = "คุณได้รับการเลือกให้เป็นครูผู้ร่วมนิเทศ โดย อ. " . $my_full_name . " ในวันที่ " . $thai_date;
            cnp_notify($pdo, (int)$peer_user_id, 'คำเชิญเป็นกรรมการนิเทศ 📋', $msg, 'teacher_supervision.html', 'bi-person-badge-fill', '#3b82f6', 'supervision');
        }

        // Notify head
        $stmt_user->execute([$head_teacher_id]);
        $head_user_id = $stmt_user->fetchColumn();
        if ($head_user_id) {
            $msg = "คุณได้รับการเลือกให้เป็นครูผู้นิเทศ โดย อ. " . $my_full_name . " ในวันที่ " . $thai_date;
            cnp_notify($pdo, (int)$head_user_id, 'คำเชิญเป็นกรรมการนิเทศ 📋', $msg, 'teacher_supervision.html', 'bi-person-badge-fill', '#3b82f6', 'supervision');
        }

        // Notify evaluatee
        $stmt_user->execute([$teacher_id]);
        $evaluatee_user_id = $stmt_user->fetchColumn();

        $action_type_th = ($data['booking_id'] ?? 0) > 0 ? 'แก้ไข' : 'จอง';
        $msg_eval = "การ{$action_type_th}คิวนิเทศรายวิชา $subject_name ($subject_code) สำหรับห้อง $classroom วันที่ $thai_date คาบที่ $booking_period สำเร็จแล้ว";

        if ($evaluatee_user_id) {
            cnp_notify($pdo, (int)$evaluatee_user_id, "{$action_type_th}คิวนิเทศสำเร็จ 🎉", $msg_eval, 'teacher_supervision.html', 'bi-calendar-check-fill', '#10b981', 'supervision');
        }

        if ($is_admin) {
            cnp_notify($pdo, (int)$user_id, "ดำเนินการ{$action_type_th}คิวนิเทศสำเร็จ 🎉", "ท่านได้ทำการ{$action_type_th}คิวนิเทศให้ อ. " . $my_full_name, 'supervision.html', 'bi-check-circle-fill', '#10b981', 'supervision');
        }

        // If updating an existing booking, notify peer and head about the modification details
        if (($data['booking_id'] ?? 0) > 0) {
            $msg_update = "รายละเอียดคิวนิเทศในวันที่ $thai_date คาบที่ $booking_period ได้รับการอัปเดต";
            if ($peer_user_id) {
                cnp_notify($pdo, (int)$peer_user_id, 'อัปเดตคิวนิเทศ 📝', $msg_update, 'teacher_supervision.html', 'bi-pencil-square', '#eab308', 'supervision');
            }
            if ($head_user_id) {
                cnp_notify($pdo, (int)$head_user_id, 'อัปเดตคิวนิเทศ 📝', $msg_update, 'teacher_supervision.html', 'bi-pencil-square', '#eab308', 'supervision');
            }
        }
    } catch (Exception $e_notif) {
        // Suppress notification insert errors so booking doesn't roll back
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $booking_id,
        'message' => $success_message
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
