<?php
// api/teacher/homeroom.php
// GET  ?date=YYYY-MM-DD&class_name=101   → คืน session ของวันนั้น + รายการ activity ก่อนหน้า (autocomplete)
// POST { date, class_name, session_type, activity, notes }  → บันทึก/อัปเดต
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $date      = $_GET['date'] ?? date('Y-m-d');
        $className = trim($_GET['class_name'] ?? '');

        $session = null;
        if ($className !== '') {
            $stmt = $pdo->prepare("
                SELECT h.*, r.classroom_code
                FROM homeroom_sessions h
                JOIN rooms r ON h.room_id = r.id
                WHERE h.date = ? AND r.classroom_code = ?
                LIMIT 1
            ");
            $stmt->execute([$date, $className]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // Distinct activities for autocomplete (last 100 days, non-empty)
        $actStmt = $pdo->query("
            SELECT DISTINCT activity FROM homeroom_sessions
            WHERE activity IS NOT NULL AND activity <> ''
              AND date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
            ORDER BY activity ASC
            LIMIT 50
        ");
        $activities = $actStmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success'    => true,
            'session'    => $session,
            'activities' => $activities,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        cnp_verify_origin();
        cnp_csrf_verify();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $date         = $body['date']         ?? date('Y-m-d');
        $className    = trim($body['class_name'] ?? '');
        $sessionType  = ($body['session_type'] ?? 'short') === 'long' ? 'long' : 'short';
        $activity     = trim($body['activity'] ?? '');
        $notes        = trim($body['notes']    ?? '');

        if ($className === '') {
            http_response_code(400);
            echo json_encode(['error' => 'กรุณาระบุห้องเรียน']);
            exit;
        }
        if ($sessionType === 'short' && $activity === '') {
            http_response_code(400);
            echo json_encode(['error' => 'โฮมรูมสั้น ต้องระบุชื่อกิจกรรม']);
            exit;
        }

        // Resolve room_id
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE classroom_code = ? LIMIT 1");
        $stmt->execute([$className]);
        $roomId = $stmt->fetchColumn();
        if (!$roomId) {
            http_response_code(400);
            echo json_encode(['error' => "ไม่พบห้อง '$className'"]);
            exit;
        }

        // Upsert
        $sql = "
            INSERT INTO homeroom_sessions (date, room_id, session_type, activity, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              session_type = VALUES(session_type),
              activity     = VALUES(activity),
              notes        = VALUES(notes),
              recorded_by  = VALUES(recorded_by)
        ";
        $pdo->prepare($sql)->execute([
            $date,
            (int)$roomId,
            $sessionType,
            $activity ?: null,
            $notes ?: null,
            (int)$_SESSION['user_id'],
        ]);

        echo json_encode(['success' => true, 'message' => 'บันทึกโฮมรูมเรียบร้อย']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (PDOException $e) {
    error_log('[homeroom] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
