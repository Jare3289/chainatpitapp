<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, subject_code, subject_name FROM supervision_bookings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
