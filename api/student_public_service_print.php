<?php
// api/student_public_service_print.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();

try {
    // 1. Session verification & Auth check
    $user = cnp_require_auth(['student', 'teacher', 'admin']);
    
    // 2. Resolve target student ID
    $studentIdParam = $_GET['student_id'] ?? '';
    $studentInternalId = null;

    if ($studentIdParam !== '') {
        // Teacher or Admin requesting for specific student, or student themselves
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? OR student_id = ? LIMIT 1");
        $stmt->execute([$studentIdParam, $studentIdParam]);
        $studentInternalId = $stmt->fetchColumn();
    } else if ($user['role'] === 'student') {
        // Default to logged-in student
        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        $studentInternalId = $stmt->fetchColumn();
    }

    if (!$studentInternalId) {
        cnp_json_error(404, 'ไม่พบข้อมูลนักเรียนในระบบ');
    }

    // 3. Query Student Profile Details
    $studentStmt = $pdo->prepare("
        SELECT id, student_id, number_in_class, class_name, grade_level, house, prefix, first_name_th, last_name_th, full_name_th, first_name_en, last_name_en
        FROM students
        WHERE id = ? LIMIT 1
    ");
    $studentStmt->execute([$studentInternalId]);
    $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$studentInfo) {
        cnp_json_error(404, 'ไม่พบข้อมูลโปรไฟล์นักเรียน');
    }

    // 4. Query Advisor Teachers for the student's room
    $advisors = [];
    if (!empty($studentInfo['class_name'])) {
        $advisorStmt = $pdo->prepare("
            SELECT CONCAT(COALESCE(t.prefix,'ครู'), t.first_name_th, ' ', t.last_name_th) AS name
            FROM teachers t
            LEFT JOIN rooms r ON r.id = t.advisory_room_id
            WHERE r.classroom_code = ? OR t.classroom = ?
            ORDER BY t.id ASC
        ");
        $advisorStmt->execute([$studentInfo['class_name'], $studentInfo['class_name']]);
        $advisors = $advisorStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 5. Get current academic year and semester (falls back to config/system_settings)
    $year = $_GET['year'] ?? '';
    $semester = $_GET['semester'] ?? '';

    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
    $settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (!$year) $year = $settings_raw['current_academic_year'] ?? '2568';
    if (!$semester) $semester = $settings_raw['current_semester'] ?? '1';

    // 6. Query Approved Public Service Records chronologically
    $recordsStmt = $pdo->prepare("
        SELECT r.*,
               (SELECT CONCAT(COALESCE(t.prefix,'ครู'), t.first_name_th, ' ', t.last_name_th) 
                FROM teachers t WHERE t.user_id = r.approver_id LIMIT 1) as approver_name
        FROM public_service_records r
        WHERE r.student_id = ? AND r.status = 'approved' AND r.academic_year = ? AND r.semester = ?
        ORDER BY r.activity_date ASC, r.created_at ASC
    ");
    $recordsStmt->execute([$studentInternalId, $year, $semester]);
    $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format Thai dates into individual parts for 3-line display
    $fullMonths = [
        '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    foreach ($records as &$rec) {
        $date = new DateTime($rec['activity_date']);
        $rec['activity_day_th'] = $date->format('j');
        $rec['activity_month_th'] = $fullMonths[(int)$date->format('m')];
        $rec['activity_year_th'] = (string)($date->format('Y') + 543);
    }

    echo json_encode([
        'success' => true,
        'student' => $studentInfo,
        'advisors' => $advisors,
        'records' => $records,
        'academic_year' => $year,
        'semester' => $semester
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    cnp_json_error(500, 'ระบบขัดข้องชั่วคราว');
}
