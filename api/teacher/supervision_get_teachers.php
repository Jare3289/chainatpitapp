<?php
/**
 * api/teacher/supervision_get_teachers.php
 * Fetches lists of teachers for choosing Peer and Head supervisors.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_schedule.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Get logged-in teacher's info (especially department)
    $stmt = $pdo->prepare("SELECT id, department, sub_department, prefix, first_name_th, last_name_th FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        // Try mapping by username if user_id mapping is empty
        $stmt = $pdo->prepare("SELECT id, department, sub_department, prefix, first_name_th, last_name_th FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $my_dept = $me ? $me['department'] : '';
    $my_sub_dept = $me ? $me['sub_department'] : '';
    $my_teacher_id = $me ? $me['id'] : 0;
    $my_full_name = $me ? trim(($me['prefix'] ?? '') . ($me['first_name_th'] ?? '') . ' ' . ($me['last_name_th'] ?? '')) : '';

    // 2. Fetch all teachers (for Peer selection)
    $stmt = $pdo->query("SELECT id, prefix, first_name_th, last_name_th, department, sub_department FROM teachers ORDER BY first_name_th ASC");
    $all_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format display names
    foreach ($all_teachers as &$t) {
        $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
    }
    unset($t);

    // 3. Fetch teachers in the same department (for Head/Deputy selection)
    $dept_teachers = [];
    if (!empty($my_dept)) {
        // Find teachers in the same department
        // Note: Computer department teachers have sub_department = 'คอมพิวเตอร์และเทคโนโลยี'
        // Science department teachers have department = 'วิทยาศาสตร์และเทคโนโลยี'
        // If logged-in user is computer, show computer teachers. If science, science.
        if ($my_sub_dept === 'คอมพิวเตอร์และเทคโนโลยี') {
            $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department FROM teachers WHERE sub_department = ? ORDER BY first_name_th ASC");
            $stmt->execute([$my_sub_dept]);
        } else {
            // Science teachers (excl computer) or any other department
            if ($my_dept === 'วิทยาศาสตร์และเทคโนโลยี') {
                $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department FROM teachers WHERE department = ? AND (sub_department != 'คอมพิวเตอร์และเทคโนโลยี' OR sub_department IS NULL) ORDER BY first_name_th ASC");
                $stmt->execute([$my_dept]);
            } else {
                $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, department, sub_department FROM teachers WHERE department = ? ORDER BY first_name_th ASC");
                $stmt->execute([$my_dept]);
            }
        }
        $dept_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dept_teachers as &$t) {
            $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
        }
        unset($t);
    }

    // Determine user's allowed dates
    $schedule_key = '';
    if ($my_sub_dept === 'คอมพิวเตอร์และเทคโนโลยี') {
        $schedule_key = 'คอมพิวเตอร์และเทคโนโลยี';
    } else {
        $schedule_key = $my_dept;
    }

    $allowed_dates = [];
    if (isset($supervision_schedules[$schedule_key])) {
        $allowed_dates = $supervision_schedules[$schedule_key]['dates'];
    } elseif (isset($supervision_schedules['สังคมศึกษา ศาสนา และวัฒนธรรม']) && $my_dept === 'สังคมศึกษา ศาสนา และวัฒนธรรม') {
        $allowed_dates = $supervision_schedules['สังคมศึกษา ศาสนา และวัฒนธรรม']['dates'];
    }

    $allowed_dates_with_counts = [];
    foreach ($allowed_dates as $d) {
        $stmt_date_count = $pdo->prepare("SELECT COUNT(*) FROM supervision_bookings WHERE booking_date = ? AND status != 'cancelled'");
        $stmt_date_count->execute([$d]);
        $count = (int)$stmt_date_count->fetchColumn();
        $allowed_dates_with_counts[] = [
            'date' => $d,
            'count' => $count,
            'max' => 5
        ];
    }

    echo json_encode([
        'success' => true,
        'my_teacher_id' => $my_teacher_id,
        'my_full_name' => $my_full_name,
        'my_department' => $my_dept,
        'my_sub_department' => $my_sub_dept,
        'allowed_dates' => $allowed_dates,
        'allowed_dates_info' => $allowed_dates_with_counts,
        'teachers' => $all_teachers,
        'department_teachers' => $dept_teachers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
