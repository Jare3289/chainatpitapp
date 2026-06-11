<?php
/**
 * api/timetable.php
 * Public (auth-gated) timetable endpoint — GET only
 * Accessible by: admin, teacher, student
 *
 * Query params:
 *   ?teacher_id=<id>              → ตารางสอนของครูคนนั้น
 *   ?class_name=101|1/1|ม.1/1    → กรองตารางสอนของห้อง
 *   ?grade_level=<ม.1>            → กรองตามชั้น
 *   ?day=<1-7>                    → กรองตามวัน
 *   ?academic_year=<2568>         → กรองตามปีการศึกษา
 *   ?semester=<1|2>               → กรองตามภาคเรียน
 *   ?get_room_locations=1         → คืนรายชื่อห้องทั้งหมด (early exit)
 *   ?get_class_teachers=1         → [student] คืนรายชื่อครูผู้สอนในห้องนักเรียน (early exit)
 *   ?get_my_classes=1             → [teacher] คืนรายชื่อห้องที่ครูสอน (early exit)
 *   ?view_homeroom_teacher=1      → [student] ตารางสอนครูที่ปรึกษา
 *   ?view_my_teacher_id=<id>      → [student] ตารางสอนของครูผู้สอน (ตรวจสอบก่อน)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../inc/classroom_codes.php';
session_start();
cnp_verify_origin();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$role   = $_SESSION['role'] ?? '';
$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {

        // ── ดึงปีการศึกษา/ภาคเรียนก่อน (ใช้ร่วมกันทุก handler) ──────────────────
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings     = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $defaultYear     = $settings['current_academic_year'] ?? '2569';
        $defaultSemester = (int) ($settings['current_semester'] ?? 1);
        $academicYear    = !empty($_GET['academic_year']) ? $_GET['academic_year'] : $defaultYear;
        $semester        = !empty($_GET['semester'])      ? (int) $_GET['semester'] : $defaultSemester;

        // ── Quick exit: รายชื่อห้อง (room-picker UI) ─────────────────────────────
        if (!empty($_GET['get_room_locations'])) {
            $stmt = $pdo->prepare("SELECT DISTINCT room_location FROM timetable WHERE room_location IS NOT NULL AND room_location != '' AND academic_year = ? AND semester = ? ORDER BY room_location");
            $stmt->execute([$academicYear, $semester]);
            echo json_encode(['success' => true, 'rooms' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            exit;
        }

        // ── Quick exit: [student] ครูผู้สอนในห้องนักเรียน ─────────────────────────
        if ($role === 'student' && !empty($_GET['get_class_teachers'])) {
            $stmtStud = $pdo->prepare("SELECT class_name FROM students WHERE user_id = ? LIMIT 1");
            $stmtStud->execute([$userId]);
            $studInfo = $stmtStud->fetch(PDO::FETCH_ASSOC);
            if (!$studInfo && isset($_SESSION['username'])) {
                $stmtStud2 = $pdo->prepare("SELECT class_name FROM students WHERE student_id = ? OR email = ? LIMIT 1");
                $stmtStud2->execute([$_SESSION['username'], $_SESSION['username']]);
                $studInfo = $stmtStud2->fetch(PDO::FETCH_ASSOC);
            }
            $studentClass = $studInfo['class_name'] ?? '';
            if ($studentClass) {
                $alts = cnp_classroom_code_variants($studentClass);
                $ph   = implode(',', array_fill(0, count($alts), '?'));
                $stmt = $pdo->prepare("
                    SELECT tc.id, tc.prefix, tc.first_name_th, tc.last_name_th,
                           GROUP_CONCAT(DISTINCT t.subject_name ORDER BY t.subject_name SEPARATOR '|') AS subjects
                    FROM timetable t
                    JOIN teachers tc ON tc.id = t.teacher_id
                    WHERE t.class_name IN ($ph) AND t.academic_year = ? AND t.semester = ?
                    GROUP BY tc.id, tc.prefix, tc.first_name_th, tc.last_name_th
                    ORDER BY tc.first_name_th
                ");
                $stmt->execute(array_merge($alts, [$academicYear, $semester]));
                echo json_encode(['success' => true, 'teachers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                echo json_encode(['success' => true, 'teachers' => []]);
            }
            exit;
        }

        // ── Quick exit: [teacher] ห้องที่ครูสอน ──────────────────────────────────
        if ($role === 'teacher' && !empty($_GET['get_my_classes'])) {
            $stmt = $pdo->prepare("
                SELECT class_name,
                       GROUP_CONCAT(DISTINCT subject_name ORDER BY subject_name SEPARATOR '|') AS subjects
                FROM timetable
                WHERE teacher_id = (SELECT id FROM teachers WHERE user_id = ? LIMIT 1)
                AND academic_year = ? AND semester = ?
                AND class_name IS NOT NULL AND class_name != ''
                GROUP BY class_name
                ORDER BY class_name
            ");
            $stmt->execute([$userId, $academicYear, $semester]);
            echo json_encode(['success' => true, 'classes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── Quick exit: ดึงรายชื่อห้องทั้งหมด (ใช้ใน swap simulation) ──────────
        if (!empty($_GET['get_classes'])) {
            $stmt = $pdo->prepare("SELECT DISTINCT class_name FROM timetable WHERE class_name IS NOT NULL AND class_name != '' AND academic_year = ? AND semester = ? ORDER BY class_name");
            $stmt->execute([$academicYear, $semester]);
            echo json_encode(['success' => true, 'classes' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            exit;
        }

        // ── Quick exit: ดึงรายชื่อครูทั้งหมด (ใช้ใน swap simulation) ─────────
        if (!empty($_GET['get_teachers'])) {
            $stmt = $pdo->prepare("SELECT DISTINCT tc.id, CONCAT(COALESCE(tc.prefix,''), tc.first_name_th, ' ', tc.last_name_th) AS name, tc.first_name_th FROM timetable t JOIN teachers tc ON tc.id = t.teacher_id WHERE t.teacher_id IS NOT NULL AND t.academic_year = ? AND t.semester = ? ORDER BY tc.first_name_th");
            $stmt->execute([$academicYear, $semester]);
            echo json_encode(['success' => true, 'teachers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── Main query ────────────────────────────────────────────────────────────
        $where  = [];
        $params = [];

        // 1. นักเรียนดูได้เฉพาะของห้องตัวเอง หรือเลือกดูตารางสอนครูที่ปรึกษา / ครูผู้สอน
        if ($role === 'student') {
            $stmtStud = $pdo->prepare("SELECT class_name FROM students WHERE user_id = ? LIMIT 1");
            $stmtStud->execute([$userId]);
            $studInfo = $stmtStud->fetch(PDO::FETCH_ASSOC);

            if (!$studInfo && isset($_SESSION['username'])) {
                $stmtStud2 = $pdo->prepare("SELECT class_name FROM students WHERE student_id = ? OR email = ? LIMIT 1");
                $stmtStud2->execute([$_SESSION['username'], $_SESSION['username']]);
                $studInfo = $stmtStud2->fetch(PDO::FETCH_ASSOC);
            }

            $studentClass = $studInfo['class_name'] ?? '';

            if (!empty($_GET['view_homeroom_teacher']) && $studentClass) {
                // นักเรียนดูตารางสอนครูที่ปรึกษา
                $hrVars = cnp_classroom_code_variants($studentClass);
                $hrPh   = implode(',', array_fill(0, count($hrVars), '?'));
                $stmtHR = $pdo->prepare("
                    SELECT t.id, t.first_name_th, t.last_name_th, t.prefix
                    FROM teachers t
                    JOIN rooms r ON t.advisory_room_id = r.id
                    WHERE r.classroom_code IN ($hrPh)
                ");
                $stmtHR->execute($hrVars);
                $advisors = $stmtHR->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($advisors)) {
                    $selectedTeacherId = !empty($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : $advisors[0]['id'];
                    $where[]  = 't.teacher_id = ?';
                    $params[] = $selectedTeacherId;
                } else {
                    $where[] = '1=0';
                }

            } elseif (!empty($_GET['view_my_teacher_id']) && $studentClass) {
                // นักเรียนดูตารางสอนของครูผู้สอน (ตรวจสอบก่อนว่าครูสอนนักเรียนจริง)
                $teacherId = (int) $_GET['view_my_teacher_id'];
                $alts = cnp_classroom_code_variants($studentClass);
                $ph   = implode(',', array_fill(0, count($alts), '?'));
                $verify = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE teacher_id = ? AND class_name IN ($ph) AND academic_year = ? AND semester = ?");
                $verify->execute(array_merge([$teacherId], $alts, [$academicYear, $semester]));
                if ($verify->fetchColumn() > 0) {
                    $where[]  = 't.teacher_id = ?';
                    $params[] = $teacherId;
                } else {
                    $where[] = '1=0';
                }

            } elseif ($studentClass) {
                // ตารางเรียนปกติของนักเรียน
                $alts = cnp_classroom_code_variants((string) $studentClass);
                [$clsSql, $clsParams] = cnp_timetable_homeroom_where_sql('t', $alts);
                $where[] = $clsSql;
                $params  = array_merge($params, $clsParams);
            } else {
                $where[] = '1=0';
            }
        }

        // 2. ครู / แอดมิน ดูได้ทั้งหมด และสามารถกรองตามครูได้
        if (($role === 'teacher' || $role === 'admin') && !empty($_GET['teacher_id'])) {
            $where[]  = 't.teacher_id = ?';
            $params[] = (int) $_GET['teacher_id'];
        } elseif ($role === 'teacher' && empty($_GET['teacher_id']) && empty($_GET['class_name']) && empty($_GET['department'])) {
            // Resolve teacher_id: try user_id first, then username fallback
            $resolvedTeacherId = null;
            $stmtTid = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
            $stmtTid->execute([$userId]);
            $rowTid = $stmtTid->fetch(PDO::FETCH_ASSOC);
            if ($rowTid) {
                $resolvedTeacherId = (int)$rowTid['id'];
            } else {
                $uname = $_SESSION['username'] ?? '';
                if ($uname) {
                    $stmtTid2 = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ? LIMIT 1");
                    $stmtTid2->execute([$uname, $uname]);
                    $rowTid2 = $stmtTid2->fetch(PDO::FETCH_ASSOC);
                    if ($rowTid2) {
                        $resolvedTeacherId = (int)$rowTid2['id'];
                        try { $pdo->prepare("UPDATE teachers SET user_id = ? WHERE id = ?")->execute([$userId, $resolvedTeacherId]); } catch (Exception $e) {}
                    }
                }
            }
            if ($resolvedTeacherId !== null) {
                $where[]  = 't.teacher_id = ?';
                $params[] = $resolvedTeacherId;
            } else {
                $where[] = '1=0';
            }
        }

        $where[]  = 't.academic_year = ?';
        $params[] = $academicYear;
        $where[]  = 't.semester = ?';
        $params[] = $semester;

        if (!empty($_GET['class_name'])) {
            $cn   = trim((string) $_GET['class_name']);
            $alts = cnp_classroom_code_variants($cn);
            [$clsSql, $clsParams] = cnp_timetable_homeroom_where_sql('t', $alts);
            $where[] = $clsSql;
            $params  = array_merge($params, $clsParams);
        }
        if (!empty($_GET['grade_level'])) {
            $where[]  = 't.grade_level = ?';
            $params[] = $_GET['grade_level'];
        }
        if (isset($_GET['day']) && $_GET['day'] !== '') {
            $where[]  = 't.day_of_week = ?';
            $params[] = (int) $_GET['day'];
        }
        if (!empty($_GET['room_location'])) {
            $where[]  = 't.room_location = ?';
            $params[] = trim($_GET['room_location']);
        }
        if (!empty($_GET['department'])) {
            $dept = trim($_GET['department']);
            if ($dept === 'บริหาร') {
                $where[]  = 'tc.department IN (?, ?)';
                $params[] = 'ผู้อำนวยการ';
                $params[] = 'รองผู้อำนวยการ';
            } else {
                $cleanDept = str_replace(' ', '', $dept);
                if ($cleanDept === 'สังคมศึกษาศาสนาและวัฒนธรรม') {
                    $where[]  = 'REPLACE(tc.department, " ", "") = ?';
                    $params[] = 'สังคมศึกษาศาสนาและวัฒนธรรม';
                } else {
                    $where[]  = 'tc.department = ?';
                    $params[] = $dept;
                }
            }
        }

        // timetable.subject_name may store either a subject code (e.g. "อ32101") or a Thai name
        // depending on how data was imported — support both via two JOIN passes
        $sql = "SELECT
                    t.*,
                    COALESCE(sc.subject_name, sn.subject_name, t.subject_name) AS subject_name,
                    COALESCE(sc.subject_code, sn.subject_code)                 AS subject_code,
                    CONCAT(COALESCE(tc.prefix,''), COALESCE(tc.first_name_th,''), ' ', COALESCE(tc.last_name_th,'')) AS teacher_name,
                    tc.first_name_th AS teacher_first_name,
                    tc.photo AS teacher_photo
                FROM timetable t
                LEFT JOIN teachers tc ON tc.id = t.teacher_id
                LEFT JOIN subjects sc ON sc.subject_code = t.subject_name
                    AND t.subject_name IS NOT NULL AND t.subject_name != ''
                LEFT JOIN subjects sn ON sn.subject_name = t.subject_name
                    AND sc.id IS NULL
                    AND t.subject_name IS NOT NULL AND t.subject_name != ''";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.day_of_week ASC, t.period ASC, t.class_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = ['success' => true, 'data' => $rows, 'count' => count($rows)];
        if (isset($advisors) && count($advisors) > 1) {
            $response['advisors']            = $advisors;
            $response['selected_advisor_id'] = $selectedTeacherId;
        }
        echo json_encode($response);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log('[timetable.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
