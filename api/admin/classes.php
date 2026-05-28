<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level VARCHAR(50) NOT NULL,
        room VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM classes ORDER BY level ASC, room ASC");
        echo json_encode(['classes' => $stmt->fetchAll()]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO classes (level, room) VALUES (?, ?)");
        $stmt->execute([$data['level'], $data['room']]);
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
