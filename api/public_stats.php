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
        SELECT s.gender, COUNT(*) as count 
        FROM students s 
        JOIN attendance a ON s.id = a.student_id 
        WHERE a.date = ? AND a.status = 'มา' 
        GROUP BY s.gender
    ");
    $genderData->execute([$today]);
    $presentGender = ['ชาย' => 0, 'หญิง' => 0];
    foreach ($genderData->fetchAll() as $row) {
        $gender = $row['gender'] == 'ชาย' ? 'ชาย' : 'หญิง';
        $presentGender[$gender] += (int)$row['count'];
    }

    // 4. Room Attendance (Top 5 or selected)
    $roomData = $pdo->prepare("
        SELECT s.room, 
               COUNT(*) as total,
               SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as present
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        GROUP BY s.room
        ORDER BY s.room ASC
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
