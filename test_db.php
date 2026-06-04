<?php
require 'config.php';
try {
    $stmt = $pdo->query('DESCRIBE teachers');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cols);
} catch (Exception $e) {
    echo $e->getMessage();
}
