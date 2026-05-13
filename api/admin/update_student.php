<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

// Auth
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$id = $input['id'] ?? null;

// Allowed fields — must exist in students table
$allowedFields = [
    'student_id', 'prefix', 'first_name_th', 'last_name_th', 'first_name_en', 'last_name_en',
    'nickname', 'id_card', 'birth_date', 'nationality', 'ethnicity', 'religion',
    'grade_level', 'room', 'number_in_class', 'faculty', 'house',
    'phone', 'email', 'line_id', 'facebook',
    'address_status',
    'curr_house_no', 'curr_moo', 'curr_soi', 'curr_road', 'curr_subdistrict',
    'curr_district', 'curr_province', 'curr_zipcode',
    'reg_house_no', 'reg_moo', 'reg_soi', 'reg_road',
    'reg_subdistrict', 'reg_district', 'reg_province', 'reg_zipcode',
    'f_prefix', 'f_first_name', 'f_last_name', 'f_age', 'f_phone', 'f_job', 'f_income',
    'm_prefix', 'm_first_name', 'm_last_name', 'm_age', 'm_phone', 'm_job', 'm_income',
    'g_prefix', 'g_first_name', 'g_last_name', 'g_age', 'g_phone', 'g_job', 'g_income',
    'guardian_relation',
    'weight', 'height', 'blood_group', 'food_allergies', 'drug_allergies', 'congenital_disease',
];

// Verify columns actually exist in DB (defensive — also normalize legacy `class_name`)
$validCols = [];
foreach ($pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN) as $col) {
    $validCols[$col] = true;
}
if (isset($input['class_name']) && !isset($input['room'])) {
    $input['room'] = $input['class_name'];     // legacy alias
}

try {
    $pdo->beginTransaction();

    // Photo upload
    $photo_path = null;
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../public/uploads/students/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
            $filename = 'student_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photo_path = 'public/uploads/students/' . $filename;
            }
        }
    }

    if ($id) {
        // UPDATE existing student — only fields actually provided
        $fieldsToUpdate = [];
        $values = [];
        foreach ($input as $key => $val) {
            if (!in_array($key, $allowedFields, true)) continue;
            if (!isset($validCols[$key]))                continue;
            $fieldsToUpdate[] = "`$key` = ?";
            $values[]         = ($val === '') ? null : $val;
        }
        if ($photo_path) {
            $fieldsToUpdate[] = "`photo` = ?";
            $values[]         = $photo_path;
        }

        if (empty($fieldsToUpdate)) {
            throw new Exception("ไม่มีข้อมูลที่จะอัปเดต");
        }

        $values[] = $id;
        $pdo->prepare("UPDATE students SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?")
            ->execute($values);
        $message = "อัปเดตข้อมูลนักเรียนสำเร็จ";
    } else {
        // INSERT new student
        $stdId = trim($input['student_id'] ?? '');
        $firstName = trim($input['first_name_th'] ?? '');
        if (!$stdId)     throw new Exception("กรุณาระบุรหัสนักเรียน");
        if (!$firstName) throw new Exception("กรุณาระบุชื่อ");

        // Check duplicate
        $exists = $pdo->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $exists->execute([$stdId]);
        if ($exists->fetchColumn()) {
            throw new Exception("รหัสนักเรียน $stdId มีอยู่แล้ว");
        }

        // Get student role id
        $studentRoleId = (int)$pdo->query("SELECT id FROM roles WHERE name = 'student' LIMIT 1")->fetchColumn();

        // Create user account
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, password, role, role_id)
            VALUES (?, ?, 'student', ?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmtUser->execute([$stdId, password_hash('cnp12345', PASSWORD_DEFAULT), $studentRoleId]);
        $userId = $pdo->lastInsertId();
        if (!$userId) {
            $g = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $g->execute([$stdId]);
            $userId = $g->fetchColumn();
        }

        // Insert student — only fields provided + that exist
        $fields       = ['user_id'];
        $placeholders = ['?'];
        $values       = [$userId];

        foreach ($input as $key => $val) {
            if (!in_array($key, $allowedFields, true)) continue;
            if (!isset($validCols[$key]))                continue;
            if ($val === '' || $val === null)            continue;
            $fields[]       = "`$key`";
            $placeholders[] = '?';
            $values[]       = $val;
        }
        if ($photo_path) {
            $fields[]       = "`photo`";
            $placeholders[] = '?';
            $values[]       = $photo_path;
        }

        $sql = "INSERT INTO students (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $pdo->prepare($sql)->execute($values);
        $message = "เพิ่มนักเรียนใหม่สำเร็จ (Username: $stdId / รหัสผ่าน: cnp12345)";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[update_student] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
