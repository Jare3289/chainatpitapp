<?php
// api/admin/update_risk_status.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_risk_flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'ยังต้องติดตาม',
        note TEXT,
        updated_by INT,
        month VARCHAR(7),
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_month (student_id, month)
    )");

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $studentId = (int)($body['student_id'] ?? 0);
        $status    = $body['status'] ?? '';
        $note      = $body['note'] ?? '';
        $month     = $body['month'] ?? date('Y-m');

        $allowed = ['ยังต้องติดตาม', 'ติดตามแล้ว', 'แก้ไขแล้ว', 'จำหน่ายออก'];
        if (!$studentId || !in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO student_risk_flags (student_id, status, note, updated_by, month)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_by = VALUES(updated_by), updated_at = NOW()");
        $stmt->execute([$studentId, $status, $note, $_SESSION['user_id'], $month]);

        echo json_encode(['success' => true, 'message' => 'บันทึกสถานะแล้ว']);

    } elseif ($method === 'GET') {
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("SELECT student_id, status, note FROM student_risk_flags WHERE month = ?");
        $stmt->execute([$month]);
        $flags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($flags as $f) $map[$f['student_id']] = $f;
        echo json_encode(['success' => true, 'flags' => $map]);
    }

} catch (PDOException $e) {
    error_log('[update_risk_status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
