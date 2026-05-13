<?php
// api/teacher/room-report.php
require_once '../../config.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Get all rooms that have students (via FK)
    $stmt = $pdo->query("
        SELECT DISTINCT r.classroom_code
        FROM rooms r
        JOIN students s ON s.room_id = r.id
        ORDER BY CAST(r.classroom_code AS UNSIGNED) ASC, r.classroom_code ASC
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Get attendance summary for each room for the requested date
    $report = [];
    $stmt = $pdo->prepare("
        SELECT
            COUNT(s.id) AS total,
            SUM(CASE WHEN a.status = 'มา'  THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN a.status IN ('ลา', 'ป่วย') THEN 1 ELSE 0 END) AS leave_count,
            SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) AS late,
            MAX(a.created_at) AS last_update
        FROM students s
        LEFT JOIN attendance a
            ON s.id = a.student_id
           AND a.date = ?
           AND a.type = 'daily'
        WHERE s.class_name = ?
    ");

    foreach ($classes as $className) {
        $stmt->execute([$date, $className]);
        $row = $stmt->fetch();

        $hasData = $row && (
            (int)$row['present'] + (int)$row['absent'] + (int)$row['leave_count'] + (int)$row['late'] > 0
        );

        $report[] = [
            'class_name'  => $className,
            'total'       => (int)($row['total'] ?? 0),
            'present'     => (int)($row['present'] ?? 0),
            'absent'      => (int)($row['absent'] ?? 0),
            'leave'       => (int)($row['leave_count'] ?? 0),
            'late'        => (int)($row['late'] ?? 0),
            'status'      => $hasData ? 'Checked' : 'Pending',
            'last_update' => $row['last_update'] ?? null,
        ];
    }

    echo json_encode(['success' => true, 'report' => $report], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[teacher/room-report] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
