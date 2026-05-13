<?php
// api/calendar.php
require_once '../config.php';
require_once '../inc/security.php';
header('Content-Type: application/json');

session_start();
cnp_verify_origin();
cnp_csrf_verify();
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

function get_settings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        if ($stmt) {
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return $settings;
    } catch (Exception $e) {
        // Table might not exist, silently ignore returning empty settings
        return [];
    }
}

// Auto-Migration: Ensure all required columns exist in academic_days
try {
    $checkCols = $pdo->query("DESCRIBE academic_days")->fetchAll(PDO::FETCH_COLUMN);
    $migrations = [
        'category' => "VARCHAR(100) DEFAULT 'ทั่วไป'",
        'day_type' => "VARCHAR(50) DEFAULT 'ปกติ'",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    ];
    foreach ($migrations as $col => $def) {
        if (!in_array($col, $checkCols)) {
            $pdo->exec("ALTER TABLE academic_days ADD COLUMN $col $def");
        }
    }
} catch (Exception $e) { /* Silent fail if already exists or other issues */ }

$action = $_GET['action'] ?? '';

try {
    if ($action === 'getInitialData') {
        $stmt = $pdo->query("SELECT id, academic_year, semester, date_val, activity, note, category, day_type FROM academic_days ORDER BY date_val ASC");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = get_settings($pdo);
        echo json_encode(['success' => true, 'events' => $events, 'settings' => $settings], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'saveEvent') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $date = $data['date_val'];
        $title = $data['activity'];
        $note = $data['note'] ?? '';
        $loc = $data['location'] ?? '';
        $cat = $data['category'] ?? 'ทั่วไป';
        $day_type = $data['day_type'] ?? 'ปกติ';
        $color = $data['color'] ?? '';
        $all_day = ($data['all_day'] ?? 1);
        
        $d = new DateTime($date);
        $m = (int)$d->format('n');
        $ay = (int)$d->format('Y') + 543;
        $sem = ($m >= 5 && $m <= 10) ? 1 : 2;
        if ($m < 5) $ay--;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE academic_days SET academic_year=?, semester=?, date_val=?, activity=?, note=?, category=?, day_type=? WHERE id=?");
            $stmt->execute([$ay, $sem, $date, $title, $note, $cat, $day_type, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO academic_days (academic_year, semester, date_val, activity, note, category, day_type) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$ay, $sem, $date, $title, $note, $cat, $day_type]);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'deleteEvent') {
        $id = json_decode(file_get_contents('php://input'), true)['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM academic_days WHERE id=?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว'], JSON_UNESCAPED_UNICODE);
}
