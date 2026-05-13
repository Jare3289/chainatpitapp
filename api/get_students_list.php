<?php
header('Content-Type: application/json');
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$search = $_GET['q'] ?? '';

try {
    $sql = "SELECT id, student_id as code, prefix, first_name_th, last_name_th, room
            FROM students
            WHERE student_id LIKE ? OR first_name_th LIKE ? OR last_name_th LIKE ?
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $q = "%$search%";
    $stmt->execute([$q, $q, $q]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function($s) {
        return [
            'id'   => $s['id'],
            'code' => $s['code'],
            'name' => trim(($s['prefix'] ?? '') . $s['first_name_th'] . ' ' . $s['last_name_th']),
            'room' => $s['room'] ?: '-',
        ];
    }, $students);

    echo json_encode(['success' => true, 'students' => $formatted]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
