<?php
// api/admin/dashboard_stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // 0. Academic year/semester from system_settings & Last login
    $academic_year = '2569';
    $semester = '1';
    try {
        $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
        if ($stmtSettings) {
            $settingsArr = [];
            while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
                $settingsArr[$row['setting_key']] = $row['setting_value'];
            }
            if (isset($settingsArr['current_academic_year'])) {
                $academic_year = $settingsArr['current_academic_year'];
            }
            if (isset($settingsArr['current_semester'])) {
                $semester = $settingsArr['current_semester'];
            }
        }
    } catch (Exception $e) {}

    $login_time_formatted = '';
    $refTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : time();
    $login_time_formatted = date('j ', $refTime) . 
        ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][(int)date('n', $refTime)] . 
        ' ' . (date('Y', $refTime) + 543) . ' เวลา ' . date('H:i', $refTime) . ' น.';

    // 1. Basic stats
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE u.role != 'admin' OR u.role IS NULL")->fetchColumn();
    $totalClasses  = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $totalSubjects = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $studentTeacherRatio = $totalTeachers > 0 ? round($totalStudents / $totalTeachers, 1) : 0;

    // 2. Determine "active date" — today if any data, else most recent date with data
    $today = date('Y-m-d');
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND type='daily'");
    $stmtCheck->execute([$today]);
    $hasToday = (int)$stmtCheck->fetchColumn();

    if ($hasToday > 0) {
        $activeDate = $today;
    } else {
        $latest = $pdo->query("SELECT MAX(date) FROM attendance WHERE type='daily'")->fetchColumn();
        $activeDate = $latest ?: $today;
    }

    // 3. Attendance breakdown for active date
    $attendanceData = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? AND type='daily' GROUP BY status");
    $attendanceData->execute([$activeDate]);
    $attStats = ['มา' => 0, 'ขาด' => 0, 'สาย' => 0, 'ลา' => 0, 'ป่วย' => 0];
    foreach ($attendanceData->fetchAll() as $row) {
        $attStats[$row['status']] = (int)$row['count'];
    }

    // 4. Gender of present students — derived from prefix (คำนำหน้าชื่อ)
    //    ชาย: เด็กชาย, นาย, ด.ช.
    //    หญิง: เด็กหญิง, นางสาว, นาง, ด.ญ., น.ส.
    $genderData = $pdo->prepare("
        SELECT
            SUM(CASE WHEN s.prefix IN ('เด็กชาย','นาย','ด.ช.','ด.ช','master','Master','Mr.','Mr')
                     THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN s.prefix IN ('เด็กหญิง','นางสาว','นาง','ด.ญ.','ด.ญ','น.ส.','น.ส','miss','Miss','Mrs.','Mrs','Ms.','Ms')
                     THEN 1 ELSE 0 END) AS female
        FROM students s
        JOIN attendance a ON s.id = a.student_id
        WHERE a.date = ? AND a.status = 'มา' AND a.type='daily'
    ");
    $genderData->execute([$activeDate]);
    $g = $genderData->fetch(PDO::FETCH_ASSOC) ?: ['male' => 0, 'female' => 0];
    $presentGender = [
        'ชาย'  => (int)$g['male'],
        'หญิง' => (int)$g['female'],
    ];

    // 5. Top 10 rooms with most problem attendance (absent + leave + late combined)
    $problemRooms = $pdo->prepare("
        SELECT s.class_name AS room_code,
               SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) AS late,
               SUM(CASE WHEN a.status IN ('ลา','ป่วย') THEN 1 ELSE 0 END) AS leave_count,
               SUM(CASE WHEN a.status IN ('ขาด','สาย','ลา','ป่วย') THEN 1 ELSE 0 END) AS problem_total
        FROM students s
        JOIN attendance a ON a.student_id = s.id
        WHERE a.date = ? AND a.type = 'daily'
          AND s.class_name IS NOT NULL AND s.class_name <> ''
        GROUP BY s.class_name
        HAVING problem_total > 0
        ORDER BY problem_total DESC, absent DESC
        LIMIT 10
    ");
    $problemRooms->execute([$activeDate]);
    $roomStats = [];
    foreach ($problemRooms->fetchAll() as $row) {
        $roomStats[] = [
            'room'          => $row['room_code'],
            'absent'        => (int)$row['absent'],
            'late'          => (int)$row['late'],
            'leave'         => (int)$row['leave_count'],
            'problem_total' => (int)$row['problem_total'],
        ];
    }

    // 6. Total checked
    $checkedCount = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = ? AND type='daily'");
    $checkedCount->execute([$activeDate]);
    $totalChecked = (int)$checkedCount->fetchColumn();

    echo json_encode([
        'basic' => [
            'total_students' => $totalStudents,
            'teachers'       => $totalTeachers,
            'classes'        => $totalClasses,
            'subjects'       => $totalSubjects,
            'ratio'          => $studentTeacherRatio,
        ],
        'attendance'     => $attStats,
        'active_date'    => $activeDate,
        'is_today'       => $hasToday > 0,
        'academic_year'  => $academic_year,
        'semester'       => $semester,
        'last_login'     => $login_time_formatted,
        'analytics' => [
            'total_checked'  => $totalChecked,
            'present_gender' => $presentGender,
            'room_stats'     => $roomStats,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[admin/dashboard_stats] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
