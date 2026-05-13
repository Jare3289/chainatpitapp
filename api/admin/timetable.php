<?php
/**
 * api/admin/timetable.php
 * Full CRUD for timetable — admin only
 *
 * GET    ?id=<id>        → single row
 * GET    (no id)         → list with filters
 * POST                   → insert row(s); body: single object OR array of objects
 * PUT                    → update row by id
 * DELETE ?id=<id>        → delete row
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/* ── helpers ── */
function sanitizeRow(array $d): array {
    return [
        'teacher_id'    => isset($d['teacher_id'])   ? (int) $d['teacher_id']   : null,
        'day_of_week'   => isset($d['day_of_week'])  ? (int) $d['day_of_week']  : null,
        'period'        => isset($d['period'])        ? (int) $d['period']        : 0,
        'subject_name'  => trim($d['subject_name']   ?? ''),
        'subject_code'  => trim($d['subject_code']   ?? '') ?: null,
        'grade_level'   => trim($d['grade_level']    ?? '') ?: null,
        'class_name'    => trim($d['class_name']     ?? '') ?: null,
        'room_location' => trim($d['room_location']  ?? '') ?: null,
        'academic_year' => trim($d['academic_year']  ?? '') ?: null,
        'semester'      => isset($d['semester'])      ? (int) $d['semester']     : 1,
        'note'          => trim($d['note']            ?? '') ?: null,
    ];
}

try {
    /* ─── GET ─────────────────────────────── */
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare(
                "SELECT t.*, 
                        CONCAT(COALESCE(tc.prefix,''), COALESCE(tc.first_name_th,''), ' ', COALESCE(tc.last_name_th,'')) AS teacher_name
                 FROM timetable t
                 LEFT JOIN teachers tc ON tc.id = t.teacher_id
                 WHERE t.id = ?"
            );
            $stmt->execute([(int)$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        } else {
            $where  = [];
            $params = [];
            if (!empty($_GET['teacher_id'])) { $where[] = 't.teacher_id = ?'; $params[] = (int)$_GET['teacher_id']; }
            if (!empty($_GET['class_name']))  { $where[] = 't.class_name = ?'; $params[] = $_GET['class_name']; }
            if (!empty($_GET['grade_level'])) { $where[] = 't.grade_level = ?'; $params[] = $_GET['grade_level']; }
            if (isset($_GET['day']) && $_GET['day'] !== '') { $where[] = 't.day_of_week = ?'; $params[] = (int)$_GET['day']; }
            if (!empty($_GET['academic_year'])) { $where[] = 't.academic_year = ?'; $params[] = $_GET['academic_year']; }
            if (!empty($_GET['semester']))  { $where[] = 't.semester = ?'; $params[] = (int)$_GET['semester']; }

            $sql = "SELECT t.*, 
                           CONCAT(COALESCE(tc.prefix,''), COALESCE(tc.first_name_th,''), ' ', COALESCE(tc.last_name_th,'')) AS teacher_name
                    FROM timetable t
                    LEFT JOIN teachers tc ON tc.user_id = t.teacher_id";
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY t.day_of_week, t.period, t.class_name';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
        }

    /* ─── POST (insert) ────────────────────── */
    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        // รองรับ single object หรือ array ของ rows
        $rows = isset($body[0]) ? $body : [$body];

        $insertSql = "INSERT INTO timetable 
                      (teacher_id, day_of_week, period, subject_name, subject_code, grade_level, class_name, room_location, academic_year, semester, note)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insertSql);

        $pdo->beginTransaction();
        $inserted = 0;
        foreach ($rows as $d) {
            $r = sanitizeRow($d);
            if (!$r['teacher_id'] || !$r['day_of_week'] || !$r['subject_name']) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => "ข้อมูลไม่ครบ: teacher_id, day_of_week, subject_name จำเป็น"]);
                exit;
            }
            $stmt->execute(array_values($r));
            $inserted++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "เพิ่มตารางสอนสำเร็จ $inserted รายการ", 'inserted' => $inserted]);

    /* ─── PUT (update) ────────────────────── */
    } elseif ($method === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }

        $r = sanitizeRow($body);
        if (!$r['teacher_id'] || !$r['day_of_week'] || !$r['subject_name']) {
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE timetable SET 
                teacher_id=?, day_of_week=?, period=?, subject_name=?, subject_code=?,
                grade_level=?, class_name=?, room_location=?, academic_year=?, semester=?, note=?
             WHERE id=?"
        );
        $stmt->execute([...array_values($r), $id]);
        echo json_encode(['success' => true, 'message' => 'อัปเดตตารางสอนสำเร็จ']);

    /* ─── DELETE ──────────────────────────── */
    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM timetable WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'ลบรายการสำเร็จ']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[admin/timetable.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
