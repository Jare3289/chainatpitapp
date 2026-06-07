<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

$res = [
    'session' => $_SESSION,
    'teacher' => null,
    'bookings' => []
];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get teacher details
    $stmt = $pdo->prepare("SELECT id, department, sub_department, prefix, first_name_th, last_name_th FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $stmt = $pdo->prepare("SELECT id, department, sub_department, prefix, first_name_th, last_name_th FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $res['teacher'] = $me;
    
    if ($me) {
        $teacher_id = $me['id'];
        $stmt = $pdo->prepare("SELECT * FROM supervision_bookings WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $res['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Also let's print ALL bookings in the system to see what exists
$stmt = $pdo->query("SELECT * FROM supervision_bookings");
$res['all_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
