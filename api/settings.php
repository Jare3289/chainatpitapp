<?php
// api/settings.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(['status' => 'success', 'data' => $settings]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    // Handle both JSON and Multipart Form Data
    $data = [];
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : "";
    
    if (strpos($contentType, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        $data = $_POST;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        // Save normal fields
        foreach ($data as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        // Handle File Uploads
        $uploadDir = '../public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileFields = ['school_logo_file' => 'school_logo', 'school_logo_mono_file' => 'school_logo_mono', 'obec_logo_file' => 'obec_logo'];
        foreach ($fileFields as $formField => $dbKey) {
            if (isset($_FILES[$formField]) && $_FILES[$formField]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$formField]['name'], PATHINFO_EXTENSION);
                $filename = $dbKey . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES[$formField]['tmp_name'], $targetPath)) {
                    $dbPath = 'public/uploads/' . $filename;
                    $stmt->execute([$dbKey, $dbPath, $dbPath]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
    exit;
}
?>
