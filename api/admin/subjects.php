<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $year = $_GET['year'] ?? '';
        $semester = $_GET['semester'] ?? '';

        if ($id) {
            $stmt = $pdo->prepare("SELECT s.*, t.prefix, t.first_name_th, t.last_name_th 
                                   FROM subjects s 
                                   LEFT JOIN teachers t ON s.teacher_id = t.user_id 
                                   WHERE s.id = ?");
            $stmt->execute([$id]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $subject]);
        } else {
            // timetable.subject_name may store codes OR Thai names depending on import method — support both
            $sql = "SELECT s.*,
                        COALESCE(t.prefix,  tbl_t.prefix,  '') AS prefix,
                        COALESCE(t.first_name_th,  tbl_t.first_name_th,  '') AS first_name_th,
                        COALESCE(t.last_name_th,   tbl_t.last_name_th,   '') AS last_name_th,
                        IF(tbl.tbl_key IS NOT NULL, 1, 0) AS in_timetable
                    FROM subjects s
                    LEFT JOIN teachers t ON s.teacher_id = t.user_id
                    LEFT JOIN (
                        SELECT subject_name AS tbl_key, MIN(teacher_id) AS first_teacher_id
                        FROM timetable
                        WHERE subject_name IS NOT NULL AND subject_name != ''
                        GROUP BY subject_name
                    ) tbl ON tbl.tbl_key = s.subject_code OR tbl.tbl_key = s.subject_name
                    LEFT JOIN teachers tbl_t ON tbl_t.id = tbl.first_teacher_id AND t.user_id IS NULL";
            $where = [];
            $params = [];

            if ($year)     { $where[] = "s.academic_year = ?"; $params[] = $year; }
            if ($semester) { $where[] = "s.semester = ?";      $params[] = $semester; }

            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY s.subject_code ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'subjects' => $subjects]);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        // Sync teacher assignments from timetable where teacher is missing/orphaned
        if (($data['action'] ?? '') === 'sync_from_timetable') {
            $year     = $data['academic_year'] ?? '';
            $semester = $data['semester'] ?? '';
            $extra_where = '';
            $extra_params = [];
            if ($year)     { $extra_where .= " AND s.academic_year = ?"; $extra_params[] = $year; }
            if ($semester) { $extra_where .= " AND s.semester = ?";      $extra_params[] = (int)$semester; }

            // group by subject id to avoid duplicate-row ambiguity from OR match
            $stmt = $pdo->prepare("
                UPDATE subjects s
                JOIN (
                    SELECT s2.id AS sid, MIN(tc.user_id) AS teacher_user_id
                    FROM subjects s2
                    JOIN timetable t ON (t.subject_name = s2.subject_code OR t.subject_name = s2.subject_name)
                    JOIN teachers tc ON tc.id = t.teacher_id
                    WHERE t.subject_name IS NOT NULL AND t.subject_name != ''
                      AND tc.user_id IS NOT NULL
                    GROUP BY s2.id
                ) tt ON s.id = tt.sid
                LEFT JOIN teachers existing ON existing.user_id = s.teacher_id
                SET s.teacher_id = tt.teacher_user_id
                WHERE existing.user_id IS NULL
                $extra_where
            ");
            $stmt->execute($extra_params);
            $updated = $stmt->rowCount();
            echo json_encode(['success' => true, 'updated' => $updated,
                'message' => $updated > 0 ? "ซิงค์ครูผู้สอนสำเร็จ $updated วิชา" : 'ทุกวิชามีครูผู้สอนครบแล้ว ไม่มีการอัปเดต']);
            exit;
        }

        $subject_code = $data['subject_code'] ?? '';
        $subject_name = $data['subject_name'] ?? '';
        $teacher_id = $data['teacher_id'] ?? null;
        $academic_year = $data['academic_year'] ?? '';
        $semester = $data['semester'] ?? '';
        $department = $data['department'] ?? '';
        $room_raw = $data['room'] ?? '';
        $room = is_array($room_raw) ? implode(',', $room_raw) : $room_raw;
        $periods_raw = $data['periods'] ?? '';
        $periods = is_array($periods_raw) ? implode(',', $periods_raw) : $periods_raw;
        $year_be = $academic_year; // Sync year_be with academic_year
        $rooms = $room; // Sync rooms with room

        if (!$subject_code || !$subject_name) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, teacher_id, academic_year, semester, department, room, periods, year_be, rooms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$subject_code, $subject_name, $teacher_id, $academic_year, $semester, $department, $room, $periods, $year_be, $rooms]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลวิชาสำเร็จ']);

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $subject_code = $data['subject_code'] ?? '';
        $subject_name = $data['subject_name'] ?? '';
        $teacher_id = $data['teacher_id'] ?? null;
        $academic_year = $data['academic_year'] ?? '';
        $semester = $data['semester'] ?? '';
        $department = $data['department'] ?? '';
        $room_raw = $data['room'] ?? '';
        $room = is_array($room_raw) ? implode(',', $room_raw) : $room_raw;
        $periods_raw = $data['periods'] ?? '';
        $periods = is_array($periods_raw) ? implode(',', $periods_raw) : $periods_raw;
        $year_be = $academic_year;
        $rooms = $room;

        if (!$id || !$subject_code || !$subject_name) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, teacher_id = ?, academic_year = ?, semester = ?, department = ?, room = ?, periods = ?, year_be = ?, rooms = ? WHERE id = ?");
        $stmt->execute([$subject_code, $subject_name, $teacher_id, $academic_year, $semester, $department, $room, $periods, $year_be, $rooms, $id]);
        echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลวิชาสำเร็จ']);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing ID']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลวิชาสำเร็จ']);
    }
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
