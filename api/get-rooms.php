<?php
/**
 * api/get-rooms.php
 * Returns the list of classroom codes from the rooms table.
 * Accessible to any authenticated role (teacher, admin, student).
 */
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();

$user = cnp_require_auth(['teacher', 'admin', 'student']);

try {
    $stmt = $pdo->query("SELECT classroom_code, grade_level FROM rooms ORDER BY classroom_code ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rooms]);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'data' => []]);
}
