<?php
// api/admin/executive_trends.php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // 1. Daily Trend (Last 15 school days)
    $dailyStmt = $pdo->query("
        SELECT 
            date,
            COUNT(*) as total_records,
            SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status IN ('ลา', 'ป่วย', 'ลากิจ') THEN 1 ELSE 0 END) as leave_count,
            ROUND(SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as attendance_rate
        FROM attendance
        WHERE type = 'daily'
        GROUP BY date
        ORDER BY date DESC
        LIMIT 15
    ");
    $dailyTrend = array_reverse($dailyStmt->fetchAll(PDO::FETCH_ASSOC));

    // 2. Weekly Trend (Last 8 weeks)
    $weeklyStmt = $pdo->query("
        SELECT 
            YEAR(date) as year,
            WEEK(date, 1) as week,
            MIN(date) as week_start,
            MAX(date) as week_end,
            COUNT(*) as total_records,
            SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status IN ('ลา', 'ป่วย', 'ลากิจ') THEN 1 ELSE 0 END) as leave_count,
            ROUND(SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as attendance_rate
        FROM attendance
        WHERE type = 'daily'
        GROUP BY YEAR(date), WEEK(date, 1)
        ORDER BY year DESC, week DESC
        LIMIT 8
    ");
    $weeklyTrend = array_reverse($weeklyStmt->fetchAll(PDO::FETCH_ASSOC));

    // 3. Monthly Trend (Last 6 months)
    $monthlyStmt = $pdo->query("
        SELECT 
            YEAR(date) as year,
            MONTH(date) as month,
            COUNT(*) as total_records,
            SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'ขาด' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status IN ('ลา', 'ป่วย', 'ลากิจ') THEN 1 ELSE 0 END) as leave_count,
            ROUND(SUM(CASE WHEN status IN ('มา', 'สาย') THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as attendance_rate
        FROM attendance
        WHERE type = 'daily'
        GROUP BY YEAR(date), MONTH(date)
        ORDER BY year DESC, month DESC
        LIMIT 6
    ");
    $monthlyTrend = array_reverse($monthlyStmt->fetchAll(PDO::FETCH_ASSOC));

    // Thai month helper
    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    // Format output data for chart readability
    $dailyFormatted = array_map(function($d) {
        $parts = explode('-', $d['date']);
        $day = (int)$parts[2];
        $month = (int)$parts[1];
        $d['label'] = "$day/$month";
        return $d;
    }, $dailyTrend);

    $weeklyFormatted = array_map(function($w) use ($thaiMonths) {
        $sParts = explode('-', $w['week_start']);
        $eParts = explode('-', $w['week_end']);
        $sDay = (int)$sParts[2];
        $sMonth = $thaiMonths[(int)$sParts[1]];
        $eDay = (int)$eParts[2];
        $eMonth = $thaiMonths[(int)$eParts[1]];
        $w['label'] = "$sDay $sMonth - $eDay $eMonth";
        return $w;
    }, $weeklyTrend);

    $monthlyFormatted = array_map(function($m) use ($thaiMonths) {
        $yearBE = ($m['year'] + 543) % 100;
        $m['label'] = $thaiMonths[$m['month']] . " " . $yearBE;
        return $m;
    }, $monthlyTrend);

    echo json_encode([
        'success' => true,
        'daily' => $dailyFormatted,
        'weekly' => $weeklyFormatted,
        'monthly' => $monthlyFormatted
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[executive_trends] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
