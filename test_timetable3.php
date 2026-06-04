<?php
require 'config.php';
$stmt = $pdo->query("SELECT 
            t.period, 
            t.day_of_week,
            COALESCE(s.subject_code, t.subject_code, t.subject_name) as subject_code,
            COALESCE(s.subject_name, t.subject_name) as subject_name,
            t.class_name, 
            t.room_location 
        FROM timetable t
        LEFT JOIN subjects s ON s.subject_code = t.subject_name OR s.subject_code = t.subject_code
        WHERE t.teacher_id = 1");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
