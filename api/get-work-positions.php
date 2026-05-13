<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $res = [
        'dept_positions' => [],
        'admin_positions' => []
    ];
    
    // Get unique department positions
    $stmt = $pdo->query("SELECT DISTINCT department_position FROM teachers WHERE department_position IS NOT NULL AND department_position != '' ORDER BY department_position ASC");
    $res['dept_positions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique admin positions
    $stmt = $pdo->query("SELECT DISTINCT admin_position FROM teachers WHERE admin_position IS NOT NULL AND admin_position != '' ORDER BY admin_position ASC");
    $res['admin_positions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($res);
} catch (Exception $e) {
    http_response_code(500);
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
