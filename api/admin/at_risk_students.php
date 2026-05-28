<?php
// api/admin/at_risk_students.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y'); // Christian Year for DB

$monthStr = sprintf("%04d-%02d", $year, $month);

try {
    $sql = "SELECT s.id, s.student_id, s.prefix, s.first_name_th, s.last_name_th, s.class_name,
            COUNT(CASE WHEN a.status = 'ขาด' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'สาย' THEN 1 END) as late_count
            FROM students s
            JOIN attendance a ON s.id = a.student_id
            WHERE a.type = 'daily' AND a.date LIKE ?
            AND (s.enrollment_status IS NULL OR s.enrollment_status = 'กำลังศึกษา')
            GROUP BY s.id
            HAVING absent_count >= 3 OR late_count >= 5
            ORDER BY absent_count DESC, late_count DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$monthStr . '%']);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
