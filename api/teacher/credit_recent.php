<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/classroom_codes.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'all'; // 'all', 'my', 'room'

try {
    $whereClauses = ["1=1"];
    $params = [];

    if ($_SESSION['role'] === 'teacher') {
        $whereClauses[] = "t.recorded_by = ?";
        $params[] = $user_id;
    } elseif ($type === 'room') {
        // Keep room type for other potential uses, but teachers are now locked to 'my' by default or choice
        $stmtRoom = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
        $stmtRoom->execute([$user_id]);
        $room = $stmtRoom->fetchColumn();
        if ($room) {
            $vars = cnp_classroom_code_variants((string) $room);
            $ph   = implode(',', array_fill(0, count($vars), '?'));
            $whereClauses[] = "s.class_name IN ($ph)";
            foreach ($vars as $v) {
                $params[] = $v;
            }
        }
    } elseif ($type === 'my') {
        $whereClauses[] = "t.recorded_by = ?";
        $params[] = $user_id;
    }

    $whereSql = implode(" AND ", $whereClauses);

    $sql = "SELECT 
                t.id, 
                t.item_id,
                t.points, 
                t.remark, 
                t.created_at,
                s.student_id,
                s.first_name_th,
                s.last_name_th,
                s.class_name AS room,
                i.item_name as reason,
                u.username as teacher_name
            FROM point_transactions t
            JOIN students s ON t.student_id = s.id
            JOIN users u ON t.recorded_by = u.id
            LEFT JOIN point_items i ON t.item_id = i.id
            WHERE $whereSql
            ORDER BY t.created_at DESC
            LIMIT 100";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as &$item) {
        $item['student_name'] = $item['first_name_th'] . ' ' . $item['last_name_th'];
    }

    echo json_encode([
        'success' => true, 
        'items' => $items,
        'room' => $type === 'room' ? ($room ?? null) : null
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
