<?php
// One-time fix: merge DUMMY_ teacher records into real teacher records
// Run once on local + server, then delete this file
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');

$thaiPrefixList = [
    'ว่าที่ร้อยตรีหญิง','ว่าที่ร้อยตรี','ว่าที่ร.ต.หญิง','ว่าที่ร.ต.',
    'ว่าที่รต.หญิง','ว่าที่รต.','นางสาว','นาย','นาง',
    'ดร.','ผศ.ดร.','ผศ.','รศ.ดร.','รศ.',
    'พระมหา','พระอาจารย์','พระ','สิบเอก','สิบโท','สิบตรี',
];

function stripThaiPrefix(string $name, array $prefixes): string {
    $name = trim($name);
    foreach ($prefixes as $p) {
        if (mb_strpos($name, $p) === 0) {
            return trim(mb_substr($name, mb_strlen($p)));
        }
    }
    return $name;
}

function findRealTeacher(PDO $pdo, string $fullName, array $prefixes): ?array {
    $stripped = stripThaiPrefix($fullName, $prefixes);
    // Split on first space: "รังสฤษฎ์ คำเอม" -> ["รังสฤษฎ์", "คำเอม"]
    $parts = preg_split('/\s+/', $stripped, 2);
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
    if (!$firstName) return null;

    if ($lastName) {
        $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th
                               FROM teachers
                               WHERE first_name_th = ? AND last_name_th = ?
                                 AND (teacher_id IS NULL OR teacher_id NOT LIKE 'DUMMY_%')
                               LIMIT 1");
        $stmt->execute([$firstName, $lastName]);
    } else {
        // Only first name available
        $stmt = $pdo->prepare("SELECT id, prefix, first_name_th, last_name_th
                               FROM teachers
                               WHERE first_name_th = ?
                                 AND (teacher_id IS NULL OR teacher_id NOT LIKE 'DUMMY_%')
                               LIMIT 1");
        $stmt->execute([$firstName]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$dryRun = !isset($_GET['confirm']);
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fix DUMMY Teachers</title>
<style>
body{font-family:sans-serif;font-size:13px;padding:20px;max-width:900px;}
table{border-collapse:collapse;width:100%;margin-bottom:20px;}
th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}
th{background:#1e3c72;color:#fff;}
.ok{background:#d4edda;} .warn{background:#fff3cd;} .err{background:#f8d7da;}
.note{background:#cff4fc;padding:12px;border-radius:6px;margin-bottom:16px;}
</style></head><body>
<?php if ($dryRun): ?>
<div class="note">
  <b>⚠️ Dry-run mode</b> — แสดงผลเท่านั้น ไม่ได้แก้ไขข้อมูลจริง<br>
  เพิ่ม <code>?confirm=1</code> ใน URL เพื่อยืนยันการแก้ไข
</div>
<?php else: ?>
<div class="note" style="background:#fff3cd;">
  <b>✏️ COMMIT mode</b> — กำลังแก้ไขข้อมูลจริง
</div>
<?php endif; ?>

<?php
try {
    // Step 0: Fix กมลพัฒน์ เอกศิริ prefix
    echo "<h2>0. อัปเดต prefix กมลพัฒน์ เอกศิริ → ว่าที่ร้อยตรี</h2>";
    $checkStmt = $pdo->prepare("SELECT id, prefix FROM teachers WHERE first_name_th = 'กมลพัฒน์' AND last_name_th = 'เอกศิริ' LIMIT 1");
    $checkStmt->execute();
    $kamonRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($kamonRow) {
        if ($kamonRow['prefix'] !== 'ว่าที่ร้อยตรี') {
            if (!$dryRun) {
                $pdo->prepare("UPDATE teachers SET prefix = 'ว่าที่ร้อยตรี' WHERE id = ?")->execute([$kamonRow['id']]);
            }
            echo "<p class='ok' style='padding:8px;border-radius:4px;'>✅ ".($dryRun?'[Dry-run] จะ':'')."อัปเดต prefix ของ กมลพัฒน์ เอกศิริ (id={$kamonRow['id']}) จาก '{$kamonRow['prefix']}' → 'ว่าที่ร้อยตรี'</p>";
        } else {
            echo "<p class='ok' style='padding:8px;border-radius:4px;'>✅ prefix ถูกต้องแล้ว: ว่าที่ร้อยตรี</p>";
        }
    } else {
        echo "<p class='warn' style='padding:8px;border-radius:4px;'>⚠️ ไม่พบ กมลพัฒน์ เอกศิริ ในตาราง teachers</p>";
    }

    // Step 1: Load all DUMMYs
    echo "<h2>1. DUMMY_ records ที่พบ</h2>";
    $dummyStmt = $pdo->query("SELECT id, teacher_id, first_name_th FROM teachers WHERE teacher_id LIKE 'DUMMY_%' ORDER BY id");
    $dummies = $dummyStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dummies) {
        echo "<p class='ok' style='padding:8px;border-radius:4px;'>✅ ไม่มี DUMMY_ records ในระบบ</p>";
    } else {
        echo "<table><tr><th>DUMMY id</th><th>teacher_id</th><th>ชื่อใน Excel</th><th>Real teacher พบ?</th><th>timetable rows</th><th>Action</th></tr>";

        foreach ($dummies as $dummy) {
            $rawName = $dummy['first_name_th'];
            $realTeacher = findRealTeacher($pdo, $rawName, $thaiPrefixList);

            // Count timetable rows
            $ttStmt = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE teacher_id = ?");
            $ttStmt->execute([$dummy['id']]);
            $ttCount = $ttStmt->fetchColumn();

            if ($realTeacher) {
                $realName = trim($realTeacher['first_name_th'].' '.$realTeacher['last_name_th']);
                $action = ($dryRun ? '[Dry-run] จะ' : '') . "Merge → real id={$realTeacher['id']}";
                echo "<tr class='ok'><td>{$dummy['id']}</td><td>{$dummy['teacher_id']}</td><td>{$rawName}</td><td>✅ {$realName} (id={$realTeacher['id']})</td><td>{$ttCount}</td><td>{$action}</td></tr>";

                if (!$dryRun) {
                    // Re-point timetable rows
                    $pdo->prepare("UPDATE timetable SET teacher_id = ? WHERE teacher_id = ?")->execute([$realTeacher['id'], $dummy['id']]);
                    // Re-point supervision_bookings if exists
                    try {
                        $pdo->prepare("UPDATE supervision_bookings SET teacher_id = ? WHERE teacher_id = ?")->execute([$realTeacher['id'], $dummy['id']]);
                    } catch (PDOException $e) { /* table may not have this column */ }
                    // Delete the DUMMY teacher record
                    $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$dummy['id']]);
                    // Also delete orphaned user if any
                    $pdo->prepare("DELETE FROM users WHERE id NOT IN (SELECT user_id FROM teachers WHERE user_id IS NOT NULL) AND id NOT IN (SELECT user_id FROM students WHERE user_id IS NOT NULL) AND role = 'teacher' AND username LIKE 'DUMMY_%'")->execute();
                }
            } else {
                $action = '⚠️ ไม่พบ real teacher — ข้ามไว้ก่อน';
                echo "<tr class='warn'><td>{$dummy['id']}</td><td>{$dummy['teacher_id']}</td><td>{$rawName}</td><td>❌ ไม่พบ</td><td>{$ttCount}</td><td>{$action}</td></tr>";
            }
        }
        echo "</table>";
    }

    if (!$dryRun) {
        echo "<hr><p class='ok' style='padding:10px;border-radius:6px;'>✅ เสร็จสิ้น — กรุณาลบไฟล์ fix_dummy_teachers.php ออกจากเซิร์ฟเวอร์</p>";
    }

} catch (PDOException $e) {
    echo "<p class='err' style='padding:8px;border-radius:4px;'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
</body></html>
