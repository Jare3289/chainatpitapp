<?php
// Diagnostic: compare timetable teacher_id vs teachers table
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Timetable Diagnostic</title>
<style>body{font-family:sans-serif;font-size:13px;padding:20px;}
table{border-collapse:collapse;width:100%;margin-bottom:20px;}
th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}
th{background:#1e3c72;color:#fff;} .ok{background:#d4edda;} .warn{background:#fff3cd;} .err{background:#f8d7da;}
h2{margin-top:30px;border-bottom:2px solid #1e3c72;padding-bottom:5px;}
</style></head><body>
<?php
try {
    // 1. Teachers in timetable that don't match any teacher record
    echo "<h2>1. teacher_id ในตาราง timetable ที่ไม่พบใน teachers table</h2>";
    $stmt = $pdo->query("
        SELECT DISTINCT t.teacher_id, COUNT(*) as rows_count
        FROM timetable t
        LEFT JOIN teachers tc ON tc.id = t.teacher_id
        WHERE tc.id IS NULL
        GROUP BY t.teacher_id
        ORDER BY rows_count DESC
    ");
    $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($orphans) {
        echo "<table><tr><th>teacher_id (ไม่พบ)</th><th>จำนวนแถว</th></tr>";
        foreach ($orphans as $r) {
            echo "<tr class='err'><td>{$r['teacher_id']}</td><td>{$r['rows_count']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='ok' style='padding:8px;border-radius:4px;'>✅ ทุก teacher_id match กับ teachers table</p>";
    }

    // 2. Teachers table - all teachers with their IDs
    echo "<h2>2. ครูในตาราง teachers (ID + ชื่อ)</h2>";
    $stmt = $pdo->query("SELECT id, prefix, first_name_th, last_name_th, department, classroom FROM teachers ORDER BY id");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>ID</th><th>ชื่อ-นามสกุล</th><th>กลุ่มสาระ</th><th>ห้อง HR</th></tr>";
    foreach ($teachers as $t) {
        $name = trim(($t['prefix']??'').' '.$t['first_name_th'].' '.$t['last_name_th']);
        $hasTimetable = '';
        echo "<tr><td>{$t['id']}</td><td>{$name}</td><td>{$t['department']}</td><td>{$t['classroom']}</td></tr>";
    }
    echo "</table>";

    // 3. Timetable teacher_id distribution - which teacher_ids actually have data
    echo "<h2>3. teacher_id ที่มีข้อมูลใน timetable (+ ชื่อครู)</h2>";
    $stmt = $pdo->query("
        SELECT t.teacher_id, tc.first_name_th, tc.last_name_th, tc.department,
               COUNT(*) as slots,
               GROUP_CONCAT(DISTINCT t.academic_year ORDER BY t.academic_year DESC SEPARATOR ', ') as years
        FROM timetable t
        LEFT JOIN teachers tc ON tc.id = t.teacher_id
        GROUP BY t.teacher_id
        ORDER BY slots DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>teacher_id</th><th>ชื่อ (จาก JOIN)</th><th>กลุ่มสาระ</th><th>คาบ</th><th>ปีการศึกษา</th></tr>";
    foreach ($rows as $r) {
        $name = trim($r['first_name_th'].' '.$r['last_name_th']);
        $cls  = $name ? 'ok' : 'err';
        echo "<tr class='{$cls}'><td>{$r['teacher_id']}</td><td>{$name}</td><td>{$r['department']}</td><td>{$r['slots']}</td><td>{$r['years']}</td></tr>";
    }
    echo "</table>";

    // 4. Timetable sample rows
    echo "<h2>4. ตัวอย่าง 10 แถวแรกใน timetable</h2>";
    $stmt = $pdo->query("SELECT t.id, t.teacher_id, t.day_of_week, t.period, t.subject_name, t.subject_code, t.class_name, t.academic_year, t.semester, tc.first_name_th, tc.last_name_th FROM timetable t LEFT JOIN teachers tc ON tc.id = t.teacher_id LIMIT 10");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>id</th><th>teacher_id</th><th>ชื่อครู</th><th>วัน</th><th>คาบ</th><th>วิชา</th><th>รหัส</th><th>ห้อง</th><th>ปี</th><th>เทอม</th></tr>";
    foreach ($samples as $r) {
        $name = trim($r['first_name_th'].' '.$r['last_name_th']);
        echo "<tr><td>{$r['id']}</td><td>{$r['teacher_id']}</td><td>{$name}</td><td>{$r['day_of_week']}</td><td>{$r['period']}</td><td>{$r['subject_name']}</td><td>{$r['subject_code']}</td><td>{$r['class_name']}</td><td>{$r['academic_year']}</td><td>{$r['semester']}</td></tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
</body></html>
