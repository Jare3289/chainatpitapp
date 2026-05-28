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
    // Ensure base table exists (with old simple schema is OK)
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure sub_departments table exists
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

    // Get existing columns
    $existingCols = array_column(
        $pdo->query("SHOW COLUMNS FROM departments")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    // Add new columns if missing (nullable to avoid ALTER errors on existing rows)
    if (!in_array('name_th', $existingCols)) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN name_th VARCHAR(200) NOT NULL DEFAULT '' AFTER id");
        // Copy old 'name' data into name_th
        if (in_array('name', $existingCols)) {
            $pdo->exec("UPDATE departments SET name_th = `name` WHERE name_th = ''");
        }
    }
    if (!in_array('name_en',   $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN name_en   VARCHAR(200) DEFAULT '' AFTER name_th");
    if (!in_array('abbr_th',   $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN abbr_th   VARCHAR(50)  DEFAULT '' AFTER name_en");
    if (!in_array('abbr_en',   $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN abbr_en   VARCHAR(50)  DEFAULT '' AFTER abbr_th");
    if (!in_array('head_name', $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN head_name VARCHAR(200) DEFAULT '' AFTER abbr_en");
    if (!in_array('location',  $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN location  VARCHAR(50)  DEFAULT '' AFTER head_name");
    if (!in_array('color',     $existingCols)) $pdo->exec("ALTER TABLE departments ADD COLUMN color     VARCHAR(7)   DEFAULT '#1e3c72' AFTER location");
    if (!in_array('icon',      $existingCols)) {
        if (in_array('emoji', $existingCols)) {
            $pdo->exec("ALTER TABLE departments CHANGE COLUMN emoji icon VARCHAR(50) DEFAULT 'fas fa-book'");
        } else {
            $pdo->exec("ALTER TABLE departments ADD COLUMN icon VARCHAR(50) DEFAULT 'fas fa-book' AFTER color");
        }
    }

    if ($method === 'GET') {
        // Safely count teachers per department
        // Check if teachers table & department column exist first
        try {
            $teacherCols = array_column(
                $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
            $hasDeptCol = in_array('department', $teacherCols);
        } catch (Exception $e) {
            $hasDeptCol = false;
        }

        if ($hasDeptCol) {
            $stmt = $pdo->query("
                SELECT d.*,
                       COUNT(DISTINCT CASE WHEN u.id IS NULL THEN t.id END) AS member_count,
                       COUNT(DISTINCT s.id) AS sub_count,
                       (SELECT t3.photo FROM teachers t3 LEFT JOIN users u3 ON t3.user_id = u3.id WHERE CONCAT(COALESCE(t3.prefix,''), COALESCE(t3.first_name_th,''), ' ', COALESCE(t3.last_name_th,'')) = d.head_name AND (u3.role != 'admin' OR u3.role IS NULL) LIMIT 1) AS head_photo,
                       (SELECT GROUP_CONCAT(photo) FROM (SELECT t2.photo, t2.department FROM teachers t2 LEFT JOIN users u2 ON t2.user_id = u2.id WHERE t2.photo IS NOT NULL AND (u2.role != 'admin' OR u2.role IS NULL)) as t2 WHERE t2.department = d.name_th LIMIT 100) as member_photos
                FROM departments d
                LEFT JOIN teachers t ON t.department = d.name_th
                LEFT JOIN users u ON t.user_id = u.id AND u.role = 'admin'
                LEFT JOIN sub_departments s ON s.department_id = d.id
                GROUP BY d.id
                ORDER BY d.name_th ASC
            ");
        } else {
            $stmt = $pdo->query("
                SELECT d.*,
                       0 AS member_count,
                       COUNT(DISTINCT s.id) AS sub_count,
                       NULL AS head_photo,
                       NULL AS member_photos
                FROM departments d
                LEFT JOIN sub_departments s ON s.department_id = d.id
                GROUP BY d.id
                ORDER BY d.name_th ASC
            ");
        }
        echo json_encode(['success' => true, 'departments' => $stmt->fetchAll()]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE departments SET name_th=?, name_en=?, abbr_th=?, abbr_en=?, head_name=?, location=?, color=?, icon=? WHERE id=?");
            $stmt->execute([$data['name_th'], $data['name_en'] ?? '', $data['abbr_th'] ?? '', $data['abbr_en'] ?? '', $data['head_name'] ?? '', $data['location'] ?? '', $data['color'] ?? '#1e3c72', $data['icon'] ?? 'fas fa-book', $id]);
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO departments (name_th, name_en, abbr_th, abbr_en, head_name, location, color, icon) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$data['name_th'], $data['name_en'] ?? '', $data['abbr_th'] ?? '', $data['abbr_en'] ?? '', $data['head_name'] ?? '', $data['location'] ?? '', $data['color'] ?? '#1e3c72', $data['icon'] ?? 'fas fa-book']);
        }
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ไม่พบ ID']); exit; }
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
