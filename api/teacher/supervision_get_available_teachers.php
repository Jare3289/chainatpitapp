<?php
/**
 * api/teacher/supervision_get_available_teachers.php
 * Fetches lists of teachers for choosing Peer and Head supervisors,
 * filtering out those who have a class scheduled on the chosen date and period.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$date = trim($_GET['date'] ?? '');
$period = isset($_GET['period']) ? (int)$_GET['period'] : -1;

if (empty($date) || $period < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing date or period']);
    exit;
}

// Get day of week (1 = Monday, 7 = Sunday)
$day_of_week = date('N', strtotime($date));

try {
    // 1. Get logged-in teacher's info (especially department)
    $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $my_dept = $me ? $me['department'] : '';
    $my_sub_dept = $me ? $me['sub_department'] : '';
    $my_teacher_id = $me ? $me['id'] : 0;

    // Get busy teacher IDs for this day and period
    // The timetable table stores schedule for teachers
    $stmt_busy = $pdo->prepare("SELECT DISTINCT teacher_id FROM timetable WHERE day_of_week = ? AND period = ?");
    $stmt_busy->execute([$day_of_week, $period]);
    $busy_teacher_ids = $stmt_busy->fetchAll(PDO::FETCH_COLUMN);

    // 2. Fetch teachers in the same department (for Peer and Head selection)
    $available_peers = [];
    $available_heads = [];
    
    if (!empty($my_dept)) {
        if ($my_sub_dept === 'คอมพิวเตอร์และเทคโนโลยี') {
            $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department, department_position, position FROM teachers WHERE sub_department = ? ORDER BY first_name_th ASC");
            $stmt->execute([$my_sub_dept]);
        } else {
            if ($my_dept === 'วิทยาศาสตร์และเทคโนโลยี') {
                $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department, department_position, position FROM teachers WHERE department = ? AND (sub_department != 'คอมพิวเตอร์และเทคโนโลยี' OR sub_department IS NULL) ORDER BY first_name_th ASC");
                $stmt->execute([$my_dept]);
            } else {
                $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department, department_position, position FROM teachers WHERE department = ? ORDER BY first_name_th ASC");
                $stmt->execute([$my_dept]);
            }
        }
        $dept_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count daily bookings for head teachers
        $stmt_head_count = $pdo->prepare("SELECT head_teacher_id, COUNT(*) as count FROM supervision_bookings WHERE booking_date = ? AND status != 'cancelled' GROUP BY head_teacher_id");
        $stmt_head_count->execute([$date]);
        $head_counts = [];
        while ($row = $stmt_head_count->fetch(PDO::FETCH_ASSOC)) {
            $head_counts[$row['head_teacher_id']] = (int)$row['count'];
        }

        $allowed_positions = ['ครู', 'ครูผู้ช่วย', 'ครูอัตราจ้าง'];
        foreach ($dept_teachers as $t) {
            if ($t['id'] == $my_teacher_id) continue;
            if (!in_array($t['position'], $allowed_positions)) continue;
            
            $is_busy = in_array($t['id'], $busy_teacher_ids);
            $full_name_base = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
            
            // Build Peer object
            $peer_obj = $t;
            $peer_obj['full_name'] = $full_name_base;
            $peer_obj['is_busy'] = $is_busy;
            $peer_obj['busy_reason'] = $is_busy ? 'มีสอน' : '';
            $available_peers[] = $peer_obj;
            
            // Check if this teacher is a Head or Deputy Head
            $pos = $t['department_position'] ?? '';
            if (strpos($pos, 'หัวหน้า') !== false || strpos($pos, 'รองหัวหน้า') !== false) {
                $head_obj = $t;
                $head_obj['is_busy'] = $is_busy;
                $head_obj['busy_reason'] = $is_busy ? 'มีสอน' : '';
                
                $daily_count = $head_counts[$t['id']] ?? 0;
                $head_obj['daily_bookings'] = $daily_count;
                
                $head_obj['full_name'] = $full_name_base;
                if ($daily_count > 0) {
                    $head_obj['full_name'] .= " (ออกนิเทศแล้ว {$daily_count} คาบ)";
                }
                
                $available_heads[] = $head_obj;
            }
        }
    }

    // 4. Calculate total school bookings for this day
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM supervision_bookings WHERE booking_date = ? AND status != 'cancelled'");
    $stmt_total->execute([$date]);
    $total_school_bookings = (int)$stmt_total->fetchColumn();

    echo json_encode([
        'success' => true,
        'my_teacher_id' => $my_teacher_id,
        'my_department' => $my_dept,
        'my_sub_department' => $my_sub_dept,
        'total_school_bookings' => $total_school_bookings,
        'teachers' => $available_peers,
        'department_teachers' => $available_heads
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
