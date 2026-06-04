<?php
require_once __DIR__ . '/../config.php';

$searchString = '-ไม่มีวิทยฐานะ-';
$replaceString = 'ไม่มีวิทยฐานะ';

// Get all tables
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$totalUpdated = 0;

foreach ($tables as $table) {
    // Get columns for the table
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        $type = strtolower($column['Type']);
        // Only target text-based columns
        if (strpos($type, 'varchar') !== false || strpos($type, 'text') !== false || strpos($type, 'char') !== false) {
            $colName = $column['Field'];
            
            // Execute UPDATE
            try {
                $sql = "UPDATE `$table` SET `$colName` = REPLACE(`$colName`, ?, ?) WHERE `$colName` LIKE ?";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute([$searchString, $replaceString, "%$searchString%"]);
                
                $count = $updateStmt->rowCount();
                if ($count > 0) {
                    echo "Updated $count rows in table `$table`, column `$colName`.\n";
                    $totalUpdated += $count;
                }
            } catch (Exception $e) {
                // Ignore errors for individual columns
            }
        }
    }
}

echo "Total rows updated for '$searchString' -> '$replaceString': $totalUpdated\n";
