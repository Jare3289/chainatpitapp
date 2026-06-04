<?php
require 'config.php';
// Simulate login
session_start();
$_SESSION['user_id'] = 1; // Need to know a valid user_id
$_SESSION['role'] = 'teacher';
$_SESSION['username'] = 'admin'; // just to make it pass teacher id check if needed
// Actually let's just query the DB directly to see if any booking has empty subject
$stmt = $pdo->query("SELECT id, subject_code, subject_name FROM supervision_bookings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
