<?php
require_once __DIR__ . '/../config.php';

$oldValue = 'สังคมศึกษาศาสนาและวัฒนธรรม';
$newValue = 'สังคมศึกษา ศาสนา และวัฒนธรรม';

$tablesStmt = $pdo->query("SHOW TABLES");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$totalUpdated = 0;

foreach ($tables as $table) {
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        $type = strtolower($column['Type']);
        // Check if column is a text type
        if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
            $colName = $column['Field'];
            $sql = "UPDATE `$table` SET `$colName` = REPLACE(`$colName`, :old, :new) WHERE `$colName` LIKE :search";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':old' => $oldValue,
                ':new' => $newValue,
                ':search' => '%' . $oldValue . '%'
            ]);
            $count = $stmt->rowCount();
            if ($count > 0) {
                echo "Updated $count rows in table '$table', column '$colName'\n";
                $totalUpdated += $count;
            }
        }
    }
}

echo "Total rows updated: $totalUpdated\n";
echo "Done.\n";
