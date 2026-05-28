<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'ไม่พบไฟล์']);
    exit;
}

$file = $_FILES['file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// ── อ่านไฟล์ ──────────────────────────────────────────────────
$rows = [];
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    // ลบ BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $firstLine = fgets($handle);
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

    $headers = fgetcsv($handle, 0, $delim, '"', '');
    while (($row = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($row) >= count($headers)) {
            $rows[] = array_combine($headers, array_slice($row, 0, count($headers)));
        }
    }
    fclose($handle);
} elseif (in_array($ext, ['xlsx', 'xls'])) {
    if (file_exists('../../vendor/autoload.php')) {
        require_once '../../vendor/autoload.php';
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name'])->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $headers = $data[0] ?? [];
        for ($i = 1; $i < count($data); $i++) {
            $r = array_pad($data[$i], count($headers), '');
            $rows[] = array_combine($headers, array_slice($r, 0, count($headers)));
        }
    } else {
        echo json_encode(['error' => 'ไม่รองรับ .xlsx บน server นี้ กรุณาใช้ .csv แทน']);
        exit;
    }
} else {
    echo json_encode(['error' => 'รองรับเฉพาะ .csv และ .xlsx']);
    exit;
}

if (empty($rows)) {
    echo json_encode(['error' => 'ไฟล์ว่างเปล่า']);
    exit;
}

// ── หา index ของ student_id และ number_in_class ──────────────
$headers = array_keys($rows[0]);

// ทำความสะอาด header (ตัด zero-width + whitespace)
$clean = function($s) {
    return preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', trim((string)$s));
};

$cleanedHeaders = array_map($clean, $headers);

// หา student_id column
$sidVariants = ['IDS(รหัสนักเรียน)', 'รหัสนักเรียน', 'studentid', 'student_id', 'IDS', 'รหัส'];
$sidCol = null;
foreach ($sidVariants as $v) {
    $cv = $clean($v);
    $pos = array_search($cv, $cleanedHeaders);
    if ($pos !== false) { $sidCol = $headers[$pos]; break; }
}

// หา number_in_class column — ลอง ทุก ชื่อที่เป็นไปได้
$numVariants = ['เลขที่', 'เลขที่นักเรียน', 'เลขที่นักเรียนในห้อง', 'ลำดับที่', 'ลำดับ', 'ที่', 'number_in_class', 'no', 'number'];
$numCol = null;
foreach ($numVariants as $v) {
    $cv = $clean($v);
    $pos = array_search($cv, $cleanedHeaders);
    if ($pos !== false) { $numCol = $headers[$pos]; break; }
}

// ถ้าหาไม่เจอ ให้ส่ง header list กลับไปให้ user เห็น
if (!$sidCol || !$numCol) {
    echo json_encode([
        'error'          => 'ไม่พบคอลัมน์ที่ต้องการ',
        'sid_found'      => $sidCol ? "พบ: $sidCol" : 'ไม่พบคอลัมน์รหัสนักเรียน',
        'num_found'      => $numCol ? "พบ: $numCol" : 'ไม่พบคอลัมน์เลขที่',
        'all_headers'    => $cleanedHeaders,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── อัปเดต ──────────────────────────────────────────────────
$updated  = 0;
$skipped  = 0;
$notFound = 0;
$errors   = [];

$stmt = $pdo->prepare("UPDATE students SET number_in_class = ? WHERE student_id = ?");

foreach ($rows as $idx => $row) {
    $stdId = trim($row[$sidCol] ?? '');
    $num   = trim($row[$numCol] ?? '');

    if ($stdId === '') { $skipped++; continue; }
    if ($num   === '') { $skipped++; continue; }

    try {
        $stmt->execute([$num, $stdId]);
        if ($stmt->rowCount() > 0) {
            $updated++;
        } else {
            $notFound++;
        }
    } catch (PDOException $e) {
        $errors[] = "แถว " . ($idx + 2) . " ($stdId): " . $e->getMessage();
    }
}

echo json_encode([
    'success'     => true,
    'updated'     => $updated,
    'not_found'   => $notFound,
    'skipped'     => $skipped,
    'errors'      => array_slice($errors, 0, 10),
    'sid_col'     => $sidCol,
    'num_col'     => $numCol,
    'message'     => "อัปเดตเลขที่สำเร็จ $updated รายการ" . ($notFound ? " (ไม่พบรหัสนักเรียน $notFound ราย)" : ''),
], JSON_UNESCAPED_UNICODE);
