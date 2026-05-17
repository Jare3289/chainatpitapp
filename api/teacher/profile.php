<?php
// api/teacher/profile.php
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

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher) {
            $teacher['house'] = $teacher['faculty'] ?? null; // alias สำหรับ house picker
            echo json_encode(['success' => true, 'data' => $teacher]);
        } else {
            // ดึงข้อมูล username จากตาราง users
            $stmtUser = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $stmtUser->execute([$user_id]);
            $user = $stmtUser->fetch();
            
            if ($user && $user['role'] === 'teacher') {
                $uname = $user['username'];
                $stmtFind = $pdo->prepare("SELECT * FROM teachers WHERE email = ? OR teacher_id = ? LIMIT 1");
                $stmtFind->execute([$uname, $uname]);
                $foundTeacher = $stmtFind->fetch();
                
                if ($foundTeacher) {
                    $stmtLink = $pdo->prepare("UPDATE teachers SET user_id = ? WHERE id = ?");
                    $stmtLink->execute([$user_id, $foundTeacher['id']]);
                    $foundTeacher['user_id'] = $user_id;
                    echo json_encode(['success' => true, 'data' => $foundTeacher]);
                    exit;
                }
            }

            $dummyData = [
                'first_name_th' => $user ? $user['username'] : 'ไม่ระบุ',
                'role' => $user ? $user['role'] : '',
                'user_id' => $user_id,
                'username' => $user ? $user['username'] : ''
            ];
            echo json_encode(['success' => true, 'data' => $dummyData]);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'No data provided']);
        exit;
    }

    $response_message = 'บันทึกข้อมูลเรียบร้อยแล้ว';

    // map frontend 'house' field → 'faculty' DB column
    if (!empty($data['house'])) {
        $data['faculty'] = $data['house'];
    }
    unset($data['house']);

    // 1. Update Username/Password in users table
    try {
        $userUpdateParts = [];
        $userParams = [];

        if (isset($data['username']) && !empty($data['username'])) {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$data['username'], $user_id]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว']);
                exit;
            }
            $userUpdateParts[] = "username = ?";
            $userParams[] = $data['username'];
            $_SESSION['username'] = $data['username']; 
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $userUpdateParts[] = "password = ?";
            $userParams[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $response_message = 'อัปเดตข้อมูลและเปลี่ยนรหัสผ่านสำเร็จ';
        }

        if (!empty($userUpdateParts)) {
            $userParams[] = $user_id;
            $sqlUser = "UPDATE users SET " . implode(', ', $userUpdateParts) . " WHERE id = ?";
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute($userParams);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
        exit;
    }

    // 2. Handle Teacher Profile Fields
    $checkTeacher = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $checkTeacher->execute([$user_id]);
    $teacherRow = $checkTeacher->fetch();

    $allowedFields = [
        'prefix', 'first_name_th', 'last_name_th', 'first_name_en', 'last_name_en', 
        'nickname', 'classroom', 'faculty', 'position', 'photo', 'academic_standing', 
        'department', 'department_position', 'admin_position', 'birth_date', 'id_card', 'appointment_date',
        'ethnicity', 'nationality', 'religion', 
        'address_no', 'address_moo', 'address_soi', 'address_road', 
        'address_subdistrict', 'address_district', 'address_province', 'address_zipcode',
        'home_address_no', 'home_address_moo', 'home_address_soi', 'home_address_road',
        'home_address_subdistrict', 'home_address_district', 'home_address_province', 'home_address_zipcode',
        'use_home_address', 'hometown', 'sub_department', 'retirement_year', 'signature',
        'email', 'phone', 'line_id', 'facebook', 'instagram', 'tiktok', 'twitter',
        'weight', 'height', 'blood_group', 'congenital_disease', 'drug_allergies', 
        'food_allergies', 'covid_vaccine', 'health_note',
        'education_history'
    ];

    if ($teacherRow) {
        if (isset($data['photo_base64'])) {
            $base64_string = $data['photo_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
                $data_img = substr($base64_string, strpos($base64_string, ',') + 1);
                $type = strtolower($type[1]);
                if (in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                    $data_img = base64_decode($data_img);
                    if ($data_img !== false) {
                        $upload_dir = '../../public/img/profiles/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        $file_name = 'teacher_' . $user_id . '_' . time() . '.' . $type;
                        $file_path = $upload_dir . $file_name;
                        file_put_contents($file_path, $data_img);
                        $data['photo'] = 'public/img/profiles/' . $file_name;
                    }
                }
            }
        }

        $updateParts = [];
        $params = [];
        foreach ($data as $key => $val) {
            if (in_array($key, $allowedFields)) {
                $updateParts[] = "$key = ?";
                $params[] = ($val === '') ? null : $val;
            }
        }

        if (!empty($updateParts)) {
            try {
                $params[] = $user_id;
                $sql = "UPDATE teachers SET " . implode(', ', $updateParts) . " WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $e) {
                error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
                exit;
            }
        }
    } else {
        // Create new teacher record if it doesn't exist (e.g. for admin)
        $insertFields = ['user_id'];
        $insertValues = [$user_id];
        $placeholders = ['?'];
        
        foreach ($data as $key => $val) {
            if (in_array($key, $allowedFields)) {
                $insertFields[] = $key;
                $insertValues[] = ($val === '') ? null : $val;
                $placeholders[] = '?';
            }
        }
        
        try {
            $sql = "INSERT INTO teachers (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertValues);
        } catch (PDOException $e) {
            // Ignore if insert fails (maybe already created by another process)
        }
    }
    echo json_encode(['success' => true, 'message' => $response_message]);
}
?>
