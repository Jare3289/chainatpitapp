<?php
// api/public_service.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}

function handleGet($pdo) {
    $year = $_GET['year'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $type = $_GET['type'] ?? '';

    // Get settings for default year/semester
    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
    $settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = [
        'academic_year' => $settings_raw['current_academic_year'] ?? '2568',
        'semester' => $settings_raw['current_semester'] ?? '1'
    ];
    
    if (!$year) $year = $settings['academic_year'];
    if (!$semester) $semester = $settings['semester'];

    $where = "1=1";
    $params = [];

    // Get the user's assigned classroom (if any)
    $stmtRoom = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
    $stmtRoom->execute([$user_id]);
    $userRoom = $stmtRoom->fetchColumn();

    if ($role === 'student') {
        $stmtS = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmtS->execute([$user_id]);
        $sId = $stmtS->fetchColumn();
        if (!$sId) {
            $uname = $_SESSION['username'] ?? '';
            if ($uname) {
                $stmtS2 = $pdo->prepare("SELECT id FROM students WHERE student_id = ? OR email = ? LIMIT 1");
                $stmtS2->execute([$uname, $uname]);
                $sId = $stmtS2->fetchColumn();
                if ($sId) {
                    try { $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$user_id, $sId]); } catch (Exception $e) {}
                }
            }
        }
        $where .= " AND r.student_id = ?";
        $params[] = $sId ?: 0;
        // Students see all their records, skip year/semester filter below
        $year = ''; $semester = '';
    } else {
        // Teacher or Admin
        if ($type === 'room' || (!$type && $userRoom && $role !== 'admin')) {
            // Default: Filter by my room if I have one
            if ($userRoom) {
                $where .= " AND (s.room = ? OR s.class_name = ?)";
                $params[] = $userRoom;
                $params[] = $userRoom;
            }
        } elseif ($type === 'my') {
            $where .= " AND r.approver_id = ?";
            $params[] = $user_id;
        } elseif ($type === 'pending') {
            $where .= " AND r.status = 'pending'";
            // If teacher, still filter by room
            if ($role === 'teacher' && $userRoom) {
                $where .= " AND (s.room = ? OR s.class_name = ?)";
                $params[] = $userRoom;
                $params[] = $userRoom;
            }
        }
        // If role is admin and type is empty or 'all', they see 1=1 (all)
    }

    // Common filters
    if ($year) { $where .= " AND r.academic_year = ?"; $params[] = $year; }
    if ($semester) { $where .= " AND r.semester = ?"; $params[] = $semester; }
    if ($status && $type !== 'pending') { $where .= " AND r.status = ?"; $params[] = $status; }
    if ($search) {
        $where .= " AND (s.first_name_th LIKE ? OR s.last_name_th LIKE ? OR s.student_id LIKE ? OR r.activity_name LIKE ?)";
        $s = "%$search%";
        $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    }

    try {
        $sql = "SELECT r.*, s.first_name_th, s.last_name_th, s.student_id as student_code,
                       COALESCE(NULLIF(s.class_name,''), s.room) AS class_name,
                       s.photo,
                       (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers t WHERE t.user_id = r.approver_id) as approver_name
                FROM public_service_records r
                JOIN students s ON r.student_id = s.id
                WHERE $where
                ORDER BY r.activity_date DESC, r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as &$rec) {
            $date = new DateTime($rec['activity_date']);
            $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $rec['activity_date_th'] = $date->format('j') . ' ' . $months[(int)$date->format('m')] . ' ' . ($date->format('y') + 543);
        }

        // Stats calculation
        // Stats calculation
        $statsSql = "SELECT 
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(DISTINCT student_id) as total_students
                     FROM public_service_records r
                     JOIN students s ON r.student_id = s.id
                     WHERE r.academic_year = ? AND r.semester = ?";
        
        $statsParams = [$year, $semester];
        
        if ($role === 'teacher' && $userRoom) {
            $statsSql .= " AND (s.room = ? OR s.class_name = ?)";
            $statsParams[] = $userRoom;
            $statsParams[] = $userRoom;
        }

        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($statsParams);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Add goal progress
        if ($stats) {
            $stats['target_times'] = 20; // 20 times per semester
            $stats['avg_progress'] = $stats['total_students'] > 0 ? round(($stats['approved_count'] / ($stats['total_students'] * 20)) * 100, 1) : 0;
        }

        echo json_encode(['success' => true, 'records' => $records, 'stats' => $stats]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']); }
}

function handlePost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['activity_name']) || !isset($data['activity_date'])) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    try {
        $student_id = null;
        if ($_SESSION['role'] === 'student') {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $student_id = $stmt->fetchColumn();
        } else {
            // Admin/Teacher can provide internal ID or Student Code
            $providedId = $data['student_internal_id'] ?? $data['student_id'] ?? '';
            
            // Check if it's an internal ID first
            $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$providedId]);
            $student_id = $stmt->fetchColumn();

            if (!$student_id) {
                // Try as student code
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                $stmt->execute([$providedId]);
                $student_id = $stmt->fetchColumn();
            }
        }

        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลนักเรียน']);
            exit;
        }

        $sql = "INSERT INTO public_service_records 
                (student_id, activity_name, location, impact_benefit, activity_date, certifier_name, approver_id, status, academic_year, semester) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
        $settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = [
            'academic_year' => $settings_raw['current_academic_year'] ?? '2568',
            'semester' => $settings_raw['current_semester'] ?? '1'
        ];
        
        $status = ($_SESSION['role'] === 'student') ? 'pending' : 'approved';
        $year = $data['academic_year'] ?? $settings['academic_year'];
        $semester = $data['semester'] ?? $settings['semester'];
        
        // If student requests a teacher, set approver_id to that teacher
        $approver_id = null;
        if (!empty($data['certifier_id'])) {
            $approver_id = $data['certifier_id'];
        }

        $stmt->execute([
            $student_id, $data['activity_name'], $data['location'], $data['impact_benefit'] ?? '',
            $data['activity_date'], $data['certifier_name'] ?? '', $approver_id, $status, $year, $semester
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'ระบบขัดข้องชั่วคราว']); }
}

function handlePut($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    try {
        // Batch approve/reject — body: { batch: true, ids: [1,2,3], status: 'approved'|'rejected' }
        if (!empty($data['batch']) && !empty($data['ids']) && is_array($data['ids'])) {
            $status = in_array($data['status'] ?? '', ['approved','rejected'], true) ? $data['status'] : 'approved';
            $userId = (int)$_SESSION['user_id'];

            // Only admin/teacher can batch
            if (!in_array($_SESSION['role'] ?? '', ['admin','teacher'], true)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                return;
            }

            $ids = array_map('intval', $data['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // If teacher, only allow approving records from their advisory room
            if ($_SESSION['role'] === 'teacher') {
                $stmtRoom = $pdo->prepare("SELECT classroom FROM teachers WHERE user_id = ?");
                $stmtRoom->execute([$userId]);
                $room = $stmtRoom->fetchColumn();
                if (!$room) {
                    echo json_encode(['success' => false, 'error' => 'ไม่ได้เป็นที่ปรึกษาห้องใด']);
                    return;
                }
                // Filter to records in this teacher's advisory room
                $verify = $pdo->prepare("
                    SELECT r.id FROM public_service_records r
                    JOIN students s ON r.student_id = s.id
                    WHERE r.id IN ($placeholders) AND s.class_name = ?
                ");
                $verify->execute(array_merge($ids, [$room]));
                $allowedIds = $verify->fetchAll(PDO::FETCH_COLUMN);
                if (empty($allowedIds)) {
                    echo json_encode(['success' => false, 'error' => 'ไม่มีรายการที่อนุญาตให้ปฏิบัติ']);
                    return;
                }
                $ids = $allowedIds;
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
            }

            $sql = "UPDATE public_service_records SET status = ?, approver_id = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$status, $userId], $ids);
            $pdo->prepare($sql)->execute($params);

            echo json_encode(['success' => true, 'count' => count($ids), 'message' => "ดำเนินการ " . count($ids) . " รายการ"]);
            return;
        }

        if (isset($data['full_edit']) && $data['full_edit']) {
            // Full record edit
            $sql = "UPDATE public_service_records SET 
                    student_id = ?, activity_name = ?, location = ?, impact_benefit = ?, 
                    activity_date = ?, certifier_name = ?, status = ?, approver_id = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['student_internal_id'] ?? $data['student_id'], 
                $data['activity_name'], 
                $data['location'], 
                $data['impact_benefit'] ?? '',
                $data['activity_date'], 
                $data['certifier_name'] ?? '', 
                $data['status'], 
                $_SESSION['user_id'],
                $data['id']
            ]);
        } else {
            // Quick status update
            $stmt = $pdo->prepare("UPDATE public_service_records SET status = ?, approver_id = ? WHERE id = ?");
            $stmt->execute([$data['status'], $_SESSION['user_id'], $data['id']]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']); }
}

function handleDelete($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM public_service_records WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']); }
}
?>
