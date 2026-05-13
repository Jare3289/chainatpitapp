<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if it's a student or teacher (in this simple implementation, we might need to know which one, but we can just check both)
    // Actually, the 'id' passed is the ID from the students/teachers table.
    
    // Find user_id first
    $stmtS = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmtS->execute([$id]);
    $student = $stmtS->fetch();

    if ($student) {
        $userId = $student['user_id'];
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    } else {
        $stmtT = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
        $stmtT->execute([$id]);
        $teacher = $stmtT->fetch();
        if ($teacher) {
            $userId = $teacher['user_id'];
            $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
