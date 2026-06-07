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
    // Update username to match student_id (only if student_id is not null/empty) and sync email
    $pdo->exec("
        UPDATE users u
        JOIN students s ON s.user_id = u.id
        SET u.username = COALESCE(NULLIF(TRIM(s.student_id), ''), u.username),
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
    // 6. Split full_name_th or first_name_th into prefix, first_name_th, last_name_th if needed
    $prefixes = ['เด็กชาย', 'เด็กหญิง', 'นางสาว', 'นาย', 'ด.ช.', 'ด.ญ.', 'น.ส.', 'นาง'];

    // Case A: first_name_th is empty but full_name_th has value
    $stmtA = $pdo->query("SELECT id, full_name_th, prefix, last_name_th FROM students WHERE (first_name_th IS NULL OR TRIM(first_name_th) = '') AND full_name_th IS NOT NULL AND TRIM(full_name_th) != ''");
    $studentsA = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    $updateStmtA = $pdo->prepare("UPDATE students SET prefix = ?, first_name_th = ?, last_name_th = ? WHERE id = ?");
    
    foreach ($studentsA as $row) {
        $fullname = trim($row['full_name_th']);
        $prefix = trim($row['prefix'] ?? '');
        $lastName = trim($row['last_name_th'] ?? '');
        
        // Extract prefix if present in fullname
        foreach ($prefixes as $pfx) {
            if (mb_strpos($fullname, $pfx) === 0) {
                if (empty($prefix)) {
                    $prefix = $pfx;
                }
                $fullname = trim(mb_substr($fullname, mb_strlen($pfx)));
                break;
            }
        }
        
        $parts = preg_split('/\s+/', $fullname, 2);
        $firstName = $parts[0] ?? '';
        if (empty($lastName)) {
            $lastName = $parts[1] ?? '';
        }
        
        if ($firstName !== '') {
            $updateStmtA->execute([$prefix ?: null, $firstName, $lastName ?: null, $row['id']]);
        }
    }

    // Case B: first_name_th contains space and last_name_th is empty
    $stmtB = $pdo->query("SELECT id, prefix, first_name_th FROM students WHERE (first_name_th IS NOT NULL AND first_name_th LIKE '% %') AND (last_name_th IS NULL OR TRIM(last_name_th) = '')");
    $studentsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    $updateStmtB = $pdo->prepare("UPDATE students SET prefix = ?, first_name_th = ?, last_name_th = ? WHERE id = ?");
    
    foreach ($studentsB as $row) {
        $fullname = trim($row['first_name_th']);
        $prefix = trim($row['prefix'] ?? '');
        
        // Extract prefix if present
        foreach ($prefixes as $pfx) {
            if (mb_strpos($fullname, $pfx) === 0) {
                if (empty($prefix)) {
                    $prefix = $pfx;
                }
                $fullname = trim(mb_substr($fullname, mb_strlen($pfx)));
                break;
            }
        }
        
        $parts = preg_split('/\s+/', $fullname, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';
        
        if ($firstName !== '') {
            $updateStmtB->execute([$prefix ?: null, $firstName, $lastName ?: null, $row['id']]);
        }
    }

    // Case C: first_name_th starts with a prefix (without space) and prefix is empty
    $stmtC = $pdo->query("SELECT id, prefix, first_name_th FROM students WHERE (prefix IS NULL OR TRIM(prefix) = '') AND first_name_th IS NOT NULL AND TRIM(first_name_th) != ''");
    $studentsC = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    $updateStmtC = $pdo->prepare("UPDATE students SET prefix = ?, first_name_th = ? WHERE id = ?");
    
    foreach ($studentsC as $row) {
        $fullname = trim($row['first_name_th']);
        foreach ($prefixes as $pfx) {
            if (mb_strpos($fullname, $pfx) === 0) {
                $prefix = $pfx;
                $firstName = trim(mb_substr($fullname, mb_strlen($pfx)));
                if ($firstName !== '') {
                    $updateStmtC->execute([$prefix, $firstName, $row['id']]);
                }
                break;
            }
        }
    }

    // 7. Standardize Prefixes
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
