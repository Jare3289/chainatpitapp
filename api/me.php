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
        $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, student_id, photo, COALESCE(NULLIF(class_name,''), room) AS class_name, grade_level, number_in_class FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details && isset($_SESSION['username'])) {
            $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th, student_id, photo, COALESCE(NULLIF(class_name,''), room) AS class_name, grade_level, number_in_class FROM students WHERE student_id = ? OR email = ?");
            $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($details && !empty($details['id'])) {
                try { $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$user_id, $details['id']]); } catch (Exception $e) {}
            }
        }

        if ($details) {
            // Always build full_name_th from parts
            $details['full_name_th'] = trim(($details['prefix'] ?? '') . $details['first_name_th'] . ' ' . $details['last_name_th']);
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
