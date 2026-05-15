<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$level = $_GET['level'] ?? 'grade'; // grade or room
$grade = $_GET['grade'] ?? null;

try {
    if ($level === 'grade') {
        // Summary by Grade Level
        $sql = "SELECT s.grade_level, 
                COUNT(s.id) as total_students,
                (100 * COUNT(s.id) + SUM(COALESCE(t.points, 0))) / COUNT(s.id) as avg_score,
                SUM(CASE WHEN (100 + COALESCE((SELECT SUM(points) FROM point_transactions WHERE student_id = s.id), 0)) < 50 THEN 1 ELSE 0 END) as at_risk_count
                FROM students s
                LEFT JOIN point_transactions t ON s.id = t.student_id
                GROUP BY s.grade_level
                ORDER BY s.grade_level ASC";
        $stmt = $pdo->query($sql);
    } else {
        // Summary by Room for a specific grade
        $sql = "SELECT s.class_name AS room, 
                COUNT(s.id) as total_students,
                (100 * COUNT(s.id) + SUM(COALESCE(t.points, 0))) / COUNT(s.id) as avg_score,
                SUM(CASE WHEN (100 + COALESCE((SELECT SUM(points) FROM point_transactions WHERE student_id = s.id), 0)) < 50 THEN 1 ELSE 0 END) as at_risk_count
                FROM students s
                LEFT JOIN point_transactions t ON s.id = t.student_id
                WHERE s.grade_level = ?
                GROUP BY s.class_name
                ORDER BY CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$grade]);
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
