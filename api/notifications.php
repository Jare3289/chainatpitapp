<?php
// api/notifications.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
require_once '../inc/classroom_codes.php';
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
                $stmtRoom = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
                $stmtRoom->execute([$user_id]);
                $room = $stmtRoom->fetchColumn();
                if ($room) {
                    $vars = cnp_classroom_code_variants((string) $room);
                    $ph   = implode(',', array_fill(0, count($vars), '?'));
                    $psSql .= " AND (s.class_name IN ($ph) OR r.approver_id = ?)";
                    foreach ($vars as $v) {
                        $psParams[] = $v;
                    }
                    $psParams[] = $user_id;
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
                    'link' => 'teacher_public_service.html',
                    'icon' => 'bi bi-heart-pulse-fill',
                    'color' => '#e11d48',
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            // Teacher: ยังไม่เช็คชื่อโฮมรูมวันนี้
            if ($role === 'teacher' && isset($room) && $room) {
                $today = date('Y-m-d');
                $varsR = cnp_classroom_code_variants((string) $room);
                $phR = implode(',', array_fill(0, count($varsR), '?'));
                $chkSql = "SELECT COUNT(*) FROM attendance WHERE date = ? AND class_name IN ($phR) AND type = 'daily'";
                $stmtChk = $pdo->prepare($chkSql);
                $stmtChk->execute(array_merge([$today], $varsR));
                $todayChecked = (int) $stmtChk->fetchColumn();

                $hour = (int) date('G');
                if ($todayChecked === 0 && $hour >= 8 && $hour < 18) {
                    $actions[] = [
                        'id' => 'alert_not_checked',
                        'type' => 'action',
                        'title' => 'ยังไม่ได้เช็คชื่อวันนี้',
                        'message' => "ห้อง {$room} ยังไม่มีการเช็คชื่อวันนี้ — กดเพื่อเช็คชื่อ",
                        'link' => 'attendance_daily.html?class_name=' . urlencode($room),
                        'icon' => 'bi-clipboard-x-fill',
                        'color' => '#ef4444',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // Student: dynamic alerts (today's absence summary, PS status)
        if ($role === 'student') {
            $stmtSid = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtSid->execute([$user_id]);
            $sid = (int) ($stmtSid->fetchColumn() ?: 0);
            if ($sid > 0) {
                // วันนี้ถูกบันทึก ขาด/สาย?
                $today = date('Y-m-d');
                $stmtToday = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND date = ? AND type = 'daily' LIMIT 1");
                $stmtToday->execute([$sid, $today]);
                $todayStatus = $stmtToday->fetchColumn();
                if ($todayStatus === 'ขาด' || $todayStatus === 'สาย') {
                    $actions[] = [
                        'id' => 'alert_today_attendance',
                        'type' => 'action',
                        'title' => $todayStatus === 'ขาด' ? '⚠️ วันนี้ถูกบันทึก: ขาด' : '⏰ วันนี้ถูกบันทึก: สาย',
                        'message' => 'กดเพื่อดูประวัติการเช็คชื่อ',
                        'link' => 'student_attendance_history.html',
                        'icon' => 'bi-clipboard2-x-fill',
                        'color' => $todayStatus === 'ขาด' ? '#ef4444' : '#f59e0b',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }

                // คำขอ PS ที่ยังรอ
                $stmtPS = $pdo->prepare("SELECT COUNT(*) FROM public_service_records WHERE student_id = ? AND status = 'pending'");
                $stmtPS->execute([$sid]);
                $pendingMine = (int) $stmtPS->fetchColumn();
                if ($pendingMine > 0) {
                    $actions[] = [
                        'id' => 'alert_my_ps_pending',
                        'type' => 'action',
                        'title' => 'คำขอสาธารณประโยชน์รอตรวจ',
                        'message' => "มีคำขอที่รอครูรับรอง {$pendingMine} รายการ",
                        'link' => 'student_public_service.html',
                        'icon' => 'bi-hourglass-split',
                        'color' => '#a16207',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
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
