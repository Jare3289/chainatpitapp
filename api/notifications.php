<?php
// api/notifications.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo, $user_id);
        break;
    case 'POST':
        handlePost($pdo, $user_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}

function handleGet($pdo, $user_id) {
    $unreadOnly = ($_GET['unread'] ?? '') === '1';
    $role = $_SESSION['role'];
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) $sql .= " AND is_read = 0";
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Dynamic System Alerts (Things that need doing) ---
        $actions = [];
        if ($role === 'teacher' || $role === 'admin') {
            // Check for pending Public Service
            $psSql = "SELECT COUNT(*) FROM public_service_records r JOIN students s ON r.student_id = s.id WHERE r.status = 'pending'";
            $psParams = [];
            
            if ($role === 'teacher') {
                $stmtRoom = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
                $stmtRoom->execute([$user_id]);
                $room = $stmtRoom->fetchColumn();
                if ($room) {
                    $psSql .= " AND s.room = ?";
                    $psParams[] = $room;
                } else {
                    $psSql .= " AND (r.approver_id = ? OR 1=0)"; // Fallback or strict
                    $psParams[] = $user_id;
                }
            }
            
            $stmtPs = $pdo->prepare($psSql);
            $stmtPs->execute($psParams);
            $pendingCount = $stmtPs->fetchColumn();
            
            if ($pendingCount > 0) {
                $actions[] = [
                    'id' => 'alert_ps_pending',
                    'type' => 'action',
                    'title' => 'คำขอสาธารณประโยชน์',
                    'message' => "มีนักเรียนในห้องรอให้คุณอนุมัติกิจกรรม $pendingCount รายการ",
                    'link' => 'admin_public_service.html',
                    'icon' => 'bi bi-heart-pulse-fill',
                    'color' => '#e11d48',
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            // Check for students at risk (Attendance < 80%)
            if ($role === 'admin') {
                // Simplified check: anyone with > 3 absences this month?
                // For now, let's just stick to the PS requests as a clear 'task'
            }
        }

        // Count total unread from DB
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $countStmt->execute([$user_id]);
        $unreadCount = $countStmt->fetchColumn();

        // Format time ago and combine
        $all = array_merge($actions, $notifications);
        foreach ($all as &$n) {
            $n['time_ago'] = formatTimeAgo($n['created_at']);
        }

        echo json_encode([
            'success' => true,
            'data' => $all,
            'unread_count' => (int)$unreadCount + count($actions)
        ]);
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
}

function handlePost($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $id = $data['id'] ?? null;
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
        }
    }
}

function formatTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'เมื่อครู่นี้';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 604800) return floor($diff / 86400) . ' วันที่แล้ว';
    return date('j M y', $time);
}
?>
