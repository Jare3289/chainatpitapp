<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$recorded_by = $_SESSION['user_id'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { echo json_encode(['success' => false, 'error' => 'Invalid data']); exit; }

    $student_id = $data['student_id'];
    $item_id = $data['item_id'] ?? null;
    $type = $data['type']; // บวก or ลบ
    $points = $data['points'];
    $reason = $data['reason'] ?? ''; // item_name
    $remark = $data['remark'] ?? '';
    $date = $data['date'];
    
    // Default semester/year
    $semester = 1;
    $academic_year = 2569;

    try {
        if (!$item_id && $reason) {
            $stmt = $pdo->prepare("SELECT id FROM point_items WHERE item_name = ? LIMIT 1");
            $stmt->execute([$reason]);
            $item = $stmt->fetch();
            $item_id = $item ? $item['id'] : null;
        }
        $finalPoints = ($type === 'ลบ' || $type === 'ลบ') ? -abs($points) : abs($points);

        $sql = "INSERT INTO point_transactions (student_id, item_id, points, remark, recorded_by, occurrence_date, semester, academic_year, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $item_id, $finalPoints, $remark, $recorded_by, $date, $semester, $academic_year]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']); }

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'];
    $item_id = $data['item_id'] ?? null;
    $type = $data['type']; // บวก or ลบ
    $points = $data['points'];
    $remark = $data['remark'] ?? '';
    $date = $data['date'];

    try {
        // Verify ownership if teacher
        if ($_SESSION['role'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT recorded_by FROM point_transactions WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() != $recorded_by) {
                echo json_encode(['success' => false, 'error' => 'Forbidden: You can only edit your own records']);
                exit;
            }
        }

        $finalPoints = ($type === 'ลบ' || $type === 'ลบ') ? -abs($points) : abs($points);
        $sql = "UPDATE point_transactions SET item_id = ?, points = ?, remark = ?, occurrence_date = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_id, $finalPoints, $remark, $date, $id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']); }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'];
    try {
        if ($_SESSION['role'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT recorded_by FROM point_transactions WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() != $recorded_by) {
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
        }
        $stmt = $pdo->prepare("DELETE FROM point_transactions WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']); }
}
?>
