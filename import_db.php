<?php
require_once 'config.php';

echo "<h1>Database Import Script</h1>";
echo "Reading SQL file...<br>";

$sqlFile = __DIR__ . '/admin_cnpapp (3).sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at " . htmlspecialchars($sqlFile));
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die("Error: Failed to read the SQL file.");
}

echo "File read successfully! Size: " . number_format(strlen($sql) / 1024 / 1024, 2) . " MB<br>";
echo "Executing queries (this may take a minute)...<br>";

// We need to disable foreign key checks during import
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    echo "Cleaning up old tables...<br>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    
    echo "Importing new data...<br>";
    // Execute the entire SQL dump
    $pdo->exec($sql);
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h2 style='color:green;'>Import completed successfully! 🎉</h2>";
    echo "<p>All tables and data have been created. You can now use your web application.</p>";
    echo "<a href='/'>Go to App</a>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Import Failed</h2>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
