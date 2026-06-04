<?php
require 'config.php';
$stmt = $pdo->query("SELECT * FROM subjects WHERE subject_code LIKE '%ท32101%'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
