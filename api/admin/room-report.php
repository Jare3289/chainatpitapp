<?php
// api/admin/room-report.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Get all rooms from the system
    // We assume classes or students.room defines the rooms
    $roomsStmt = $pdo->query("SELECT DISTINCT room FROM students WHERE room IS NOT NULL AND room != '' ORDER BY room ASC");
    $rooms = $roomsStmt->fetchAll(PDO::FETCH_COLUMN);

    $report = [];
    foreach ($rooms as $room) {
        // Count students in room
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM students WHERE room = ?");
        $stmtTotal->execute([$room]);
        $total = $stmtTotal->fetchColumn();

        // Get attendance status for today
        $stmtAtt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE class_name = ? AND date = ? AND type = 'daily' GROUP BY status");
        $stmtAtt->execute([$room, $date]);
        $rows = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

        $stats = ['มา' => 0, 'ขาด' => 0, 'สาย' => 0, 'ลา' => 0, 'ป่วย' => 0, 'กิจกรรม' => 0];
        $checked_count = 0;
        foreach ($rows as $r) {
            $stats[$r['status']] = (int)$r['count'];
            $checked_count += (int)$r['count'];
        }

        $report[] = [
            'room' => $room,
            'total_students' => (int)$total,
            'present' => $stats['มา'] ?? 0,
            'absent' => $stats['ขาด'] ?? 0,
            'late' => $stats['สาย'] ?? 0,
            'leave' => ($stats['ลา'] ?? 0) + ($stats['ป่วย'] ?? 0),
            'checked_count' => $checked_count,
            'is_complete' => ($checked_count >= $total && $total > 0)
        ];
    }

    echo json_encode(['success' => true, 'date' => $date, 'report' => $report]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
