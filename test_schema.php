<?php
require 'config.php';
$tables = ['supervision_bookings', 'supervision_evaluations', 'supervision_docs'];
$schema = [];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $schema[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        $schema[$table] = "Not found";
    }
}
echo json_encode($schema, JSON_PRETTY_PRINT);
