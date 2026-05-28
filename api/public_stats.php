<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $today = date('Y-m-d');
    
    // 1. Total Students
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    
    // 2. Attendance Stats (Present, Absent, etc.)
    $attendanceData = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY status");
    $attendanceData->execute([$today]);
    $attStats = ['มา' => 0, 'ขาด' => 0, 'สาย' => 0, 'ลา' => 0, 'ป่วย' => 0];
    foreach ($attendanceData->fetchAll() as $row) {
        $attStats[$row['status']] = (int)$row['count'];
    }
    
    // 3. Gender breakdown of Present students
    $genderData = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN s.prefix IN ('เด็กชาย','นาย','ด.ช.','ด.ช','master','Master','Mr.','Mr') THEN 1 ELSE 0 END) as male,
            SUM(CASE WHEN s.prefix IN ('เด็กหญิง','นางสาว','นาง','ด.ญ.','ด.ญ','น.ส.','น.ส','miss','Miss','Mrs.','Mrs','Ms.','Ms') THEN 1 ELSE 0 END) as female
        FROM students s 
        JOIN attendance a ON s.id = a.student_id 
        WHERE a.date = ? AND a.status = 'มา'
    ");
    $genderData->execute([$today]);
    $g = $genderData->fetch(PDO::FETCH_ASSOC) ?: ['male' => 0, 'female' => 0];
    $presentGender = [
        'ชาย' => (int)$g['male'],
        'หญิง' => (int)$g['female']
    ];

    // 4. Room Attendance (Top 5 or selected)
    $roomData = $pdo->prepare("
        SELECT s.class_name AS room,
               COUNT(*) as total,
               SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as present
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.class_name IS NOT NULL AND s.class_name <> ''
        GROUP BY s.class_name
        ORDER BY CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC
        LIMIT 10
    ");
    $roomData->execute([$today]);
    $roomStats = [];
    foreach ($roomData->fetchAll() as $row) {
        $percent = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100) : 0;
        $roomStats[] = [
            'room' => $row['room'],
            'percent' => $percent
        ];
    }

    // 5. Total Checked vs Total
    $checkedCount = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date = ?");
    $checkedCount->execute([$today]);
    $totalChecked = (int)$checkedCount->fetchColumn();

    echo json_encode([
        'total_students' => (int)$totalStudents,
        'total_checked' => $totalChecked,
        'attendance' => $attStats,
        'present_gender' => $presentGender,
        'room_stats' => $roomStats
    ]);

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
