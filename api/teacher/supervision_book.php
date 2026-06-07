<?php
/**
 * api/teacher/supervision_book.php
 * Handles booking requests with strict date validation and daily period limits.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_schedule.php';
require_once '../../inc/supervision_notify.php';
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
    // 1. Get Evaluatee details
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
    if (!in_array($booking_date, $allowed_dates)) {
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

    // 4. Check school daily limit (max 4 bookings total across the school)
    $sql_limit = "SELECT COUNT(*) FROM supervision_bookings WHERE booking_date = ? AND status != 'cancelled'";
    $params_limit = [$booking_date];
    if ($booking_id > 0) {
        $sql_limit .= " AND id != ?";
        $params_limit[] = $booking_id;
    }
    $stmt = $pdo->prepare($sql_limit);
    $stmt->execute($params_limit);
    $daily_count = (int)$stmt->fetchColumn();

    if ($daily_count >= 4) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถจองได้ เนื่องจากคิวการนิเทศในวันที่เลือกเต็มแล้ว']);
        exit;
    }

    if ($booking_id > 0) {
        $stmt_check = $pdo->prepare("SELECT id, status FROM supervision_bookings WHERE id = ? AND teacher_id = ?");
        $stmt_check->execute([$booking_id, $teacher_id]);
        $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง หรือคุณไม่มีสิทธิ์แก้ไข']);
            exit;
        }



        $stmt_update = $pdo->prepare("UPDATE supervision_bookings 
            SET teacher_position = ?, academic_standing = ?, evaluation_purpose = ?, 
                subject_code = ?, subject_name = ?, classroom = ?, room_number = ?, 
                lesson_topic = ?, booking_date = ?, booking_period = ?, 
                peer_teacher_id = ?, head_teacher_id = ?, status = 'pending', updated_at = NOW() 
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
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->execute([
            $teacher_id, $teacher_position, $academic_standing, $evaluation_purpose,
            $semester, $year, $subject_code, $subject_name, $classroom, $room_number, $lesson_topic,
            $booking_date, $booking_period, $peer_teacher_id, $head_teacher_id
        ]);

        $booking_id = $pdo->lastInsertId();
        $success_message = 'จองคิวนิเทศเรียบร้อยแล้ว (สามารถอัปโหลดเอกสารได้ทันที)';
    }

    // Notify relevant parties
    try {
        $ids = supervisionBookingUserIds($pdo, (int)$booking_id);
        $peerUid = $ids['peer_user_id'];
        $headUid = $ids['head_user_id'];

        if ($booking_id > 0 && isset($existing)) {
            // Edit booking — notify peer + head about the change
            $editMsg = "ข้อมูลการนิเทศการสอน (จอง #{$booking_id}) ถูกแก้ไขโดยครูผู้รับการนิเทศ วันที่สอน: {$booking_date}";
            supervisionNotify($pdo, [$peerUid, $headUid], 'แก้ไขข้อมูลการนิเทศ', $editMsg, 'supervision_booking.html');
        } else {
            // New booking
            $newPeerMsg = "คุณได้รับการเลือกเป็นครูผู้ร่วมนิเทศ วิชา {$subject_name} วันที่ {$booking_date}";
            supervisionNotify($pdo, [$peerUid], 'คำเชิญเป็นกรรมการนิเทศ', $newPeerMsg, 'supervision_booking.html');
            $newHeadMsg = "คุณได้รับการเลือกเป็นครูผู้นิเทศ วิชา {$subject_name} วันที่ {$booking_date}";
            supervisionNotify($pdo, [$headUid], 'คำเชิญเป็นกรรมการนิเทศ', $newHeadMsg, 'supervision_booking.html');
        }
    } catch (Throwable $e_notif) {
        error_log('[supervision_book notify] ' . $e_notif->getMessage());
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
