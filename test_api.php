<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'teacher';
$_SESSION['username'] = 'test';

$options = [
    'http' => [
        'header' => "Cookie: PHPSESSID=" . session_id() . "\r\n"
    ]
];
$context = stream_context_create($options);
$output = file_get_contents('http://localhost:8000/api/teacher/supervision_get_available_teachers.php?date=2026-06-15&period=1', false, $context);

echo "Output: \n" . $output;
