<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
@ignore_user_abort(true);

require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ============================================================
// Mapping: Excel/CSV header → DB column in `teachers`
// ============================================================
$mapping = [
    // ── Required-ish ──
    'EMAIL'             => 'email',
    'อีเมล'             => 'email',
    'ชื่อ'              => 'first_name_th',
    'สกุล'              => 'last_name_th',
    'นามสกุล'           => 'last_name_th',
    'คำนำหน้า'          => 'prefix',
    'คำนำหน้าชื่อ'      => 'prefix',

    // ── Optional ──
    'IDT'               => 'teacher_id',
    'รหัสบุคลากร'       => 'teacher_id',
    'NAME'              => 'first_name_en',
    'SURENAME'          => 'last_name_en',
    'SURNAME'           => 'last_name_en',
    'ห้อง'              => 'classroom',          // shorthand
    'ห้องที่ปรึกษา'     => 'classroom',          // ← ตัวที่ user ระบุ
    'คณะ'              => 'faculty',
    'รูปถ่าย'           => 'photo',
    'ตำแหน่ง'           => 'position',
    'วิทยฐานะ'          => 'academic_standing',
    'กลุ่มสาระการเรียนรู้' => 'department',
    'สาขาย่อย'          => 'sub_department',
    'เลขบัตรประชาชน'    => 'id_card',
    'เบอร์โทรศัพท์'    => 'phone',
    'ID Line'           => 'line_id',
    'ปีที่เกษียณ'       => 'retirement_year',
];

// ============================================================
// Read uploaded file (CSV or XLSX)
// ============================================================
if (!isset($_FILES['excelFile'])) {
    echo json_encode(['error' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$file = $_FILES['excelFile'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    $headers = array_map(fn($h) => trim($h, " \t\n\r\0\x0B\""), @fgetcsv($handle, 0, $delim, '"', ''));
    while (($row = @fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
        $rows[] = array_combine($headers, $row);
    }
    fclose($handle);
} elseif (in_array($ext, ['xlsx', 'xls'])) {
    if (file_exists('../../vendor/autoload.php')) {
        require_once '../../vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $data  = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map('trim', $data[0]);
        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) < count($headers)) $data[$i] = array_pad($data[$i], count($headers), '');
            $rows[] = array_combine($headers, $data[$i]);
        }
    } else {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            echo json_encode(['error' => 'อ่านไฟล์ Excel ไม่ได้ กรุณา save เป็น .xlsx']);
            exit;
        }
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ssObj = simplexml_load_string($ssXml);
            foreach ($ssObj->si as $si) {
                $val = '';
                foreach ($si->r as $r) $val .= (string)$r->t;
                if ($val === '') $val = (string)$si->t;
                $sharedStrings[] = $val;
            }
        }
        $zip->close();
        $excelRows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $v = (string)($cell->v ?? '');
                if ((string)$cell['t'] === 's') $v = $sharedStrings[(int)$v] ?? '';
                $rowData[] = $v;
            }
            $excelRows[] = $rowData;
        }
        if (empty($excelRows)) { echo json_encode(['error' => 'ไฟล์ว่างเปล่า']); exit; }
        $headers = array_map('trim', $excelRows[0]);
        for ($i = 1; $i < count($excelRows); $i++) {
            $r = $excelRows[$i];
            if (count($r) < count($headers)) $r = array_pad($r, count($headers), '');
            $rows[] = array_combine($headers, $r);
        }
    }
} else {
    echo json_encode(['error' => 'รองรับเฉพาะไฟล์ .csv และ .xlsx เท่านั้น']);
    exit;
}

// ============================================================
// Helpers
// ============================================================
function normaliseRoomCode(string $val): string {
    if ($val === '' || preg_match('/^[1-6]\d{2}$/', $val)) return $val;
    preg_match_all('/\d+/', $val, $matches);
    if (count($matches[0]) >= 2) {
        $grade = (int)$matches[0][0];
        if ($grade >= 1 && $grade <= 6) {
            return $grade . str_pad($matches[0][1], 2, '0', STR_PAD_LEFT);
        }
    }
    return $val;
}

/**
 * Extract prefix and clean name
 * Example: "นายสมชาย" -> ["นาย", "สมชาย"]
 * Example: "ครูใจดี" -> ["ครู", "ใจดี"]
 */
function parseThaiName($fullName) {
    $prefixes = ['นาย', 'นางสาว', 'นาง', 'เด็กชาย', 'เด็กหญิง', 'ว่าที่ร้อยตรี', 'ดร.', 'ศ.', 'พศ.', 'ครู'];
    $fullName = trim($fullName);
    $foundPrefix = '';
    
    foreach ($prefixes as $p) {
        if (mb_strpos($fullName, $p) === 0) {
            $foundPrefix = $p;
            $fullName = mb_substr($fullName, mb_strlen($p));
            break;
        }
    }
    
    // Split name and surname if space exists
    $parts = preg_split('/\s+/', $fullName, 2);
    return [
        'prefix' => $foundPrefix,
        'first'  => $parts[0] ?? '',
        'last'   => $parts[1] ?? ''
    ];
}

// ============================================================
// Process
// ============================================================
$dryRun      = ($_POST['dry_run'] ?? '0') === '1';
$insertCount = 0;
$updateCount = 0;
$skipCount   = 0;
$willUpdate  = [];   // dry-run: รายการที่จะ update
$skipReasons = [];

try {
    $teacherRoleId = (int)$pdo->query("SELECT id FROM roles WHERE name = 'teacher' LIMIT 1")->fetchColumn();
    if (!$teacherRoleId) throw new Exception("ไม่พบ role 'teacher'");

    // Pre-fetch existing rooms by classroom_code → id (สำหรับ resolve advisory_room_id)
    $roomsByCode = [];
    foreach ($pdo->query("SELECT id, classroom_code FROM rooms")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $roomsByCode[$r['classroom_code']] = (int)$r['id'];
    }

    // Pre-fetch existing teachers (by email and by teacher_id)
    $existingByEmail = [];
    $existingByTid   = [];
    foreach ($pdo->query("SELECT id, teacher_id, email, first_name_th, last_name_th, classroom FROM teachers")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        if (!empty($t['email']))      $existingByEmail[strtolower($t['email'])] = $t;
        if (!empty($t['teacher_id'])) $existingByTid[$t['teacher_id']] = $t;
    }

    // Get valid columns in teachers table (so we don't try to insert non-existent cols)
    $validCols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN) as $col) {
        $validCols[$col] = true;
    }

    // Pre-hash default password ONCE
    $defaultPasswordHash = password_hash('cnp12345', PASSWORD_DEFAULT);

    $stmtUser    = $pdo->prepare("INSERT INTO users (username, password, role, role_id) VALUES (?, ?, 'teacher', ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    $stmtGetUid  = $pdo->prepare("SELECT id FROM users WHERE username = ?");

    if (!$dryRun) $pdo->beginTransaction();
    $batchSize = 100;
    $sinceLastCommit = 0;

    foreach ($rows as $idx => $rawData) {
        // Identify teacher: prefer EMAIL, fallback to IDT
        $email  = trim($rawData['EMAIL'] ?? $rawData['อีเมล'] ?? '');
        $idt    = trim($rawData['IDT']   ?? $rawData['รหัสบุคลากร'] ?? '');
        $prefix = trim($rawData['คำนำหน้า'] ?? $rawData['คำนำหน้าชื่อ'] ?? '');
        $first  = trim($rawData['ชื่อ']  ?? '');
        $last   = trim($rawData['สกุล']  ?? $rawData['นามสกุล'] ?? '');
        $room   = trim($rawData['ห้องที่ปรึกษา'] ?? $rawData['ห้อง'] ?? '');

        // Smart Parsing: If prefix or last name is missing, try to extract from 'ชื่อ'
        if (empty($prefix) || empty($last)) {
            $parsed = parseThaiName($first);
            if (empty($prefix)) $prefix = $parsed['prefix'];
            if (!empty($parsed['first'])) $first = $parsed['first'];
            if (empty($last))   $last   = $parsed['last'];
        }

        // Use email as username if valid, otherwise IDT
        $username = (filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : $idt;

        if (!$username) {
            $skipCount++;
            $skipReasons[] = "แถวที่ " . ($idx + 2) . ": ไม่มี EMAIL หรือ IDT";
            continue;
        }
        if (!$first) {
            $skipCount++;
            $skipReasons[] = "แถวที่ " . ($idx + 2) . " ($username): ไม่มีชื่อ";
            continue;
        }

        // ── Lookup existing record (by email first, then teacher_id) ──
        $emailKey = strtolower($email);
        $existing = (!empty($emailKey) && isset($existingByEmail[$emailKey]))
            ? $existingByEmail[$emailKey]
            : (!empty($idt) && isset($existingByTid[$idt]) ? $existingByTid[$idt] : null);

        // ── Dry-run: classify row ──
        if ($dryRun) {
            if ($existing) {
                $willUpdate[] = [
                    'username'  => $username,
                    'old_name'  => trim(($existing['prefix'] ?? '') . $existing['first_name_th'] . ' ' . $existing['last_name_th']),
                    'new_name'  => trim($prefix . $first . ' ' . $last),
                    'old_class' => $existing['classroom'] ?? '-',
                    'new_class' => $room ?: '-',
                    'is_different' => true,
                ];
                $updateCount++;
            } else {
                $insertCount++;
            }
            continue;
        }

        // ── Real run ──
        if ($existing) {
            // UPDATE existing teacher record
            $teacherId = $existing['id'];

            // Build fieldMap for UPDATE
            $fieldMap = [
                'prefix'       => $prefix,
                'first_name_th'=> $first,
                'last_name_th' => $last,
            ];
            if ($email) $fieldMap['email'] = $email;
            if ($idt)   $fieldMap['teacher_id'] = $idt;

            foreach ($mapping as $headerName => $dbCol) {
                if (!isset($rawData[$headerName])) continue;
                if (!isset($validCols[$dbCol]))    continue;
                if (in_array($dbCol, ['prefix', 'first_name_th', 'last_name_th', 'email', 'teacher_id'])) continue;

                $val = trim($rawData[$headerName]);
                if ($val === '') continue;

                if ($dbCol === 'phone' && $val[0] !== '0' && strlen($val) >= 9) $val = '0' . $val;
                if ($dbCol === 'classroom') $val = normaliseRoomCode($val);

                $fieldMap[$dbCol] = $val;
            }

            // Resolve advisory_room_id
            if (isset($fieldMap['classroom']) && isset($roomsByCode[$fieldMap['classroom']]) && isset($validCols['advisory_room_id'])) {
                $fieldMap['advisory_room_id'] = $roomsByCode[$fieldMap['classroom']];
            }

            if (!empty($fieldMap)) {
                $setParts = [];
                $setVals  = [];
                foreach ($fieldMap as $col => $val) {
                    $setParts[] = "`$col` = ?";
                    $setVals[]  = $val;
                }
                $setVals[] = $teacherId;
                $pdo->prepare("UPDATE teachers SET " . implode(', ', $setParts) . " WHERE id = ?")
                    ->execute($setVals);
            }
            $updateCount++;

        } else {

        // Set basic fields manually to ensure they are updated by our smart parsing
        $fields = ['user_id', 'prefix', 'first_name_th', 'last_name_th', 'email'];
        $placeholders = ['?', '?', '?', '?', '?'];
        $values = [$uid, $prefix, $first, $last, $email];

        // teacher_id handling: If empty, use NULL to avoid UNIQUE constraint violation on empty strings
        if (isset($validCols['teacher_id'])) {
            $fields[] = 'teacher_id';
            $placeholders[] = '?';
            $values[] = !empty($idt) ? $idt : null;
        }

        $advisoryRoomCode = null;

        foreach ($mapping as $headerName => $dbCol) {
            if (!isset($rawData[$headerName])) continue;
            if (!isset($validCols[$dbCol]))    continue;
            
            // Skip basic fields as we already added them
            if (in_array($dbCol, ['prefix', 'first_name_th', 'last_name_th', 'email', 'teacher_id'])) continue;

            $val = trim($rawData[$headerName]);
            if ($val === '') continue;

            // Phone normalisation: prepend 0 if missing
            if ($dbCol === 'phone' && $val[0] !== '0' && strlen($val) >= 9) {
                $val = '0' . $val;
            }

            // Classroom: normalise "ม.5/1" → "501"
            if ($dbCol === 'classroom') {
                $val = normaliseRoomCode($val);
                $advisoryRoomCode = $val;
            }

            // Don't double-write same column (e.g., 'ห้อง' and 'ห้องที่ปรึกษา' both map to classroom)
            $existingIdx = array_search($dbCol, $fields, true);
            if ($existingIdx !== false) {
                $values[$existingIdx] = $val;
                continue;
            }

            $fields[]       = $dbCol;
            $placeholders[] = '?';
            $values[]       = $val;
        }

        // Resolve advisory_room_id from classroom code
        if ($advisoryRoomCode && isset($roomsByCode[$advisoryRoomCode]) && isset($validCols['advisory_room_id'])) {
            $fields[]       = 'advisory_room_id';
            $placeholders[] = '?';
            $values[]       = $roomsByCode[$advisoryRoomCode];
        }

            // INSERT new teacher
            $stmtUser->execute([$username, $defaultPasswordHash, $teacherRoleId]);
            $uid = $pdo->lastInsertId();
            if (!$uid) { $stmtGetUid->execute([$username]); $uid = $stmtGetUid->fetchColumn(); }

            // Update $values[0] with correct uid
            $values[0] = $uid;

            $sql = "INSERT INTO teachers (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
            $pdo->prepare($sql)->execute($values);

            $insertCount++;
            $sinceLastCommit++;
        } // end if/else (existing)

        if (!$dryRun && $sinceLastCommit >= $batchSize) {
            $pdo->commit();
            $pdo->beginTransaction();
            $sinceLastCommit = 0;
        }
    }

    if (!$dryRun && $pdo->inTransaction()) $pdo->commit();

    echo json_encode([
        'success'         => true,
        'inserted'        => $insertCount,
        'updated'         => $updateCount,
        'imported'        => $insertCount + $updateCount,
        'skipped'         => $skipCount,
        'duplicates'      => $dryRun ? $willUpdate : [],
        'duplicate_count' => $dryRun ? count($willUpdate) : $updateCount,
        'skip_reasons'    => array_slice($skipReasons, 0, 20),
        'message'         => $dryRun
            ? "ตรวจสอบแล้ว: เพิ่มใหม่ $insertCount รายการ, อัปเดต $updateCount รายการ"
            : "เพิ่มใหม่ $insertCount รายการ, อัปเดต $updateCount รายการ, ข้าม $skipCount รายการ",
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
