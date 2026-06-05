<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/classroom_codes.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

$role   = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['admin', 'teacher'], true)) {
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

// Teacher: get advisory room (required for INSERT, checked for UPDATE)
$advisoryRoom = null;
if ($role === 'teacher') {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS room
                               FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id
                               WHERE t.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $advisoryRoom = $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $advisoryRoom = $stmt->fetchColumn() ?: null;
    }
    if (!$advisoryRoom) {
        http_response_code(403);
        echo json_encode(['error' => 'คุณไม่ได้เป็นครูที่ปรึกษาของห้องใด']);
        exit;
    }
}

$id = $input['id'] ?? null;

// Standardize prefix to full words
if (isset($input['prefix'])) {
    $p = trim($input['prefix']);
    if ($p === 'ด.ช.') $input['prefix'] = 'เด็กชาย';
    elseif ($p === 'ด.ญ.') $input['prefix'] = 'เด็กหญิง';
    elseif ($p === 'น.ส.') $input['prefix'] = 'นางสาว';
}

// ── If first_name_th contains space, split it! ──
if (isset($input['first_name_th']) && strpos(trim($input['first_name_th']), ' ') !== false) {
    $fullname = trim($input['first_name_th']);
    $prefixes = ['เด็กชาย', 'เด็กหญิง', 'นางสาว', 'นาย', 'ด.ช.', 'ด.ญ.', 'น.ส.', 'นาง'];
    
    $prefix = $input['prefix'] ?? '';
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
        $input['first_name_th'] = $firstName;
        if ($prefix !== '') {
            $input['prefix'] = $prefix;
        }
        if ($lastName !== '') {
            $input['last_name_th'] = $lastName;
        }
    }
}

// Allowed fields — must exist in students table
$allowedFields = ['student_id','student_id_card','number_in_class','class_name','house','room_id','grade_level','grade_level_id','faculty','photo','prefix','first_name_th','last_name_th','is_active','enrollment_status','full_name_th','first_name_en','last_name_en','nickname','email','gender','birth_sex','id_card','ethnicity','nationality','religion','birth_date','child_order','phone','line_id','facebook','instagram','address_status','reg_house_no','reg_soi','reg_road','reg_moo','reg_village','reg_subdistrict','reg_district','reg_province','reg_zipcode','curr_house_no','curr_soi','curr_road','curr_moo','curr_village','curr_subdistrict','curr_district','curr_province','curr_zipcode','location_coords','location_landmark','village_headman','subdistrict_headman','house_type','house_style','house_condition','house_cleanliness','has_electricity','has_water','has_toilet','dist_to_school','travel_time','travel_method','f_prefix','f_first_name','f_last_name','f_age','f_phone','f_education','f_job','f_workplace','f_family_status','f_welfare','f_income','m_prefix','m_first_name','m_last_name','m_age','m_phone','m_education','m_job','m_workplace','m_family_status','m_welfare','m_income','family_status','guardian_relation','g_prefix','g_first_name','g_last_name','g_age','g_phone','g_education','g_job','g_workplace','g_income','total_family_members','male_members','female_members','full_siblings','full_siblings_male','full_siblings_female','half_siblings','half_siblings_male','half_siblings_female','family_relationship','rel_father','rel_mother','rel_brothers','rel_sisters','rel_grandparents','rel_relatives','time_spent_together','allowance_source','allowance_per_day','responsibilities','caregiver_when_away','part_time_job','part_time_income','weight','height','blood_group','food_allergies','drug_allergies','congenital_disease','covid_vaccine','internet_access','social_media_usage','talents','interests','hobbies'];

// Validate email domain
$emailInput = trim($input['email'] ?? '');
if ($emailInput !== '' && !str_ends_with(strtolower($emailInput), '@chainatpit.ac.th')) {
    echo json_encode(['error' => 'อีเมลต้องเป็น @chainatpit.ac.th เท่านั้น'], JSON_UNESCAPED_UNICODE);
    exit;
}

$validCols = [];
foreach ($pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN) as $col) {
    $validCols[$col] = true;
}

$validUserCols = [];
foreach ($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN) as $col) {
    $validUserCols[$col] = true;
}
if (isset($input['room']) && !isset($input['class_name'])) {
    $input['class_name'] = $input['room'];
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
        // UPDATE — teacher can only update students in their advisory room
        if ($role === 'teacher') {
            $vars = cnp_classroom_code_variants($advisoryRoom);
            $ph   = implode(',', array_fill(0, count($vars), '?'));
            $chk  = $pdo->prepare("SELECT id, user_id FROM students WHERE id = ? AND class_name IN ($ph) LIMIT 1");
            $chk->execute(array_merge([(int)$id], $vars));
            $currStudent = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$currStudent) {
                $pdo->rollBack();
                echo json_encode(['error' => 'นักเรียนคนนี้ไม่อยู่ในห้องที่ปรึกษาของคุณ'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $chk = $pdo->prepare("SELECT id, user_id FROM students WHERE id = ? LIMIT 1");
            $chk->execute([(int)$id]);
            $currStudent = $chk->fetch(PDO::FETCH_ASSOC);
        }

        if (!$currStudent) throw new Exception("ไม่พบข้อมูลนักเรียน");

        $fieldsToUpdate = [];
        $values = [];
        foreach ($input as $key => $val) {
            if (!in_array($key, $allowedFields, true)) continue;
            if (!isset($validCols[$key]))                continue;
            $fieldsToUpdate[] = "`$key` = ?";
            $values[]         = ($val === '') ? null : $val;
        }
        if ($photo_path) { $fieldsToUpdate[] = "`photo` = ?"; $values[] = $photo_path; }

        if (empty($fieldsToUpdate)) throw new Exception("ไม่มีข้อมูลที่จะอัปเดต");

        $values[] = $id;
        $pdo->prepare("UPDATE students SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?")
            ->execute($values);

        // SYNC WITH USERS TABLE
        if ($currStudent['user_id']) {
            $userFields = [];
            $userVals   = [];

            if (isset($input['student_id']) && isset($validUserCols['username'])) {
                $userFields[] = "username = ?";
                $userVals[]   = trim($input['student_id']);
            }
            if (isset($input['email']) && isset($validUserCols['email'])) {
                $userFields[] = "email = ?";
                $userVals[]   = trim($input['email']);
            }
            
            if (!empty($userFields)) {
                $userVals[] = $currStudent['user_id'];
                $pdo->prepare("UPDATE users SET " . implode(', ', $userFields) . " WHERE id = ?")
                    ->execute($userVals);
            }
        }

        $message = "อัปเดตข้อมูลนักเรียนสำเร็จ";

    } else {
        // INSERT new student
        $stdId     = trim($input['student_id'] ?? '');
        $firstName = trim($input['first_name_th'] ?? '');
        $email     = trim($input['email'] ?? '');

        if (!$stdId)     throw new Exception("กรุณาระบุรหัสนักเรียน");
        if (!$firstName) throw new Exception("กรุณาระบุชื่อ");

        // Check duplicate
        $exists = $pdo->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
        $exists->execute([$stdId]);
        if ($exists->fetchColumn()) throw new Exception("รหัสนักเรียน $stdId มีอยู่แล้ว");

        // Teacher: force class_name to their advisory room
        if ($role === 'teacher') {
            $input['class_name'] = $advisoryRoom;
            if (isset($input['room'])) $input['room'] = $advisoryRoom;
        }

        $studentRoleId = (int)$pdo->query("SELECT id FROM roles WHERE name = 'student' LIMIT 1")->fetchColumn();
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, email, password, role, role_id)
            VALUES (?, ?, ?, 'student', ?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmtUser->execute([$stdId, $email, password_hash('cnp12345', PASSWORD_DEFAULT), $studentRoleId]);
        $userId2 = (int)$pdo->lastInsertId();
        
        if (!$userId2) {
            $g = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $g->execute([$stdId]);
            $userId2 = (int)$g->fetchColumn();
        }

        $fields       = ['user_id'];
        $placeholders = ['?'];
        $values       = [$userId2];

        foreach ($input as $key => $val) {
            if (!in_array($key, $allowedFields, true)) continue;
            if (!isset($validCols[$key]))                continue;
            if ($val === '' || $val === null)            continue;
            $fields[]       = "`$key`";
            $placeholders[] = '?';
            $values[]       = $val;
        }
        if ($photo_path) { $fields[] = "`photo`"; $placeholders[] = '?'; $values[] = $photo_path; }

        $pdo->prepare("INSERT INTO students (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")")
            ->execute($values);

        $message = "เพิ่มนักเรียนใหม่สำเร็จ (Username: $stdId / รหัสผ่าน: cnp12345)";
    }

    $pdo->commit();
    $resp = ['success' => true, 'message' => $message];
    if ($photo_path) $resp['photo_path'] = $photo_path;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[update_student] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
