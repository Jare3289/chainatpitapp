<?php
header('Content-Type: application/json');
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch teachers list for dropdown selection - Filter for Thai names only
    $stmt = $pdo->query("SELECT user_id, prefix, first_name_th, last_name_th FROM teachers WHERE first_name_th REGEXP '[ก-ฮ]' ORDER BY first_name_th ASC");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = array_map(function($t) {
        return [
            'id' => $t['user_id'],
            'name' => trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th'])
        ];
    }, $teachers);

    echo json_encode(['success' => true, 'teachers' => $formatted]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
