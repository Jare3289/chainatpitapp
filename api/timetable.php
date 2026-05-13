<?php
/**
 * api/timetable.php
 * Public (auth-gated) timetable endpoint — GET only
 * Accessible by: admin, teacher, student
 * 
 * Query params:
 *   ?teacher_id=<id>         → ตารางสอนของครูคนนั้น
 *   ?class_name=<1/1>        → ตารางสอนของห้องนั้น (นักเรียนดู)
 *   ?grade_level=<ม.1>       → กรองตามชั้น
 *   ?day=<1-7>               → กรองตามวัน
 *   ?academic_year=<2568>    → กรองตามปีการศึกษา
 *   ?semester=<1|2>          → กรองตามภาคเรียน
 *   (ไม่ส่ง param → ดึงทั้งหมด ตามสิทธิ์)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/security.php';
session_start();
cnp_verify_origin();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$role      = $_SESSION['role'] ?? '';
$userId    = (int) $_SESSION['user_id'];
$method    = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {

        $where  = [];
        $params = [];

        // 1. นักเรียนดูได้เฉพาะของห้องตัวเอง
        if ($role === 'student') {
            $stmtStud = $pdo->prepare("SELECT COALESCE(NULLIF(class_name,''), room) AS class_name FROM students WHERE user_id = ? LIMIT 1");
            $stmtStud->execute([$userId]);
            $studInfo = $stmtStud->fetch(PDO::FETCH_ASSOC);

            if (!$studInfo && isset($_SESSION['username'])) {
                $stmtStud2 = $pdo->prepare("SELECT COALESCE(NULLIF(class_name,''), room) AS class_name FROM students WHERE student_id = ? LIMIT 1");
                $stmtStud2->execute([$_SESSION['username']]);
                $studInfo = $stmtStud2->fetch(PDO::FETCH_ASSOC);
            }

            if ($studInfo && !empty($studInfo['class_name'])) {
                $where[]  = 't.class_name = ?';
                $params[] = $studInfo['class_name'];
            } else {
                $where[] = '1=0';
            }
        }

        // 2. ครูดูได้เฉพาะของตัวเอง (หาจาก user_id)
        if ($role === 'teacher') {
            // เราเช็ก id ของครูที่มี user_id ตรงกับคนที่ล็อกอินอยู่
            $where[]  = 't.teacher_id = (SELECT id FROM teachers WHERE user_id = ? LIMIT 1)';
            $params[] = $userId;
        }

        // 3. แอดมิน ดูได้ทั้งหมด และสามารถกรองตามครูได้
        if ($role === 'admin' && !empty($_GET['teacher_id'])) {
            $where[]  = 't.teacher_id = ?';
            $params[] = (int) $_GET['teacher_id'];
        }

        if (!empty($_GET['class_name'])) {
            $where[]  = 't.class_name = ?';
            $params[] = $_GET['class_name'];
        }
        if (!empty($_GET['grade_level'])) {
            $where[]  = 't.grade_level = ?';
            $params[] = $_GET['grade_level'];
        }
        if (isset($_GET['day']) && $_GET['day'] !== '') {
            $where[]  = 't.day_of_week = ?';
            $params[] = (int) $_GET['day'];
        }
        if (!empty($_GET['academic_year'])) {
            $where[]  = 't.academic_year = ?';
            $params[] = $_GET['academic_year'];
        }
        if (!empty($_GET['semester'])) {
            $where[]  = 't.semester = ?';
            $params[] = (int) $_GET['semester'];
        }

        $sql = "SELECT 
                    t.*,
                    CONCAT(COALESCE(tc.prefix,''), COALESCE(tc.first_name_th,''), ' ', COALESCE(tc.last_name_th,'')) AS teacher_name,
                    tc.first_name_th AS teacher_first_name,
                    tc.photo AS teacher_photo
                FROM timetable t
                LEFT JOIN teachers tc ON tc.id = t.teacher_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.day_of_week ASC, t.period ASC, t.class_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log('[timetable.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
