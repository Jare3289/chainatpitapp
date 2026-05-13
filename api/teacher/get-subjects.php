<?php
// api/teacher/get-subjects.php
// Returns subjects for the logged-in teacher, plus history of sessions.
//
// GET params:
//   ?room=101&recent=1  → also return recent attendance sessions for this room
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$room   = trim($_GET['room'] ?? '');

try {
    // 1. My subjects (assigned in subjects table)
    $stmt = $pdo->prepare("
        SELECT id, subject_code, subject_name, department, room, periods, academic_year, semester
        FROM subjects
        WHERE teacher_id = ?
        ORDER BY subject_code ASC
    ");
    $stmt->execute([$userId]);
    $mySubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Subjects I've used to check attendance (historical fallback)
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.subject_code,
               (SELECT subject_name FROM subjects WHERE subject_code = a.subject_code LIMIT 1) AS subject_name
        FROM attendance a
        WHERE a.recorded_by = ? AND a.subject_code IS NOT NULL AND a.subject_code <> ''
        ORDER BY a.subject_code ASC
    ");
    $stmt->execute([$userId]);
    $historicalSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build unified list — prefer entries from subjects table for full info
    $byCode = [];
    foreach ($mySubjects as $s) {
        $byCode[$s['subject_code']] = [
            'subject_code' => $s['subject_code'],
            'subject_name' => $s['subject_name'],
            'department'   => $s['department'] ?: '',
            'room'         => $s['room'] ?: '',
            'is_mine'      => true,
        ];
    }
    foreach ($historicalSubjects as $s) {
        if (!isset($byCode[$s['subject_code']])) {
            $byCode[$s['subject_code']] = [
                'subject_code' => $s['subject_code'],
                'subject_name' => $s['subject_name'] ?: $s['subject_code'],
                'department'   => '',
                'room'         => '',
                'is_mine'      => true,
            ];
        }
    }

    // 3. All subjects in system (autocomplete fallback for shared subjects)
    $stmt = $pdo->query("
        SELECT subject_code, subject_name, department
        FROM subjects
        WHERE subject_code IS NOT NULL AND subject_code <> ''
        ORDER BY subject_code ASC
        LIMIT 500
    ");
    $allSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Recent sessions (last 30) for the requested room — for history view
    $recent = [];
    if ($room !== '') {
        $stmt = $pdo->prepare("
            SELECT date, period, subject_code,
                   (SELECT subject_name FROM subjects WHERE subject_code = a.subject_code LIMIT 1) AS subject_name,
                   COUNT(*) AS student_count,
                   SUM(CASE WHEN status='มา'   THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN status='ขาด' THEN 1 ELSE 0 END) AS absent,
                   SUM(CASE WHEN status='สาย' THEN 1 ELSE 0 END) AS late,
                   SUM(CASE WHEN status IN ('ลา','ป่วย') THEN 1 ELSE 0 END) AS leave_count
            FROM attendance a
            WHERE class_name = ? AND type = 'subject'
            GROUP BY date, period, subject_code
            ORDER BY date DESC, period DESC
            LIMIT 30
        ");
        $stmt->execute([$room]);
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Current Suggestion (What is the teacher teaching right now?)
    $suggestion = null;
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $teacherRecord = $stmt->fetch();
    
    if ($teacherRecord) {
        $teacherId = $teacherRecord['id'];
        $now = new DateTime();
        $dayOfWeek = (int)$now->format('N'); // 1=Mon, 7=Sun
        $timeStr = $now->format('H:i');
        
        $currentPeriod = 0;
        $schedule = [
            ['08:30', '09:20', 1], ['09:20', '10:10', 2], ['10:10', '11:00', 3],
            ['11:00', '11:50', 4], ['11:50', '12:40', 5], ['12:40', '13:30', 6],
            ['13:30', '14:20', 7], ['14:20', '15:10', 8], ['15:10', '16:00', 9]
        ];
        foreach ($schedule as $slot) {
            if ($timeStr >= $slot[0] && $timeStr < $slot[1]) {
                $currentPeriod = $slot[2];
                break;
            }
        }
        
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5 && $currentPeriod > 0) {
            $stmt = $pdo->prepare("
                SELECT subject_code, class_name 
                FROM timetable 
                WHERE teacher_id = ? AND day_of_week = ? AND period = ? 
                LIMIT 1
            ");
            $stmt->execute([$teacherId, $dayOfWeek, $currentPeriod]);
            $found = $stmt->fetch();
            if ($found) {
                $suggestion = [
                    'subject_code' => $found['subject_code'],
                    'class_name' => $found['class_name'],
                    'period' => $currentPeriod,
                    'date' => $now->format('Y-m-d')
                ];
            }
        }
    }

    echo json_encode([
        'success'      => true,
        'my_subjects'  => array_values($byCode),
        'all_subjects' => $allSubjects,
        'recent'       => $recent,
        'suggestion'   => $suggestion
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[teacher/get-subjects] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
