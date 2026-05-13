<?php
// api/teacher/get-advisory-students.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Get teacher's room
    $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();

    if (!$teacher || !$teacher['classroom']) {
        echo json_encode(['success' => true, 'students' => []]);
        exit;
    }

    $room = $teacher['classroom'];

    // 2. Get students in that room
    $stmt = $pdo->prepare("SELECT id, student_id, prefix, first_name_th, last_name_th, number_in_class, photo FROM students WHERE room = ? ORDER BY CAST(number_in_class AS UNSIGNED) ASC");
    $stmt->execute([$room]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'students' => $students
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
