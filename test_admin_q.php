<?php
require 'config.php';
// Simulate admin login
session_start();
$_SESSION['user_id'] = 1; 
$_SESSION['role'] = 'admin';

// Execute the same query as in supervision_admin.php
$semester = 1;
$year = 2569;

$stmt = $pdo->prepare("SELECT b.*, 
    t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.photo as t_photo, t.department as t_dept, t.academic_standing as t_standing,
    (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.peer_teacher_id) as peer_name,
    (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.head_teacher_id) as head_name,
    (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.academic_teacher_id) as academic_name
    FROM supervision_bookings b
    JOIN teachers t ON b.teacher_id = t.id
    WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'
    ORDER BY b.booking_date ASC, b.booking_period ASC");
$stmt->execute([$semester, $year]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($bookings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
