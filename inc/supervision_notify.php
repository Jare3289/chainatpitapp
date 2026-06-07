<?php
/**
 * inc/supervision_notify.php
 * Helper: insert notifications for all supervision-related events.
 *
 * Usage:
 *   require_once '../inc/supervision_notify.php';
 *   supervisionNotify($pdo, [$user_id1, $user_id2], 'Title', 'Message');
 */

/**
 * Insert a notification for one or more users.
 *
 * @param PDO    $pdo      Active DB connection
 * @param array  $userIds  Array of user_id values to notify (duplicates/nulls are ignored)
 * @param string $title    Short notification title (Thai)
 * @param string $message  Detailed message (Thai)
 * @param string $link     Optional relative URL for click-through (e.g. 'supervision_booking.html')
 */
function supervisionNotify(PDO $pdo, array $userIds, string $title, string $message, string $link = ''): void
{
    $userIds = array_unique(array_filter($userIds, fn($id) => is_numeric($id) && (int)$id > 0));
    if (empty($userIds)) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read)
         VALUES (?, 'supervision', ?, ?, ?, 0)"
    );

    foreach ($userIds as $uid) {
        try {
            $stmt->execute([(int)$uid, $title, $message, $link]);
        } catch (Throwable $e) {
            // Never let notification errors break the main flow
            error_log('[supervision_notify] ' . $e->getMessage());
        }
    }
}

/**
 * Resolve user_ids for a booking's peer and head teacher.
 *
 * @param PDO $pdo
 * @param int $bookingId
 * @return array ['teacher_user_id' => int|null, 'peer_user_id' => int|null, 'head_user_id' => int|null]
 */
function supervisionBookingUserIds(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            ut.user_id   AS teacher_user_id,
            up.user_id   AS peer_user_id,
            uh.user_id   AS head_user_id
         FROM supervision_bookings sb
         JOIN teachers  t  ON t.id  = sb.teacher_id
         JOIN users     ut ON ut.id = t.user_id
         LEFT JOIN teachers tp ON tp.id = sb.peer_teacher_id
         LEFT JOIN users    up ON up.id = tp.user_id
         LEFT JOIN teachers th ON th.id = sb.head_teacher_id
         LEFT JOIN users    uh ON uh.id = th.user_id
         WHERE sb.id = ?"
    );
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: ['teacher_user_id' => null, 'peer_user_id' => null, 'head_user_id' => null];
}
