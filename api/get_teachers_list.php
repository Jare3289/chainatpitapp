<?php
header('Content-Type: application/json');
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch teachers list for dropdown selection - Filter for Thai names only and exclude admins
    $stmt = $pdo->query("SELECT t.id, t.user_id, t.prefix, t.first_name_th, t.last_name_th, t.department, t.classroom, t.faculty
                         FROM teachers t
                         LEFT JOIN users u ON t.user_id = u.id
                         WHERE (t.first_name_th REGEXP '[ก-ฮ]' OR t.last_name_th REGEXP '[ก-ฮ]')
                           AND (u.role != 'admin' OR u.role IS NULL)
                         ORDER BY t.first_name_th ASC");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function($t) {
        return [
            'id'           => $t['user_id'],
            'teachers_id'  => $t['id'],
            'user_id'      => $t['user_id'],
            'first_name_th' => $t['first_name_th'] ?? '',
            'last_name_th'  => $t['last_name_th'] ?? '',
            'name'         => trim(($t['prefix'] ?? '') . ' ' . $t['first_name_th'] . ' ' . $t['last_name_th']),
            'department'   => $t['department'] ?? '',
            'classroom'    => $t['classroom'] ?? '',
            'faculty'      => $t['faculty'] ?? ''
        ];
    }, $teachers);

    echo json_encode(['success' => true, 'teachers' => $formatted]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
