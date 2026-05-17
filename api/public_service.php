<?php
// api/public_service.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
require_once '../inc/classroom_codes.php';
require_once '../inc/notifications.php';
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

    // Common filters applied BEFORE role-based filtering for stats parity
    if ($year) { $where .= " AND r.academic_year = ?"; $params[] = $year; }
    if ($semester && $semester !== 'all') { $where .= " AND r.semester = ?"; $params[] = $semester; }
    if ($status && $type !== 'pending') { $where .= " AND r.status = ?"; $params[] = $status; }

    // Get the user's assigned classroom (if any)
    $stmtRoom = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
    $stmtRoom->execute([$user_id]);
    $userRoom = $stmtRoom->fetchColumn() ?: null;

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
        if ($role === 'teacher') {
            if ($type === 'room') {
                // Report page: show all records from teacher's advisory room
                if ($userRoom) {
                    cnp_classroom_append_sql_in($where, $params, 's.class_name', (string) $userRoom);
                } else {
                    $where .= " AND 1=0";
                }
            } else {
                // Default / type=my: show records where this teacher is the approver OR students in teacher's advisory room
                if ($userRoom) {
                    $vars = cnp_classroom_code_variants((string) $userRoom);
                    $ph   = implode(',', array_fill(0, count($vars), '?'));
                    $where .= " AND (r.approver_id = ? OR s.class_name IN ($ph))";
                    $params[] = $user_id;
                    foreach ($vars as $v) {
                        $params[] = $v;
                    }
                } else {
                    $where .= " AND r.approver_id = ?";
                    $params[] = $user_id;
                }
            }
        } else {
            // Admin: optional filters
            if ($type === 'room' && $userRoom) {
                cnp_classroom_append_sql_in($where, $params, 's.class_name', (string) $userRoom);
            } elseif ($type === 'my') {
                $where .= " AND r.approver_id = ?";
                $params[] = $user_id;
            }
            // Empty/all: admin sees everything
        }

        if ($type === 'pending') {
            $where .= " AND r.status = 'pending'";
        }
    }

    if ($search) {
        $where .= " AND (s.first_name_th LIKE ? OR s.last_name_th LIKE ? OR s.student_id LIKE ? OR r.activity_name LIKE ?)";
        $s = "%$search%";
        $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    }

    try {
        $sql = "SELECT r.*, s.first_name_th, s.last_name_th, s.student_id as student_code,
                       s.class_name, s.class_name as room,
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
            $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $rec['activity_date_th'] = $date->format('j') . ' ' . $months[(int)$date->format('m')] . ' ' . ($date->format('Y') + 543);
        }

        // Stats calculation
        $statsWhere = "r.academic_year = ?";
        $statsParams = [$year];
        if ($semester && $semester !== 'all') {
            $statsWhere .= " AND r.semester = ?";
            $statsParams[] = $semester;
        }

        if ($role === 'teacher' && $userRoom) {
            cnp_classroom_append_sql_in($statsWhere, $statsParams, 's.class_name', (string) $userRoom);
        }

        $statsSql = "SELECT
                        COUNT(CASE WHEN r.status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending_count,
                        COUNT(DISTINCT r.student_id) as total_students
                     FROM public_service_records r
                     LEFT JOIN students s ON r.student_id = s.id
                     WHERE $statsWhere";

        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($statsParams);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Add goal progress
        if (!$stats) {
            $stats = ['approved_count' => 0, 'pending_count' => 0, 'total_students' => 0];
        }
        $stats['target_times'] = 20; // 20 times per semester
        $stats['avg_progress'] = $stats['total_students'] > 0 ? round(($stats['approved_count'] / ($stats['total_students'] * 20)) * 100, 1) : 0;

        // Add goal progress (students with >= 20 times)
        $passedStmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT r2.student_id, SUM(r2.duration) as total 
                FROM public_service_records r2
                WHERE r2.academic_year = ? AND r2.status = 'approved' 
                GROUP BY r2.student_id 
                HAVING total >= 20
            ) as t");
        $passedStmt->execute([$year]);
        $stats['passed_count'] = (int)$passedStmt->fetchColumn();

        // Top 5 active students
        $topSStmt = $pdo->prepare("
            SELECT s.first_name_th, s.last_name_th, s.class_name, SUM(r3.duration) as total
            FROM public_service_records r3
            JOIN students s ON r3.student_id = s.id
            WHERE r3.academic_year = ? AND r3.status = 'approved'
            GROUP BY r3.student_id
            ORDER BY total DESC
            LIMIT 5");
        $topSStmt->execute([$year]);
        $topStudents = $topSStmt->fetchAll(PDO::FETCH_ASSOC);

        // Add student counts per class for percentage calculations
        $countsStmt = $pdo->query("SELECT class_name, COUNT(*) as count FROM students GROUP BY class_name");
        $classCounts = $countsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $roomStudents = [];
        if ($type === 'room' && $userRoom) {
            $vars = cnp_classroom_code_variants((string) $userRoom);
            $phRoom = implode(',', array_fill(0, count($vars), '?'));
            $stmtRoomS = $pdo->prepare("SELECT id, student_id as student_code, prefix, first_name_th, last_name_th, photo FROM students WHERE class_name IN ($phRoom) ORDER BY CAST(number_in_class AS UNSIGNED) ASC, student_id ASC");
            $stmtRoomS->execute($vars);
            $roomStudents = $stmtRoomS->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'records' => $records,
            'students' => $roomStudents,
            'stats' => $stats,
            'class_counts' => $classCounts,
            'top_students' => $topStudents
        ]);
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
            if (!$student_id) {
                $uname = $_SESSION['username'] ?? '';
                if ($uname) {
                    $stmt2 = $pdo->prepare("SELECT id FROM students WHERE student_id = ? OR email = ? LIMIT 1");
                    $stmt2->execute([$uname, $uname]);
                    $student_id = $stmt2->fetchColumn();
                    if ($student_id) {
                        try { $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$_SESSION['user_id'], $student_id]); } catch (Exception $e) {}
                    }
                }
            }
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
                (student_id, activity_name, location, impact_benefit, activity_date, duration, duration_unit, certifier_name, approver_id, status, academic_year, semester) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
            $data['activity_date'], $data['duration'] ?? 1, $data['duration_unit'] ?? 'ครั้ง',
            $data['certifier_name'] ?? '', $approver_id, $status, $year, $semester
        ]);
        $newRecordId = (int) $pdo->lastInsertId();

        // 🔔 Notify the approver (teacher) if student submitted and selected a certifier
        if ($_SESSION['role'] === 'student' && $approver_id) {
            $sName = $pdo->prepare("SELECT CONCAT(COALESCE(prefix,''), first_name_th, ' ', last_name_th) AS nm FROM students WHERE id = ?");
            $sName->execute([$student_id]);
            $studentName = $sName->fetchColumn() ?: 'นักเรียน';
            cnp_notify(
                $pdo,
                (int) $approver_id,
                'คำขอรับรองสาธารณประโยชน์',
                "นักเรียน {$studentName} ขอให้คุณรับรองกิจกรรม: {$data['activity_name']}",
                'teacher_public_service.html',
                'bi-hand-thumbs-up-fill',
                '#3b82f6',
                'public_service',
                'ps_request_' . $newRecordId
            );
        }

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

            // Teacher: only allow batch on records where they are the approver OR students in their advisory room
            if ($_SESSION['role'] === 'teacher') {
                $stmtRoom = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
                $stmtRoom->execute([$userId]);
                $userRoom = $stmtRoom->fetchColumn() ?: null;

                if ($userRoom) {
                    $vars = cnp_classroom_code_variants((string) $userRoom);
                    $phRoom = implode(',', array_fill(0, count($vars), '?'));
                    $verify = $pdo->prepare("
                        SELECT r.id FROM public_service_records r
                        JOIN students s ON r.student_id = s.id
                        WHERE r.id IN ($placeholders) AND (r.approver_id = ? OR s.class_name IN ($phRoom))
                    ");
                    $verify->execute(array_merge($ids, [$userId], $vars));
                } else {
                    $verify = $pdo->prepare("
                        SELECT id FROM public_service_records
                        WHERE id IN ($placeholders) AND approver_id = ?
                    ");
                    $verify->execute(array_merge($ids, [$userId]));
                }
                
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

            // 🔔 Notify each affected student
            $studentLookup = $pdo->prepare("
                SELECT r.id, r.activity_name, s.user_id, CONCAT(COALESCE(s.prefix,''), s.first_name_th, ' ', s.last_name_th) AS sname
                FROM public_service_records r
                JOIN students s ON r.student_id = s.id
                WHERE r.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $studentLookup->execute($ids);
            while ($row = $studentLookup->fetch(PDO::FETCH_ASSOC)) {
                if (!$row['user_id']) continue;
                $isOk = ($status === 'approved');
                cnp_notify(
                    $pdo,
                    (int) $row['user_id'],
                    $isOk ? '✅ กิจกรรมได้รับการรับรองแล้ว' : '❌ กิจกรรมไม่ผ่านการรับรอง',
                    "กิจกรรม: {$row['activity_name']}",
                    'student_public_service.html',
                    $isOk ? 'bi-patch-check-fill' : 'bi-x-circle-fill',
                    $isOk ? '#10b981' : '#ef4444',
                    'public_service',
                    'ps_status_' . $row['id']
                );
            }

            echo json_encode(['success' => true, 'count' => count($ids), 'message' => "ดำเนินการ " . count($ids) . " รายการ"]);
            return;
        }

        if (isset($data['full_edit']) && $data['full_edit']) {
            // Full record edit
            $sql = "UPDATE public_service_records SET 
                    student_id = ?, activity_name = ?, location = ?, impact_benefit = ?, 
                    activity_date = ?, duration = ?, duration_unit = ?, certifier_name = ?, status = ?, approver_id = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['student_internal_id'] ?? $data['student_id'], 
                $data['activity_name'], 
                $data['location'], 
                $data['impact_benefit'] ?? '',
                $data['activity_date'], 
                $data['duration'] ?? 1,
                $data['duration_unit'] ?? 'ครั้ง',
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

        // 🔔 Notify the affected student for single-record updates
        $lookup = $pdo->prepare("
            SELECT r.id, r.activity_name, s.user_id
            FROM public_service_records r
            JOIN students s ON r.student_id = s.id
            WHERE r.id = ?");
        $lookup->execute([$data['id']]);
        $info = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($info && $info['user_id']) {
            $newStatus = $data['status'];
            $isOk = ($newStatus === 'approved');
            $title = $isOk ? '✅ กิจกรรมได้รับการรับรองแล้ว'
                   : ($newStatus === 'rejected' ? '❌ กิจกรรมไม่ผ่านการรับรอง'
                                                : '🔄 สถานะกิจกรรมถูกปรับ');
            cnp_notify(
                $pdo,
                (int) $info['user_id'],
                $title,
                "กิจกรรม: {$info['activity_name']}",
                'student_public_service.html',
                $isOk ? 'bi-patch-check-fill' : ($newStatus === 'rejected' ? 'bi-x-circle-fill' : 'bi-arrow-repeat'),
                $isOk ? '#10b981' : ($newStatus === 'rejected' ? '#ef4444' : '#3b82f6'),
                'public_service',
                'ps_status_' . $info['id']
            );
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
