<?php
// api/teacher/student-history.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $_GET['student_id'] ?? '';
$subject_code = $_GET['subject_code'] ?? '';
$type = $_GET['type'] ?? '';

if (!$student_id || (!$subject_code && $type !== 'daily')) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    if ($type === 'daily') {
        // Fetch daily attendance history
        $stmt = $pdo->prepare("
            SELECT date, status, remark, NULL as period 
            FROM attendance 
            WHERE student_id = ? AND type = 'daily' 
            ORDER BY date DESC
        ");
        $stmt->execute([$student_id]);
    } else {
        // Fetch lesson/subject history
        $stmt = $pdo->prepare("
            SELECT date, status, remark, period 
            FROM attendance 
            WHERE student_id = ? AND subject_code = ? 
            ORDER BY date DESC, period DESC
        ");
        $stmt->execute([$student_id, $subject_code]);
    }
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
