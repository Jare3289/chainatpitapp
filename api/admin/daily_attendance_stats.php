<?php
// api/admin/daily_attendance_stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

function getThaiDate($dateStr) {
    if (!$dateStr) return '';
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return $dateStr;
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp) + 543;
    return "$d " . $thai_months[$m] . " $y";
}

try {
    // Query statistics for each room on the date (joining teachers to get advisor name)
    $statsSql = "
        SELECT 
            r.classroom_code,
            r.grade_level,
            (SELECT COUNT(*) FROM students s WHERE s.class_name = r.classroom_code AND s.is_active = 1 AND (s.enrollment_status IS NULL OR s.enrollment_status = 'กำลังศึกษา')) as total_students,
            SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'ป่วย' THEN 1 ELSE 0 END) as sick_count,
            SUM(CASE WHEN a.status IN ('ลากิจ', 'ลา') THEN 1 ELSE 0 END) as leave_count,
            SUM(CASE WHEN a.status IN ('มา', 'สาย') THEN 1 ELSE 0 END) as present_count,
            COUNT(a.id) as reported_checks,
            t.first_name_th as advisor_name
        FROM rooms r
        LEFT JOIN teachers t ON t.advisory_room_id = r.id
        LEFT JOIN attendance a ON r.classroom_code = a.class_name AND a.date = ? AND a.type = 'daily'
        GROUP BY r.classroom_code
        ORDER BY CAST(r.classroom_code AS UNSIGNED) ASC, r.classroom_code ASC
    ";
    
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute([$date]);
    $statsData = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $rooms = [];
    $totals = [
        'absent' => 0, 'sick' => 0, 'leave' => 0,
        'total_students' => 0, 'present' => 0, 'not_present' => 0
    ];

    foreach ($statsData as $row) {
        $total   = (int)$row['total_students'];
        $absent  = (int)$row['absent_count'];
        $sick    = (int)$row['sick_count'];
        $leave   = (int)$row['leave_count'];
        $present = (int)$row['reported_checks'] > 0 ? (int)$row['present_count'] : 0;

        $not_present = $absent + $sick + $leave;
        $pct = $total > 0 ? ($not_present / $total) * 100 : 0;

        $rooms[] = [
            'room'                => $row['classroom_code'],
            'grade_level'         => $row['grade_level'],
            'absent'              => $absent,
            'sick'                => $sick,
            'leave'               => $leave,
            'total_students'      => $total,
            'present'             => $present,
            'not_present'         => $not_present,
            'percent_not_present' => round($pct, 2),
            'reported'            => (int)$row['reported_checks'] > 0,
        ];

        $totals['absent']         += $absent;
        $totals['sick']           += $sick;
        $totals['leave']          += $leave;
        $totals['total_students'] += $total;
        $totals['present']        += $present;
        $totals['not_present']    += $not_present;
    }

    $totals['percent_not_present'] = $totals['total_students'] > 0
        ? round(($totals['not_present'] / $totals['total_students']) * 100, 2)
        : 0;

    // ── Unreported rooms: NOT IN approach (ตรงนี้แม่นยำกว่า LEFT JOIN) ────────
    // ใช้ query เดียวกันกับ executive_overview.php เพื่อ consistency
    $unreportedStmt = $pdo->prepare("
        SELECT r.classroom_code AS room,
               r.grade_level,
               (SELECT first_name_th FROM teachers
                WHERE advisory_room_id = r.id ORDER BY id LIMIT 1) AS advisor_name
        FROM rooms r
        WHERE r.classroom_code NOT IN (
            SELECT DISTINCT class_name FROM attendance
            WHERE date = ? AND type = 'daily' AND class_name IS NOT NULL
        )
        ORDER BY r.grade_level ASC,
                 CAST(r.classroom_code AS UNSIGNED) ASC,
                 r.classroom_code ASC
    ");
    $unreportedStmt->execute([$date]);
    $unreportedRooms = $unreportedStmt->fetchAll(PDO::FETCH_ASSOC);

    $thai_date  = getThaiDate($date);
    $print_time = getThaiDate(date('Y-m-d')) . ' เวลา ' . date('H:i') . ' น.';

    echo json_encode([
        'success'         => true,
        'date'            => $date,
        'thai_date'       => $thai_date,
        'rooms'           => $rooms,
        'totals'          => $totals,
        'unreported_rooms' => $unreportedRooms,
        'print_time'      => $print_time,
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
