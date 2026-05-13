<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$subject_code = trim($data['subject_code'] ?? '');
$subject_name = trim($data['subject_name'] ?? '');
$department = trim($data['department'] ?? '');
$academic_year = trim($data['academic_year'] ?? '');
$semester = trim($data['semester'] ?? '');
$room_data = $data['room'] ?? '';
$room = is_array($room_data) ? implode(',', $room_data) : trim($room_data);
$periods_data = $data['periods'] ?? '';
$periods = is_array($periods_data) ? implode(',', $periods_data) : trim($periods_data);

if (!$subject_code || !$subject_name) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกรหัสวิชาและชื่อวิชา']);
    exit;
}

try {
    // Check if it already exists
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
    $stmt->execute([$subject_code]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว']);
        exit;
    }

    // Insert new subject
    $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, department, academic_year, semester, room, periods, teacher_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$subject_code, $subject_name, $department, $academic_year, $semester, $room, $periods, $user_id]);

    echo json_encode(['success' => true, 'message' => 'เพิ่มวิชาถาวรสำเร็จ']);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
