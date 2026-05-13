<?php
// api/admin/catalog/get_calendar_unify.php
require_once '../../../config.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get distinct semesters
    $stmt = $pdo->query("SELECT DISTINCT academic_year, semester FROM academic_days ORDER BY academic_year DESC, semester ASC");
    $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($semesters as $sem) {
        $year = $sem['academic_year'];
        $semester = $sem['semester'];

        // Get all days for this semester
        $stmtDays = $pdo->prepare("SELECT date_val, activity, note, category, day_type FROM academic_days WHERE academic_year = ? AND semester = ? ORDER BY date_val ASC");
        $stmtDays->execute([$year, $semester]);
        $days = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

        // Get min and max date to know the range
        $stmtRange = $pdo->prepare("SELECT MIN(date_val) as start_date, MAX(date_val) as end_date FROM academic_days WHERE academic_year = ? AND semester = ?");
        $stmtRange->execute([$year, $semester]);
        $range = $stmtRange->fetch(PDO::FETCH_ASSOC);

        $results[] = [
            'academic_year' => $year,
            'semester' => $semester,
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'days' => $days // List of all records for quick lookup
        ];
    }

    echo json_encode($results);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
}
