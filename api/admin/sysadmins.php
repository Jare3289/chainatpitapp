<?php
// api/admin/sysadmins.php - CRUD for system administrators
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$currentUserId = $_SESSION['user_id'];

// GET - List all admins
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT u.id as user_id, u.username, t.first_name_th, t.last_name_th, t.position, t.photo
            FROM users u
            LEFT JOIN teachers t ON t.user_id = u.id
            WHERE u.role = 'admin'
            ORDER BY u.id ASC
        ");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $admins, 'current_user_id' => (int)$currentUserId]);
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
}

// POST - Add new admin
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? 'cnp12345';
    $firstName = trim($input['first_name_th'] ?? '');
    $lastName = trim($input['last_name_th'] ?? '');
    $position = trim($input['position'] ?? 'ผู้ดูแลระบบ');

    if (!$username || !$firstName) {
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุ Username และชื่อ']);
        exit;
    }

    try {
        // Check duplicate username
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Username นี้มีอยู่ในระบบแล้ว']);
            exit;
        }

        $pdo->beginTransaction();

        // Create user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$username, $hash]);
        $newUserId = $pdo->lastInsertId();

        // Create teacher profile for admin
        $stmt2 = $pdo->prepare("INSERT INTO teachers (user_id, first_name_th, last_name_th, position) VALUES (?, ?, ?, ?)");
        $stmt2->execute([$newUserId, $firstName, $lastName, $position]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'เพิ่มผู้ดูแลระบบเรียบร้อยแล้ว']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
}

// DELETE - Remove admin (cannot remove self)
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบ ID ที่ต้องการลบ']);
        exit;
    }

    if ((int)$id === (int)$currentUserId) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบบัญชีตัวเองได้']);
        exit;
    }

    // Count total admins - must have at least 1
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $totalAdmins = $countStmt->fetchColumn();
        if ($totalAdmins <= 1) {
            echo json_encode(['success' => false, 'error' => 'ต้องมีผู้ดูแลระบบอย่างน้อย 1 คน']);
            exit;
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$id]);
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'ลบผู้ดูแลระบบเรียบร้อยแล้ว']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
}
?>
