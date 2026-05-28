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

// Fetch student info before deleting (include photo for filesystem cleanup)
$stmt = $pdo->prepare("SELECT s.id, s.student_id, s.user_id, s.first_name_th, s.last_name_th, s.class_name, s.room_id, s.photo
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

    // ── 1. ลบข้อมูลที่เชื่อมกับ student_id ──────────────────────────────────

    // ประวัติการเช็คชื่อรายวัน
    $pdo->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$studentDbId]);

    // ประวัติการเช็คชื่อรายวิชา (อาจไม่มีในทุก installation)
    try {
        $pdo->prepare("DELETE FROM attendance_subjects WHERE student_id = ?")->execute([$studentDbId]);
    } catch (PDOException $e2) {
        // ข้ามหากตารางไม่มีอยู่
        if (strpos($e2->getMessage(), "doesn't exist") === false) throw $e2;
    }

    // คะแนนความประพฤติ / point transactions
    $pdo->prepare("DELETE FROM point_transactions WHERE student_id = ?")->execute([$studentDbId]);

    // ผลการประเมินนักเรียน
    try {
        $pdo->prepare("DELETE FROM student_evaluations WHERE student_id = ?")->execute([$studentDbId]);
    } catch (PDOException $e2) {
        if (strpos($e2->getMessage(), "doesn't exist") === false) throw $e2;
    }

    // บันทึกจิตอาสา / กิจกรรมสาธารณประโยชน์
    $pdo->prepare("DELETE FROM public_service_records WHERE student_id = ?")->execute([$studentDbId]);

    // ── 2. ลบข้อมูลที่เชื่อมกับ user_id ─────────────────────────────────────
    if ($student['user_id']) {
        // การแจ้งเตือน (มี ON DELETE CASCADE แต่ลบล่วงหน้าเพื่อความแน่นอน)
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$student['user_id']]);

        // โทเค็น Remember-me
        $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$student['user_id']]);
    }

    // ── 3. ลบรูปภาพออกจาก Filesystem ────────────────────────────────────────
    if (!empty($student['photo'])) {
        $photoPath = realpath(__DIR__ . '/../../public/uploads/students/' . basename($student['photo']));
        $uploadsDir = realpath(__DIR__ . '/../../public/uploads/students');
        // ตรวจสอบว่าไฟล์อยู่ใน uploads/students จริง (ป้องกัน path traversal)
        if ($photoPath && $uploadsDir && strpos($photoPath, $uploadsDir) === 0 && is_file($photoPath)) {
            @unlink($photoPath);
        }
    }

    // ── 4. ลบข้อมูลหลัก ───────────────────────────────────────────────────────
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentDbId]);

    if ($student['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$student['user_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "ลบข้อมูลนักเรียน {$student['first_name_th']} {$student['last_name_th']} และข้อมูลที่เกี่ยวข้องทั้งหมดเรียบร้อยแล้ว",
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[delete_student] ' . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
