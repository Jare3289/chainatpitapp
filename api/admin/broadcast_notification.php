<?php
// api/admin/broadcast_notification.php
// ส่งแจ้งเตือนในระบบถึงครูทุกคน (admin only)
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

$user = cnp_require_auth(['admin']);
cnp_csrf_verify();

$data = json_decode(file_get_contents('php://input'), true);

$title   = trim($data['title']   ?? '');
$message = trim($data['message'] ?? '');
$link    = trim($data['link']    ?? '');
$icon    = trim($data['icon']    ?? 'bi-megaphone');
$color   = trim($data['color']   ?? '#1e3a8a');
$type    = trim($data['type']    ?? 'system');

if ($title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'title is required']);
    exit;
}

// dedup_key: ป้องกันส่งซ้ำในวันเดียวกัน (แต่ละ title+วัน)
$dedup_key = 'broadcast_' . date('Ymd') . '_' . substr(md5($title), 0, 8);

try {
    // ดึง user_id ของครูทุกคน
    $stmt = $pdo->query(
        "SELECT u.id FROM users u WHERE u.role = 'teacher' AND u.is_active = 1"
    );
    $teacher_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($teacher_user_ids)) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }

    $insert = $pdo->prepare(
        "INSERT IGNORE INTO notifications (user_id, type, title, message, link, icon, color, dedup_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $count = 0;
    foreach ($teacher_user_ids as $uid) {
        $insert->execute([(int)$uid, $type, $title, $message, $link ?: null, $icon, $color, $dedup_key]);
        $count += $insert->rowCount();
    }

    echo json_encode(['success' => true, 'count' => $count, 'total' => count($teacher_user_ids)]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
