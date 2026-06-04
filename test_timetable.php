<?php
require 'config.php';
$stmt = $pdo->query("
    SELECT 
        t.period, 
        COALESCE(s.subject_code, t.subject_code, t.subject_name) as subject_code,
        COALESCE(s.subject_name, t.subject_name) as subject_name,
        t.class_name, 
        t.room_location 
    FROM timetable t
    LEFT JOIN subjects s ON s.subject_code = t.subject_name OR s.subject_code = t.subject_code
    WHERE t.subject_name != 'PLC' AND t.subject_name != 'ประชุม'
    LIMIT 5
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
