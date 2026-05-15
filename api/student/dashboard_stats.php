<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Get internal student ID
    $stmt = $pdo->prepare("SELECT id, first_name_th, last_name_th, prefix, class_name, grade_level FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    // Fallback search if user_id is not linked
    if (!$student && isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("SELECT id, first_name_th, last_name_th, prefix, class_name, grade_level FROM students WHERE student_id = ?");
        $stmt->execute([$_SESSION['username']]);
        $student = $stmt->fetch();
    }

    if (!$student) {
        throw new Exception('ไม่พบข้อมูลนักเรียนในฐานข้อมูล');
    }

    $student_id = $student['id'];
    $fullName = trim(($student['prefix'] ?? '') . $student['first_name_th'] . ' ' . $student['last_name_th']);

    // 2. Fetch Attendance Stats — daily only, matching student_attendance_history.html daily tab
    $stmt = $pdo->prepare("SELECT
        COUNT(CASE WHEN status = 'มา' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'ขาด' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'สาย' THEN 1 END) as late,
        COUNT(CASE WHEN status IN ('ลา', 'ป่วย', 'กิจ', 'ลากิจ') THEN 1 END) as leave_count
        FROM attendance WHERE (student_id = ? OR student_id = ?) AND (type = 'daily' OR type IS NULL)");
    $stmt->execute([$student_id, $_SESSION['username']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Behavior Points (Base 100 + transactions)
    $stmt = $pdo->prepare("SELECT SUM(points) as total_points FROM point_transactions WHERE student_id = ? OR student_id = ?");
    $stmt->execute([$student_id, $_SESSION['username']]);
    $points_res = $stmt->fetch();
    $current_points = 100 + (int)($points_res['total_points'] ?? 0);

    // 4. Fetch Public Service Progress (20 times goal)
    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
    $settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = [
        'academic_year' => $settings_raw['current_academic_year'] ?? '2568',
        'semester' => $settings_raw['current_semester'] ?? '1'
    ];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as approved_count FROM public_service_records WHERE student_id = ? AND status = 'approved' AND academic_year = ? AND semester = ?");
    $stmt->execute([$student_id, $settings['academic_year'], $settings['semester']]);
    $ps_count = $stmt->fetchColumn();

    // 5. Fetch Recent Activity (Mixed attendance and points)
    $recent = [];
    
    // Get last 3 attendance
    $stmt = $pdo->prepare("SELECT 'เช็คชื่อ' as title, date, status FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 3");
    $stmt->execute([$student_id]);
    $recent_att = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($recent_att as $r) {
        $recent[] = [
            'title' => 'เช็คชื่อโฮมรูม',
            'date' => $r['date'],
            'status' => $r['status']
        ];
    }

    // Get last 2 point transactions
    $stmt = $pdo->prepare("SELECT i.item_name as title, t.created_at as date, t.points 
        FROM point_transactions t 
        JOIN point_items i ON t.item_id = i.id 
        WHERE t.student_id = ? ORDER BY t.created_at DESC LIMIT 2");
    $stmt->execute([$student_id]);
    $recent_points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($recent_points as $r) {
        $recent[] = [
            'title' => $r['title'],
            'date' => $r['date'],
            'status' => ($r['points'] >= 0 ? '+' : '') . $r['points'] . ' แต้ม'
        ];
    }

    echo json_encode([
        'success' => true,
        'student' => $student,
        'student_name' => $fullName,
        'stats' => [
            'present' => (int)$stats['present'],
            'absent' => (int)$stats['absent'],
            'late' => (int)$stats['late'],
            'leave' => (int)$stats['leave_count'],
            'points' => $current_points,
            'volunteer_count' => (int)$ps_count,
            'volunteer_target' => 20
        ],
        'settings' => $settings,
        'recent' => $recent
    ]);

} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
