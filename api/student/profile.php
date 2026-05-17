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
        $allowed = ['id_card','student_id_card','house','room_id','grade_level_id','faculty','photo','prefix','first_name_th','last_name_th','is_active','full_name_th','first_name_en','last_name_en','nickname','email','gender','birth_sex','ethnicity','nationality','religion','birth_date','child_order','phone','line_id','facebook','instagram','address_status','reg_house_no','reg_soi','reg_road','reg_moo','reg_village','reg_subdistrict','reg_district','reg_province','reg_zipcode','curr_house_no','curr_soi','curr_road','curr_moo','curr_village','curr_subdistrict','curr_district','curr_province','curr_zipcode','location_coords','location_landmark','village_headman','subdistrict_headman','house_type','house_style','house_condition','house_cleanliness','has_electricity','has_water','has_toilet','dist_to_school','travel_time','travel_method','f_prefix','f_first_name','f_last_name','f_age','f_phone','f_education','f_job','f_workplace','f_family_status','f_welfare','f_income','m_prefix','m_first_name','m_last_name','m_age','m_phone','m_education','m_job','m_workplace','m_family_status','m_welfare','m_income','family_status','guardian_relation','g_prefix','g_first_name','g_last_name','g_age','g_phone','g_education','g_job','g_workplace','g_income','total_family_members','male_members','female_members','full_siblings','full_siblings_male','full_siblings_female','half_siblings','half_siblings_male','half_siblings_female','family_relationship','rel_father','rel_mother','rel_brothers','rel_sisters','rel_grandparents','rel_relatives','time_spent_together','allowance_source','allowance_per_day','responsibilities','caregiver_when_away','part_time_job','part_time_income','weight','height','blood_group','food_allergies','drug_allergies','congenital_disease','covid_vaccine','internet_access','social_media_usage','talents','interests','hobbies'];

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
