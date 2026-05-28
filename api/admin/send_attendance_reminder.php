<?php
// api/admin/send_attendance_reminder.php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/notifications.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$date = date('Y-m-d');

try {
    // Find all rooms that haven't checked attendance today and get their advisor teachers
    $stmt = $pdo->prepare("
        SELECT t.user_id, r.classroom_code 
        FROM rooms r 
        JOIN teachers t ON t.advisory_room_id = r.id 
        WHERE t.user_id IS NOT NULL 
          AND r.classroom_code NOT IN (
              SELECT DISTINCT class_name 
              FROM attendance 
              WHERE date = ? AND type = 'daily' AND class_name IS NOT NULL
          )
    ");
    $stmt->execute([$date]);
    $unreported = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($unreported)) {
        echo json_encode(['success' => true, 'reminded_count' => 0, 'message' => 'ทุกห้องได้ทำการเช็คชื่อเรียบร้อยแล้ว']);
        exit;
    }

    $remindedRooms = [];
    foreach ($unreported as $row) {
        $title = '⚠️ ด่วนที่สุด: เตือนให้เช็คชื่อโฮมรูมประจำวัน';
        $message = "แอดมินส่งคำสั่งเตือนด่วน: กรุณาดำเนินการเช็คชื่อนักเรียนห้อง {$row['classroom_code']} โดยด่วนที่สุด";
        $link = 'attendance_daily.html?class_name=' . urlencode($row['classroom_code']);
        $icon = 'bi-exclamation-triangle-fill';
        $color = '#dc3545'; // red
        $type = 'action';
        $dedupKey = 'attendance_reminder_' . $date . '_' . $row['user_id'];
        
        cnp_notify($pdo, (int)$row['user_id'], $title, $message, $link, $icon, $color, $type, $dedupKey);
        $remindedRooms[] = $row['classroom_code'];
    }

    echo json_encode([
        'success' => true,
        'reminded_count' => count($remindedRooms),
        'reminded_rooms' => $remindedRooms,
        'message' => 'ส่งการแจ้งเตือนเตือนเช็คชื่อแล้ว ไปยัง ' . count($remindedRooms) . ' ห้องเรียน'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[send_attendance_reminder] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
