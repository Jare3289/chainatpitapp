<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Configuration ──
set_time_limit(0);
ini_set('memory_limit', '512M');

$file = $_FILES['file'] ?? null;
if (!$file) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

$cleanHeaders = function($h) { 
    $h = trim((string)$h);
    $res = preg_replace('/[^\x20-\x7E\x{0E00}-\x{0E7F}]+/u', '', $h); 
    return trim($res);
};

// ── Read File Content ──
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
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
                        if ($si->r) { foreach($si->r as $r) $val .= (string)$r->t; }
                        else { $val = (string)$si->t; }
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

if (empty($rows)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลที่สามารถนำเข้าได้']);
    exit;
}

// ── Process Data ──
try {
    $pdo->beginTransaction();
    
    // Preparation
    $stmtCheck = $pdo->prepare("SELECT id FROM classrooms WHERE classroom_code = ?");
    $stmtUpdate = $pdo->prepare("UPDATE classrooms SET 
        grade_level = ?, class_level = ?, classroom_no = ?, 
        building = ?, floor = ?, room_no = ?, 
        location_code = ?, house = ?, program = ? 
        WHERE classroom_code = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO classrooms 
        (classroom_code, grade_level, class_level, classroom_no, building, floor, room_no, location_code, house, program) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $successCount = 0;
    $updateCount = 0;

    foreach ($rows as $row) {
        $code = trim($row['รหัสห้อง (Code)'] ?? '');
        if (!$code) continue;

        $grade = trim($row['ระดับชั้น (เช่น มัธยมศึกษาปีที่ 1)'] ?? '');
        $level = (int)($row['ม.ปีที่'] ?? 0);
        $no    = (int)($row['ห้องที่'] ?? 0);
        $build = (int)($row['อาคาร'] ?? 0);
        $floor = (int)($row['ชั้น'] ?? 0);
        $rm_no = (int)($row['เลขห้อง'] ?? 0);
        $loc   = trim($row['รหัสที่ตั้ง'] ?? '');
        $house = trim($row['คณะ (บ้าน)'] ?? '');
        $prog  = trim($row['แผนการเรียน'] ?? '');

        $stmtCheck->execute([$code]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $stmtUpdate->execute([$grade, $level, $no, $build, $floor, $rm_no, $loc, $house, $prog, $code]);
            $updateCount++;
        } else {
            $stmtInsert->execute([$code, $grade, $level, $no, $build, $floor, $rm_no, $loc, $house, $prog]);
            $successCount++;
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'imported' => $successCount, 
        'updated' => $updateCount,
        'message' => "นำเข้าสำเร็จ $successCount รายการ, อัปเดต $updateCount รายการ"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
