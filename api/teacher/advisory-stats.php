<?php
// api/teacher/advisory-stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$room = $_GET['room'] ?? null;
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

if (!$room) {
    // If room not provided, try to find the teacher's advisory room
    $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $room = $stmt->fetchColumn();
}

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลห้องเรียนที่ปรึกษาของคุณ']);
    exit;
}

try {
    // 1. Overall Summary for Chart (Daily Attendance)
    $sqlSum = "SELECT status, COUNT(*) as count 
               FROM attendance 
               WHERE class_name = ? AND date BETWEEN ? AND ? AND type = 'daily'
               GROUP BY status";
    $stmt = $pdo->prepare($sqlSum);
    $stmt->execute([$room, $start_date, $end_date]);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Daily Trends
    $sqlTrend = "SELECT date, 
                        SUM(CASE WHEN status = 'มา' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
                        COUNT(*) as total
                 FROM attendance 
                 WHERE class_name = ? AND date BETWEEN ? AND ? AND type = 'daily'
                 GROUP BY date
                 ORDER BY date ASC";
    $stmt = $pdo->prepare($sqlTrend);
    $stmt->execute([$room, $start_date, $end_date]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Student Ranking (Absences)
    $sqlRank = "SELECT s.id, s.student_id as code, s.prefix, s.first_name_th, s.last_name_th, s.number_in_class,
                       SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) as absent_count,
                       SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) as late_count,
                       SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN a.status IN ('ลา', 'ป่วย', 'กิจ') THEN 1 ELSE 0 END) as leave_count,
                       COUNT(a.id) as total_sessions
                FROM students s
                LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN ? AND ? AND a.type = 'daily'
                WHERE s.room = ?
                GROUP BY s.id
                ORDER BY absent_count DESC, CAST(s.number_in_class AS UNSIGNED) ASC";
    $stmt = $pdo->prepare($sqlRank);
    $stmt->execute([$start_date, $end_date, $room]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'summary' => $summary,
        'trends' => $trends,
        'students' => $students,
        'period_label' => date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
