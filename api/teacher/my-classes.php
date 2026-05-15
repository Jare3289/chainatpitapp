<?php
// api/teacher/my-classes.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['classes' => [], 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

try {
    // รหัสห้องทั้งหมดจาก students.class_name (เช่น 101 — ตรงกับ rooms.classroom_code / attendance.class_name)
    $classes = [];
    $stmt = $pdo->query("
        SELECT DISTINCT class_name
        FROM students
        WHERE class_name IS NOT NULL AND class_name != ''
        ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC
    ");
    $classes = array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));

    $advisory = null;
    if ($role === 'teacher') {
        // ลำดับการหาห้องที่ปรึกษา
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(r.classroom_code, t.classroom) AS room_code
                FROM teachers t
                LEFT JOIN rooms r ON r.id = t.advisory_room_id
                WHERE t.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $advisory = $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            // rooms.advisory_room_id ไม่มี — ใช้ classroom text โดยตรง
            $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $advisory = $stmt->fetchColumn() ?: null;
        }

        if (!$advisory && !empty($_SESSION['username'])) {
            try {
                $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE teacher_id = ? OR email = ? LIMIT 1");
                $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
                $advisory = $stmt->fetchColumn() ?: null;
            } catch (Exception $e) { $advisory = null; }
        }
    }

    $firstDate = date('Y-m-d');
    try {
        $stmtFirst = $pdo->query("SELECT MIN(date) FROM attendance WHERE type = 'daily'");
        $firstDate = $stmtFirst->fetchColumn() ?: date('Y-m-d');
    } catch (Exception $e) {}

    echo json_encode([
        'success'       => true,
        'classes'       => $classes,
        'advisory_room' => $advisory,
        'first_attendance_date' => $firstDate
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[my-classes] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'classes' => [], 'error' => 'ระบบขัดข้องชั่วคราว']);
}
