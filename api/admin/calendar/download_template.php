<?php
// api/admin/calendar/download_template.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=calendar_template.csv');

$output = fopen('php://output', 'w');
// Add BOM for Excel Thai compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['academic_year', 'semester', 'date', 'activity', 'note', 'day_type']);
fputcsv($output, ['2569', '1', '18 พฤษภาคม 2569', 'วันเปิดภาคเรียน', 'เริ่มเรียนเทอม 1', 'ปกติ']);
fputcsv($output, ['2569', '1', '1 มิถุนายน 2569', 'วันวิสาขบูชา', 'หยุดนักขัตฤกษ์', 'หยุด']);
fputcsv($output, ['2569', '1', '15 มิถุนายน 2569', 'วันเรียนชดเชย', 'ชดเชยวันหยุด', 'ชดเชย']);
fputcsv($output, ['2569', '1', '1 กรกฎาคม 2569', 'วันสถาปนาลูกเสือแห่งชาติ', 'กิจกรรมลูกเสือ', 'กิจกรรมพิเศษ']);

fclose($output);
