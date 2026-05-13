<?php
// api/teacher/detailed-room-report.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$room = $_GET['room'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if (empty($room)) {
    echo json_encode(['success' => false, 'error' => 'Room is required']);
    exit;
}

// Security: If teacher, verify it's their room
if ($role === 'teacher') {
    $stmtVerify = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
    $stmtVerify->execute([$user_id]);
    $assigned_room = $stmtVerify->fetchColumn();
    
    if ($assigned_room !== $room) {
        echo json_encode(['success' => false, 'error' => 'Forbidden: You can only view your own room report']);
        exit;
    }
}

try {
    // 1. Get all students in this room
    $stmtStudents = $pdo->prepare("SELECT id, student_id, first_name_th, last_name_th, prefix, number_in_class FROM students WHERE room = ? ORDER BY CAST(number_in_class AS UNSIGNED) ASC, student_id ASC");
    $stmtStudents->execute([$room]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get all attendance records for this room and date range
    $stmtAtt = $pdo->prepare("SELECT student_id, date, status FROM attendance WHERE class_name = ? AND date BETWEEN ? AND ? AND type = 'daily'");
    $stmtAtt->execute([$room, $startDate, $endDate]);
    $attendanceRecords = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Organise attendance by student_id and date
    $attendanceMap = [];
    foreach ($attendanceRecords as $record) {
        $attendanceMap[$record['student_id']][$record['date']] = $record['status'];
    }

    // 4. Generate list of dates in range
    $dates = [];
    $current = strtotime($startDate);
    $last = strtotime($endDate);
    while ($current <= $last) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }

    echo json_encode([
        'success' => true,
        'room' => $room,
        'dates' => $dates,
        'students' => $students,
        'attendance' => $attendanceMap
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
