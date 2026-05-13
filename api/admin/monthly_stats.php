<?php
// api/admin/monthly_stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$room = $_GET['room'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

try {
    if (empty($room)) {
        // --- OVERVIEW MODE: Summarize all rooms for the month ---
        $roomsStmt = $pdo->query("SELECT DISTINCT room FROM students WHERE room IS NOT NULL AND room != '' ORDER BY room ASC");
        $rooms = $roomsStmt->fetchAll(PDO::FETCH_COLUMN);

        $startDate = sprintf("%04d-%02d-01", $year, $month);
        $endDate = date("Y-m-t", strtotime($startDate));

        $stmtAtt = $pdo->prepare("
            SELECT a.class_name as room, a.status, COUNT(*) as count 
            FROM attendance a
            WHERE a.date BETWEEN ? AND ? AND a.type = 'daily'
            GROUP BY a.class_name, a.status
        ");
        $stmtAtt->execute([$startDate, $endDate]);
        $allAtt = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

        $roomStats = [];
        foreach ($allAtt as $row) {
            $grouped[$row['room']][$row['status']] = (int)$row['count'];
        }

        $report = [];
        foreach ($rooms as $r) {
            $stats = $grouped[$r] ?? [];
            
            // Get total students for this room
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE room = ?");
            $stmtCount->execute([$r]);
            $totalInRoom = (int)$stmtCount->fetchColumn();

            $present = $stats['มา'] ?? 0;
            $absent = $stats['ขาด'] ?? 0;
            $late = $stats['สาย'] ?? 0;
            $personal = $stats['ลากิจ'] ?? 0;
            $sick = $stats['ป่วย'] ?? 0;

            $report[] = [
                'room' => $r,
                'total_students' => $totalInRoom,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'personal' => $personal,
                'sick' => $sick,
                'checked_count' => $present + $absent + $late + $personal + $sick
            ];
        }

        echo json_encode([
            'success' => true,
            'mode' => 'overview',
            'month' => $month,
            'year' => $year,
            'report' => $report
        ]);
        exit;
    }

    // --- DETAILED MODE: Day-by-day for a specific room ---
    // (Existing logic remains below)
    $stmtStudents = $pdo->prepare("SELECT id, gender FROM students WHERE room = ?");
    $stmtStudents->execute([$room]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    $totalStudentsInRoom = count($students);
    
    $totalMaleInRoom = 0;
    $totalFemaleInRoom = 0;
    foreach ($students as $s) {
        if ($s['gender'] == 'ชาย') $totalMaleInRoom++;
        else $totalFemaleInRoom++;
    }

    // 2. Generate days of the month
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
    $report = [];

    // 3. Get all attendance records for this room and month in one go to be efficient
    $startDate = sprintf("%04d-%02d-01", $year, $month);
    $endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);
    
    $stmtAtt = $pdo->prepare("
        SELECT a.date, s.gender, a.status, COUNT(*) as count 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.class_name = ? AND a.date BETWEEN ? AND ? AND a.type = 'daily'
        GROUP BY a.date, s.gender, a.status
    ");
    $stmtAtt->execute([$room, $startDate, $endDate]);
    $allAttendance = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    // Group by date
    $groupedAtt = [];
    foreach ($allAttendance as $row) {
        $groupedAtt[$row['date']][] = $row;
    }

    // 4. Build the day-by-day report
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $dayRows = $groupedAtt[$dateStr] ?? [];

        $dayStats = [
            'present_m' => 0, 'present_f' => 0,
            'absent_m' => 0, 'absent_f' => 0,
            'checked_count' => 0
        ];

        foreach ($dayRows as $row) {
            $gSuffix = ($row['gender'] == 'ชาย') ? '_m' : '_f';
            if ($row['status'] == 'มา') {
                $dayStats['present' . $gSuffix] += (int)$row['count'];
            } else {
                // ขาด, ลา, สาย, ป่วย -> Not "มา" (Present)
                $dayStats['absent' . $gSuffix] += (int)$row['count'];
            }
            $dayStats['checked_count'] += (int)$row['count'];
        }

        $report[] = [
            'date' => $dateStr,
            'day' => $day,
            'total_m' => $totalMaleInRoom,
            'total_f' => $totalFemaleInRoom,
            'present_m' => $dayStats['present_m'],
            'present_f' => $dayStats['present_f'],
            'absent_m' => $dayStats['absent_m'],
            'absent_f' => $dayStats['absent_f'],
            'is_complete' => ($dayStats['checked_count'] >= $totalStudentsInRoom && $totalStudentsInRoom > 0),
            'checked_count' => $dayStats['checked_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'room' => $room,
        'month' => $month,
        'year' => $year,
        'total_students' => $totalStudentsInRoom,
        'report' => $report
    ]);

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
