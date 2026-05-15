<?php
// api/admin/executive_overview.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Overall Completion KPI
    $totalRooms = (int)$pdo->query("
        SELECT COUNT(DISTINCT class_name) FROM students
        WHERE class_name IS NOT NULL AND class_name <> ''
    ")->fetchColumn();

    $checkedRoomsStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT class_name) FROM attendance
        WHERE date = ? AND type = 'daily'
    ");
    $checkedRoomsStmt->execute([$date]);
    $checkedCount = (int)$checkedRoomsStmt->fetchColumn();

    // 2. Student Distribution
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status = 'มา' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN status = 'สาย' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN status IN ('ลา', 'ป่วย') THEN 1 ELSE 0 END) AS leave_count
        FROM attendance
        WHERE date = ? AND type = 'daily'
    ");
    $stmt->execute([$date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['present'=>0,'absent'=>0,'late'=>0,'leave_count'=>0];

    // 3. Grade-Level Performance
    $gradeStmt = $pdo->prepare("
        SELECT s.grade_level AS grade,
               SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(a.id), 0) AS attendance_rate
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date = ? AND a.type = 'daily'
        GROUP BY s.grade_level
        ORDER BY s.grade_level ASC
    ");
    $gradeStmt->execute([$date]);
    $gradeStats = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Critical Rooms (Top 5 highest absence today)
    $badRoomsStmt = $pdo->prepare("
        SELECT class_name AS room, COUNT(*) AS absent_count
        FROM attendance
        WHERE date = ? AND status = 'ขาด' AND type = 'daily'
        GROUP BY class_name
        ORDER BY absent_count DESC
        LIMIT 5
    ");
    $badRoomsStmt->execute([$date]);
    $criticalRooms = $badRoomsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Room Gender Summary
    $roomGenderStmt = $pdo->prepare("
        SELECT s.class_name AS room,
            SUM(CASE WHEN s.gender = 'ชาย' THEN 1 ELSE 0 END) AS total_m,
            SUM(CASE WHEN s.gender = 'หญิง' THEN 1 ELSE 0 END) AS total_f,
            SUM(CASE WHEN a.status = 'มา' AND s.gender = 'ชาย' THEN 1 ELSE 0 END) AS present_m,
            SUM(CASE WHEN a.status = 'มา' AND s.gender = 'หญิง' THEN 1 ELSE 0 END) AS present_f,
            SUM(CASE WHEN a.status IN ('ขาด','สาย','ลา','ป่วย') AND s.gender = 'ชาย' THEN 1 ELSE 0 END) AS absent_m,
            SUM(CASE WHEN a.status IN ('ขาด','สาย','ลา','ป่วย') AND s.gender = 'หญิง' THEN 1 ELSE 0 END) AS absent_f,
            COUNT(a.id) AS checked_count
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ? AND a.type = 'daily'
        WHERE s.class_name IS NOT NULL AND s.class_name <> ''
        GROUP BY s.class_name
        ORDER BY CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC
    ");
    $roomGenderStmt->execute([$date]);
    $roomGenderStats = $roomGenderStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Check-in Timeline (hourly)
    $timelineStmt = $pdo->prepare("
        SELECT HOUR(created_at) AS hour, COUNT(*) AS count
        FROM attendance
        WHERE date = ? AND type = 'daily'
        GROUP BY hour
        ORDER BY hour ASC
    ");
    $timelineStmt->execute([$date]);
    $timeline = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. At-Risk Students: ≥5 absences cumulative (not requiring absent today)
    $atRiskStmt = $pdo->prepare("
        SELECT s.first_name_th, s.last_name_th, s.class_name AS room, s.student_id,
               COUNT(a.id) AS total_absent
        FROM students s
        JOIN attendance a ON s.id = a.student_id
        WHERE a.status = 'ขาด' AND a.type = 'daily'
        GROUP BY s.id
        HAVING total_absent >= 5
        ORDER BY total_absent DESC, s.class_name ASC
        LIMIT 20
    ");
    $atRiskStmt->execute();
    $atRisk = $atRiskStmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Unreported rooms: rooms with students but no attendance today
    //    Includes grade_level + advisor first name only
    $unreportedStmt = $pdo->prepare("
        SELECT s.class_name AS room,
               MAX(s.grade_level) AS grade_level,
               COUNT(s.id) AS total_students,
               MAX(t.first_name_th) AS advisor_name
        FROM students s
        LEFT JOIN rooms r ON r.classroom_code = s.class_name
        LEFT JOIN teachers t ON t.advisory_room_id = r.id
        WHERE s.class_name IS NOT NULL AND s.class_name <> ''
          AND s.class_name NOT IN (
              SELECT DISTINCT class_name FROM attendance
              WHERE date = ? AND type = 'daily' AND class_name IS NOT NULL
          )
        GROUP BY s.class_name
        ORDER BY MAX(s.grade_level) ASC, CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC
    ");
    $unreportedStmt->execute([$date]);
    $unreportedRooms = $unreportedStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'kpis' => [
            'total_rooms'      => $totalRooms,
            'checked_rooms'    => $checkedCount,
            'unreported_rooms' => $totalRooms - $checkedCount,
            'completion_rate'  => $totalRooms > 0 ? round(($checkedCount / $totalRooms) * 100, 1) : 0,
            'attendance_summary' => [
                'present'       => (int)$summary['present'],
                'absent'        => (int)$summary['absent'],
                'late'          => (int)$summary['late'],
                'leave'         => (int)$summary['leave_count'],
                'total_checked' => (int)$summary['present'] + (int)$summary['absent'] + (int)$summary['late'] + (int)$summary['leave_count'],
            ],
        ],
        'grade_stats'       => $gradeStats,
        'critical_rooms'    => $criticalRooms,
        'room_gender_stats' => $roomGenderStats,
        'timeline'          => $timeline,
        'at_risk'           => $atRisk,
        'unreported_rooms'  => $unreportedRooms,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[executive_overview] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
