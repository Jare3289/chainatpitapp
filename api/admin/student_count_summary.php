<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

cnp_require_auth(['admin']);

$stmt = $pdo->query("
    SELECT
        s.class_name,
        SUM(CASE WHEN s.birth_sex = 'ชาย'  THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN s.birth_sex = 'หญิง' THEN 1 ELSE 0 END) AS female,
        COUNT(*) AS total
    FROM students s
    WHERE s.class_name IS NOT NULL AND s.class_name != ''
    AND (s.enrollment_status IS NULL OR s.enrollment_status NOT IN ('พ้นสภาพ', 'ลาออก', 'สำเร็จการศึกษา'))
    GROUP BY s.class_name
    ORDER BY s.class_name
");

echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
