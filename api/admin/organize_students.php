<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Sync room_id and class_name from rooms table
    // If room_id exists, update class_name and grade_level
    $pdo->exec("
        UPDATE students s
        JOIN rooms r ON s.room_id = r.id
        SET s.class_name = r.classroom_code,
            s.grade_level = r.grade_level,
            s.house = COALESCE(s.house, r.house)
    ");

    // 2. If room_id is NULL but class_name exists, try to find room_id
    $pdo->exec("
        UPDATE students s
        JOIN rooms r ON s.class_name = r.classroom_code
        SET s.room_id = r.id
        WHERE s.room_id IS NULL
    ");

    // 3. Standardize House names (trim and fix common variations if any)
    // (Assuming they are already mostly correct but just in case)
    $pdo->exec("UPDATE students SET house = TRIM(house) WHERE house IS NOT NULL");

    // 4. Synchronize Users table
    // Update username to match student_id and sync email
    $pdo->exec("
        UPDATE users u
        JOIN students s ON s.user_id = u.id
        SET u.username = s.student_id,
            u.email = s.email
    ");

    // 5. Categorize: Ensure grade_level is in a standard format if it's mixed
    // e.g., "ม.1" -> "มัธยมศึกษาปีที่ 1"
    $mapping = [
        'ม.1' => 'มัธยมศึกษาปีที่ 1',
        'ม.2' => 'มัธยมศึกษาปีที่ 2',
        'ม.3' => 'มัธยมศึกษาปีที่ 3',
        'ม.4' => 'มัธยมศึกษาปีที่ 4',
        'ม.5' => 'มัธยมศึกษาปีที่ 5',
        'ม.6' => 'มัธยมศึกษาปีที่ 6',
    ];
    foreach ($mapping as $short => $long) {
        $stmt = $pdo->prepare("UPDATE students SET grade_level = ? WHERE grade_level = ?");
        $stmt->execute([$long, $short]);
    }

    // 6. Standardize Prefixes
    $pdo->exec("UPDATE students SET prefix = 'เด็กชาย' WHERE prefix = 'ด.ช.'");
    $pdo->exec("UPDATE students SET prefix = 'เด็กหญิง' WHERE prefix = 'ด.ญ.'");
    $pdo->exec("UPDATE students SET prefix = 'นางสาว' WHERE prefix = 'น.ส.'");

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'จัดระเบียบข้อมูลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
