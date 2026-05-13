<?php
// api/student/profile.php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            // Try to link by student_id/username
            $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtUser->execute([$user_id]);
            $uname = $stmtUser->fetchColumn();
            
            if ($uname) {
                $stmtFind = $pdo->prepare("SELECT * FROM students WHERE student_id = ? OR email = ? LIMIT 1");
                $stmtFind->execute([$uname, $uname]);
                $student = $stmtFind->fetch();
                if ($student) {
                    $stmtLink = $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?");
                    $stmtLink->execute([$user_id, $student['id']]);
                }
            }
        }
        
        if ($student) {
            echo json_encode(['success' => true, 'data' => $student]);
        } else {
            // Check total count to rule out DB issues
            $total = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
            echo json_encode(['success' => false, 'error' => "ไม่พบข้อมูลนักเรียน (User ID: $user_id)"]);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { exit; }

    try {
        // Handle Photo upload
        if (isset($data['photo_base64'])) {
            $base64_string = $data['photo_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
                $data_img = substr($base64_string, strpos($base64_string, ',') + 1);
                $type = strtolower($type[1]);
                if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                    $data_img = base64_decode($data_img);
                    $upload_dir = '../../public/img/profiles/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_name = 'student_' . $user_id . '_' . time() . '.' . $type;
                    file_put_contents($upload_dir . $file_name, $data_img);
                    $data['photo'] = 'public/img/profiles/' . $file_name;
                }
            }
        }

        // Filter fields
        $allowed = [
            'prefix', 'first_name_th', 'last_name_th', 'first_name_en', 'last_name_en', 'nickname',
            'id_card', 'birth_date', 'nationality', 'ethnicity', 'religion', 'gender',
            'email', 'phone', 'line_id', 'facebook', 'photo',
            'weight', 'height', 'blood_group', 'congenital_disease', 'drug_allergies', 'food_allergies', 'covid_vaccine',
            'house', 'address_no', 'address_road', 'address_subdistrict', 'address_district', 'address_province', 'address_zipcode',
            'home_address_no', 'home_address_road', 'home_address_subdistrict', 'home_address_district', 'home_address_province', 'home_address_zipcode',
            'father_name', 'mother_name', 'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_occupation', 'guardian_income'
        ];

        $updateParts = [];
        $params = [];
        foreach ($data as $key => $val) {
            if (in_array($key, $allowed)) {
                $updateParts[] = "$key = ?";
                $params[] = ($val === '') ? null : $val;
            }
        }

        if (!empty($updateParts)) {
            $params[] = $user_id;
            $sql = "UPDATE students SET " . implode(', ', $updateParts) . " WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลที่เปลี่ยนแปลง']);
        }

    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'บันทึกข้อมูลไม่สำเร็จ']);
    }
}
