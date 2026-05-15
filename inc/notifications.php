<?php
/**
 * inc/notifications.php — Helper สำหรับยิงการแจ้งเตือนเข้าตาราง notifications
 *
 * วิธีใช้:
 *   require_once __DIR__ . '/../inc/notifications.php';
 *   cnp_notify($pdo, $userId, 'หัวข้อ', 'ข้อความ',
 *              link: 'somewhere.html',
 *              icon: 'bi-bell-fill',
 *              color: '#3b82f6',
 *              type: 'public_service',
 *              dedupKey: 'ps_approved_42');   // กันส่งซ้ำ
 */

if (!function_exists('cnp_notify')) {
    function cnp_notify(
        PDO $pdo,
        int $userId,
        string $title,
        string $message,
        ?string $link = null,
        string $icon = 'bi-bell',
        string $color = '#3b82f6',
        string $type = 'info',
        ?string $dedupKey = null
    ): bool {
        if ($userId <= 0) return false;
        try {
            $sql = "INSERT INTO notifications
                        (user_id, type, title, message, link, icon, color, dedup_key)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        message = VALUES(message),
                        link    = VALUES(link),
                        is_read = 0,
                        created_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $type, $title, $message, $link, $icon, $color, $dedupKey]);
            return true;
        } catch (Throwable $e) {
            error_log('[cnp_notify] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cnp_notify_many')) {
    /**
     * ยิง notification เดียวกันไปยังหลาย user — ใช้ใน cron
     * @param int[] $userIds
     */
    function cnp_notify_many(
        PDO $pdo,
        array $userIds,
        string $title,
        string $message,
        ?string $link = null,
        string $icon = 'bi-bell',
        string $color = '#3b82f6',
        string $type = 'info',
        ?string $dedupKey = null
    ): int {
        $count = 0;
        foreach ($userIds as $uid) {
            if (cnp_notify($pdo, (int)$uid, $title, $message, $link, $icon, $color, $type, $dedupKey)) {
                $count++;
            }
        }
        return $count;
    }
}
