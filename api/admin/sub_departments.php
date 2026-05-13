<?php
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

try {
    // Auto-create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS sub_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        name_th VARCHAR(200) NOT NULL DEFAULT '',
        name_en VARCHAR(200) DEFAULT '',
        abbr_th VARCHAR(50)  DEFAULT '',
        abbr_en VARCHAR(50)  DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($method === 'GET') {
        // GET ?dept_id=X → สาขาย่อยทั้งหมดของกลุ่มสาระนั้น
        $dept_id = $_GET['dept_id'] ?? null;
        if (!$dept_id) {
            echo json_encode(['error' => 'dept_id is required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM sub_departments WHERE department_id = ? ORDER BY id ASC");
        $stmt->execute([$dept_id]);
        echo json_encode(['success' => true, 'subs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE sub_departments SET name_th=?, name_en=?, abbr_th=?, abbr_en=? WHERE id=?");
            $stmt->execute([
                $data['name_th'] ?? '',
                $data['name_en'] ?? '',
                $data['abbr_th'] ?? '',
                $data['abbr_en'] ?? '',
                $id
            ]);
        } else {
            // INSERT
            $dept_id = $data['department_id'] ?? null;
            if (!$dept_id) {
                echo json_encode(['success' => false, 'message' => 'department_id is required']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO sub_departments (department_id, name_th, name_en, abbr_th, abbr_en) VALUES (?,?,?,?,?)");
            $stmt->execute([
                $dept_id,
                $data['name_th'] ?? '',
                $data['name_en'] ?? '',
                $data['abbr_th'] ?? '',
                $data['abbr_en'] ?? ''
            ]);
        }
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM sub_departments WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
