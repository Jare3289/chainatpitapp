<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT t.*, s.student_id, s.prefix, s.first_name_th, s.last_name_th, s.room, 
            i.item_name, c.category_name, u.username as recorder_name
            FROM point_transactions t
            JOIN students s ON t.student_id = s.id
            JOIN point_items i ON t.item_id = i.id
            JOIN point_categories c ON i.category_id = c.id
            JOIN users u ON t.recorded_by = u.id
            ORDER BY t.created_at DESC";
            
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
