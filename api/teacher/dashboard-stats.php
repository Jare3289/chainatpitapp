<?php
// api/teacher/dashboard-stats.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // 1. Fetch Academic Year from system_settings
    $academic_year = '2567/1'; // Default fallback
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
        $settingsArr = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settingsArr[$row['setting_key']] = $row['setting_value'];
            }
            if (isset($settingsArr['current_academic_year'])) {
                $academic_year = $settingsArr['current_academic_year'] . '/' . ($settingsArr['current_semester'] ?? '1');
            }
        }
    } catch (Exception $e) {
        // Fallback to default if table missing
    }

    // 2. Get Teacher Info & Assigned Room
    // Try user_id first, then fallback to username/email
    $stmt = $pdo->prepare("SELECT classroom, id, first_name_th FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        $username = $_SESSION['username'] ?? '';
        $stmt = $pdo->prepare("SELECT classroom, id, first_name_th FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher && $user_id) {
            // Auto-link for future
            try {
                $pdo->prepare("UPDATE teachers SET user_id = ? WHERE id = ?")->execute([$user_id, $teacher['id']]);
            } catch(Exception $e) {}
        }
    }
    
    $room = $teacher ? trim($teacher['classroom']) : null;
    $teacher_name = $teacher ? $teacher['first_name_th'] : 'คุณครู';

    if (!$room) {
        echo json_encode([
            'success' => true,
            'room' => null,
            'teacher_name' => $teacher_name,
            'totalStudents' => 0,
            'today' => ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'sick' => 0],
            'recent' => [],
            'academic_year' => $academic_year
        ]);
        exit;
    }

    // 3. Count Total Students in advisory room
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE (room = ? OR class_name = ?)");
    $stmt->execute([$room, $room]);
    $totalStudents = $stmt->fetchColumn();

    // 4. Get Today's Attendance Stats
    // Detect column name for date in attendance table
    $dateCol = 'date';
    try {
        $pdo->query("SELECT date FROM attendance LIMIT 0");
    } catch (Exception $e) {
        $dateCol = 'attendance_date';
    }

    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN status = 'มา' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'สาย' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'ลา' OR status = 'ลากิจ' THEN 1 ELSE 0 END) as leave_val,
        SUM(CASE WHEN status = 'ป่วย' THEN 1 ELSE 0 END) as sick
        FROM attendance 
        WHERE class_name = ? AND $dateCol = ? AND type = 'daily'");
    $stmt->execute([$room, $today]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Get Recent Activities from both tables
    $stmt = $pdo->prepare("
        (SELECT date as date_val, 'daily' as type, class_name FROM attendance WHERE recorded_by = ?)
        UNION
        (SELECT date as date_val, 'subject' as type, class_name FROM attendance_subjects WHERE recorded_by = ?)
        ORDER BY date_val DESC LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $recentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $recent = [];
    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    foreach ($recentData as $row) {
        $typeStr = ($row['type'] === 'daily') ? 'ประจำวัน' : 'รายวิชา';
        $actualDate = $row['date_val'];
        $d = new DateTime($actualDate);
        $displayDate = $actualDate;
        $recent[] = [
            'date_val' => $actualDate,
            'type' => $row['type'],
            'type_label' => $typeStr,
            'display_date' => $displayDate,
            'message' => "บันทึกเช็คชื่อ{$typeStr} ห้อง {$row['class_name']}",
            'room' => $row['class_name']
        ];
    }

    echo json_encode([
        'success' => true,
        'room' => $room,
        'teacher_name' => $teacher_name,
        'totalStudents' => (int)$totalStudents,
        'today' => [
            'present' => (int)($summary['present'] ?? 0),
            'absent' => (int)($summary['absent'] ?? 0),
            'late' => (int)($summary['late'] ?? 0),
            'leave' => (int)($summary['leave_val'] ?? 0),
            'sick' => (int)($summary['sick'] ?? 0)
        ],
        'recent' => $recent,
        'academic_year' => $academic_year
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
