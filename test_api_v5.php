<?php
session_start();
$_SESSION['user_id'] = 6727; 
$_SESSION['role'] = 'teacher';
$_SESSION['username'] = 'test';

$_GET['day'] = 1;

ob_start();
try {
    chdir('api/teacher');
    include 'get-timetable-by-day.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Error: " . $e->getMessage();
}
$output = ob_get_clean();

echo "Output: \n" . $output;
