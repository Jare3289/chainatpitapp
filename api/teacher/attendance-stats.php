<?php
// api/teacher/attendance-stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$subject_code = $_GET['subject_code'] ?? '';
$class_name = $_GET['class_name'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // 1. Overall Summary for Chart
    $sqlSum = "SELECT status, COUNT(*) as count 
               FROM attendance_subjects 
               WHERE date BETWEEN ? AND ?";
    $paramsSum = [$start_date, $end_date];
    
    if ($subject_code) { $sqlSum .= " AND subject_code = ?"; $paramsSum[] = $subject_code; }
    if ($class_name) { $sqlSum .= " AND class_name = ?"; $paramsSum[] = $class_name; }
    
    $sqlSum .= " GROUP BY status";
    $stmt = $pdo->prepare($sqlSum);
    $stmt->execute($paramsSum);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Student List with Summary
    $sqlRank = "SELECT s.id, s.student_id as code, s.prefix, s.first_name_th, s.last_name_th, s.number_in_class,
                       SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) as absent_count,
                       SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) as late_count,
                       SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN a.status IN ('ลา', 'ป่วย', 'กิจ') THEN 1 ELSE 0 END) as leave_count,
                       COUNT(a.id) as total_sessions
                FROM students s
                LEFT JOIN attendance_subjects a ON s.id = a.student_id
                WHERE a.date BETWEEN ? AND ?";
    $paramsRank = [$start_date, $end_date];

    if ($subject_code) { $sqlRank .= " AND a.subject_code = ?"; $paramsRank[] = $subject_code; }
    if ($class_name) { $sqlRank .= " AND a.class_name = ?"; $paramsRank[] = $class_name; }
    
    $sqlRank .= " GROUP BY s.id ORDER BY CAST(s.number_in_class AS UNSIGNED) ASC";
    $stmt = $pdo->prepare($sqlRank);
    $stmt->execute($paramsRank);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'students' => $students,
        'filters' => [
            'subject' => $subject_code,
            'class' => $class_name,
            'start' => $start_date,
            'end' => $end_date
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[attendance-stats] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการดึงข้อมูลสถิติ']);
}
