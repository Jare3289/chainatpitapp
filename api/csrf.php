<?php
// api/csrf.php — frontend เรียกเพื่อรับ CSRF token หลัง login
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo json_encode(['csrf_token' => cnp_csrf_token()]);
