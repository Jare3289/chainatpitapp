<?php
// api/teacher/check-subject.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$subject_code = $_GET['subject_code'] ?? '';

if (!$subject_code) {
    echo json_encode(['success' => false, 'error' => 'Missing subject_code']);
    exit;
}

try {
    // Check how many teachers have recorded this subject
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT recorded_by) as teacher_count, 
               GROUP_CONCAT(DISTINCT t.first_name_th) as teacher_names
        FROM attendance a
        LEFT JOIN teachers t ON a.recorded_by = t.user_id
        WHERE a.subject_code = ? AND a.recorded_by != ?
    ");
    $stmt->execute([$subject_code, $user_id]);
    $result = $stmt->fetch();

    // Fetch subject name from permanent table
    $stmtSub = $pdo->prepare("SELECT subject_name FROM subjects WHERE subject_code = ? LIMIT 1");
    $stmtSub->execute([$subject_code]);
    $subject_name = $stmtSub->fetchColumn();

    echo json_encode([
        'success' => true,
        'subject_name' => $subject_name ?: '',
        'other_teachers_count' => (int)$result['teacher_count'],
        'other_teachers_names' => $result['teacher_names']
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
