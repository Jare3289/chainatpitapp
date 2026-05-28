<?php
// api/teacher/get-advisory-period-attendance.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');
$classroom = $_GET['classroom'] ?? null;

try {
    // If classroom is not specified, get the teacher's advisory room
    if (!$classroom) {
        $stmt = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS room_code
                               FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id
                               WHERE t.user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $classroom = $stmt->fetchColumn() ?: null;
    }

    if (!$classroom) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบห้องเรียนที่ปรึกษาของคุณ']);
        exit;
    }

    // 1. Get all students in the classroom
    $stmt = $pdo->prepare("SELECT id, student_id AS code, prefix, first_name_th, last_name_th, number_in_class 
                           FROM students 
                           WHERE class_name = ? AND (enrollment_status IS NULL OR enrollment_status NOT IN ('พ้นสภาพ', 'ลาออก', 'สำเร็จการศึกษา')) 
                           ORDER BY CAST(number_in_class AS UNSIGNED) ASC, id ASC");
    $stmt->execute([$classroom]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get period attendance for this date in this classroom
    $stmt = $pdo->prepare("SELECT student_id, period, subject_code, status, remark 
                           FROM attendance_subjects 
                           WHERE class_name = ? AND date = ?");
    $stmt->execute([$classroom, $date]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map records by student_id and period
    $attendanceMap = [];
    foreach ($records as $r) {
        $studentId = $r['student_id'];
        $period = $r['period'];
        if (!isset($attendanceMap[$studentId])) {
            $attendanceMap[$studentId] = [];
        }
        $attendanceMap[$studentId][$period] = [
            'subject_code' => $r['subject_code'],
            'status' => $r['status'],
            'remark' => $r['remark']
        ];
    }

    // 3. Get cumulative subject absences (status = 'ขาด') for all students in this room
    $stmt = $pdo->prepare("
        SELECT student_id, COUNT(*) AS total_absents 
        FROM attendance_subjects 
        WHERE status = 'ขาด' AND student_id IN (
            SELECT id FROM students WHERE class_name = ? AND (enrollment_status IS NULL OR enrollment_status NOT IN ('พ้นสภาพ', 'ลาออก', 'สำเร็จการศึกษา'))
        )
        GROUP BY student_id
    ");
    $stmt->execute([$classroom]);
    $absentCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns [student_id => count]

    // Construct final data structure
    $resultStudents = [];
    foreach ($students as $s) {
        $sid = $s['id'];
        $s['periods'] = $attendanceMap[$sid] ?? new stdClass(); // keys: 1-9
        $s['cumulative_absents'] = (int)($absentCounts[$sid] ?? 0);
        $resultStudents[] = $s;
    }

    echo json_encode([
        'success' => true,
        'classroom' => $classroom,
        'date' => $date,
        'students' => $resultStudents
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[advisory_period_attendance] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
