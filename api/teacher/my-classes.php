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
    // ดึงห้องทั้งหมดที่มีนักเรียนอยู่ (ผ่าน FK ไป rooms.classroom_code)
    $stmt = $pdo->query("
        SELECT DISTINCT r.classroom_code
        FROM rooms r
        JOIN students s ON s.room_id = r.id
        ORDER BY CAST(r.classroom_code AS UNSIGNED) ASC, r.classroom_code ASC
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $advisory = null;
    if ($role === 'teacher') {
        // ลำดับการหาห้องที่ปรึกษา:
        //   1) advisory_room_id FK → rooms.classroom_code (ถ้าตั้งไว้)
        //   2) classroom text (legacy)
        $stmt = $pdo->prepare("
            SELECT COALESCE(r.classroom_code, t.classroom) AS room_code
            FROM teachers t
            LEFT JOIN rooms r ON r.id = t.advisory_room_id
            WHERE t.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $advisory = $stmt->fetchColumn() ?: null;

        // Fallback: เผื่อ teacher record ใช้ teacher_id (รหัสบุคลากร) แทน user_id
        if (!$advisory && !empty($_SESSION['username'])) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(r.classroom_code, t.classroom) AS room_code
                FROM teachers t
                LEFT JOIN rooms r ON r.id = t.advisory_room_id
                WHERE t.teacher_id = ? OR t.email = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
            $advisory = $stmt->fetchColumn() ?: null;
        }
    }

    // Get first attendance date
    $stmtFirst = $pdo->query("SELECT MIN(date) FROM attendance WHERE type = 'daily'");
    $firstDate = $stmtFirst->fetchColumn() ?: date('Y-m-d');

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
