<?php
// api/teacher/reports.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$className = $_GET['class_name'] ?? '';
$academic_year = $_GET['academic_year'] ?? '2567';
$month = $_GET['month'] ?? null; // YYYY-MM format

if (!$className) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    // 1. Get Students in the room
    $stmt = $pdo->prepare("SELECT id, student_id, prefix, first_name_th, last_name_th, number_in_class FROM students WHERE class_name = ? ORDER BY CAST(number_in_class AS UNSIGNED) ASC");
    $stmt->execute([$className]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    
    // 2. Query Summary for each student
    $sql = "SELECT 
                student_id,
                SUM(CASE WHEN status = 'มา' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'สาย' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'ลา' THEN 1 ELSE 0 END) as leave_val,
                SUM(CASE WHEN status = 'ป่วย' THEN 1 ELSE 0 END) as sick,
                COUNT(*) as total_checks
            FROM attendance 
            WHERE class_name = ? AND type = 'daily'";
    
    $params = [$className];
    
    if ($month) {
        $sql .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
        $params[] = $month;
    }
    
    $sql .= " GROUP BY student_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $attendanceMap = [];
    foreach ($attendanceData as $row) {
        $attendanceMap[$row['student_id']] = $row;
    }

    foreach ($students as $s) {
        $data = $attendanceMap[$s['id']] ?? [
            'present' => 0, 'absent' => 0, 'late' => 0, 'leave_val' => 0, 'sick' => 0, 'total_checks' => 0
        ];
        
        $total = $data['total_checks'];
        $percent = $total > 0 ? (($data['present'] / $total) * 100) : 0;
        
        $results[] = [
            'number_in_class' => $s['number_in_class'],
            'student_id' => $s['student_id'],
            'full_name' => $s['prefix'] . $s['first_name_th'] . ' ' . $s['last_name_th'],
            'present' => (int)$data['present'],
            'absent' => (int)$data['absent'],
            'late' => (int)$data['late'],
            'leave' => (int)$data['leave_val'],
            'sick' => (int)$data['sick'],
            'percent' => round($percent, 1)
        ];
    }

    echo json_encode(['success' => true, 'reports' => $results]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
