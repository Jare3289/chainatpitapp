<?php
require_once 'config.php';

echo "<h1>Database Diagnostic</h1>";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Found " . count($tables) . " tables:</h3>";
    echo "<ul>";
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "<li><strong>" . htmlspecialchars($t) . "</strong>: $count rows</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Database Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
