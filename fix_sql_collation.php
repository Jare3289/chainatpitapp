<?php
/**
 * fix_sql_collation.php
 * Utility to fix unsupported database collations (like utf8mb3_uca1400_ai_ci) in SQL dumps.
 */

// Increase memory and execution time limits for large SQL dumps
ini_set('memory_limit', '256M');
set_time_limit(300);

$sqlFile = __DIR__ . '/admin_cnpapp (3).sql';
$backupFile = __DIR__ . '/admin_cnpapp (3).sql.bak';
$tmpFile = __DIR__ . '/admin_cnpapp (3).sql.tmp';

$error = null;
$success = false;
$report = [];
$action = $_REQUEST['action'] ?? '';

if ($action === 'fix') {
    if (!file_exists($sqlFile)) {
        $error = "ไม่พบไฟล์ SQL ที่พาธ: " . htmlspecialchars($sqlFile);
    } else {
        // Create backup if it doesn't already exist
        if (!file_exists($backupFile)) {
            if (!copy($sqlFile, $backupFile)) {
                $error = "ไม่สามารถสร้างไฟล์สำรอง (Backup) ได้";
            }
        }

        if (!$error) {
            $handleRead = fopen($sqlFile, 'r');
            $handleWrite = fopen($tmpFile, 'w');

            if (!$handleRead || !$handleWrite) {
                $error = "ไม่สามารถเปิดไฟล์เพื่ออ่านหรือเขียนได้";
                if ($handleRead) fclose($handleRead);
                if ($handleWrite) fclose($handleWrite);
            } else {
                $lineNumber = 0;
                $replacementsCount = 0;

                while (($line = fgets($handleRead)) !== false) {
                    $lineNumber++;
                    $modified = false;
                    $origLine = $line;

                    // Check for utf8mb3_uca1400_ai_ci and replace it
                    if (stripos($line, 'utf8mb3_uca1400_ai_ci') !== false) {
                        $line = str_ireplace('utf8mb3_uca1400_ai_ci', 'utf8mb4_unicode_ci', $line);
                        $modified = true;
                    }

                    // Check for utf8mb3 and replace it with utf8mb4 (to prevent collation mismatches)
                    if (stripos($line, 'utf8mb3') !== false) {
                        $line = str_ireplace('utf8mb3', 'utf8mb4', $line);
                        $modified = true;
                    }

                    if ($modified) {
                        $replacementsCount++;
                        if (count($report) < 100) {
                            $report[] = [
                                'line' => $lineNumber,
                                'original' => trim($origLine),
                                'modified' => trim($line)
                            ];
                        }
                    }

                    fwrite($handleWrite, $line);
                }

                fclose($handleRead);
                fclose($handleWrite);

                // Replace original file with the modified one
                if (rename($tmpFile, $sqlFile)) {
                    $success = true;
                } else {
                    $error = "ไม่สามารถแทนที่ไฟล์ SQL เดิมด้วยไฟล์ที่แก้ไขแล้วได้";
                    if (file_exists($tmpFile)) {
                        unlink($tmpFile);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Collation Fixer</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent-success: #10b981;
            --accent-error: #ef4444;
        }

        body {
            font-family: 'Outfit', 'Sarabun', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
        }

        .container {
            width: 100%;
            max-width: 800px;
            padding: 40px 20px;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(12px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 22px 48px rgba(59, 130, 246, 0.15);
        }

        h1 {
            font-size: 32px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 10px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 16px;
            margin-bottom: 40px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 16px;
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 30px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--accent-success);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--accent-error);
        }

        .status-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .status-title {
            font-size: 14px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .status-value {
            font-size: 18px;
            font-weight: 600;
        }

        .report-section {
            margin-top: 30px;
        }

        .report-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-muted);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge-line {
            background: rgba(255, 255, 255, 0.08);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }

        .code-snippet {
            font-family: 'Courier New', Courier, monospace;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .code-orig {
            background: rgba(239, 68, 68, 0.08);
            color: rgba(239, 68, 68, 0.8);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .code-mod {
            background: rgba(16, 185, 129, 0.08);
            color: rgba(16, 185, 129, 0.8);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .instruction-box {
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 16px;
            padding: 24px;
            margin-top: 35px;
        }

        .instruction-box h3 {
            margin-top: 0;
            color: #60a5fa;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .instruction-box ol {
            margin: 0;
            padding-left: 20px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .instruction-box li {
            margin-bottom: 8px;
        }

        .instruction-box li strong {
            color: var(--text-main);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Database Collation Fixer</h1>
            <div class="subtitle">ระบบแก้ไขปัญหา Collation ที่ไม่รองรับ (เช่น utf8mb3_uca1400_ai_ci) ในไฟล์ SQL Dump</div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>แก้ไขโครงสร้างคอลัมน์และตารางในไฟล์ SQL สำเร็จแล้ว!</div>
                </div>

                <div class="status-box">
                    <div class="status-title">ผลการแก้ไข</div>
                    <div class="status-value">
                        ทำการแทนที่ Collation ไปทั้งหมด: <span style="color: #60a5fa;"><?php echo count($report); ?></span> จุด
                    </div>
                    <div style="font-size: 14px; color: var(--text-muted); margin-top: 10px;">
                        * ไฟล์ต้นฉบับเดิมได้ถูกสำรองไว้ที่ <code>admin_cnpapp (3).sql.bak</code> เรียบร้อยแล้ว
                    </div>
                </div>

                <?php if (count($report) > 0): ?>
                    <div class="report-section">
                        <div class="report-title">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            รายการแถวที่ได้รับการปรับปรุง (แสดงสูงสุด 100 รายการ)
                        </div>
                        <div class="report-table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">บรรทัด</th>
                                        <th style="width: 45%;">ค่าเดิม</th>
                                        <th style="width: 45%;">ค่าใหม่</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report as $item): ?>
                                        <tr>
                                            <td><span class="badge-line"><?php echo $item['line']; ?></span></td>
                                            <td><span class="code-snippet code-orig" title="<?php echo htmlspecialchars($item['original']); ?>"><?php echo htmlspecialchars($item['original']); ?></span></td>
                                            <td><span class="code-snippet code-mod" title="<?php echo htmlspecialchars($item['modified']); ?>"><?php echo htmlspecialchars($item['modified']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="instruction-box">
                    <h3>ขั้นตอนต่อไปในการนำเข้าข้อมูล:</h3>
                    <ol>
                        <li>ให้เปิดหน้าต่าง <strong>import_db.bat</strong> ขึ้นมารันใหม่อีกครั้ง หรือ ดับเบิลคลิกไฟล์ <code>import_db.bat</code> ในเครื่องคอมพิวเตอร์ของคุณ</li>
                        <li>ระบบจะเริ่มล้างฐานข้อมูลเดิมและนำเข้าไฟล์ SQL ตัวที่ได้รับการแก้ไขนี้เข้าไปใหม่</li>
                        <li>เมื่อนำเข้าเรียบร้อย ระบบจะทำงานได้ตามปกติ</li>
                    </ol>
                </div>
            <?php else: ?>
                <div class="status-box">
                    <div class="status-title">สถานะไฟล์ SQL ปัจจุบัน</div>
                    <div class="status-value" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>admin_cnpapp (3).sql</span>
                        <span style="font-size: 14px; color: var(--text-muted);">
                            ขนาดไฟล์: <?php echo number_format(filesize($sqlFile) / 1024 / 1024, 2); ?> MB
                        </span>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="fix">
                    <button type="submit" class="btn">
                        เริ่มสแกนและแก้ไข Collation
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
