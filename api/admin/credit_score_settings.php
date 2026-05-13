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
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM point_categories ORDER BY is_positive DESC, category_name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM point_items ORDER BY item_name ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'categories' => $categories, 'items' => $items]);
    }
    elseif ($action === 'save_item') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $category_id = $data['category_id'];
        $item_name = $data['item_name'];
        $points = $data['points'];
        
        if ($id) {
            $stmt = $pdo->prepare("UPDATE point_items SET category_id = ?, item_name = ?, points = ? WHERE id = ?");
            $stmt->execute([$category_id, $item_name, $points, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO point_items (category_id, item_name, points) VALUES (?, ?, ?)");
            $stmt->execute([$category_id, $item_name, $points]);
        }
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete_item') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM point_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
