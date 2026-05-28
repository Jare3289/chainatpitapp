<?php
// api/student/profile.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();

        if (!$student) {
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
            echo json_encode(['success' => true, 'data' => $student], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            echo json_encode(['success' => false, 'error' => "ไม่พบข้อมูลนักเรียน (User ID: $user_id)"], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] GET ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cnp_csrf_verify();

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Handle photo upload
        if (!empty($data['photo_base64'])) {
            $b64 = $data['photo_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $b64, $typeMatch)) {
                $imgData  = base64_decode(substr($b64, strpos($b64, ',') + 1));
                $ext      = strtolower($typeMatch[1]);
                if (in_array($ext, ['jpg', 'jpeg', 'png']) && $imgData !== false) {
                    $dir = '../../public/img/profiles/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fname = 'student_' . $user_id . '_' . time() . '.' . $ext;
                    file_put_contents($dir . $fname, $imgData);
                    $data['photo'] = 'public/img/profiles/' . $fname;
                }
            }
        }
        unset($data['photo_base64']);

        // Allowed student-editable fields
        $allowed = [
            'photo','prefix','first_name_th','last_name_th','full_name_th',
            'first_name_en','last_name_en','nickname','number_in_class','house',
            'email','gender','birth_sex','ethnicity','nationality','religion',
            'birth_date','child_order','id_card','student_id_card','phone',
            'line_id','facebook','instagram',
            'address_status',
            'reg_house_no','reg_soi','reg_road','reg_moo','reg_village',
            'reg_province','reg_district','reg_subdistrict','reg_zipcode',
            'curr_house_no','curr_soi','curr_road','curr_moo','curr_village',
            'curr_province','curr_district','curr_subdistrict','curr_zipcode',
            'location_coords','location_landmark','village_headman','subdistrict_headman',
            'house_type','house_style','house_condition','house_cleanliness',
            'has_electricity','has_water','has_toilet',
            'dist_to_school','travel_time','travel_method',
            'f_prefix','f_first_name','f_last_name','f_age','f_phone',
            'f_education','f_job','f_workplace','f_family_status','f_welfare','f_income',
            'm_prefix','m_first_name','m_last_name','m_age','m_phone',
            'm_education','m_job','m_workplace','m_family_status','m_welfare','m_income',
            'family_status','guardian_relation',
            'g_prefix','g_first_name','g_last_name','g_age','g_phone',
            'g_education','g_job','g_workplace','g_income',
            'total_family_members','male_members','female_members',
            'full_siblings','full_siblings_male','full_siblings_female',
            'half_siblings','half_siblings_male','half_siblings_female',
            'family_relationship',
            'rel_father','rel_mother','rel_brothers','rel_sisters','rel_grandparents','rel_relatives',
            'time_spent_together','allowance_source','allowance_per_day',
            'responsibilities','caregiver_when_away','part_time_job','part_time_income',
            'weight','height','blood_group',
            'food_allergies','drug_allergies','congenital_disease','covid_vaccine',
            'internet_access','social_media_usage','talents','interests','hobbies',
        ];

        $updateParts = [];
        $params      = [];
        foreach ($data as $key => $val) {
            if (!in_array($key, $allowed, true)) continue;
            // Don't nullify house/ENUM fields that were submitted empty — skip them
            if (in_array($key, ['house'], true) && ($val === '' || $val === null)) continue;
            $updateParts[] = "`$key` = ?";
            $params[]      = ($val === '') ? null : $val;
        }

        if (empty($updateParts)) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลที่เปลี่ยนแปลง'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        $params[] = $user_id;
        $sql = 'UPDATE students SET ' . implode(', ', $updateParts) . ' WHERE user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] POST ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'บันทึกข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
