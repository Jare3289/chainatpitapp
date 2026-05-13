<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode([]);
    exit;
}

$grade = $_GET['grade'] ?? '';

try {
    if ($grade) {
        $stmt = $pdo->prepare("SELECT DISTINCT room FROM students WHERE grade_level = ? AND is_active = 1 ORDER BY room ASC");
        $stmt->execute([$grade]);
    } else {
        $stmt = $pdo->query("SELECT DISTINCT room FROM students WHERE is_active = 1 ORDER BY room ASC");
    }
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rooms);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
