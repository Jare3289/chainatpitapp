<?php
header('Content-Type: application/json');
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!isset($_FILES['excelFile'])) {
    echo json_encode(['error' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$recorded_by = $_SESSION['user_id'];
$file = $_FILES['excelFile'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

$cleanHeaders = function($h) { 
    $h = trim((string)$h);
    // Remove all non-visible characters, BOM, and spaces
    $res = preg_replace('/[^\x20-\x7E\x{0E00}-\x{0E7F}]+/u', '', $h); 
    return trim($res);
};

// ── Read File Content ──
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    
    // Detect delimiter: Comma, Semicolon, or Tab
    $delim = ',';
    if (strpos($firstLine, "\t") !== false) $delim = "\t";
    elseif (strpos($firstLine, ';') !== false) $delim = ";";
    
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
$stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
$settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = [
    'academic_year' => $settings_raw['current_academic_year'] ?? '2568',
    'semester' => $settings_raw['current_semester'] ?? '1'
];

// Cache students and behavior items for fast lookup
$studentMap = [];
$stmt = $pdo->query("SELECT id, student_id FROM students");
while ($s = $stmt->fetch(PDO::FETCH_ASSOC)) { $studentMap[$s['student_id']] = $s['id']; }

$itemMap = [];
$stmt = $pdo->query("SELECT id, item_name FROM point_items");
while ($i = $stmt->fetch(PDO::FETCH_ASSOC)) { $itemMap[trim($i['item_name'])] = $i['id']; }

$teacherMap = [];
$stmt = $pdo->query("SELECT user_id as id, CONCAT(prefix, first_name_th, ' ', last_name_th) as name, first_name_th, last_name_th FROM teachers");
while ($t = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    $teacherMap[trim($t['name'])] = $t['id'];
    $teacherMap[trim($t['first_name_th'] . ' ' . $t['last_name_th'])] = $t['id'];
}

$dryRun = ($_POST['dry_run'] ?? '0') === '1';
$successCount = 0;
$duplicateCount = 0;
$skipCount = 0;
$errors = [];
$willUpdate = [];

// Cache existing transactions to detect duplicates
$existingTx = [];
$stmt = $pdo->query("SELECT student_id, item_id, points, occurrence_date, recorded_by, remark, semester, academic_year FROM point_transactions");
while ($tx = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $tx['student_id'] . '_' . $tx['item_id'] . '_' . (int)$tx['points'] . '_' . $tx['occurrence_date'] . '_' . $tx['recorded_by'] . '_' . ($tx['semester'] ?? '') . '_' . ($tx['academic_year'] ?? '');
    $existingTx[$key] = $tx;
}

try {
    if (!$dryRun) {
        $pdo->beginTransaction();
    }
    
    $sql = "INSERT INTO point_transactions (student_id, item_id, points, remark, recorded_by, occurrence_date, semester, academic_year, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);

    foreach ($rows as $idx => $row) {
        $studentCode = trim($row['รหัสนักเรียน'] ?? '');
        $type = trim($row['ประเภท (เติม/ตัด)'] ?? $row['ประเภท'] ?? '');
        $itemName = trim($row['รายการพฤติกรรม'] ?? '');
        $points = (int)($row['คะแนน'] ?? 0);
        $remark = trim($row['หมายเหตุ'] ?? '');
        $dateRaw = trim($row['วันที่ (ค.ศ. เช่น 2024-05-13)'] ?? $row['วันที่'] ?? '');
        $yearRaw  = trim($row['ปีการศึกษา (เช่น 2568)'] ?? $row['ปีการศึกษา'] ?? '');
        $semRaw   = trim($row['ภาคเรียน (1 หรือ 2)'] ?? $row['ภาคเรียน'] ?? '');
        $teacherName = trim($row['ครูผู้บันทึก'] ?? '');
        // ปีการศึกษาและภาคเรียน: ใช้จาก CSV ถ้าระบุไว้ ไม่งั้นใช้ค่าปัจจุบันจากระบบ
        $finalYear     = ($yearRaw !== '' && is_numeric($yearRaw)) ? (string)(int)$yearRaw : $settings['academic_year'];
        $finalSemester = ($semRaw  !== '' && in_array((int)$semRaw, [1, 2])) ? (string)(int)$semRaw : $settings['semester'];

        if (!$studentCode && !$itemName) {
            $skipCount++;
            continue;
        }

        // 1. Validate Student
        $studentInternalId = $studentMap[$studentCode] ?? null;
        if (!$studentInternalId) {
            $errors[] = "แถวที่ " . ($idx + 2) . ": ไม่พบรหัสนักเรียน '$studentCode'";
            $skipCount++;
            continue;
        }

        // 2. Validate Behavior Item
        $itemId = $itemMap[$itemName] ?? null;
        if (!$itemId) {
            $errors[] = "แถวที่ " . ($idx + 2) . ": ไม่พบรายการพฤติกรรม '$itemName'";
            $skipCount++;
            continue;
        }

        // 3. Normalize Date
        $finalDate = date('Y-m-d'); // Default
        if ($dateRaw) {
            // Handle common formats like 7/25/2024 or 25/7/2024
            $d = str_replace('/', '-', $dateRaw);
            $time = strtotime($d);
            if ($time) {
                $finalDate = date('Y-m-d', $time);
            } else {
                // Try manual parsing for Excel dates or weird formats
                $parts = preg_split('/[-\/.]/', $dateRaw);
                if (count($parts) === 3) {
                    // Try to guess if it's Y-m-d or d-m-Y or m-d-Y
                    if (strlen($parts[0]) === 4) $finalDate = "$parts[0]-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                    elseif ((int)$parts[1] > 12) $finalDate = "$parts[2]-" . str_pad($parts[0], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT); // m-d-Y
                    else $finalDate = "$parts[2]-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[0], 2, '0', STR_PAD_LEFT); // d-m-Y
                }
            }
        }

        $finalPoints = ($type === 'ตัด') ? -abs($points) : abs($points);
        
        $finalRecordedBy = $recorded_by;
        if ($teacherName && isset($teacherMap[$teacherName])) {
            $finalRecordedBy = $teacherMap[$teacherName];
        }

        // Check for duplicate transaction (key includes year+semester to allow same record in different terms)
        $txKey = $studentInternalId . '_' . $itemId . '_' . $finalPoints . '_' . $finalDate . '_' . $finalRecordedBy . '_' . $finalSemester . '_' . $finalYear;
        if (isset($existingTx[$txKey])) {
            $duplicateCount++;
            if ($dryRun) {
                $willUpdate[] = [
                    'student_id'   => $studentCode,
                    'old_name'     => 'บันทึกความประพฤติเดิม',
                    'new_name'     => $itemName . ' (' . ($finalPoints > 0 ? '+' : '') . $finalPoints . ' คะแนน)',
                    'old_class'    => $existingTx[$txKey]['remark'] ?: '-',
                    'new_class'    => $remark ?: '-',
                    'is_different' => ($existingTx[$txKey]['remark'] !== $remark)
                ];
            }
            continue;
        }

        if ($dryRun) {
            $successCount++;
        } else {
            $stmt->execute([
                $studentInternalId,
                $itemId,
                $finalPoints,
                $remark,
                $finalRecordedBy,
                $finalDate,
                $finalSemester,
                $finalYear
            ]);
            $successCount++;
        }
    }

    if ($dryRun) {
        echo json_encode([
            'success' => true,
            'inserted' => $successCount,
            'updated' => $duplicateCount,
            'imported' => $successCount + $duplicateCount,
            'skipped' => $skipCount,
            'duplicates' => $willUpdate,
            'duplicate_count' => $duplicateCount,
            'skip_reasons' => array_slice($errors, 0, 20),
            'message' => "ตรวจสอบแล้ว: ข้อมูลใหม่ $successCount รายการ, ซ้ำ $duplicateCount รายการ"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        if ($successCount > 0) {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'imported' => $successCount, 
                'duplicate_count' => $duplicateCount,
                'errors' => $errors,
                'message' => "นำเข้าสำเร็จ $successCount รายการ (ข้ามรายการซ้ำ $duplicateCount รายการ)"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'error' => 'ไม่มีข้อมูลใหม่ที่สามารถนำเข้าได้', 
                'details' => $errors
            ], JSON_UNESCAPED_UNICODE);
        }
    }

} catch (Exception $e) {
    if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
