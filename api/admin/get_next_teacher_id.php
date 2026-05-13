<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$dept = $_GET['dept'] ?? '';
if (!$dept) {
    exit(json_encode(['id' => '']));
}

try {
    // Find max numerical teacher_id for this department
    // Use CAST to ensure numerical sorting so 100 > 99
    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE department = ? AND teacher_id REGEXP '^[0-9]+$' ORDER BY CAST(teacher_id AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute([$dept]);
    $lastId = $stmt->fetchColumn();

    if ($lastId && is_numeric($lastId)) {
        echo json_encode(['id' => (int)$lastId + 1]);
    } else {
        // Fallback: look for ANY teacher in this dept to guess prefix, 
        // or use a hardcoded map for new departments.
        $deptMap = [
            'คณิตศาสตร์' => 100,
            'ภาษาไทย' => 200,
            'สังคมศึกษา ศาสนา และวัฒนธรรม' => 300,
            'วิทยาศาสตร์และเทคโนโลยี' => 400,
            'ภาษาต่างประเทศ' => 500,
            'การงานอาชีพ' => 600,
            'ศิลปะ' => 700,
            'สุขศึกษาและพลศึกษา' => 800,
            'แนะแนว' => 900,
            'รองผู้อำนวยการ' => 1001,
            'ผู้อำนวยการ' => 1000
        ];
        
        $startNode = 100;
        foreach($deptMap as $key => $val) {
            if (strpos($dept, $key) !== false) {
                $startNode = $val;
                break;
            }
        }
        
        echo json_encode(['id' => $startNode + 1]);
    }
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
