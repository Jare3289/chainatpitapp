<?php
// api/teacher/at-risk.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$class_name = $_GET['class_name'] ?? '';
$limit = $_GET['limit'] ?? 3; // At-risk if absent > 3 times

if (!$class_name) {
    echo json_encode(['success' => false, 'error' => 'Missing class_name']);
    exit;
}

try {
    // Current semester dates (mock or from DB)
    // For now, count total absences in the last 30 days or academic year
    $sql = "SELECT s.id, s.student_id, s.prefix, s.first_name_th, s.last_name_th, s.room, s.number_in_class,
            COUNT(CASE WHEN a.status = 'ขาด' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'สาย' THEN 1 END) as late_count,
            COUNT(CASE WHEN a.status = 'ป่วย' OR a.status = 'ลากิจ' THEN 1 END) as leave_count
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id AND a.type = 'daily'
            WHERE s.room = ?
            GROUP BY s.id
            HAVING absent_count >= ?
            ORDER BY absent_count DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$class_name, $limit]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
