<?php
/**
 * api/admin/upload-timetable.php
 * Import timetable from Excel/CSV — admin only
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config.php';
require_once '../../inc/classroom_codes.php';
require_once '../../inc/subject_lookup.php';
$subjectLookup = cnp_subject_lookup();
// ต้องปิด error อีกรอบหลังจากเรียก config.php เพราะในนั้นอาจจะสั่งเปิดไว้
error_reporting(0);
ini_set('display_errors', 0);

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ── Read uploaded file ──────────────────────────────────────────────
if (!isset($_FILES['excelFile'])) {
    echo json_encode(['error' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$file = $_FILES['excelFile'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

// Helper for column aliases
function col(array $row, array $aliases): string {
    foreach ($aliases as $k) {
        if (isset($row[$k]) && trim($row[$k]) !== '') return trim($row[$k]);
    }
    return '';
}

// Read CSV or XLSX
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom    = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    $headers = array_map('trim', fgetcsv($handle, 0, $delim));
    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
        $rows[] = array_combine($headers, $row);
    }
    fclose($handle);
} elseif (in_array($ext, ['xlsx', 'xls'])) {
    // Basic ZipArchive reader for XLSX
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) === true) {
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ssObj = simplexml_load_string($ssXml);
            foreach ($ssObj->si as $si) {
                $val = '';
                if (isset($si->r)) foreach ($si->r as $r) $val .= (string)$r->t;
                else $val = (string)$si->t;
                $sharedStrings[] = $val;
            }
        }
        $zip->close();
        $excelRows = [];
        if (isset($xml->sheetData->row)) {
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
            $headers = array_map('trim', $excelRows[0]);
            for ($i = 1; $i < count($excelRows); $i++) {
                $r = $excelRows[$i];
                if (count($r) < count($headers)) $r = array_pad($r, count($headers), '');
                $rows[] = array_combine($headers, $r);
            }
        }
    }
}

if (empty($rows)) {
    echo json_encode(['error' => 'ไม่พบข้อมูลในไฟล์หรือรูปแบบไฟล์ไม่ถูกต้อง']);
    exit;
}

// ── Process ──────────────────────────────────────────────────────────
$dryRun      = ($_POST['dry_run'] ?? '0') === '1';
$replaceAll  = ($_POST['replace_all'] ?? '0') === '1';
$insertCount = 0;
$updateCount = 0;
$skipCount   = 0;
$skipReasons = [];
$willUpdate  = [];

try {
    // Pre-load teachers
    $teacherByIdt  = [];
    $teacherByName = [];
    $tQuery = $pdo->query("SELECT id, teacher_id, prefix, first_name_th, last_name_th FROM teachers");
    while ($t = $tQuery->fetch()) {
        if (!empty($t['teacher_id'])) $teacherByIdt[trim($t['teacher_id'])] = (int)$t['id'];
        
        $pfx = trim($t['prefix'] ?? '');
        $fname = trim($t['first_name_th'] ?? '');
        $lname = trim($t['last_name_th'] ?? '');
        
        // แบบเต็ม: นายสมชาย ใจดี -> ตัดช่องว่างทั้งหมดเป็น: นายสมชายใจดี
        $fullClean = mb_strtolower(preg_replace('/\s+/', '', $pfx . $fname . $lname));
        if ($fullClean !== '') $teacherByName[$fullClean] = (int)$t['id'];
        
        // แบบไม่มีคำนำหน้า: สมชาย ใจดี -> ตัดช่องว่างเป็น: สมชายใจดี (เผื่อไฟล์ Excel ไม่พิมพ์คำนำหน้า)
        $noPfxClean = mb_strtolower(preg_replace('/\s+/', '', $fname . $lname));
        if ($noPfxClean !== '') $teacherByName[$noPfxClean] = (int)$t['id'];
    }

    // Pre-load existing timetable
    $existingMap = [];
    $ttQuery = $pdo->query("SELECT id, teacher_id, day_of_week, period, class_name FROM timetable");
    while ($tt = $ttQuery->fetch()) {
        $key = $tt['teacher_id'] . '|' . $tt['day_of_week'] . '|' . $tt['period'] . '|' . ($tt['class_name'] ?? '');
        $existingMap[$key] = (int)$tt['id'];
    }

    if (!$dryRun) {
        $pdo->beginTransaction();
        if ($replaceAll) {
            $pdo->exec("DELETE FROM timetable");
            $existingMap = []; // map is now empty
        }
    }
    $batch = 0;

    foreach ($rows as $idx => $raw) {
        $rowNo = $idx + 2;
        $idt      = col($raw, ['IDT (รหัสครู)', 'IDT', 'รหัสครู']);
        $nameRaw  = col($raw, ['ชื่อ-สกุล()', 'ชื่อ-สกุล(ครู)', 'ชื่อครู', 'ชื่อ-สกุล']);
        $cleanRaw = mb_strtolower(preg_replace('/\s+/', '', $nameRaw));
        $teacherPk = ($idt && isset($teacherByIdt[$idt])) ? $teacherByIdt[$idt] : ($teacherByName[$cleanRaw] ?? null);

        if (!$teacherPk) {
            // Auto-create a dummy teacher so the timetable can still be displayed
            $stmtFindDummy = $pdo->prepare("SELECT id FROM teachers WHERE first_name_th = ? AND teacher_id LIKE 'DUMMY_%' LIMIT 1");
            $stmtFindDummy->execute([$nameRaw]);
            $dummyId = $stmtFindDummy->fetchColumn();

            if ($dummyId) {
                $teacherPk = (int)$dummyId;
            } else {
                $dummyIdt = 'DUMMY_' . substr(md5($nameRaw . microtime()), 0, 8);
                $pdo->prepare("INSERT INTO teachers (teacher_id, first_name_th) VALUES (?, ?)")->execute([$dummyIdt, $nameRaw]);
                $teacherPk = (int)$pdo->lastInsertId();
            }
            $teacherByName[$cleanRaw] = $teacherPk;
        }

        $dayRaw  = col($raw, ['วัน', 'วัน (1-7)', 'วัน (1=จันทร์ 2=อังคาร 3=พุธ 4=พฤหัส 5=ศุกร์ 6=เสาร์ 7=อาทิตย์)']);
        $dayMap = [
            'จันทร์' => 1, 'อังคาร' => 2, 'พุธ' => 3, 'พฤหัส' => 4, 'พฤหัสบดี' => 4,
            'ศุกร์' => 5, 'เสาร์' => 6, 'อาทิตย์' => 7
        ];
        $day = (int)$dayRaw;
        if ($day === 0 && isset($dayMap[trim($dayRaw)])) {
            $day = $dayMap[trim($dayRaw)];
        }

        $periodRaw  = col($raw, ['คาบ', 'คาบที่', 'คาบที่ (0-11)']);
        $subject = col($raw, ['กิจกรรม/วิชา', 'ชื่อวิชา', 'วิชา']);

        if ($day < 1 || $day > 7 || $periodRaw === '' || !$subject) {
            $skipCount++; $skipReasons[] = "แถวที่ $rowNo: ข้อมูลไม่ครบ (วัน/คาบ/วิชา)"; continue;
        }

        // จัดการคาบที่อาจกรอกมาเป็นช่วง เช่น "1-2" หรือ "1,2"
        $periods = [];
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($periodRaw), $m)) {
            for ($p = (int)$m[1]; $p <= (int)$m[2]; $p++) if ($p >= 0 && $p <= 11) $periods[] = $p;
        } elseif (strpos($periodRaw, ',') !== false) {
            foreach (explode(',', $periodRaw) as $p) if ((int)trim($p) >= 0 && (int)trim($p) <= 11) $periods[] = (int)trim($p);
        } else {
            if ((int)$periodRaw >= 0 && (int)$periodRaw <= 11) $periods[] = (int)$periodRaw;
        }

        if (empty($periods)) {
            $skipCount++; $skipReasons[] = "แถวที่ $rowNo: เลขคาบไม่ถูกต้อง ($periodRaw)"; continue;
        }

        // Resolve subject code vs subject name
        $subjectCode = col($raw, ['รหัสวิชา', 'รหัส', 'subject_code']);
        if ($subjectCode === '' && isset($subjectLookup[$subject])) {
            // Column contains only the subject code — look up the real name
            $subjectCode = $subject;
            $subject     = $subjectLookup[$subject];
        } elseif ($subjectCode !== '' && isset($subjectLookup[$subjectCode]) && $subject === '') {
            // Have an explicit code column but no name column — look up from code
            $subject = $subjectLookup[$subjectCode];
        }

        // ชั้น = กลุ่มเรียน/ห้องนักเรียน (เช่น 205 = ม.2 ห้อง 5)
        // ห้องเรียน = ห้องที่ครูไปสอนจริง (เช่น 321 = ห้อง 321 ในอาคาร)
        // ถ้ามีทั้งสองคอลัมน์: ชั้น → class_name, ห้องเรียน → room_location
        // ถ้ามีแค่ ห้องเรียน: ตรวจสอบว่าเป็นรหัสห้องเรียน หรือ ห้องสอน
        $className     = col($raw, ['รหัสห้อง', 'ห้องตามสมุด', 'class_name', 'กลุ่มเรียน']);
        $grade_level   = col($raw, ['ระดับชั้น', 'ชั้นเรียน']);
        $room_location = col($raw, ['ห้องที่สอน', 'ห้องสอน', 'สถานที่สอน', 'อาคาร/ห้อง']);
        $chan = col($raw, ['ชั้น']);
        $hong = col($raw, ['ห้อง']);

        // "ชั้น" มีลำดับความสำคัญสูงสุดสำหรับ class_name
        if ($chan !== '') {
            $chanIsHomeroom = cnp_classroom_canonical_code($chan) !== null
                || (bool) preg_match('/^ม\.?\s*\d+\s*\/\s*\d+/u', $chan);
            if ($chanIsHomeroom) {
                if ($className === '') $className = $chan;
            } elseif ($grade_level === '') {
                $grade_level = $chan;
            }
        }

        // "ห้องเรียน": ถ้า ชั้น ได้ set class_name ไปแล้ว → ห้องเรียน คือห้องที่สอนจริง
        //              ถ้า ชั้น ว่าง → ใช้ ห้องเรียน เป็น class_name (backward compat)
        $hongRaw = col($raw, ['ห้องเรียน']);
        if ($hongRaw !== '') {
            if ($className !== '') {
                // ชั้น ให้ class_name แล้ว → ห้องเรียน = ที่ตั้งห้องสอน
                if ($room_location === '') $room_location = $hongRaw;
            } else {
                $hongIsHomeroom = cnp_classroom_canonical_code($hongRaw) !== null
                    || (bool) preg_match('/^ม\.?\s*\d+\s*\/\s*\d+/u', $hongRaw);
                if ($hongIsHomeroom) {
                    $className = $hongRaw;
                } elseif ($room_location === '') {
                    $room_location = $hongRaw;
                }
            }
        }

        // "ห้อง" (generic column): ถ้าเป็นรหัสห้อง → class_name; ถ้าเป็นชื่อ → room_location
        if ($hong !== '') {
            $hongIsHomeroom = cnp_classroom_canonical_code($hong) !== null
                || (bool) preg_match('/^ม\.?\s*\d+\s*\/\s*\d+/u', $hong);
            if ($hongIsHomeroom && $className === '') {
                $className = $hong;
            } elseif (!$hongIsHomeroom && $room_location === '') {
                $room_location = $hong;
            }
        }
        if ($className !== '') {
            $cn = cnp_classroom_canonical_code($className);
            if ($cn !== null) {
                $className = $cn;
            }
        }

        $year      = col($raw, ['ปีการศึกษา', 'ปีการศึกษา (เช่น 2569)', 'ปี']) ?: '2569';
        $semester  = (int)col($raw, ['ภาคเรียน', 'เทอม']) ?: 1;
        
        foreach ($periods as $period) {
            $upsertKey = $teacherPk . '|' . $day . '|' . $period . '|' . $className;
            $existId   = $existingMap[$upsertKey] ?? null;

            if ($dryRun) {
                if ($existId) { 
                    $willUpdate[] = [
                        'teacher_id'   => $teacherPk, 
                        'old_name'     => $nameRaw ?: "ครู", 
                        'new_name'     => "วิชา: $subject", 
                        'old_class'    => "วัน $day คาบ $period", 
                        'new_class'    => "ปี $year เทอม $semester", 
                        'is_different' => true
                    ]; 
                    $updateCount++; 
                }
                else { $insertCount++; }
                continue;
            }

            if ($existId) {
                $pdo->prepare("UPDATE timetable SET subject_name=?, subject_code=?, grade_level=?, class_name=?, room_location=?, academic_year=?, semester=?, note=? WHERE id=?")
                    ->execute([$subject, $subjectCode ?: null, $grade_level ?: null, $className ?: null, $room_location ?: null, $year, $semester, col($raw, ['หมายเหตุ']), $existId]);
                $updateCount++;
            } else {
                $pdo->prepare("INSERT INTO timetable (teacher_id, day_of_week, period, subject_name, subject_code, grade_level, class_name, room_location, academic_year, semester, note) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$teacherPk, $day, $period, $subject, $subjectCode ?: null, $grade_level ?: null, $className ?: null, $room_location ?: null, $year, $semester, col($raw, ['หมายเหตุ'])]);
                
                // อัปเดต Map เพื่อป้องกันการเพิ่มซ้ำในไฟล์เดียวกัน
                $insertId = $pdo->lastInsertId();
                if ($insertId) $existingMap[$upsertKey] = (int)$insertId;
                $insertCount++;
            }
        }

        if (!$dryRun && ++$batch >= 100) { 
            $pdo->commit(); 
            $pdo->beginTransaction(); 
            $batch = 0; 
        }
    }

    if (!$dryRun && $pdo->inTransaction()) $pdo->commit();

    echo json_encode([
        'success' => true,
        'inserted' => $insertCount,
        'updated' => $updateCount,
        'imported' => $insertCount + $updateCount,
        'skipped' => $skipCount,
        'duplicates' => $dryRun ? $willUpdate : [],
        'duplicate_count' => $dryRun ? count($willUpdate) : $updateCount,
        'skip_reasons' => array_slice($skipReasons, 0, 20),
        'message' => "สำเร็จ: เพิ่ม $insertCount, อัปเดต $updateCount, ข้าม $skipCount",
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'ขัดข้อง: ' . $e->getMessage()]);
}
