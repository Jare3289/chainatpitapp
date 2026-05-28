<?php
/**
 * api/cron/daily_reminders.php
 *
 * Run daily at 08:00 — set up via Plesk Scheduled Tasks:
 *   Command: php /var/www/vhosts/chainatpit.com/httpdocs/api/cron/daily_reminders.php
 *   Schedule: 0 8 * * *   (every day at 08:00)
 *
 * Security: only allow CLI execution OR a secret token via HTTP for manual testing.
 *   Manual test (browser): https://chainatpit.com/api/cron/daily_reminders.php?token=<CRON_TOKEN>
 *   CRON_TOKEN should be set in .env
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../inc/notifications.php';

// Access control
$isCli = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';
$expectedToken = env('CRON_TOKEN', '');

if (!$isCli) {
    if (!$expectedToken || !hash_equals($expectedToken, $token)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$today = date('Y-m-d');
$todayThai = date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . (date('y') + 543);

echo "=== Daily reminders — {$today} ===\n";

// Check if today is a weekend (6 = Saturday, 7 = Sunday) or recorded in the academic_days table as a holiday
$dayOfWeek = (int)date('N');
$isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);

try {
    $stmtHoliday = $pdo->prepare("SELECT COUNT(*) FROM academic_days WHERE date_val = ? AND day_type = 'วันหยุด'");
    $stmtHoliday->execute([$today]);
    $isHoliday = ((int)$stmtHoliday->fetchColumn() > 0);
} catch (Throwable $e) {
    $isHoliday = false;
    echo "ERR checking holidays: " . $e->getMessage() . "\n";
}

if ($isWeekend || $isHoliday) {
    $reason = $isWeekend ? "weekend" : "public holiday";
    echo "Skipping daily reminders: today is a {$reason}.\n";
    echo "=== Done ===\n";
    exit;
}

// ---------- 1. Advisory teachers: เช็คชื่อโฮมรูม ----------
try {
    $stmt = $pdo->query("
        SELECT t.user_id, COALESCE(r.classroom_code, t.classroom) AS room
        FROM teachers t
        LEFT JOIN rooms r ON r.id = t.advisory_room_id
        WHERE t.user_id IS NOT NULL
          AND (t.advisory_room_id IS NOT NULL OR (t.classroom IS NOT NULL AND TRIM(t.classroom) <> ''))");
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uid = (int) $row['user_id'];
        $room = trim((string) $row['room']);
        if ($uid <= 0 || $room === '') continue;

        $sent = cnp_notify(
            $pdo,
            $uid,
            '⏰ อย่าลืมเช็คชื่อนักเรียน',
            "วันที่ {$todayThai} — กรุณาเช็คชื่อโฮมรูมห้อง {$room}",
            'attendance_daily.html?date=' . urlencode($today) . '&class_name=' . urlencode($room),
            'bi-bell-fill',
            '#f59e0b',
            'reminder',
            'daily_attendance_' . $today
        );
        if ($sent) $count++;
    }
    echo "Sent {$count} attendance reminders to advisory teachers\n";
} catch (Throwable $e) {
    echo "ERR teachers reminder: " . $e->getMessage() . "\n";
}

// ---------- 2. Admin: daily check summary ----------
try {
    $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        cnp_notify(
            $pdo,
            (int) $adminId,
            '📊 รายงานประจำวัน',
            "วันที่ {$todayThai} — เริ่มต้นวันใหม่ ระบบกำลังเก็บข้อมูลเช็คชื่อ",
            'admin_dashboard.html',
            'bi-clipboard-data-fill',
            '#3b82f6',
            'reminder',
            'daily_admin_' . $today
        );
    }
    echo "Sent " . count($admins) . " summaries to admins\n";
} catch (Throwable $e) {
    echo "ERR admin reminder: " . $e->getMessage() . "\n";
}

// ---------- 3. Cleanup old notifications (> 60 days, already read) ----------
try {
    $del = $pdo->exec("DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 60 DAY)");
    echo "Cleaned {$del} old read notifications\n";
} catch (Throwable $e) {
    echo "ERR cleanup: " . $e->getMessage() . "\n";
}

echo "=== Done ===\n";
