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

    if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $dept = $_GET['department'] ?? null;
    $classroom = $_GET['classroom'] ?? null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
            $stmt->execute([$id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $teacher]);
        } else {
            $sql = "SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id";
            $where = [];
            $params = [];

            if ($dept) {
                $where[] = "t.department = ?";
                $params[] = $dept;
            }
            if ($classroom) {
                $where[] = "t.classroom = ?";
                $params[] = $classroom;
            }

            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY t.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $teachers]);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
} elseif ($method === 'POST') {
    // Check if it's a JSON request or FormData
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    $id = $data['id'] ?? null;

    try {
        if ($id) {
            // Check for photo upload
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/teachers/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'teacher_' . $id . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                    $photo_path = 'uploads/teachers/' . $filename;
                    
                    // Delete old photo if exists
                    $stmt = $pdo->prepare("SELECT photo FROM teachers WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && $old['photo'] && file_exists('../../' . $old['photo'])) {
                        unlink('../../' . $old['photo']);
                    }
                }
            }

            // Update
            $fields = [
                'teacher_id', 'email', 'id_card', 'prefix', 'first_name_th', 'last_name_th', 'birth_date',
                'classroom', 'faculty', 'position', 'academic_standing', 'department', 
                'department_position', 'admin_position', 'sub_department', 
                'retirement_year', 'phone', 'line_id',
                'address_no', 'address_soi', 'address_moo', 'address_road', 
                'address_subdistrict', 'address_district', 'address_province', 'hometown',
                'ethnicity', 'nationality', 'religion', 'education_history'
            ];
            
            $sql = "UPDATE teachers SET ";
            $params = [];
            foreach ($fields as $field) {
                $sql .= "`$field`=?, ";
                $val = $data[$field] ?? '';
                
                // Handle multiple selection (array to string)
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }

                if ($field === 'phone' && strlen((string)$val) >= 8 && substr((string)$val, 0, 1) !== '0') {
                    $val = '0' . $val;
                }
                $params[] = $val;
            }
            
            if ($photo_path) {
                $sql .= "`photo`=?, ";
                $params[] = $photo_path;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id=?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ', 'photo' => $photo_path]);
        } else {
            $fields = [
                'teacher_id', 'email', 'id_card', 'prefix', 'first_name_th', 'last_name_th', 'birth_date',
                'classroom', 'faculty', 'position', 'academic_standing', 'department', 
                'department_position', 'admin_position', 'sub_department', 
                'retirement_year', 'phone', 'line_id',
                'address_no', 'address_soi', 'address_moo', 'address_road', 
                'address_subdistrict', 'address_district', 'address_province', 'hometown',
                'ethnicity', 'nationality', 'religion', 'education_history'
            ];

            // Attempt to create user account
            $email = trim($data['email'] ?? '');
            $idt = trim($data['teacher_id'] ?? '');
            $username = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $idt;
            
            if (!$username) {
                echo json_encode(['error' => 'กรุณาระบุรหัสครู (IDT) หรืออีเมลเพื่อใช้เป็นชื่อบัญชีผู้ใช้']);
                exit;
            }

            $pdo->beginTransaction();

            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'teacher') 
                                        ON DUPLICATE KEY UPDATE password = VALUES(password)");
            $stmtUser->execute([$username, password_hash('cnp12345', PASSWORD_DEFAULT)]);
            
            $stmtGetUid = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtGetUid->execute([$username]);
            $uid = $stmtGetUid->fetchColumn();

            $insertCols = ['user_id'];
            $params = [$uid];
            $placeholders = ['?'];

            foreach ($fields as $field) {
                $val = $data[$field] ?? '';

                // Handle multiple selection (array to string)
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }

                if ($field === 'phone' && strlen((string)$val) >= 8 && substr((string)$val, 0, 1) !== '0') {
                    $val = '0' . $val;
                }
                $insertCols[] = "`$field`";
                $params[] = $val;
                $placeholders[] = '?';
            }

            // Photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/teachers/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'teacher_new_' . time() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                    $insertCols[] = "`photo`";
                    $params[] = 'uploads/teachers/' . $filename;
                    $placeholders[] = '?';
                }
            }

            $sql = "INSERT INTO teachers (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลครูสำเร็จ (รหัสผ่านเริ่มต้น: cnp12345)']);
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) { exit(json_encode(['error' => 'Missing ID'])); }
    try {
        $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
}
?>
