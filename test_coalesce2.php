<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, subject_code, subject_name FROM timetable WHERE subject_name LIKE '%ท32101%' LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
