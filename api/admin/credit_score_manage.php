<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_categories') {
        $stmt = $pdo->query("SELECT * FROM point_categories ORDER BY is_positive DESC, category_name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM point_items ORDER BY item_name ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'categories' => $categories, 'items' => $items]);
    } 
    elseif ($action === 'submit_transaction') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) throw new Exception('Invalid data');
        
        $student_id = $data['student_id'];
        $item_id = $data['item_id'];
        $points = $data['points'];
        $remark = $data['remark'] ?? '';
        $recorded_by = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("INSERT INTO point_transactions (student_id, item_id, points, remark, recorded_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $item_id, $points, $remark, $recorded_by]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'search_students') {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, student_id, prefix, first_name_th, last_name_th, class_name AS room, number_in_class 
                             FROM students 
                             WHERE student_id LIKE ? OR first_name_th LIKE ? OR last_name_th LIKE ? 
                             LIMIT 10");
        $searchTerm = "%$q%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
