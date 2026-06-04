<?php
require 'config.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'teacher';
// Call the logic inside get-timetable-by-day.php manually
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([1]);
$teacher_id = $stmt->fetchColumn();

// Just check for Monday (day_of_week = 1)
$stmt2 = $pdo->prepare("
        SELECT 
            t.period, 
            COALESCE(s.subject_code, t.subject_code, t.subject_name) as subject_code,
            COALESCE(s.subject_name, t.subject_name) as subject_name,
            t.class_name, 
            t.room_location 
        FROM timetable t
        LEFT JOIN subjects s ON s.subject_code = t.subject_name OR s.subject_code = t.subject_code
        WHERE t.teacher_id = ? AND t.day_of_week = ?
        ORDER BY t.period ASC
");
$stmt2->execute([$teacher_id, 1]);
$res = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
