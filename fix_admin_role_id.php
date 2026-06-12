<?php
// Fix: set role_id for admins that were inserted without it
require_once 'config.php';
try {
    $stmt = $pdo->prepare("
        UPDATE users
        SET role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1)
        WHERE role = 'admin' AND role_id IS NULL
    ");
    $stmt->execute();
    $fixed = $stmt->rowCount();
    echo "<b style='color:green'>✅ Fixed {$fixed} admin account(s) — role_id updated.</b>";
} catch (PDOException $e) {
    echo "<b style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</b>";
}
?>
