<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT i.id, i.item_name as behavior, i.points, c.category_name, c.is_positive
            FROM point_items i
            JOIN point_categories c ON i.category_id = c.id
            ORDER BY c.is_positive DESC, i.item_name ASC";
            
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for frontend
    $formatted = array_map(function($item) {
        return [
            'id' => $item['id'],
            'type' => $item['is_positive'] ? 'เติม' : 'ตัด',
            'behavior' => $item['behavior'],
            'score' => $item['points']
        ];
    }, $data);
    
    echo json_encode(['success' => true, 'data' => $formatted]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
