<?php
header('Content-Type: application/json');
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Mapping Thai Headers to DB Columns
$mapping = [
    "รหัสวิชา" => "subject_code",
    "ชื่อวิชา" => "subject_name",
    "ภาคเรียน" => "semester",
    "ปีการศึกษา" => "academic_year",
    "กลุ่มสาระการเรียนรู้" => "department",
    "ชั้น" => "room",
    "ครูผู้สอน" => "teacher_name"
];

// Normalize mapping keys
$normalizedMapping = [];
foreach ($mapping as $k => $v) {
    $cleanK = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $k);
    $normalizedMapping[$cleanK] = $v;
}

if (!isset($_FILES['excelFile'])) {
    echo json_encode(['error' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$file = $_FILES['excelFile'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

$cleanHeaders = function($h) { 
    $h = trim((string)$h);
    $res = @preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $h); 
    return $res !== null ? $res : $h;
};

// ── Read File Content ──
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    
    $headerRow = @fgetcsv($handle, 0, $delim, '"', '');
    $headers = is_array($headerRow) ? array_map($cleanHeaders, $headerRow) : [];
    while (($row = @fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
        $rows[] = array_combine($headers, $row);
    }
    fclose($handle);
} elseif (in_array($ext, ['xlsx', 'xls'])) {
    if (file_exists('../../vendor/autoload.php')) {
        require_once '../../vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $headerRow = $data[0] ?? [];
        $headers = is_array($headerRow) ? array_map($cleanHeaders, $headerRow) : [];
        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) < count($headers)) $data[$i] = array_pad($data[$i], count($headers), '');
            $rows[] = array_combine($headers, $data[$i]);
        }
    } else {
        // Fallback ZipArchive reader for XLSX
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            $xml = @simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $sharedStrings = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml) {
                $ssObj = @simplexml_load_string($ssXml);
                if ($ssObj) {
                    foreach ($ssObj->si as $si) {
                        $val = '';
                        foreach ($si->r as $r) $val .= (string)$r->t;
                        if (empty($val)) $val = (string)$si->t;
                        $sharedStrings[] = $val;
                    }
                }
            }
            $zip->close();
            $excelRows = [];
            if ($xml) {
                foreach ($xml->sheetData->row as $row) {
                    $rowData = [];
                    foreach ($row->c as $cell) {
                        $v = (string)($cell->v ?? '');
                        if ((string)$cell['t'] === 's') $v = $sharedStrings[(int)$v] ?? '';
                        $rowData[] = $v;
                    }
                    $excelRows[] = $rowData;
                }
            }
            if (!empty($excelRows)) {
                $headerRow = $excelRows[0] ?? [];
                $headers = is_array($headerRow) ? array_map($cleanHeaders, $headerRow) : [];
                for ($i = 1; $i < count($excelRows); $i++) {
                    $r = $excelRows[$i];
                    if (count($r) < count($headers)) $r = array_pad($r, count($headers), '');
                    $rows[] = array_combine($headers, $r);
                }
            }
        }
    }
}

// ── Process Data ──
$dryRun = ($_POST['dry_run'] ?? '0') === '1';
$subjectsToProcess = [];

// Cache teachers
$teacherStmt = $pdo->query("SELECT user_id, prefix, first_name_th, last_name_th FROM teachers");
$allTeachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

function findTeacherId($name, $allTeachers) {
    $name = trim($name);
    if (!$name) return 0; // Default to 0 instead of null for better unique key matching
    
    // 1. Exact match (Full name or Name + Surname)
    foreach ($allTeachers as $t) {
        $fullName = trim(($t['prefix'] ?? '') . ($t['first_name_th'] ?? '') . ' ' . ($t['last_name_th'] ?? ''));
        $shortName = trim(($t['first_name_th'] ?? '') . ' ' . ($t['last_name_th'] ?? ''));
        if ($name === $fullName || $name === $shortName) return (int)$t['user_id'];
    }
    
    // 2. Partial match (Match prefix + start of first name)
    foreach ($allTeachers as $t) {
        $prefixFirst = trim(($t['prefix'] ?? '') . ($t['first_name_th'] ?? ''));
        if (strpos($name, $prefixFirst) === 0 || strpos($prefixFirst, $name) === 0) return (int)$t['user_id'];
    }
    
    // 3. Just First Name match
    foreach ($allTeachers as $t) {
        if ($t['first_name_th'] && strpos($name, $t['first_name_th']) !== false) return (int)$t['user_id'];
    }
    
    return 0; 
}

foreach ($rows as $idx => $rawData) {
    $col = function($key) use ($rawData, $normalizedMapping) {
        $cleanK = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $key);
        return trim($rawData[$cleanK] ?? '');
    };

    $code = $col('รหัสวิชา');
    $name = $col('ชื่อวิชา');
    $sem = (int)$col('ภาคเรียน');
    $year = $col('ปีการศึกษา');
    $dept = $col('กลุ่มสาระการเรียนรู้');
    $room = $col('ชั้น');
    $teacherName = $col('ครูผู้สอน');

    if (!$code || !$name) continue;

    $teacherId = findTeacherId($teacherName, $allTeachers);
    $key = "{$code}_{$year}_{$sem}_{$teacherId}";

    if (!isset($subjectsToProcess[$key])) {
        $subjectsToProcess[$key] = [
            'subject_code' => $code,
            'subject_name' => $name,
            'semester' => $sem,
            'academic_year' => $year,
            'department' => $dept,
            'teacher_id' => $teacherId,
            'teacher_name' => $teacherName,
            'rooms' => [$room]
        ];
    } else {
        if (!in_array($room, $subjectsToProcess[$key]['rooms'])) {
            $subjectsToProcess[$key]['rooms'][] = $room;
        }
    }
}

// Check for duplicates (existing assignments)
$existingMap = [];
$stmt = $pdo->query("SELECT subject_code, academic_year, semester, teacher_id, room FROM subjects");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingMap["{$row['subject_code']}_{$row['academic_year']}_{$row['semester']}_{$row['teacher_id']}"] = $row;
}

$duplicates = [];
$inserted = 0;
$updated = 0;

foreach ($subjectsToProcess as $key => $s) {
    if (isset($existingMap[$key])) {
        $old = $existingMap[$key];
        $newRooms = implode(',', $s['rooms']);
        $duplicates[] = [
            'student_id' => $s['subject_code'], // Map to 'รหัส' in modal
            'old_name' => "ห้อง: " . ($old['room'] ?: '-'),
            'new_name' => "ห้อง: " . $newRooms,
            'old_class' => $s['teacher_name'],
            'new_class' => $s['teacher_name'],
            'is_different' => ($old['room'] !== $newRooms)
        ];
        $updated++;
    } else {
        $inserted++;
    }
}

if ($dryRun) {
    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'duplicates' => $duplicates,
        'message' => "ตรวจสอบแล้ว: เพิ่มใหม่ $inserted รายการ, อัปเดต $updated รายการ"
    ]);
    exit;
}

// ── Actual Upload ──
try {
    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, teacher_id, academic_year, semester, department, room, year_be, rooms) 
                                 VALUES (:code, :name, :teacher, :year, :sem, :dept, :room, :year_be, :rooms)
                                 ON DUPLICATE KEY UPDATE 
                                 subject_name = VALUES(subject_name),
                                 department = VALUES(department),
                                 room = VALUES(room),
                                 rooms = VALUES(rooms),
                                 year_be = VALUES(year_be)");

    foreach ($subjectsToProcess as $s) {
        $roomsStr = implode(',', $s['rooms']);
        $insertStmt->execute([
            ':code' => $s['subject_code'],
            ':name' => $s['subject_name'],
            ':teacher' => $s['teacher_id'],
            ':year' => $s['academic_year'],
            ':sem' => $s['semester'],
            ':dept' => $s['department'],
            ':room' => $roomsStr,
            ':year_be' => $s['academic_year'],
            ':rooms' => $roomsStr
        ]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => count($subjectsToProcess), 'message' => 'นำเข้าข้อมูลรายวิชาสำเร็จ ' . count($subjectsToProcess) . ' รายการ']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
