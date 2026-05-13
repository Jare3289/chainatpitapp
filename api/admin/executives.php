<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}

// Fixed 5 executive positions (slug => label)
$POSITIONS = [
    'director'        => 'ผู้อำนวยการโรงเรียน',
    'deputy_academic' => 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
    'deputy_student'  => 'รองผู้อำนวยการกลุ่มบริหารกิจการนักเรียน',
    'deputy_general'  => 'รองผู้อำนวยการกลุ่มบริหารทั่วไป',
    'deputy_budget'   => 'รองผู้อำนวยการกลุ่มบริหารงบประมาณและงานบุคคล',
];

try {
    // 1. Ensure table exists in ANY form (safe for old schema)
    $pdo->exec("CREATE TABLE IF NOT EXISTS executives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) DEFAULT '',
        position VARCHAR(200) DEFAULT '',
        photo_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Get existing columns
    $existingCols = array_column(
        $pdo->query("SHOW COLUMNS FROM executives")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    // 3. Add new columns if missing
    if (!in_array('position_slug',  $existingCols)) $pdo->exec("ALTER TABLE executives ADD COLUMN position_slug  VARCHAR(50)  DEFAULT NULL  AFTER id");
    if (!in_array('position_label', $existingCols)) $pdo->exec("ALTER TABLE executives ADD COLUMN position_label VARCHAR(200) DEFAULT ''   AFTER position_slug");
    if (!in_array('teacher_id',     $existingCols)) $pdo->exec("ALTER TABLE executives ADD COLUMN teacher_id     INT          DEFAULT NULL  AFTER position_label");
    if (!in_array('name_override',  $existingCols)) $pdo->exec("ALTER TABLE executives ADD COLUMN name_override  VARCHAR(200) DEFAULT NULL  AFTER teacher_id");
    if (!in_array('updated_at',     $existingCols)) $pdo->exec("ALTER TABLE executives ADD COLUMN updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER name_override");

    // 4. Add UNIQUE constraint on position_slug if not already there
    try {
        $pdo->exec("ALTER TABLE executives ADD UNIQUE KEY uq_position_slug (position_slug)");
    } catch (Exception $e) { /* already exists */ }

    // 5. Seed/upsert the 5 fixed positions
    foreach ($POSITIONS as $slug => $label) {
        $check = $pdo->prepare("SELECT id FROM executives WHERE position_slug = ?");
        $check->execute([$slug]);
        if (!$check->fetch()) {
            // Insert new fixed row
            $pdo->prepare("INSERT INTO executives (position_slug, position_label) VALUES (?, ?)")
                ->execute([$slug, $label]);
        } else {
            // Ensure label is up to date
            $pdo->prepare("UPDATE executives SET position_label = ? WHERE position_slug = ?")
                ->execute([$label, $slug]);
        }
    }

    // 6. Clean up any old rows that don't have a valid position_slug
    $pdo->exec("DELETE FROM executives WHERE position_slug IS NULL OR position_slug = ''");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Check what columns teachers table actually has
        $teacherCols = [];
        try {
            $teacherCols = array_column(
                $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (Exception $e) {}

        $hasPhoto    = in_array('photo',     $teacherCols);
        $hasPrefix   = in_array('prefix',    $teacherCols);
        $hasFirst    = in_array('first_name_th', $teacherCols);
        $hasLast     = in_array('last_name_th',  $teacherCols);

        // Build safe SELECT for resolved name
        // Requirement: Prefix and First Name must stay together (no space)
        $nameExpr = "COALESCE(e.name_override, CONCAT(COALESCE(t.prefix,''), COALESCE(t.first_name_th,''), ' ', COALESCE(t.last_name_th,'')))";
        $fullNameSel = "CONCAT(COALESCE(t.prefix,''), COALESCE(t.first_name_th,''), ' ', COALESCE(t.last_name_th,'')) AS teacher_full_name,";
        $photoSel = $hasPhoto ? "t.photo AS teacher_photo" : "NULL AS teacher_photo";

        $stmt = $pdo->query("
            SELECT e.*,
                   {$nameExpr} AS resolved_name,
                   {$fullNameSel}
                   {$photoSel}
            FROM executives e
            LEFT JOIN teachers t ON t.id = e.teacher_id
            ORDER BY e.id ASC
        ");
        $execs = $stmt->fetchAll();

        // Return all teachers for dropdown
        try {
            if (count($teacherCols) === 0) throw new Exception('no teachers table');

            // Requirement: Prefix and First Name must stay together
            $nameSelect = "CONCAT(COALESCE(prefix,''), COALESCE(first_name_th,''), ' ', COALESCE(last_name_th,'')) AS display_name";
            $photoSelect = $hasPhoto ? "photo" : "NULL AS photo";

            $tStmt = $pdo->query("SELECT id, {$nameSelect}, {$photoSelect} FROM teachers 
                                  WHERE position = 'ผู้บริหาร' 
                                  OR department = 'ผู้อำนวยการ' 
                                  OR department = 'รองผู้อำนวยการ' 
                                  ORDER BY display_name ASC");
            $teachers = $tStmt->fetchAll();
        } catch (Exception $e) { $teachers = []; }

        echo json_encode(['success' => true, 'executives' => $execs, 'teachers' => $teachers]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        // Expects array of { position_slug, teacher_id }
        $rows = $data['rows'] ?? [];
        $stmt = $pdo->prepare("UPDATE executives SET teacher_id=? WHERE position_slug=?");
        foreach ($rows as $row) {
            $tid = ($row['teacher_id'] === '' || $row['teacher_id'] === null) ? null : intval($row['teacher_id']);
            $stmt->execute([$tid, $row['position_slug']]);
        }
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
