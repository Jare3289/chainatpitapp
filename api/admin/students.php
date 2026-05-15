<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/classroom_codes.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$role   = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Helper: get teacher's advisory room code
function getAdvisoryRoom($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS room
                               FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id
                               WHERE t.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    }
}

try {
    if ($method === 'DELETE') {
        cnp_verify_origin();
        cnp_csrf_verify();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            exit;
        }

        // Teacher: can only delete students in their advisory room
        if ($role === 'teacher') {
            $advisoryRoom = getAdvisoryRoom($pdo, $userId);
            if (!$advisoryRoom) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'คุณไม่ได้เป็นครูที่ปรึกษาของห้องใด']);
                exit;
            }
            $vars = cnp_classroom_code_variants($advisoryRoom);
            $ph   = implode(',', array_fill(0, count($vars), '?'));
            $chk  = $pdo->prepare("SELECT id FROM students WHERE id = ? AND class_name IN ($ph) LIMIT 1");
            $chk->execute(array_merge([$id], $vars));
            if (!$chk->fetchColumn()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'นักเรียนคนนี้ไม่อยู่ในห้องที่ปรึกษาของคุณ']);
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id, student_id FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบนักเรียน']);
            exit;
        }

        $pdo->prepare("DELETE FROM attendance          WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM point_transactions  WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_evaluations WHERE student_id = ?")->execute([$id]);
        try { $pdo->prepare("DELETE FROM public_service_records WHERE student_id = ?")->execute([$id]); } catch (Exception $e) {}

        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

        if (!empty($row['user_id'])) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$row['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'ลบนักเรียนแล้ว']);
        exit;
    }

    // GET: admin sees all; teacher sees their advisory room only
    if ($role === 'teacher') {
        $advisoryRoom = getAdvisoryRoom($pdo, $userId);
        if (!$advisoryRoom) {
            echo json_encode(['success' => true, 'students' => [], 'advisory_room' => null]);
            exit;
        }
        $vars = cnp_classroom_code_variants($advisoryRoom);
        $ph   = implode(',', array_fill(0, count($vars), '?'));
        $stmt = $pdo->prepare("SELECT s.*, u.username FROM students s
                               LEFT JOIN users u ON s.user_id = u.id
                               WHERE s.class_name IN ($ph)
                               ORDER BY CAST(s.number_in_class AS UNSIGNED) ASC, s.student_id ASC");
        $stmt->execute($vars);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($students as &$row) {
            if (!isset($row['room']) || $row['room'] === null) $row['room'] = $row['class_name'];
        }
        unset($row);
        echo json_encode(['success' => true, 'students' => $students, 'advisory_room' => $advisoryRoom]);
        exit;
    }

    // Admin GET
    $classroom = $_GET['classroom'] ?? null;
    if ($classroom) {
        $stmt = $pdo->prepare("SELECT s.*, u.username FROM students s
                               LEFT JOIN users u ON s.user_id = u.id
                               WHERE s.class_name = ?
                               ORDER BY CAST(s.number_in_class AS UNSIGNED) ASC, s.student_id ASC");
        $stmt->execute([$classroom]);
    } else {
        $stmt = $pdo->query("SELECT s.*, u.username FROM students s
                             LEFT JOIN users u ON s.user_id = u.id
                             ORDER BY s.grade_level, s.class_name, CAST(s.number_in_class AS UNSIGNED) ASC, s.student_id ASC");
    }

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as &$row) {
        if (isset($row['class_name']) && (!array_key_exists('room', $row) || $row['room'] === null || $row['room'] === '')) {
            $row['room'] = $row['class_name'];
        }
    }
    unset($row);
    echo json_encode(['success' => true, 'students' => $students]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[admin/students] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
