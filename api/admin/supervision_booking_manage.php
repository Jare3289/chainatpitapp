<?php
/**
 * api/admin/supervision_booking_manage.php
 * Endpoint for managing supervision queue, assigning evaluators,
 * and checking teacher availability.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_notify.php';
session_start();

$is_supervision_manager = false;
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        $is_supervision_manager = true;
    } elseif ($_SESSION['role'] === 'teacher') {
        // เก็บ users.id (ตรงกับ $_SESSION['user_id']) คั่นด้วย comma ใน system_settings
        // ไม่ต้อง JOIN teachers — เช็คได้ตรงจาก session
        $stmt_mgr = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'supervision_manager_user_ids'");
        $stmt_mgr->execute();
        $mgr_val = $stmt_mgr->fetchColumn();
        $allowed_user_ids = $mgr_val
            ? array_map('intval', array_filter(array_map('trim', explode(',', $mgr_val))))
            : [];
        if (in_array((int)$_SESSION['user_id'], $allowed_user_ids)) {
            $is_supervision_manager = true;
        }
    }
}

if (!$is_supervision_manager) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if (!function_exists('thDate')) {
    function thDate(string $dateStr): string {
        $ts = strtotime($dateStr);
        if (!$ts) return $dateStr;
        $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543);
    }
}

try {
    if ($action === 'list') {
        $semester = 1;
        $year = 2569;

        // Fetch all bookings
        $stmt = $pdo->prepare("SELECT b.*,
            t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.department as t_dept, t.academic_standing as t_standing,
            tp.prefix as p_prefix, tp.first_name_th as p_first, tp.last_name_th as p_last, tp.department as p_dept,
            th.prefix as h_prefix, th.first_name_th as h_first, th.last_name_th as h_last, th.department as h_dept,
            ta.prefix as a_prefix, ta.first_name_th as a_first, ta.last_name_th as a_last, ta.department as a_dept
            FROM supervision_bookings b
            JOIN teachers t ON b.teacher_id = t.id
            LEFT JOIN teachers tp ON b.peer_teacher_id = tp.id
            LEFT JOIN teachers th ON b.head_teacher_id = th.id
            LEFT JOIN teachers ta ON b.academic_teacher_id = ta.id
            WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'
            ORDER BY b.booking_date DESC, b.booking_period ASC");
        $stmt->execute([$semester, $year]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format names and classroom info
        foreach ($bookings as &$b) {
            // ชื่อผู้รับประเมิน: ชื่อแรกเท่านั้น (first name only)
            $b['teacher_name'] = trim($b['t_first'] ?? '');
            $b['peer_name'] = $b['peer_teacher_id'] ? trim($b['p_first'] ?? '') : '-';
            $b['head_name'] = $b['head_teacher_id'] ? trim($b['h_first'] ?? '') : '-';
            $b['academic_name'] = $b['academic_teacher_id'] ? trim($b['a_first'] ?? '') : '-';

            // Parse classroom code: "205" → grade=2, class=05
            $classroom = trim($b['classroom'] ?? '');
            if (preg_match('/^([1-6])(\d{2})$/', $classroom, $m)) {
                $b['grade_level'] = 'ม.' . $m[1];
                $b['class_name'] = (int)$m[2];
            } else {
                $b['grade_level'] = $classroom ?: 'ไม่ระบุ';
                $b['class_name'] = '';
            }
            $b['room_location'] = trim($b['room_number'] ?? '');
        }
        unset($b);

        echo json_encode([
            'success' => true,
            'bookings' => $bookings
        ]);

    } elseif ($action === 'get_available_evaluators') {
        $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
        $date = trim($_GET['date'] ?? '');
        $period = isset($_GET['period']) ? (int)$_GET['period'] : -1;

        if ($booking_id > 0) {
            $stmt_bk = $pdo->prepare("SELECT booking_date, booking_period, teacher_id FROM supervision_bookings WHERE id = ?");
            $stmt_bk->execute([$booking_id]);
            $bk = $stmt_bk->fetch();
            if ($bk) {
                $date = $bk['booking_date'];
                $period = (int)$bk['booking_period'];
                $evaluatee_teacher_id = (int)$bk['teacher_id'];
            }
        }

        if (empty($date) || $period < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'พารามิเตอร์ไม่ครบถ้วน']);
            exit;
        }

        // Get day of week (1 = Monday, 7 = Sunday)
        $day_of_week = date('N', strtotime($date));

        // ── ปีการศึกษา/ภาคเรียนปัจจุบัน — ต้องกรองทุก query ที่อ่าน timetable
        //    มิฉะนั้นข้อมูลเทอมเก่า/ปีเก่าจะทำให้ครูถูกนับว่า "ไม่ว่าง" ทั้งที่เทอมนี้ว่างจริง
        $settingsStmt    = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $sys             = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $currentYear     = $sys['current_academic_year'] ?? '2569';
        $currentSemester = (int) ($sys['current_semester'] ?? 1);
        $term_filter = " AND (academic_year IS NULL OR academic_year = '' OR academic_year = ?)
                         AND (semester IS NULL OR semester = 0 OR semester = ?)";

        // ไม่มีแนวคิด "คาบประชุมทั้งโรงเรียน" อีกต่อไป
        // แต่ละคนตรวจเอง: ถ้ามี ประชุม ในตารางสอนคาบนั้น → busy / ถ้าไม่มีตาราง → ว่าง
        $isMeetingPeriod = false;

        // 1. Fetch busy teachers from timetable
        $stmt_busy = $pdo->prepare("SELECT DISTINCT teacher_id FROM timetable WHERE day_of_week = ? AND period = ?" . $term_filter);
        $stmt_busy->execute([$day_of_week, $period, $currentYear, $currentSemester]);
        $busy_timetable_teacher_ids = array_map('intval', $stmt_busy->fetchAll(PDO::FETCH_COLUMN));

        // รองผู้อำนวยการ: ว่างเสมอ ยกเว้นคาบที่มีรายการ "ประชุม" ในตารางสอน
        $stmt_mtg = $pdo->prepare("SELECT DISTINCT teacher_id FROM timetable WHERE day_of_week = ? AND period = ? AND subject_name LIKE '%ประชุม%'" . $term_filter);
        $stmt_mtg->execute([$day_of_week, $period, $currentYear, $currentSemester]);
        $meeting_busy_teacher_ids = array_map('intval', $stmt_mtg->fetchAll(PDO::FETCH_COLUMN));

        // 2. Fetch busy teachers from active supervision bookings
        // (If they are evaluatee, peer, head, or academic evaluator in another active booking on this date and period)
        $stmt_conflicts = $pdo->prepare("
            SELECT b.id, b.teacher_id, b.peer_teacher_id, b.head_teacher_id, b.academic_teacher_id,
            t.prefix, t.first_name_th, t.last_name_th
            FROM supervision_bookings b
            JOIN teachers t ON b.teacher_id = t.id
            WHERE b.booking_date = ? AND b.booking_period = ? AND b.status != 'cancelled' AND b.id != ?
        ");
        $stmt_conflicts->execute([$date, $period, $booking_id]);
        $conflicts = $stmt_conflicts->fetchAll(PDO::FETCH_ASSOC);

        $conflict_map = [];
        foreach ($conflicts as $c) {
            $tname = trim(($c['prefix'] ?? '') . $c['first_name_th'] . ' ' . $c['last_name_th']);
            if ($c['teacher_id']) {
                $conflict_map[$c['teacher_id']] = "เป็นผู้รับประเมิน (สอนวิชาของ อ. {$tname})";
            }
            if ($c['peer_teacher_id']) {
                $conflict_map[$c['peer_teacher_id']] = "เป็นครูผู้ร่วมนิเทศของ อ. {$tname}";
            }
            if ($c['head_teacher_id']) {
                $conflict_map[$c['head_teacher_id']] = "เป็นผู้นิเทศของ อ. {$tname}";
            }
            if ($c['academic_teacher_id']) {
                $conflict_map[$c['academic_teacher_id']] = "เป็นคณะกรรมการวิชาการของ อ. {$tname}";
            }
        }

        // 3. Fetch all teachers in school (for peer/head evaluator selection)
        $stmt_teachers = $pdo->query("SELECT id, prefix, first_name_th, last_name_th, department, sub_department, department_position, position, academic_standing, nationality FROM teachers ORDER BY first_name_th ASC");
        $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

        $all_teachers = [];
        $academic_teachers = []; // กรรมการคนลำดับที่ 3: คณะกรรมการวิชาการ (15 คน: 11 ครู + 4 รองผู้อำนวยการ)

        // รายชื่อคณะกรรมการวิชาการที่อนุญาต: ครูผู้รับผิดชอบ + รองผู้อำนวยการทั้ง 4 ท่าน (ระบุชื่อตรงๆ กันแน่นอน)
        $allowed_academic_names = [
            'วิลาวรรณ', 'เพ็ญประภา', 'ปวีณ์นุช', 'จิตรดา',
            'สุภัค', 'ปณิตา', 'สุชาดา จ๋วงพานิช', 'อังคณา', 'สันธินี', 'สาธิต', 'สามารถ',
            // รองผู้อำนวยการ 4 ท่าน (ระบุชื่อ+นามสกุลเพื่อความแม่นยำ)
            'บารมี คงฤทธิ์', 'อดิศักดิ์ เอี่ยมรักษา', 'สวรส แตงโสภา', 'ธีรพงศ์ เพ็งชัย',
        ];

        // ชื่อ+นามสกุลของรองผู้อำนวยการ (ใช้ตรวจสอบ is_deputy แม้ position ในฐานข้อมูลไม่ตรง)
        $known_deputy_fullnames = ['บารมี คงฤทธิ์', 'อดิศักดิ์ เอี่ยมรักษา', 'สวรส แตงโสภา', 'ธีรพงศ์ เพ็งชัย'];

        // normalize: ตัด space/อักขระล่องหนทุกชนิด + ตัดคำนำหน้าที่อาจติดมาในช่องชื่อ
        $cnp_norm = function (string $s): string {
            $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}\s]+/u', '', $s);
            return preg_replace('/^(นางสาว|นาง|นาย|ว่าที่ร้อยตรีหญิง|ว่าที่ร้อยตรี|ดร\.?)/u', '', $s);
        };

        $matched_allowed = [];   // checkName => [ชื่อครูที่ match] (ใช้ debug)

        foreach ($teachers as $t) {
            $t_id = (int)$t['id'];
            $t['full_name'] = trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);

            // คำนวณชื่อก่อนเพื่อใช้ตรวจสอบ deputy
            $fname        = trim($t['first_name_th'] ?? '');
            $lname        = trim($t['last_name_th']  ?? '');
            $fullNoPrefix = trim($fname . ' ' . $lname);

            // รองผู้อำนวยการ: ตรวจจาก position หรือจากชื่อที่รู้จักโดยตรง
            $is_deputy = stripos($t['position'] ?? '', 'รองผู้อำนวยการ') !== false
                      || in_array($fullNoPrefix, $known_deputy_fullnames);

            // ทุกคนใช้ตรรกะเดียวกัน: ถ้ามีรายการใดก็ตามในตารางสอน (รวม ประชุม) → busy
            $is_busy_timetable = in_array($t_id, $busy_timetable_teacher_ids);
            $is_busy_booking   = isset($conflict_map[$t_id]);

            $t['is_busy'] = ($is_busy_timetable || $is_busy_booking);
            $t['is_deputy'] = $is_deputy;

            $reasons = [];
            if ($is_busy_timetable) {
                $reasons[] = in_array($t_id, $meeting_busy_teacher_ids) ? "มีประชุมในคาบนี้" : "มีสอนตามตารางสอนในคาบนี้";
            }
            if ($is_busy_booking) {
                $reasons[] = $conflict_map[$t_id];
            }

            $t['busy_reason'] = implode(' และ ', $reasons);

            $all_teachers[] = $t;

            // --- กรองสำหรับคณะกรรมการวิชาการ: เฉพาะคนในรายชื่อที่กำหนด + ว่าง ---
            $is_in_allowed_list = $is_deputy; // รองผู้อำนวยการเข้าลิสต์ทันที

            $fnameNorm = $cnp_norm($fname);
            $fullNorm  = $cnp_norm($fullNoPrefix);
            foreach ($allowed_academic_names as $checkName) {
                if ($checkName === '') continue;
                $checkNorm = $cnp_norm($checkName);
                $hit = str_contains($checkName, ' ')
                    ? ($fullNorm === $checkNorm)
                    : ($fnameNorm === $checkNorm);
                if ($hit) {
                    $is_in_allowed_list = true;
                    $matched_allowed[$checkName][] = $t['full_name'];
                }
            }

            // ต้องว่างในคาบนั้น
            $is_available = !$t['is_busy'];
            // ต้องไม่เป็นครูที่รับประเมินเอง
            $is_evaluatee = isset($evaluatee_teacher_id) && ($t_id === (int)$evaluatee_teacher_id);

            // Add to academic_teachers ถ้า: ในรายชื่อ + ไม่ใช่ผู้รับประเมิน (รวมคนไม่ว่าง ให้ JS แสดง grey-out)
            if ($is_in_allowed_list && !$is_evaluatee) {
                $academic_teachers[] = $t;
            }
        }

        $response = [
            'success' => true,
            'teachers' => $all_teachers,
            'academic_teachers' => $academic_teachers,
            'is_meeting_period' => $isMeetingPeriod
        ];

        // ── โหมดวินิจฉัย: เปิดด้วย &debug=1 เพื่อดูว่าใครเข้า/ไม่เข้าลิสต์ เพราะอะไร ──
        if (!empty($_GET['debug'])) {
            $unmatched = array_values(array_filter($allowed_academic_names, fn($n) => $n !== '' && empty($matched_allowed[$n])));
            $response['debug'] = [
                'date'                 => $date,
                'period'               => $period,
                'day_of_week'          => $day_of_week,
                'current_year'         => $currentYear,
                'current_semester'     => $currentSemester,
                'is_meeting_period'    => $isMeetingPeriod,
                'busy_from_timetable'  => count($busy_timetable_teacher_ids),
                'conflict_map'         => $conflict_map,
                'allowed_matched'      => $matched_allowed,
                'allowed_NOT_matched'  => $unmatched,
                'academic_detail'      => array_map(fn($a) => [
                    'id'          => $a['id'],
                    'name'        => $a['full_name'],
                    'is_busy'     => $a['is_busy'],
                    'busy_reason' => $a['busy_reason'],
                    'is_deputy'   => $a['is_deputy'],
                ], $academic_teachers),
            ];
        }

        echo json_encode($response);

    } elseif ($action === 'sync_subjects') {
        // ── ซิงก์รหัสวิชา/ชื่อวิชา/ห้องของคิวที่จองไว้แล้ว ให้ตรงกับตารางสอนจริงของครู ──
        $settingsStmt    = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $sys             = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $currentYear     = $sys['current_academic_year'] ?? '2569';
        $currentSemester = (int) ($sys['current_semester'] ?? 1);

        $stmt_bks = $pdo->query("SELECT id, teacher_id, booking_date, booking_period, subject_code, subject_name, classroom, room_number
                                 FROM supervision_bookings WHERE status != 'cancelled'");
        $bookings = $stmt_bks->fetchAll(PDO::FETCH_ASSOC);

        $stmt_tt = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(t.subject_code, ''), MAX(sc.subject_code), MAX(sn.subject_code), t.subject_name) AS subject_code,
                COALESCE(MAX(sc.subject_name), MAX(sn.subject_name), t.subject_name) AS subject_name,
                t.class_name, t.room_location
            FROM timetable t
            LEFT JOIN subjects sc ON sc.subject_code = t.subject_name
            LEFT JOIN subjects sn ON sn.subject_name = t.subject_name AND sc.id IS NULL
            WHERE t.teacher_id = ? AND t.day_of_week = ? AND t.period = ?
              AND (t.academic_year IS NULL OR t.academic_year = '' OR t.academic_year = ?)
              AND (t.semester IS NULL OR t.semester = 0 OR t.semester = ?)
            GROUP BY t.id, t.class_name, t.room_location
            ORDER BY (t.subject_code IS NULL OR t.subject_code = '') ASC, t.id ASC
            LIMIT 1
        ");
        $stmt_upd = $pdo->prepare("UPDATE supervision_bookings
            SET subject_code = ?, subject_name = ?, classroom = ?, room_number = ?, updated_at = NOW()
            WHERE id = ?");

        $updated = 0; $skipped = 0; $changes = [];
        foreach ($bookings as $b) {
            $dow = (int)date('N', strtotime($b['booking_date']));
            $stmt_tt->execute([(int)$b['teacher_id'], $dow, (int)$b['booking_period'], $currentYear, $currentSemester]);
            $tt = $stmt_tt->fetch(PDO::FETCH_ASSOC);
            if (!$tt) { $skipped++; continue; }

            $new_code = trim($tt['subject_code'] ?? '') ?: $b['subject_code'];
            $new_name = trim($tt['subject_name'] ?? '') ?: $b['subject_name'];
            $new_room = trim($tt['class_name'] ?? '')    ?: $b['classroom'];
            $new_loc  = trim($tt['room_location'] ?? '') ?: $b['room_number'];

            if ($new_code !== $b['subject_code'] || $new_name !== $b['subject_name']
                || $new_room !== $b['classroom'] || $new_loc !== $b['room_number']) {
                $stmt_upd->execute([$new_code, $new_name, $new_room, $new_loc, $b['id']]);
                $updated++;
                $changes[] = [
                    'booking_id' => (int)$b['id'],
                    'old' => ['code' => $b['subject_code'], 'name' => $b['subject_name']],
                    'new' => ['code' => $new_code, 'name' => $new_name],
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "ซิงก์เรียบร้อย: อัปเดต {$updated} คิว, ไม่พบตารางสอน {$skipped} คิว",
            'updated' => $updated,
            'no_timetable' => $skipped,
            'changes' => $changes
        ]);

    } elseif ($action === 'assign') {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
        $peer_teacher_id = isset($data['peer_teacher_id']) ? (int)$data['peer_teacher_id'] : 0;
        $head_teacher_id = isset($data['head_teacher_id']) ? (int)$data['head_teacher_id'] : 0;
        $academic_teacher_id = isset($data['academic_teacher_id']) ? (int)$data['academic_teacher_id'] : 0;

        if ($booking_id <= 0 || $peer_teacher_id <= 0 || $head_teacher_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'กรุณาระบุข้อมูลการจอง เพื่อนครูร่วมประเมิน และหัวหน้ากลุ่มสาระฯ ให้ครบถ้วน']);
            exit;
        }

        // Get current booking details and verify evaluatee is not assigned as evaluator
        $stmt_bk = $pdo->prepare("SELECT b.*, t.prefix, t.first_name_th, t.last_name_th FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk = $stmt_bk->fetch(PDO::FETCH_ASSOC);

        if (!$bk) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
            exit;
        }

        $evaluatee_teacher_id = (int)$bk['teacher_id'];
        $evaluatee_name = trim(($bk['prefix'] ?? '') . $bk['first_name_th'] . ' ' . $bk['last_name_th']);

        if ($peer_teacher_id === $evaluatee_teacher_id || $head_teacher_id === $evaluatee_teacher_id || $academic_teacher_id === $evaluatee_teacher_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ครูผู้รับประเมินไม่สามารถรับหน้าที่เป็นกรรมการนิเทศคิวของตนเองได้']);
            exit;
        }

        // ตรวจ academic ว่าถูก commit เป็น peer/head/academic ในคิวอื่นคาบเดียวกันหรือไม่
        if ($academic_teacher_id > 0) {
            $stmt_ac = $pdo->prepare("
                SELECT id FROM supervision_bookings
                WHERE booking_date = ? AND booking_period = ? AND status != 'cancelled' AND id != ?
                  AND (peer_teacher_id = ? OR head_teacher_id = ? OR academic_teacher_id = ?)
            ");
            $stmt_ac->execute([
                $bk['booking_date'], $bk['booking_period'], $booking_id,
                $academic_teacher_id, $academic_teacher_id, $academic_teacher_id
            ]);
            if ($stmt_ac->fetch()) {
                $stmt_an = $pdo->prepare("SELECT CONCAT(COALESCE(prefix,''), first_name_th, ' ', last_name_th) FROM teachers WHERE id = ?");
                $stmt_an->execute([$academic_teacher_id]);
                $acad_name = $stmt_an->fetchColumn() ?: 'คณะกรรมการวิชาการที่เลือก';
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "{$acad_name} ถูกมอบหมายในคาบนี้แล้ว — กรรมการออกนิเทศได้แค่ตำแหน่งเดียวต่อคาบ"]);
                exit;
            }
        }

        // Fetch old assignments to check who has been updated/added
        $old_peer = (int)$bk['peer_teacher_id'];
        $old_head = (int)$bk['head_teacher_id'];
        $old_academic = (int)$bk['academic_teacher_id'];

        $new_status = ($bk['status'] === 'pending') ? 'approved' : $bk['status'];

        $stmt_update = $pdo->prepare("
            UPDATE supervision_bookings 
            SET peer_teacher_id = ?, head_teacher_id = ?, academic_teacher_id = ?, status = ? 
            WHERE id = ?
        ");
        $stmt_update->execute([$peer_teacher_id, $head_teacher_id, ($academic_teacher_id > 0 ? $academic_teacher_id : null), $new_status, $booking_id]);

        // Create notification list
        // Fetch user IDs
        $teachers_to_notify = []; // [user_id => message]
        
        $msg_date = thDate($bk['booking_date']);
        $msg_period = $bk['booking_period'];

        // Helper function to get user_id of teacher
        $getUser = function($pdo, $teacher_id) {
            if (!$teacher_id) return null;
            $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            return $stmt->fetchColumn();
        };

        // Notify Peer if assigned or changed
        if ($peer_teacher_id !== $old_peer) {
            $uid = $getUser($pdo, $peer_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'กรรมการร่วมประเมิน (ครูคู่หู)' ของ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Notify Head if assigned or changed
        if ($head_teacher_id !== $old_head) {
            $uid = $getUser($pdo, $head_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'กรรมการประเมิน (หัวหน้า/รองกลุ่มสาระฯ)' ของ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Notify Academic if assigned or changed
        if ($academic_teacher_id > 0 && $academic_teacher_id !== $old_academic) {
            $uid = $getUser($pdo, $academic_teacher_id);
            if ($uid) {
                $teachers_to_notify[$uid] = "ฝ่ายวิชาการได้มอบหมายให้คุณเป็น 'คณะกรรมการวิชาการ' ประเมินนิเทศให้กับ อ. {$evaluatee_name} ในวันที่ {$msg_date} คาบ {$msg_period}";
            }
        }

        // Send notifications
        foreach ($teachers_to_notify as $uid => $msg) {
            supervisionNotify($pdo, [$uid], 'งานมอบหมายเป็นกรรมการนิเทศ', $msg, 'supervision_evaluate.html');
        }

        // Also notify the evaluatee if status changed from pending to approved
        if ($bk['status'] === 'pending' && $new_status === 'approved') {
            $evaluatee_uid = $getUser($pdo, $evaluatee_teacher_id);
            if ($evaluatee_uid) {
                supervisionNotify($pdo, [$evaluatee_uid], 'คำร้องจองคิวนิเทศได้รับการอนุมัติ', "คำร้องการจองคิวนิเทศของคุณในวันที่ {$msg_date} ได้รับอนุมัติและตั้งกรรมการเรียบร้อยแล้ว", 'supervision_booking.html');
            }
        }

        echo json_encode(['success' => true, 'message' => 'บันทึกการมอบหมายกรรมการและอัปเดตสถานะคิวนิเทศเรียบร้อยแล้ว']);

    } elseif ($action === 'cancel') {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;

        if ($booking_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
            exit;
        }

        // Get details before cancelling to notify
        $stmt_bk = $pdo->prepare("SELECT b.*, t.prefix, t.first_name_th, t.last_name_th FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk = $stmt_bk->fetch(PDO::FETCH_ASSOC);

        if (!$bk) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE supervision_bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);

        if ($stmt->rowCount() > 0) {
            // Send cancellation notifications
            $ids = supervisionBookingUserIds($pdo, $booking_id);
            $msg_date = thDate($bk['booking_date']);
            $msg_period = $bk['booking_period'];
            $teacher_name = trim(($bk['prefix'] ?? '') . $bk['first_name_th'] . ' ' . $bk['last_name_th']);

            $notif_msg = "ฝ่ายวิชาการได้ยกเลิกคิวนิเทศการสอนของ อ. {$teacher_name} ในวันที่ {$msg_date} คาบ {$msg_period} เรียบร้อยแล้ว";
            
            $uids = array_filter([$ids['teacher_user_id'], $ids['peer_user_id'], $ids['head_user_id']]);
            // Add academic evaluator user id
            if ($bk['academic_teacher_id']) {
                $stmt_acad = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                $stmt_acad->execute([$bk['academic_teacher_id']]);
                $acad_uid = $stmt_acad->fetchColumn();
                if ($acad_uid) {
                    $uids[] = $acad_uid;
                }
            }

            supervisionNotify($pdo, $uids, 'ยกเลิกคิวนิเทศการสอน', $notif_msg, 'supervision_booking.html');

            echo json_encode(['success' => true, 'message' => 'ยกเลิกรายการคิวนิเทศการสอนเรียบร้อยแล้ว']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถยกเลิกรายการนี้ได้']);
        }

    } elseif ($action === 'notify_no_academic') {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id  = (int)($data['booking_id'] ?? 0);
        $action_type = trim($data['action_type'] ?? ''); // 'change_slot', 'special_condition', 'video_supervision'

        if ($booking_id <= 0 || !in_array($action_type, ['change_slot', 'special_condition', 'video_supervision'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'พารามิเตอร์ไม่ถูกต้อง']);
            exit;
        }

        $stmt_bk = $pdo->prepare("SELECT b.*, t.user_id as t_user_id, CONCAT(t.prefix, t.first_name_th, ' ', t.last_name_th) as t_name FROM supervision_bookings b JOIN teachers t ON b.teacher_id = t.id WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk = $stmt_bk->fetch(PDO::FETCH_ASSOC);

        if (!$bk) {
            echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจอง']);
            exit;
        }

        $msg_date = thDate($bk['booking_date']);

        if ($action_type === 'change_slot') {
            supervisionNotify($pdo, [$bk['t_user_id']],
                '📅 กรุณาเปลี่ยนวัน/คาบนิเทศ',
                "ฝ่ายวิชาการส่งคิวคืน เนื่องจากไม่มีคณะกรรมการวิชาการว่างในคิวที่จองไว้ ({$msg_date} คาบ {$bk['booking_period']}) กรุณาเข้าระบบเพื่อยกเลิกและเลือกวัน/คาบใหม่",
                'supervision_booking.html'
            );
            echo json_encode(['success' => true, 'message' => 'ส่งคิวคืนและแจ้งเตือนครูแล้ว']);
        } elseif ($action_type === 'video_supervision') {
            supervisionNotify($pdo, [$bk['t_user_id']],
                '🎥 คิวนิเทศ: นิเทศแบบพิเศษ (บันทึกวิดีโอ)',
                "ฝ่ายวิชาการอนุมัติคิวนิเทศของท่าน ({$msg_date} คาบ {$bk['booking_period']}) แบบ<strong>นิเทศพิเศษ</strong> เนื่องจากไม่มีคณะกรรมการวิชาการว่าง — กรุณา<strong>บันทึกวิดีโอการสอน</strong>แทนการนิเทศในชั้นเรียน",
                'teacher_supervision.html'
            );
            echo json_encode(['success' => true, 'message' => 'แจ้งครูนิเทศแบบพิเศษ (บันทึกวิดีโอ) เรียบร้อยแล้ว']);
        } else {
            supervisionNotify($pdo, [$bk['t_user_id']],
                '✅ คิวนิเทศ: ใช้เงื่อนไขพิเศษ',
                "ฝ่ายวิชาการอนุมัติคิวนิเทศของท่าน ({$msg_date} คาบ {$bk['booking_period']}) ภายใต้เงื่อนไขพิเศษ เนื่องจากไม่มีคณะกรรมการวิชาการว่างในช่วงเวลานั้น",
                'teacher_supervision.html'
            );
            echo json_encode(['success' => true, 'message' => 'บันทึกเงื่อนไขพิเศษและแจ้งครูเรียบร้อยแล้ว']);
        }

    } elseif ($action === 'toggle_booking_lock') {
        $stmt_cur = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'supervision_booking_open' LIMIT 1");
        $cur = $stmt_cur ? (int)$stmt_cur->fetchColumn() : 1;
        $newVal = $cur ? 0 : 1;
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('supervision_booking_open', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$newVal, $newVal]);
        echo json_encode(['success' => true, 'booking_open' => (bool)$newVal, 'message' => $newVal ? 'เปิดระบบรับจองแล้ว' : 'ปิดระบบรับจองแล้ว']);

    } elseif ($action === 'get_booking_lock') {
        $stmt_cur = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'supervision_booking_open' LIMIT 1");
        $val = $stmt_cur ? (int)$stmt_cur->fetchColumn() : 1;
        echo json_encode(['success' => true, 'booking_open' => (bool)$val]);

    } elseif ($action === 'get_unbooked') {
        $semester = 1;
        $year     = 2569;

        // ครูที่ยังไม่มีคิวนิเทศในภาคเรียนนี้ (ยกเว้น admin, นักศึกษาฝึกสอน)
        $stmt_ub = $pdo->prepare("
            SELECT t.id,
                   CONCAT(COALESCE(t.prefix,''), t.first_name_th, ' ', t.last_name_th) AS full_name,
                   COALESCE(NULLIF(t.sub_department,''), t.department) AS eff_dept,
                   t.department,
                   t.sub_department,
                   t.photo
            FROM teachers t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE (u.role IS NULL OR u.role != 'admin')
              AND (t.position NOT LIKE '%นักศึกษาฝึกสอน%'
                   AND t.position NOT LIKE '%ฝึกประสบการณ์%')
              AND (t.nationality IS NULL OR t.nationality = '' OR t.nationality = 'ไทย')
              AND t.id NOT IN (
                  SELECT teacher_id FROM supervision_bookings
                  WHERE semester = ? AND year = ? AND status != 'cancelled'
              )
            ORDER BY eff_dept, t.first_name_th ASC
        ");
        $stmt_ub->execute([$semester, $year]);
        $rows = $stmt_ub->fetchAll(PDO::FETCH_ASSOC);

        // จัด group ตาม effective dept
        $groups = [];
        foreach ($rows as $t) {
            $dept = $t['eff_dept'] ?: 'ไม่ระบุกลุ่มสาระ';
            if (!isset($groups[$dept])) $groups[$dept] = [];
            $groups[$dept][] = ['id' => $t['id'], 'full_name' => $t['full_name'], 'photo' => $t['photo']];
        }

        echo json_encode([
            'success' => true,
            'total'   => count($rows),
            'groups'  => $groups,
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action not supported']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
