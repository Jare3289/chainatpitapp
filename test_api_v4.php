<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'teacher';
$_SESSION['username'] = 'test';

$_GET['date'] = '2026-06-15';
$_GET['period'] = 1;

ob_start();
try {
    chdir('api/teacher');
    include 'supervision_get_available_teachers.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Error: " . $e->getMessage();
}
$output = ob_get_clean();

echo "Output: \n" . $output;
