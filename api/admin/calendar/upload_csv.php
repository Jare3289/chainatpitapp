<?php
// api/admin/calendar/upload_csv.php
require_once '../../../config.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['csv_file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$months_th = [
    "มกราคม" => "01", "กุมภาพันธ์" => "02", "มีนาคม" => "03", "เมษายน" => "04",
    "พฤษภาคม" => "05", "มิถุนายน" => "06", "กรกฎาคม" => "07", "สิงหาคม" => "08",
    "กันยายน" => "09", "ตุลาคม" => "10", "พฤศจิกายน" => "11", "ธันวาคม" => "12",
    "ม.ค." => "01", "ก.พ." => "02", "มี.ค." => "03", "เม.ย." => "04",
    "พ.ค." => "05", "มิ.ย." => "06", "ก.ค." => "07", "ส.ค." => "08",
    "ก.ย." => "09", "ต.ค." => "10", "พ.ย." => "11", "ธ.ค." => "12"
];

function parseThaiDateToISO($dateStr) {
    global $months_th;
    $dateStr = trim($dateStr);
    if (empty($dateStr)) return [];
    
    // Support ISO format YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) return [$dateStr];

    // 1. Support Numeric Format (D/M/Y): "18/5/2026" or "18-5-2569"
    if (preg_match('/^(\d+)[-\/\.](\d+)[-\/\.](\d+)$/', $dateStr, $m)) {
        $d = (int)$m[1];
        $m_num = (int)$m[2];
        $y = (int)$m[3];
        
        // BE detection
        if ($y > 2400) $y -= 543;
        else if ($y < 100) $y += 2000;
        
        return [sprintf("%04d-%02d-%02d", $y, $m_num, $d)];
    }

    // 2. Handle Range: "14 - 22 ตุลาคม 2569"
    if (preg_match('/(\d+)\s*[-–]\s*(\d+)\s+([\x{0E00}-\x{0E7F}A-Za-z.]+)\s+(\d+)/u', $dateStr, $m)) {
        $start_d = (int)$m[1];
        $end_d = (int)$m[2];
        $m_name = trim($m[3]);
        $y_be = (int)$m[4];
        $y_ce = $y_be > 2400 ? $y_be - 543 : $y_be;
        $m_num = isset($months_th[$m_name]) ? $months_th[$m_name] : '01';
        
        $dates = [];
        for ($d = $start_d; $d <= $end_d; $d++) {
            $dates[] = sprintf("%04d-%02d-%02d", $y_ce, $m_num, $d);
        }
        return $dates;
    }

    // 3. Handle Single Thai: "18 พฤษภาคม 2569" or "18 พ.ค. 2569"
    if (preg_match('/(\d+)\s*([\x{0E00}-\x{0E7F}A-Za-z.]+)\s*(\d+)/u', $dateStr, $m)) {
        $d = (int)$m[1];
        $m_name = trim($m[2]);
        $y_be = (int)$m[3];
        $y_ce = $y_be > 2400 ? $y_be - 543 : $y_be;
        if ($y_ce < 100) $y_ce += 2000;
        $m_num = isset($months_th[$m_name]) ? $months_th[$m_name] : '01';
        return [sprintf("%04d-%02d-%02d", $y_ce, $m_num, $d)];
    }

    return [];
}

try {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // Check and remove BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    // Detect delimiter (Semi-colon common in Thai Excel)
    $firstLine = fgets($handle);
    $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3); // Skip BOM again after rewind

    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO academic_days (academic_year, semester, date_val, activity, note, day_type) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                                activity = VALUES(activity), 
                                note = VALUES(note), 
                                academic_year = VALUES(academic_year), 
                                semester = VALUES(semester),
                                day_type = VALUES(day_type)");

    $count = 0;
    $isHeader = true;
    $debug = [];

    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        if ($isHeader) { 
            $isHeader = false; 
            continue; 
        }
        
        // Skip empty lines
        if (empty($data) || !isset($data[2])) continue;

        $year = trim($data[0]);
        $semester = trim($data[1]);
        $rawDate = trim($data[2]);
        $activity = isset($data[3]) ? trim($data[3]) : '';
        $note = isset($data[4]) ? trim($data[4]) : '';
        $dayType = isset($data[5]) ? trim($data[5]) : 'ปกติ';

        if (empty($year) || empty($rawDate)) continue;

        $isoDates = parseThaiDateToISO($rawDate);
        if (empty($isoDates)) {
            $debug[] = "Could not parse date: $rawDate";
            continue;
        }

        foreach ($isoDates as $iso) {
            $stmt->execute([$year, $semester, $iso, $activity, $note, $dayType]);
            $count++;
        }
    }

    fclose($handle);
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "บันทึกข้อมูลเรียบร้อยแล้ว $count รายการ", 'debug' => $debug]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
