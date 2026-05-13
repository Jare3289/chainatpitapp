<?php
header('Content-Type: application/json');
require_once '../config.php';

$type = $_GET['type'] ?? '';
$parentId = $_GET['parentId'] ?? '';

try {
    if ($type === 'provinces') {
        $stmt = $pdo->query("SELECT id, name FROM provinces ORDER BY name ASC");
        echo json_encode($stmt->fetchAll());
    } 
    elseif ($type === 'districts' && $parentId) {
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE province_id = ? ORDER BY name ASC");
        $stmt->execute([$parentId]);
        echo json_encode($stmt->fetchAll());
    } 
    elseif ($type === 'subdistricts' && $parentId) {
        $stmt = $pdo->prepare("SELECT id, name, postcode FROM subdistricts WHERE district_id = ? ORDER BY name ASC");
        $stmt->execute([$parentId]);
        echo json_encode($stmt->fetchAll());
    } 
    else {
        echo json_encode(['error' => 'Invalid parameters']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
