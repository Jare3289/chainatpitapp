<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT user_id, email, teacher_id FROM teachers WHERE id = 66 OR teacher_id = 66 LIMIT 1");
print_r($stmt->fetch());
