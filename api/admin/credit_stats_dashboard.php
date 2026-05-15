<?php
header('Content-Type: application/json');
require_once '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Determine scope: teacher sees only their room, admin can filter
$forceRoom = '';
$forceGrade = '';

if ($role === 'teacher') {
    // Get teacher's room from teachers table
    $stmt = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($teacher && $teacher['classroom']) {
        $forceRoom = $teacher['classroom'];
    }
}

// If admin, allow URL filter params; teacher is locked to their room
$grade = ($role === 'admin') ? ($_GET['grade'] ?? '') : $forceGrade;
$room  = ($role === 'teacher') ? $forceRoom : ($_GET['room'] ?? '');

try {
    // Get settings for current semester/year
    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_academic_year', 'current_semester')");
    $settings_raw = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = [
        'academic_year' => $settings_raw['current_academic_year'] ?? '2568',
        'semester' => $settings_raw['current_semester'] ?? '1'
    ];

    if ($grade) {
        $where[] = "s.grade_level = ?";
        $params[] = $grade;
    }
    
    // Always filter by current year/semester for stats
    $where[] = "t.academic_year = ?";
    $params[] = $settings['academic_year'];
    $where[] = "t.semester = ?";
    $params[] = $settings['semester'];

    // If teacher, MUST have a room filter
    if ($role === 'teacher') {
        if (!$forceRoom) {
            echo json_encode(['success' => false, 'error' => 'You are not assigned as an advisory teacher for any room.']);
            exit;
        }
        $where[] = "s.class_name = ?";
        $params[] = $forceRoom;
    } elseif ($room) {
        $where[] = "s.class_name = ?";
        $params[] = $room;
    }

    // Special handling for the table which joins students (who might not have transactions)
    $tableWhere = [];
    $tableParams = [];
    if ($grade) { $tableWhere[] = "s.grade_level = ?"; $tableParams[] = $grade; }
    if ($role === 'teacher') { $tableWhere[] = "s.class_name = ?"; $tableParams[] = $forceRoom; }
    elseif ($room) { $tableWhere[] = "s.class_name = ?"; $tableParams[] = $room; }
    
    $tableWhereSql = !empty($tableWhere) ? "WHERE " . implode(" AND ", $tableWhere) : "";

    // Build WHERE for transaction-joined queries
    $txWhere = "WHERE " . implode(" AND ", $where);
    $txWhereAnd = "WHERE " . implode(" AND ", $where) . " AND ";

    // 1. Top Violation Categories (Donut)
    $sqlCat = "SELECT c.category_name as label, COUNT(*) as value
               FROM point_transactions t
               JOIN point_items i ON t.item_id = i.id
               JOIN point_categories c ON i.category_id = c.id
               JOIN students s ON t.student_id = s.id
               {$txWhereAnd} c.is_positive = 0
               GROUP BY c.id ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($sqlCat);
    $stmt->execute($params);
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Most Frequent Negative Behaviors (Bar)
    $sqlNeg = "SELECT i.item_name as label, COUNT(*) as value
               FROM point_transactions t
               JOIN point_items i ON t.item_id = i.id
               JOIN students s ON t.student_id = s.id
               {$txWhereAnd} t.points < 0
               GROUP BY i.id ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($sqlNeg);
    $stmt->execute($params);
    $topNegative = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Most Frequent Positive Behaviors (Bar)
    $sqlPos = "SELECT i.item_name as label, COUNT(*) as value
               FROM point_transactions t
               JOIN point_items i ON t.item_id = i.id
               JOIN students s ON t.student_id = s.id
               {$txWhereAnd} t.points > 0
               GROUP BY i.id ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($sqlPos);
    $stmt->execute($params);
    $topPositive = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Student Rankings Table - only students with transactions
    $sqlTable = "SELECT 
                    s.id, s.student_id, s.number_in_class as number, s.first_name_th, s.last_name_th, s.class_name, s.grade_level,
                    IFNULL(SUM(CASE WHEN t.points > 0 AND t.semester = ? AND t.academic_year = ? THEN t.points ELSE 0 END), 0) as plus_points,
                    IFNULL(SUM(CASE WHEN t.points < 0 AND t.semester = ? AND t.academic_year = ? THEN ABS(t.points) ELSE 0 END), 0) as minus_points,
                    (100 + IFNULL(SUM(CASE WHEN t.semester = ? AND t.academic_year = ? THEN t.points ELSE 0 END), 0)) as remaining_score,
                    COUNT(CASE WHEN t.semester = ? AND t.academic_year = ? THEN t.id ELSE NULL END) as total_transactions
                 FROM students s
                 LEFT JOIN point_transactions t ON s.id = t.student_id
                 $tableWhereSql
                 GROUP BY s.id
                 ORDER BY remaining_score ASC, s.class_name ASC, s.number_in_class ASC";
    $stmt = $pdo->prepare($sqlTable);
    $stmt->execute(array_merge([
        $settings['semester'], $settings['academic_year'],
        $settings['semester'], $settings['academic_year'],
        $settings['semester'], $settings['academic_year'],
        $settings['semester'], $settings['academic_year']
    ], $tableParams));
    $studentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return info about scope for frontend
    echo json_encode([
        'success' => true,
        'scope' => [
            'role' => $role,
            'room' => $room,
            'grade' => $grade
        ],
        'charts' => [
            'categories' => $topCategories,
            'negative' => $topNegative,
            'positive' => $topPositive
        ],
        'students' => $studentData
    ]);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
?>
