<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ── Generous limits for bulk imports (e.g. 3000+ rows) ──
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
@ignore_user_abort(true);

require_once '../../config.php';
require_once '../../inc/classroom_codes.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ============================================================
// Mapping: Thai (Excel header) → DB column in `students`
// คอลัมน์ที่ไม่อยู่ในไฟล์ Excel = ข้ามไป (ไม่ error)
// ============================================================
$mapping = [
    "IDS (รหัสนักเรียน)" => "student_id",
    "รหัสนักเรียน"     => "student_id",
    "เลขที่"           => "number_in_class",
    "ห้อง"             => "class_name",
    "ระดับชั้น"         => "grade_level",
    "คณะ"             => "house",
    "รูปถ่าย"          => "photo",
    "คำนำหน้าชื่อ"     => "prefix",
    "ชื่อ"             => "first_name_th",
    "นามสกุล"          => "last_name_th",
    "ชื่อจริง"         => "full_name_th",
    "NAME"             => "first_name_en",
    "SURENAME"         => "last_name_en",
    "SURNAME"          => "last_name_en",
    "ชื่อเล่น"         => "nickname",
    "ที่อยู่อีเมล"     => "email",
    "อีเมล"            => "email",
    "เพศกำเนิด"       => "birth_sex",
    "เพศวิถี"         => "gender",
    "เพศ"              => "gender",
    "เลขบัตรประชาชน"   => "id_card",
    "เชื้อชาติ"         => "ethnicity",
    "สัญชาติ"         => "nationality",
    "ศาสนา"           => "religion",
    "วันเดือนปีเกิด"    => "birth_date",
    "เป็นบุตรคนที่"    => "child_order",
    "เบอร์โทรศัพท์"    => "phone",
    "IDLine"           => "line_id",
    "Facebook"        => "facebook",
    "ที่อยู่ปัจจุบันเป็น" => "address_status",
    "บ้านเลขที่"       => "reg_house_no",
    "ซอย"             => "reg_soi",
    "ถนน"             => "reg_road",
    "หมู่"             => "reg_moo",
    "ชื่อหมู่บ้าน"      => "reg_village",
    "แขวง/ตำบล"       => "reg_subdistrict",
    "เขต/อำเภอ"       => "reg_district",
    "จังหวัด"          => "reg_province",
    "รหัสไปรษณีย์"     => "reg_zipcode",
    "บ้านเลขที่ปัจจุบัน" => "curr_house_no",
    "ซอยปัจจุบัน"      => "curr_soi",
    "ถนนปัจจุบัน"      => "curr_road",
    "หมู่ปัจจุบัน"      => "curr_moo",
    "ชื่อหมู่บ้านปัจจุบัน" => "curr_village",
    "แขวง/ตำบลปัจจุบัน" => "curr_subdistrict",
    "เขต/อำเภอปัจจุบัน" => "curr_district",
    "จังหวัดปัจจุบัน"    => "curr_province",
    "รหัสไปรษณีย์ปัจจุบัน" => "curr_zipcode",
    "พิกัดที่อยู่ปัจจุบัน" => "location_coords",
    "จุดสังเกต"         => "location_landmark",
    "ผู้ใหญ่บ้าน"      => "village_headman",
    "กำนัน"           => "subdistrict_headman",
    "ประเภทบ้าน"       => "house_type",
    "ลักษณะบ้านที่อยู่" => "house_style",
    "สภาพตัวบ้าน"      => "house_condition",
    "ความสะอาด"       => "house_cleanliness",
    "ไฟฟ้า"           => "has_electricity",
    "น้ำ"             => "has_water",
    "ห้องน้ำ"          => "has_toilet",
    "ระยะห่างจากโรงเรียน" => "dist_to_school",
    "ใช้เวลาเดินทาง"    => "travel_time",
    "เดินทางโดย"       => "travel_method",
    "คำนำหน้าบิดา"     => "f_prefix",
    "ชื่อบิดา"         => "f_first_name",
    "สกุลบิดา"         => "f_last_name",
    "อายุบิดา"         => "f_age",
    "เบอร์โทรศัพท์บิดา" => "f_phone",
    "วุฒิการศึกษาบิดา" => "f_education",
    "อาชีพบิดา"       => "f_job",
    "สถานที่ทำงานบิดา" => "f_workplace",
    "สถานะทางครอบครัวบิดา" => "f_family_status",
    "เบิกค่าเล่าเรียนบิดา" => "f_welfare",
    "รายได้ต่อเดือนบิดา" => "f_income",
    "คำนำหน้ามารดา"     => "m_prefix",
    "ชื่อมารดา"         => "m_first_name",
    "สกุลมารดา"         => "m_last_name",
    "อายุมารดา"         => "m_age",
    "เบอร์โทรศัพท์มารดา" => "m_phone",
    "วุฒิการศึกษามารดา" => "m_education",
    "อาชีพมารดา"       => "m_job",
    "สถานที่ทำงานมารดา" => "m_workplace",
    "สถานะทางครอบครัวมารดา" => "m_family_status",
    "เบิกค่าเล่าเรียนมารดา" => "m_welfare",
    "รายได้ต่อเดือนมารดา" => "m_income",
    "สถานภาพครอบครัว" => "family_status",
    "ความสัมพันธ์"     => "guardian_relation",
    "คำนำหน้าผู้ปกครอง" => "g_prefix",
    "ชื่อผู้ปกครอง"     => "g_first_name",
    "สกุลผู้ปกครอง"     => "g_last_name",
    "อายุผู้ปกครอง"     => "g_age",
    "เบอร์โทรศัพท์ผู้ปกครอง" => "g_phone",
    "วุฒิการศึกษาผู้ปกครอง" => "g_education",
    "อาชีพผู้ปกครอง"   => "g_job",
    "สถานที่ทำงานผู้ปกครอง" => "g_workplace",
    "รายได้ต่อเดือนผู้ปกครอง" => "g_income",
    "สมาชิกครอบครัวทั้งหมด" => "total_family_members",
    "เป็นชาย"          => "male_members",
    "เป็นหญิง"          => "female_members",
    "พี่น้องร่วมบิดามารดา" => "full_siblings",
    "พี่น้องร่วมบิดามารดาเป็นชาย" => "full_siblings_male",
    "พี่น้องร่วมบิดามารดาเป็นหญิง" => "full_siblings_female",
    "พี่น้องต่างบิดามารดา" => "half_siblings",
    "พี่น้องต่างบิดามารดาเป็นชาย" => "half_siblings_male",
    "พี่น้องต่างบิดามารดาเป็นหญิง" => "half_siblings_female",
    "ความสัมพันธ์ของสมาชิก" => "family_relationship",
    "ความสัมพันธ์กับบิดา" => "rel_father",
    "ความสัมพันธ์กับมารดา" => "rel_mother",
    "ความสัมพันธ์กับพี่ชายน้องชาย" => "rel_brothers",
    "ความสัมพันธ์กับพี่สาวน้องสาว" => "rel_sisters",
    "ความสัมพันธ์ปู่ย่าตายาย" => "rel_grandparents",
    "ความสัมพันธ์กับญาติ" => "rel_relatives",
    "เวลาอยู่ร่วมกัน"    => "time_spent_together",
    "ได้เงินจาก"       => "allowance_source",
    "ได้เงินวันละ"     => "allowance_per_day",
    "ภาระรับผิดชอบ"     => "responsibilities",
    "นักเรียนอยู่กับใครเมื่อผู้ปกครองไม่ว่าง" => "caregiver_when_away",
    "ทำงานพิเศษ"       => "part_time_job",
    "รายได้"           => "part_time_income",
    "น้ำหนัก"          => "weight",
    "ส่วนสูง"          => "height",
    "กรุ๊ปเลือด"       => "blood_group",
    "แพ้อาหาร"         => "food_allergies",
    "แพ้ยา"            => "drug_allergies",
    "โรคประจำตัว"      => "congenital_disease",
    "ฉีดวัคซีนโควิด"    => "covid_vaccine",
    "เข้าถึงอินเทอร์เน็ต" => "internet_access",
    "ใช้โซเชียลมีเดีย"   => "social_media_usage",
    "ความสามารถพิเศษ"   => "talents",
    "ความสนใจ"         => "interests",
    "งานอดิเรก"       => "hobbies",
];

// Normalize mapping keys
$normalizedMapping = [];
foreach ($mapping as $k => $v) {
    $cleanK = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $k);
    $normalizedMapping[$cleanK] = $v;
}

// ============================================================
// Read existing columns in `students` table — กัน insert ผิดคอลัมน์
// ============================================================
$validCols = [];
foreach ($pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN) as $col) {
    $validCols[$col] = true;
}

// ============================================================
// Read uploaded file
// ============================================================
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
            // Fallback: ZipArchive xlsx reader
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                echo json_encode(['error' => 'ไม่สามารถอ่านไฟล์ Excel ได้ กรุณาบันทึกเป็น .xlsx จาก Excel']);
                exit;
            }
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
            if (empty($excelRows)) {
                echo json_encode(['error' => 'ไฟล์ว่างเปล่า']);
                exit;
            }
            $headerRow = $excelRows[0] ?? [];
            $headers = is_array($headerRow) ? array_map($cleanHeaders, $headerRow) : [];
            
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
// Process
// ============================================================
$dryRun       = ($_POST['dry_run'] ?? '0') === '1';
$insertCount  = 0;
$updateCount  = 0;
$skipCount    = 0;
$willUpdate   = [];   // dry-run: รายการที่จะ update
$skipReasons  = [];

try {
    // Lookup student role id (need for users INSERT)
    $studentRoleId = (int)$pdo->query("SELECT id FROM roles WHERE name = 'student' LIMIT 1")->fetchColumn();
    if (!$studentRoleId) {
        throw new Exception("ไม่พบ role 'student' ในตาราง roles");
    }

    // ── Pre-fetch existing student_ids (1 query instead of N) ──
    $existingMap = [];
    foreach ($pdo->query("SELECT student_id, first_name_th, last_name_th, class_name FROM students")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingMap[$row['student_id']] = $row;
    }

    // ── Hash default password ONCE (not 2757 times — bcrypt is slow on purpose) ──
    $defaultPasswordHash = password_hash('cnp12345', PASSWORD_DEFAULT);

    $stmtUser    = $pdo->prepare("INSERT INTO users (username, password, role, role_id) VALUES (?, ?, 'student', ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    $stmtGetUid  = $pdo->prepare("SELECT id FROM users WHERE username = ?");

    if (!$dryRun) $pdo->beginTransaction();
    $batchSize = 200;
    $sinceLastCommit = 0;

    foreach ($rows as $idx => $rawData) {
        // ── Helper to find column regardless of spaces ──
        $col = function($keys) use ($rawData) {
            foreach ($keys as $k) {
                $cleanK = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $k);
                if (isset($rawData[$cleanK])) return trim($rawData[$cleanK]);
            }
            return '';
        };

        // ── Validate required fields ──
        $stdId       = $col(['IDS(รหัสนักเรียน)', 'รหัสนักเรียน']);
        $firstNameTh = $col(['ชื่อ', 'ชื่อจริง']);
        $idCardVal   = $col(['เลขบัตรประชาชน']);
        $phoneVal    = $col(['เบอร์โทรศัพท์']);

        if (!$stdId) {
            $skipCount++;
            $skipReasons[] = "แถวที่ " . ($idx + 2) . ": ไม่มีรหัสนักเรียน";
            continue;
        }
        if (!$firstNameTh) {
            $skipCount++;
            $skipReasons[] = "แถวที่ " . ($idx + 2) . " ($stdId): ไม่มีชื่อ";
            continue;
        }

        // ── ตรวจสอบเลขบัตรประชาชน (13 หลัก) ──
        if ($idCardVal) {
            $cleanIdCard = preg_replace('/\D/', '', $idCardVal);
            if (strlen($cleanIdCard) !== 13) {
                $skipCount++;
                $skipReasons[] = "แถวที่ " . ($idx + 2) . " ($stdId): เลขบัตรประชาชนไม่ครบ 13 หลัก ($idCardVal)";
                continue;
            }
        }

        // ── ตรวจสอบเบอร์โทรศัพท์ (10 หลัก ขึ้นต้นด้วย 0) ──
        if ($phoneVal) {
            $cleanPhone = preg_replace('/\D/', '', $phoneVal);
            if (strlen($cleanPhone) < 10 || substr($cleanPhone, 0, 1) !== '0') {
                $skipCount++;
                $skipReasons[] = "แถวที่ " . ($idx + 2) . " ($stdId): เบอร์โทรศัพท์ต้องมี 10 หลักและขึ้นต้นด้วย 0 ($phoneVal)";
                continue;
            }
        }

        // ── Lookup existing record ──
        $existing = $existingMap[$stdId] ?? null;
        
        $roomVal = $col(['ห้อง']);
        $prefixVal = $col(['คำนำหน้าชื่อ']);
        $lastNameVal = $col(['นามสกุล']);

        if ($dryRun) {
            if ($existing) {
                $newName = trim($prefixVal . $firstNameTh . ' ' . $lastNameVal);
                $oldName = trim($existing['first_name_th'] . ' ' . $existing['last_name_th']);
                $willUpdate[] = [
                    'student_id'   => $stdId,
                    'old_name'     => $oldName,
                    'new_name'     => $newName,
                    'old_class'    => $existing['class_name'] ?? '-',
                    'new_class'    => $roomVal ?: '-',
                    'is_different' => ($newName !== $oldName || ($existing['class_name'] ?? '') !== $roomVal),
                ];
                $updateCount++;
            } else {
                $insertCount++;
            }
            continue;
        }

        // ── Build field map from Excel row ──
        $fieldMap = [];

        foreach ($normalizedMapping as $cleanHeader => $dbCol) {
            if (!isset($rawData[$cleanHeader])) continue;
            if (!isset($validCols[$dbCol]))    continue;

            $val = trim($rawData[$cleanHeader]);
            if ($val === '') continue;

            // Normalise birth_date
            if ($dbCol === 'birth_date') {
                $val = str_replace(['/', '.'], '-', $val);
                $parts = explode('-', $val);
                if (count($parts) === 3) {
                    if (strlen($parts[2]) === 4) { $d = $parts[0]; $m = $parts[1]; $y = $parts[2]; }
                    else                          { $y = $parts[0]; $m = $parts[1]; $d = $parts[2]; }
                    if ((int)$y > 2400) $y -= 543;
                    $val = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
            }

            // Normalise classroom code (class_name) — ม.1/1 / 1/1 → 101 เมื่อจับรูปแบบได้
            if ($dbCol === 'class_name') {
                $can = cnp_classroom_canonical_code($val);
                if ($can !== null) {
                    $val = $can;
                } elseif (!preg_match('/^[1-6]\d{2}$/', $val)) {
                    preg_match_all('/\d+/', $val, $matches);
                    if (count($matches[0]) >= 2) {
                        $val = $matches[0][0] . str_pad($matches[0][1], 2, '0', STR_PAD_LEFT);
                    }
                }
            }

            $fieldMap[$dbCol] = $val;
        }

        if ($existing) {
            // ── UPDATE existing student ──
            if (!empty($fieldMap)) {
                $setParts = [];
                $setVals  = [];
                foreach ($fieldMap as $col => $val) {
                    $setParts[] = "`$col` = ?";
                    $setVals[]  = $val;
                }
                $setVals[] = $stdId;
                $pdo->prepare("UPDATE students SET " . implode(', ', $setParts) . " WHERE student_id = ?")
                    ->execute($setVals);
            }
            $updateCount++;
        } else {
            // ── INSERT new student — ensure user account first ──
            $stmtUser->execute([$stdId, $defaultPasswordHash, $studentRoleId]);
            $uid = $pdo->lastInsertId();
            if (!$uid) { $stmtGetUid->execute([$stdId]); $uid = $stmtGetUid->fetchColumn(); }

            $fieldMap['user_id'] = $uid;
            $cols = array_keys($fieldMap);
            $vals = array_values($fieldMap);
            $pdo->prepare("INSERT INTO students (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")
                ->execute($vals);
            $insertCount++;
        }
        $sinceLastCommit++;

        // Commit every $batchSize rows
        if (!$dryRun && $sinceLastCommit >= $batchSize) {
            $pdo->commit();
            $pdo->beginTransaction();
            $sinceLastCommit = 0;
        }
    }

    if (!$dryRun && $pdo->inTransaction()) $pdo->commit();

    echo json_encode([
        'success'      => true,
        'inserted'     => $insertCount,
        'updated'      => $updateCount,
        'imported'     => $insertCount + $updateCount,   // backward-compat
        'skipped'      => $skipCount,
        'will_update'  => $dryRun ? $willUpdate : [],
        'duplicates'   => $dryRun ? $willUpdate : [],    // compat key for modal
        'duplicate_count' => $dryRun ? count($willUpdate) : $updateCount,
        'skip_reasons' => array_slice($skipReasons, 0, 20),
        'message'      => $dryRun
            ? "ตรวจสอบแล้ว: เพิ่มใหม่ $insertCount รายการ, อัปเดต $updateCount รายการ"
            : "เพิ่มใหม่ $insertCount รายการ, อัปเดต $updateCount รายการ, ข้าม $skipCount รายการ",
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
