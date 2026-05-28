<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

$user = cnp_require_auth(['admin', 'teacher']);
cnp_csrf_verify();

$data = json_decode(file_get_contents('php://input'), true);
$studentDbId = isset($data['id']) ? (int)$data['id'] : 0;

if (!$studentDbId) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่ระบุรหัสนักเรียน']);
    exit;
}

// Fetch student info before deleting
$stmt = $pdo->prepare("SELECT s.id, s.student_id, s.user_id, s.first_name_th, s.last_name_th, s.class_name, s.room_id
                        FROM students s WHERE s.id = ?");
$stmt->execute([$studentDbId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'ไม่พบข้อมูลนักเรียน']);
    exit;
}

// Teacher role: can only delete students in their advisory room
if ($_SESSION['role'] === 'teacher') {
    $teacherStmt = $pdo->prepare("SELECT advisory_room_id FROM teachers WHERE user_id = ?");
    $teacherStmt->execute([$_SESSION['user_id']]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher || $teacher['advisory_room_id'] != $student['room_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'ไม่มีสิทธิ์ลบนักเรียนในห้องนี้']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentDbId]);

    if ($student['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$student['user_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "ลบข้อมูลนักเรียน {$student['first_name_th']} {$student['last_name_th']} เรียบร้อยแล้ว",
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (strpos($e->getMessage(), '1451') !== false || stripos($e->getMessage(), 'foreign key') !== false) {
        echo json_encode(['error' => 'ไม่สามารถลบได้ เนื่องจากนักเรียนมีประวัติเช็คชื่อหรือคะแนนความประพฤติอยู่ในระบบ']);
    } else {
        error_log('[delete_student] ' . $e->getMessage());
        echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}
