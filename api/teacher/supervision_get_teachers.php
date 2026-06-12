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
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

    $my_dept = '';
    $my_sub_dept = '';
    $my_teacher_id = 0;
    $my_full_name = '';

    if ($_SESSION['role'] === 'admin' && $booking_id > 0) {
        $stmt_bk = $pdo->prepare("SELECT b.teacher_id, t.department, t.sub_department, t.prefix, t.first_name_th, t.last_name_th 
            FROM supervision_bookings b 
            JOIN teachers t ON b.teacher_id = t.id 
            WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk_info = $stmt_bk->fetch(PDO::FETCH_ASSOC);
        if ($bk_info) {
            $my_dept = $bk_info['department'];
            $my_sub_dept = $bk_info['sub_department'];
            $my_teacher_id = (int)$bk_info['teacher_id'];
            $my_full_name = trim(($bk_info['prefix'] ?? '') . ($bk_info['first_name_th'] ?? '') . ' ' . ($bk_info['last_name_th'] ?? ''));
        }
    } else {
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
    }

    // 2. Fetch all teachers excluding admins (for reference / heads list base)
    $base_sql = "SELECT t.id, t.prefix, t.first_name_th, t.last_name_th, t.department, t.sub_department, t.department_position
                 FROM teachers t
                 LEFT JOIN users u ON u.id = t.user_id
                 WHERE (u.role IS NULL OR u.role != 'admin')
                 ORDER BY t.first_name_th ASC";
    $stmt = $pdo->query($base_sql);
    $all_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_teachers as &$t) {
        $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
    }
    unset($t);

    // 3. Fetch same-department teachers (excluding admins) for Peer and Head selection
    $dept_teachers = [];
    if (!empty($my_dept)) {
        $dept_base = "SELECT t.id, t.prefix, t.first_name_th, t.last_name_th, t.department, t.sub_department, t.department_position
                      FROM teachers t
                      LEFT JOIN users u ON u.id = t.user_id
                      WHERE (u.role IS NULL OR u.role != 'admin')
                        AND (t.position NOT LIKE '%นักศึกษาฝึกสอน%' AND t.position NOT LIKE '%ฝึกประสบการณ์%')";

        if ($my_sub_dept === 'คอมพิวเตอร์และเทคโนโลยี') {
            $stmt = $pdo->prepare($dept_base . " AND t.sub_department = ? ORDER BY t.first_name_th ASC");
            $stmt->execute([$my_sub_dept]);
        } elseif ($my_dept === 'วิทยาศาสตร์และเทคโนโลยี') {
            $stmt = $pdo->prepare($dept_base . " AND t.department = ? AND (t.sub_department != 'คอมพิวเตอร์และเทคโนโลยี' OR t.sub_department IS NULL) ORDER BY t.first_name_th ASC");
            $stmt->execute([$my_dept]);
        } else {
            $stmt = $pdo->prepare($dept_base . " AND t.department = ? ORDER BY t.first_name_th ASC");
            $stmt->execute([$my_dept]);
        }
        $dept_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dept_teachers as &$t) {
            $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
            $t['position_label'] = $t['department_position'] ?: 'กรรมการ';
        }
        unset($t);
    }

    // peers = same-dept teachers, exclude self
    $peers = array_values(array_filter($dept_teachers, fn($t) => (int)$t['id'] !== $my_teacher_id));

    // heads = same-dept teachers who have a department_position, exclude self
    $heads = array_values(array_filter($dept_teachers, function($t) use ($my_teacher_id) {
        if ((int)$t['id'] === $my_teacher_id) return false;
        return !empty($t['department_position'] ?? '');
    }));

    // Filter peers/heads by availability + check academic evaluator availability
    $date_param   = isset($_GET['date'])   ? trim($_GET['date'])       : '';
    $period_param = isset($_GET['period']) ? (int)$_GET['period']      : 0;

    $has_academic_available = true; // ถ้าไม่ได้ระบุ date/period ให้ถือว่าว่างก่อน

    if ($date_param && $period_param > 0) {
        $ts = strtotime($date_param);
        if ($ts !== false) {
            $day_of_week = (int)date('N', $ts); // 1=Monday … 7=Sunday

            // กรองเฉพาะปีการศึกษา/ภาคเรียนปัจจุบัน — ข้อมูลเทอมเก่าต้องไม่ทำให้ครูถูกนับว่าไม่ว่าง
            $settingsStmt    = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $sys             = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $currentYear     = $sys['current_academic_year'] ?? '2569';
            $currentSemester = (int) ($sys['current_semester'] ?? 1);
            $term_filter = " AND (academic_year IS NULL OR academic_year = '' OR academic_year = ?)
                             AND (semester IS NULL OR semester = 0 OR semester = ?)";

            // ครูที่มีตารางสอนในคาบนี้ (ทุกวิชา)
            $stmt_busy = $pdo->prepare(
                "SELECT DISTINCT teacher_id FROM timetable WHERE day_of_week = ? AND period = ?" . $term_filter
            );
            $stmt_busy->execute([$day_of_week, $period_param, $currentYear, $currentSemester]);
            $busy_ids = array_map('intval', array_column($stmt_busy->fetchAll(PDO::FETCH_ASSOC), 'teacher_id'));

            // ครูที่ถูกจองเป็น peer หรือ head ในคาบเดียวกัน (วันเดียวกัน + คาบเดียวกัน)
            // ยกเว้น booking ที่กำลังแก้ไขอยู่ (booking_id)
            $excl_sql = "SELECT peer_teacher_id AS id FROM supervision_bookings
                         WHERE booking_date = ? AND booking_period = ? AND status != 'cancelled'";
            $excl_params = [$date_param, $period_param];
            if ($booking_id > 0) { $excl_sql .= " AND id != ?"; $excl_params[] = $booking_id; }
            $excl_sql .= " UNION SELECT head_teacher_id FROM supervision_bookings
                         WHERE booking_date = ? AND booking_period = ? AND status != 'cancelled'";
            $excl_params = array_merge($excl_params, [$date_param, $period_param]);
            if ($booking_id > 0) { $excl_sql .= " AND id != ?"; $excl_params[] = $booking_id; }

            $stmt_committed = $pdo->prepare($excl_sql);
            $stmt_committed->execute($excl_params);
            $committed_ids = array_values(array_filter(
                array_map('intval', array_column($stmt_committed->fetchAll(PDO::FETCH_ASSOC), 'id'))
            ));

            $peers = array_values(array_filter($peers, fn($t) =>
                !in_array((int)$t['id'], $busy_ids) && !in_array((int)$t['id'], $committed_ids)));
            $heads = array_values(array_filter($heads, fn($t) =>
                !in_array((int)$t['id'], $busy_ids) && !in_array((int)$t['id'], $committed_ids)));

            // รายชื่อคณะกรรมการวิชาการ (ต้องตรงกับ supervision_booking_manage.php เสมอ)
            $allowed_academic_names = [
                'วิลาวรรณ', 'เพ็ญประภา', 'ปวีณ์นุช', 'จิตรดา',
                'สุภัค', 'ปณิตา', 'สุชาดา จ๋วงพานิช', 'อังคณา', 'สันธินี', 'สาธิต', 'สามารถ',
                'บารมี คงฤทธิ์', 'อดิศักดิ์ เอี่ยมรักษา', 'สวรส แตงโสภา', 'ธีรพงศ์ เพ็งชัย',
            ];
            $known_deputy_fullnames = ['บารมี คงฤทธิ์', 'อดิศักดิ์ เอี่ยมรักษา', 'สวรส แตงโสภา', 'ธีรพงศ์ เพ็งชัย'];

            $stmt_acad = $pdo->query("SELECT id, first_name_th, last_name_th, position FROM teachers");
            $has_academic_available = false;
            foreach ($stmt_acad->fetchAll(PDO::FETCH_ASSOC) as $at) {
                $at_id  = (int)$at['id'];
                $fname2 = trim($at['first_name_th'] ?? '');
                $full2  = trim($fname2 . ' ' . ($at['last_name_th'] ?? ''));
                $is_dep = stripos($at['position'] ?? '', 'รองผู้อำนวยการ') !== false
                       || in_array($full2, $known_deputy_fullnames);
                $in_list = $is_dep;
                if (!$in_list) {
                    foreach ($allowed_academic_names as $cn) {
                        if (str_contains($cn, ' ') ? ($full2 === $cn) : ($fname2 === $cn)) {
                            $in_list = true; break;
                        }
                    }
                }
                if (!$in_list) continue;
                // ทุกคนใช้ตรรกะเดียวกัน: มีรายการในตารางสอน (รวม ประชุม) = busy
                $is_busy = in_array($at_id, $busy_ids) || in_array($at_id, $committed_ids);
                if (!$is_busy) { $has_academic_available = true; break; }
            }
        }
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

        $stmt_bookings = $pdo->prepare("SELECT b.id, b.booking_period, t.first_name_th, t.last_name_th, t.photo, b.subject_code, b.subject_name, b.classroom, b.room_number, b.lesson_topic, b.teacher_id FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.booking_date = ? AND b.status != 'cancelled' ORDER BY b.booking_period ASC");
        $stmt_bookings->execute([$d]);
        $bookings_on_date = [];
        $names = [];
        while($row = $stmt_bookings->fetch(PDO::FETCH_ASSOC)) {
            $teacher_name = $row['first_name_th'] . ' ' . $row['last_name_th'];
            $names[] = $teacher_name;
            $bookings_on_date[] = [
                'id' => $row['id'],
                'period' => $row['booking_period'],
                'teacher_name' => $teacher_name,
                'teacher_id' => $row['teacher_id'],
                'teacher_photo' => $row['photo'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'classroom' => $row['classroom'],
                'room_number' => $row['room_number'],
                'lesson_topic' => $row['lesson_topic']
            ];
        }

        $allowed_dates_with_counts[] = [
            'date' => $d,
            'count' => $count,
            'max' => 4,
            'booked_by' => $names,
            'bookings' => $bookings_on_date
        ];
    }

    $stmt_lock = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'supervision_booking_open'");
    $lock_row = $stmt_lock->fetch(PDO::FETCH_COLUMN);
    $booking_open = ($lock_row !== false) ? ((int)$lock_row === 1) : false;

    echo json_encode([
        'success' => true,
        'my_teacher_id' => $my_teacher_id,
        'my_full_name' => $my_full_name,
        'my_department' => $my_dept,
        'my_sub_department' => $my_sub_dept,
        'allowed_dates' => $allowed_dates,
        'allowed_dates_info' => $allowed_dates_with_counts,
        'peers' => $peers,
        'heads' => $heads,
        'teachers' => $all_teachers,
        'department_teachers' => $dept_teachers,
        'has_academic_available' => $has_academic_available,
        'booking_open' => $booking_open,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
