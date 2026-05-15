<?php
// api/teacher/attendance.php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/notifications.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Ensure the new table exists and data is migrated (Auto-Migration)
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance_subjects'");
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // 1. Create Table
        $pdo->exec("CREATE TABLE `attendance_subjects` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `student_id` int(11) DEFAULT NULL,
          `subject_code` varchar(20) DEFAULT NULL,
          `class_name` varchar(50) DEFAULT NULL,
          `date` date NOT NULL,
          `period` int(11) DEFAULT 1,
          `status` varchar(20) NOT NULL,
          `remark` varchar(255) DEFAULT NULL,
          `recorded_by` int(11) DEFAULT NULL,
          `academic_year` varchar(10) DEFAULT NULL,
          `semester` int(11) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_as_student` (`student_id`),
          KEY `idx_as_date` (`date`),
          KEY `idx_as_class` (`class_name`),
          KEY `idx_as_subject` (`subject_code`),
          CONSTRAINT `fk_as_student_new` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Migrate existing subject data if any
        $pdo->exec("INSERT INTO attendance_subjects (student_id, subject_code, class_name, date, period, status, remark, recorded_by, created_at)
                    SELECT student_id, subject_code, class_name, date, period, status, remark, recorded_by, created_at
                    FROM attendance
                    WHERE type = 'subject'");
        
        // 3. Delete from old table
        $pdo->exec("DELETE FROM attendance WHERE type = 'subject'");
    }
} catch (Exception $e) {
    error_log("Migration failed: " . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. Get common parameters
$type = $_GET['type'] ?? $_POST['type'] ?? 'subject';
if ($method === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (isset($raw['type'])) $type = $raw['type'];
}

$tableName = ($type === 'subject') ? 'attendance_subjects' : 'attendance';

if ($method === 'GET') {
    // Fetch students for a class
    $class_name = $_GET['class_name'] ?? '';
    $date = $_GET['date'] ?? date('Y-m-d');
    $period = $_GET['period'] ?? 1;

    if (!$class_name) {
        echo json_encode(['success' => false, 'error' => 'Missing class_name']);
        exit;
    }

    try {
        // 1. Get students in room
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class_name = ? ORDER BY CAST(number_in_class AS UNSIGNED) ASC");
        $stmt->execute([$class_name]);
        $students = $stmt->fetchAll();

        foreach ($students as &$s) {
            $fname = $s['first_name_th'] ?? $s['first_name'] ?? '';
            $lname = $s['last_name_th'] ?? $s['last_name'] ?? '';
            $s['full_name_th'] = trim(($s['prefix'] ?? '') . ' ' . $fname . ' ' . $lname);
        }

        // 2. Get existing attendance from correct table
        $sql = "SELECT student_id, status, remark, period FROM $tableName WHERE class_name = ? AND date = ?";
        $params = [$class_name, $date];
        
        if ($type === 'subject') {
            $sql .= " AND period = ?";
            $params[] = $period;
            
            $subj = $_GET['subject_code'] ?? '';
            if ($subj) { $sql .= " AND subject_code = ?"; $params[] = $subj; }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetchAll();
        
        $map = [];
        foreach ($existing as $row) { $map[$row['student_id']] = $row; }

        foreach ($students as &$s) {
            if (isset($map[$s['id']])) {
                $s['status'] = $map[$s['id']]['status'];
                $s['remark'] = $map[$s['id']]['remark'] ?? '';
                $s['period'] = $map[$s['id']]['period'] ?? 1;
            } else {
                $s['status'] = null;
                $s['remark'] = '';
                $s['period'] = 1;
            }
        }
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (PDOException $e) {
        error_log('[attendance] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? date('Y-m-d');
    $class_name = $data['class_name'] ?? '';
    $records = $data['records'] ?? [];
    $period = $data['period'] ?? 1;
    $recorded_by = $_SESSION['user_id'];
    $subject_code = $data['subject_code'] ?? null;

    if (!$class_name || empty($records)) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Delete existing
        $sqlDel = "DELETE FROM $tableName WHERE class_name = ? AND date = ?";
        $paramsDel = [$class_name, $date];
        if ($type === 'subject') {
            $sqlDel .= " AND period = ?";
            $paramsDel[] = $period;
            if ($subject_code) { $sqlDel .= " AND subject_code = ?"; $paramsDel[] = $subject_code; }
        }
        $pdo->prepare($sqlDel)->execute($paramsDel);

        // 2. Insert new
        if ($type === 'subject') {
            $sqlIns = "INSERT INTO attendance_subjects (student_id, class_name, date, status, remark, period, subject_code, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sqlIns);
            foreach ($records as $r) {
                $stmt->execute([$r['student_id'], $class_name, $date, $r['status'], $r['remark'] ?? null, $period, $subject_code, $recorded_by]);
            }
        } else {
            $sqlIns = "INSERT INTO attendance (student_id, class_name, date, status, remark, recorded_by, type) VALUES (?, ?, ?, ?, ?, ?, 'daily')";
            $stmt = $pdo->prepare($sqlIns);
            foreach ($records as $r) {
                $stmt->execute([$r['student_id'], $class_name, $date, $r['status'], $r['remark'] ?? null, $recorded_by]);
            }
        }

        $pdo->commit();

        // 🔔 Notify each student whose status was set to ขาด / สาย / ลา / ป่วย / กิจ / ลากิจ
        $notable = ['ขาด','สาย','ลา','ป่วย','กิจ','ลากิจ'];
        foreach ($records as $r) {
            $st = $r['status'] ?? '';
            if (!in_array($st, $notable, true)) continue;
            $sid = (int)($r['student_id'] ?? 0);
            if ($sid <= 0) continue;
            // Get student's user_id
            $look = $pdo->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
            $look->execute([$sid]);
            $uid = (int) ($look->fetchColumn() ?: 0);
            if ($uid <= 0) continue;

            $emoji = ['ขาด'=>'⚠️','สาย'=>'⏰','ลา'=>'📝','ป่วย'=>'🤒','กิจ'=>'📝','ลากิจ'=>'📝'][$st] ?? '🔔';
            $color = ['ขาด'=>'#ef4444','สาย'=>'#f59e0b','ลา'=>'#3b82f6','ป่วย'=>'#06b6d4','กิจ'=>'#3b82f6','ลากิจ'=>'#3b82f6'][$st] ?? '#3b82f6';
            $title = "{$emoji} ผลการเช็คชื่อ: {$st}";
            $msg   = ($type === 'subject')
                ? "วันที่ " . date('j/n/Y', strtotime($date)) . " คาบ {$period} — สถานะ: {$st}"
                : "วันที่ " . date('j/n/Y', strtotime($date)) . " (โฮมรูม) — สถานะ: {$st}";
            cnp_notify(
                $pdo, $uid, $title, $msg,
                'student_attendance_history.html',
                'bi-clipboard2-check-fill',
                $color,
                'attendance',
                'att_' . $type . '_' . $date . '_' . ($period ?? 'd') . '_' . $sid
            );
        }

        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[attendance POST] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }

} elseif ($method === 'DELETE') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $class_name = $_GET['class_name'] ?? '';
    $period = $_GET['period'] ?? 1;
    $subject_code = $_GET['subject_code'] ?? null;

    try {
        $sql = "DELETE FROM $tableName WHERE class_name = ? AND date = ?";
        $params = [$class_name, $date];
        if ($type === 'subject') {
            $sql .= " AND period = ?";
            $params[] = $period;
            if ($subject_code) { $sql .= " AND subject_code = ?"; $params[] = $subject_code; }
        }
        $pdo->prepare($sql)->execute($params);
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    } catch (PDOException $e) {
        error_log('[attendance DELETE] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
    }
}
?>
