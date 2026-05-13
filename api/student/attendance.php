<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

try {
    // 1. Get internal student ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    // Fallback if not linked
    if (!$student && $username) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt->execute([$username]);
        $student = $stmt->fetch();
    }

    if (!$student) {
        echo json_encode(['success' => true, 'attendance' => [], 'debug' => 'Student record not found for user_id: ' . $user_id . ' or username: ' . $username]);
        exit;
    }

    $student_internal_id = $student['id'];

    // 2. Fetch Attendance Records from both tables
    $sql = "SELECT date, class_name, status, remark, recorded_by, 'daily' as type, 
                   NULL as subject_code, 1 as period, NULL as subject_name
            FROM attendance
            WHERE student_id = ? OR student_id = ?
            
            UNION ALL
            
            SELECT a.date, a.class_name, a.status, a.remark, a.recorded_by, 'subject' as type,
                   a.subject_code, a.period, s.subject_name
            FROM attendance_subjects a
            LEFT JOIN subjects s ON a.subject_code = s.subject_code
            WHERE a.student_id = ? OR a.student_id = ?
            
            ORDER BY date DESC, period DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_internal_id, $username, $student_internal_id, $username]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all teacher names for the UI
    $teachersStmt = $pdo->query("SELECT user_id, prefix, first_name_th, last_name_th FROM teachers");
    $teachersMap = [];
    while ($t = $teachersStmt->fetch()) {
        $teachersMap[$t['user_id']] = trim($t['prefix'] . ' ' . $t['first_name_th'] . ' ' . $t['last_name_th']);
    }

    $formatted = array_map(function($r) use ($teachersMap) {
        $teacherName = $teachersMap[$r['recorded_by']] ?? 'ระบบอัตโนมัติ';
        $typeName = ($r['type'] === 'daily') ? 'กิจกรรมหน้าเสาธง / โฮมรูม' : ($r['subject_name'] ? $r['subject_name'] : $r['subject_code']);

        return [
            'date' => $r['date'],
            'class_name' => $r['class_name'] ?? '-',
            'status' => $r['status'],
            'recorded_by_name' => $teacherName ?: 'ระบบอัตโนมัติ',
            'type' => $r['type'],
            'type_name' => $typeName,
            'period' => $r['period'],
            'subject_code' => $r['subject_code']
        ];
    }, $records);

    echo json_encode(['success' => true, 'attendance' => $formatted, 'count' => count($formatted)]);

} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
