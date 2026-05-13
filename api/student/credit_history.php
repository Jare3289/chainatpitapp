<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

$is_staff = in_array($_SESSION['role'], ['teacher', 'admin']);
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] === 'student' && !isset($_SESSION['user_id'])) && !$is_staff) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$target_student_id = isset($_GET['student_id']) && $is_staff ? $_GET['student_id'] : null;

try {
    // Get student_id (the primary key in students table) for this user
    if ($target_student_id) {
        $student_internal_id = $target_student_id;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();
        
        if (!$student) throw new Exception('Student record not found');
        $student_internal_id = $student['id'];
    }

    $sql = "SELECT t.*, i.item_name, c.category_name
            FROM point_transactions t
            JOIN point_items i ON t.item_id = i.id
            JOIN point_categories c ON i.category_id = c.id
            WHERE t.student_id = ?
            ORDER BY t.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_internal_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
