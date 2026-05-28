<?php
// api/me.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();

// Try persistent token auto-login if no active session
if (empty($_SESSION['user_id'])) {
    try { cnp_auth_token_check($pdo); } catch (Exception $e) { error_log('[me] token check: '.$e->getMessage()); }
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

cnp_slide_session();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$userData = [
    'id' => $user_id,
    'username' => $_SESSION['username'],
    'role' => $role
];

try {
    if ($role === 'teacher' || $role === 'admin') {
        $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, academic_standing, position, photo, classroom, faculty FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details && isset($_SESSION['username'])) {
            $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, academic_standing, position, photo, classroom, faculty FROM teachers WHERE teacher_id = ? OR email = ?");
            $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($details && !empty($details['id'])) {
                try { $pdo->prepare("UPDATE teachers SET user_id = ? WHERE id = ?")->execute([$user_id, $details['id']]); } catch (Exception $e) {}
            }
        }

        if ($details) {
            $details['full_name_th'] = trim(($details['prefix'] ?? '') . $details['first_name_th'] . ' ' . $details['last_name_th']);
            $userData = array_merge($userData, $details);
        }
    } else if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details && isset($_SESSION['username'])) {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? OR email = ?");
            $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($details && !empty($details['id'])) {
                try { $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$user_id, $details['id']]); } catch (Exception $e) {}
            }
        }

        if ($details) {
            // Always build full_name_th from parts
            $details['full_name_th'] = trim(($details['prefix'] ?? '') . $details['first_name_th'] . ' ' . $details['last_name_th']);
            
            // Check if profile is complete
            $required_fields = [
                'prefix', 'first_name_th', 'last_name_th', 'first_name_en', 'last_name_en', 'nickname',
                'id_card', 'birth_date', 'birth_sex', 'gender', 'ethnicity', 'nationality', 'religion',
                'child_order', 'phone', 'email', 'line_id', 'facebook', 'instagram',
                'reg_house_no', 'reg_moo', 'reg_soi', 'reg_road', 'reg_village', 'reg_province', 'reg_district', 'reg_subdistrict', 'reg_zipcode',
                'address_status', 'curr_house_no', 'curr_moo', 'curr_soi', 'curr_road', 'curr_village', 'curr_province', 'curr_district', 'curr_subdistrict', 'curr_zipcode',
                'location_coords', 'location_landmark', 'village_headman', 'subdistrict_headman',
                'house_type', 'house_style', 'house_condition', 'house_cleanliness', 'has_electricity', 'has_water', 'has_toilet',
                'dist_to_school', 'travel_time', 'travel_method',
                'f_prefix', 'f_first_name', 'f_last_name', 'f_age', 'f_phone', 'f_education', 'f_job', 'f_workplace', 'f_family_status', 'f_welfare', 'f_income',
                'm_prefix', 'm_first_name', 'm_last_name', 'm_age', 'm_phone', 'm_education', 'm_job', 'm_workplace', 'm_family_status', 'm_welfare', 'm_income',
                'family_status', 'guardian_relation', 'g_income',
                'g_prefix', 'g_first_name', 'g_last_name', 'g_age', 'g_phone', 'g_education', 'g_job', 'g_workplace',
                'total_family_members', 'male_members', 'female_members',
                'full_siblings', 'full_siblings_male', 'full_siblings_female',
                'half_siblings', 'half_siblings_male', 'half_siblings_female',
                'family_relationship',
                'rel_father', 'rel_mother', 'rel_brothers', 'rel_sisters', 'rel_grandparents', 'rel_relatives',
                'time_spent_together', 'allowance_source', 'allowance_per_day', 'responsibilities', 'caregiver_when_away',
                'part_time_job', 'part_time_income', 'weight', 'height', 'blood_group',
                'food_allergies', 'drug_allergies', 'congenital_disease', 'covid_vaccine',
                'internet_access', 'social_media_usage', 'talents', 'interests', 'hobbies'
            ];

            $is_complete = true;
            foreach ($required_fields as $fld) {
                if (!isset($details[$fld]) || trim((string)$details[$fld]) === '') {
                    $is_complete = false;
                    break;
                }
            }
            $details['is_profile_complete'] = $is_complete;
            $userData = array_merge($userData, $details);
        }
    }
} catch (PDOException $e) {
    // Silently fail and just use basic session info if DB error
}

echo json_encode([
    'user'       => $userData,
    'csrf_token' => cnp_csrf_token(),
]);
?>
