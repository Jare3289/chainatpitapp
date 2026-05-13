<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'DELETE') {
        cnp_verify_origin();
        cnp_csrf_verify();

        // admin only
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'เฉพาะ admin']);
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            exit;
        }

        $pdo->beginTransaction();

        // Find user_id linked to this student (to also delete login account)
        $stmt = $pdo->prepare("SELECT user_id, student_id FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'ไม่พบนักเรียน']);
            exit;
        }

        // Manually clean up FK references that use RESTRICT
        $pdo->prepare("DELETE FROM attendance          WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM point_transactions  WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_evaluations WHERE student_id = ?")->execute([$id]);

        // public_service_records uses int student_id (FK didn't add — but clean anyway)
        try {
            $pdo->prepare("DELETE FROM public_service_records WHERE student_id = ?")->execute([$id]);
        } catch (Exception $e) { /* ignore if FK type differs */ }

        // Now delete the student
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

        // Delete linked user (if any)
        if (!empty($row['user_id'])) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")
                ->execute([$row['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'ลบนักเรียนแล้ว']);
        exit;
    }

    // GET (default)
    $classroom = $_GET['classroom'] ?? null;
    if ($classroom) {
        $stmt = $pdo->prepare("SELECT s.*, u.username FROM students s
                               LEFT JOIN users u ON s.user_id = u.id
                               WHERE s.room = ?
                               ORDER BY CAST(s.number_in_class AS UNSIGNED) ASC, s.student_id ASC");
        $stmt->execute([$classroom]);
    } else {
        $stmt = $pdo->query("SELECT s.*, u.username FROM students s
                             LEFT JOIN users u ON s.user_id = u.id
                             ORDER BY s.grade_level, s.room, CAST(s.number_in_class AS UNSIGNED) ASC, s.student_id ASC");
    }

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'students' => $students]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[admin/students] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
