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
            $stmt = $pdo->prepare("SELECT t.*, u.username, u.role FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND (u.role != 'admin' OR u.role IS NULL)");
            $stmt->execute([$id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $teacher]);
        } else {
            $sql = "SELECT t.*, u.username, u.role FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE (u.role != 'admin' OR u.role IS NULL)";
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
                $sql .= " AND (" . implode(" AND ", $where) . ")";
            }

            $sql .= " ORDER BY t.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $teachers]);
        }
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Check if it's a JSON request or FormData
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }

    // Reset profile_confirmed_at for all teachers (force re-confirmation)
    if (($_GET['action'] ?? '') === 'reset_profile_confirm') {
        try {
            $stmt = $pdo->prepare("UPDATE teachers SET profile_confirmed_at = NULL");
            $stmt->execute();
            echo json_encode(['success' => true, 'count' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            error_log('[reset_profile_confirm] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'รีเซ็ตไม่สำเร็จ: ' . $e->getMessage()]);
        }
        exit;
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
                if (!array_key_exists($field, $data)) continue;
                $sql .= "`$field`=?, ";
                $val = $data[$field] ?? '';
                
                // Handle multiple selection (array to string)
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }

                if ($field === 'phone' && strlen((string)$val) >= 8 && substr((string)$val, 0, 1) !== '0') {
                    $val = '0' . $val;
                }
                
                // MySQL strict mode fixes: convert empty string to NULL for date and int fields
                if ($val === '' && in_array($field, ['birth_date', 'appointment_date', 'retirement_year', 'weight', 'height'])) {
                    $val = null;
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
            $idt   = trim($data['teacher_id'] ?? '');

            // Enforce @chainatpit.ac.th domain
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => 'กรุณาระบุอีเมลให้ถูกต้อง'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!str_ends_with(strtolower($email), '@chainatpit.ac.th')) {
                echo json_encode(['error' => 'อีเมลต้องเป็น @chainatpit.ac.th เท่านั้น'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $username = $email;

            $pdo->beginTransaction();

            $teacherRoleId = (int)($pdo->query("SELECT id FROM roles WHERE name = 'teacher' LIMIT 1")->fetchColumn() ?: 0);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, role_id) VALUES (?, ?, 'teacher', ?)
                                        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
            $stmtUser->execute([$username, password_hash('cnp12345', PASSWORD_DEFAULT), $teacherRoleId ?: null]);
            $uid = (int)$pdo->lastInsertId();
            if (!$uid) {
                $stmtGetUid = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtGetUid->execute([$username]);
                $uid = (int)$stmtGetUid->fetchColumn();
            }

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
                
                // MySQL strict mode fixes: convert empty string to NULL for date and int fields
                if ($val === '' && in_array($field, ['birth_date', 'appointment_date', 'retirement_year', 'weight', 'height'])) {
                    $val = null;
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
            echo json_encode(['success' => true, 'message' => "เพิ่มข้อมูลครูสำเร็จ (Username: $username / รหัสผ่าน: cnp12345)"], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => $e->getMessage()]);
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
