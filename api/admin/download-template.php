<?php
/**
 * api/admin/download-template.php
 * Generates CSV templates - Headers only to avoid PHP 8.4 deprecation noise
 */
error_reporting(0); // Disable error reporting to ensure clean CSV output
require_once '../../config.php';

$type   = $_GET['type']   ?? 'student';
$fields = $_GET['fields'] ?? 'minimal'; 

$filename = "Template_" . ucfirst($type) . "_CNP.csv";
$headers = [];

if ($type === 'student') {
    if ($fields === 'full') {
        $headers = ["IDS (รหัสนักเรียน)", "เลขที่", "ห้อง", "ระดับชั้น", "คณะ", "แผนการเรียน", "รูปถ่าย", "คำนำหน้าชื่อ", "ชื่อ", "นามสกุล", "ชื่อจริง", "NAME", "SURENAME", "ชื่อเล่น", "อีเมล", "เพศ", "เลขบัตรประชาชน", "วันเดือนปีเกิด", "เบอร์โทรศัพท์", "ไลน์ไอดี", "Facebook", "น้ำหนัก", "ส่วนสูง", "กรุ๊ปเลือด", "โรคประจำตัว", "แพ้อาหาร", "แพ้ยา"];
    } else {
        $headers = ["IDS (รหัสนักเรียน)", "เลขที่", "ห้อง", "ระดับชั้น", "คณะ", "รูปถ่าย", "คำนำหน้าชื่อ", "ชื่อ", "นามสกุล", "ชื่อจริง", "NAME", "SURENAME"];
    }
} elseif ($type === 'teacher') {
    if ($fields === 'full') {
        $headers = ["IDT", "EMAIL", "เลขบัตรประชาชน", "คำนำหน้า", "ชื่อ", "สกุล", "NAME", "SURENAME", "ห้อง", "คณะ", "รูปถ่าย", "ตำแหน่ง", "วิทยฐานะ", "กลุ่มสาระการเรียนรู้", "สาขาย่อย", "วันเดือนปีเกิด", "วันที่บรรจุ", "ปีที่เกษียณ", "เบอร์โทรศัพท์", "ID Line", "บ้านเลขที่", "ซอย", "หมู่", "ถนน", "แขวง/ตำบล", "เขต/อำเภอ", "จังหวัด", "ภูมิลำเนา", "เชื้อชาติ", "สัญชาติ", "ศาสนา", "ลายเซ็น"];
    } else {
        $headers = ["คำนำหน้า", "ชื่อ", "นามสกุล", "อีเมล", "ห้องที่ปรึกษา"];
    }
} elseif ($type === 'timetable') {
    if ($fields === 'full') {
        // สำหรับฉบับเต็มก็ใช้หัวข้อที่คุณต้องการ แต่เพิ่มรายละเอียดปีการศึกษา/ภาคเรียนให้ (ถ้าแอดมินอยากกรอก)
        $headers = ["ชื่อ-สกุล(ครู)", "วัน", "คาบ", "กิจกรรม/วิชา", "ชั้น", "ห้องเรียน", "ปีการศึกษา (เช่น 2569)", "ภาคเรียน", "หมายเหตุ"];
    } else {
        // ฉบับย่อตามที่คุณแจ้งเป๊ะๆ
        $headers = ["ชื่อ-สกุล(ครู)", "วัน", "คาบ", "กิจกรรม/วิชา", "ชั้น", "ห้องเรียน"];
    }
} elseif ($type === 'subject') {
    $headers = ["รหัสวิชา", "ชื่อวิชา", "ภาคเรียน", "ปีการศึกษา", "กลุ่มสาระการเรียนรู้", "ชั้น", "ครูผู้สอน"];
} elseif ($type === 'credit') {
    $headers = ["รหัสนักเรียน", "ประเภท (เติม/ตัด)", "รายการพฤติกรรม", "คะแนน", "หมายเหตุ", "วันที่ (ค.ศ. เช่น 2024-05-13)", "ครูผู้บันทึก"];
} elseif ($type === 'class') {
    $headers = ["รหัสห้อง (Code)", "ระดับชั้น (เช่น มัธยมศึกษาปีที่ 1)", "ม.ปีที่", "ห้องที่", "อาคาร", "ชั้น", "เลขห้อง", "รหัสที่ตั้ง", "คณะ (บ้าน)", "แผนการเรียน"];
} else {
    exit("Invalid type");
}

// ── Output CSV ─────────────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // BOM for Thai in Excel

$output = fopen('php://output', 'w');
// Use explicit parameters to avoid PHP 8.4 deprecation warnings
fputcsv($output, $headers, ",", "\"", "\\"); 
fclose($output);
exit;
