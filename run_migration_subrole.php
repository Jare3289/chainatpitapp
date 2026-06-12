<?php
// One-off migration script — delete after running
require_once 'config.php';

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'sub_role'");
    if ($check->rowCount() > 0) {
        echo "<b style='color:green'>✅ Column sub_role already exists — nothing to do.</b>";
        exit;
    }

    $pdo->exec("ALTER TABLE users ADD COLUMN sub_role VARCHAR(50) DEFAULT NULL AFTER role");
    echo "<b style='color:green'>✅ Migration complete: sub_role column added to users table.</b>";
} catch (PDOException $e) {
    echo "<b style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</b>";
}
?>
