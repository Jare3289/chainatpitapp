<?php
require_once 'config.php';

echo "<h1>Database Import Script</h1>";
echo "Reading SQL file...<br>";

$sqlFile = __DIR__ . '/admin_cnpapp (3).sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at " . htmlspecialchars($sqlFile));
}

echo "File found successfully! Size: " . number_format(filesize($sqlFile) / 1024 / 1024, 2) . " MB<br>";
echo "Executing queries (this may take a minute)...<br>";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("SET SESSION innodb_strict_mode = 0;");
    
    echo "Cleaning up old tables...<br>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    
    echo "Importing new data (line-by-line parsing)...<br>";
    
    $handle = fopen($sqlFile, 'r');
    if (!$handle) {
        throw new Exception("Cannot open SQL dump file.");
    }
    
    $queryBuffer = '';
    $delimiter = ';';
    $queryCount = 0;
    
    while (($line = fgets($handle)) !== false) {
        $trimmed = trim($line);
        
        // Skip comments and empty lines
        if ($trimmed === '') {
            continue;
        }
        if (substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') {
            continue;
        }
        if (substr($trimmed, 0, 2) === '/*' && substr($trimmed, 0, 3) !== '/*!') {
            continue;
        }
        
        // Check for delimiter changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = trim($matches[1]);
            continue;
        }
        
        $queryBuffer .= $line;
        
        // Check if the query is complete
        $trimmedBuffer = rtrim($queryBuffer);
        if (substr($trimmedBuffer, -strlen($delimiter)) === $delimiter) {
            // Strip delimiter
            $queryToExecute = substr($trimmedBuffer, 0, -strlen($delimiter));
            $trimmedQuery = trim($queryToExecute);
            
            if ($trimmedQuery !== '') {
                $pdo->exec($trimmedQuery);
                $queryCount++;
            }
            $queryBuffer = '';
        }
    }
    
    fclose($handle);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h2 style='color:green;'>Import completed successfully! 🎉</h2>";
    echo "<p>Successfully executed " . number_format($queryCount) . " queries. All tables, triggers, and data have been created.</p>";
    echo "<a href='/'>Go to App</a>";
} catch (Exception $e) {
    // Re-enable FK checks in case of error
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch(Exception $ex) {}
    
    echo "<h2 style='color:red;'>Import Failed</h2>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
