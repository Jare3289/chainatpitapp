<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get students whose base 100 + transactions < 50
    $sql = "SELECT s.id, s.student_id, s.first_name_th, s.last_name_th, s.nickname, s.room, s.photo,
            (100 + COALESCE(SUM(t.points), 0)) as current_score
            FROM students s
            LEFT JOIN point_transactions t ON s.id = t.student_id
            GROUP BY s.id
            HAVING current_score < 50
            ORDER BY current_score ASC";
            
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
