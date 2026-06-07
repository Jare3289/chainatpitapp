<?php
/**
 * api/admin/supervision_stats.php
 * Endpoint for admins to retrieve aggregated statistics on teaching supervision.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_schedule.php';
session_start();

$is_supervision_manager = false;
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        $is_supervision_manager = true;
    } elseif ($_SESSION['role'] === 'teacher') {
        $stmt_check = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt_check->execute([$_SESSION['user_id']]);
        $teacher_id = $stmt_check->fetchColumn();
        if ($teacher_id && (int)$teacher_id === 518) {
            $is_supervision_manager = true;
        }
    }
}

if (!$is_supervision_manager) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$semester = 1;
$year = 2569;

try {
    // 1. Basic counts
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM supervision_bookings 
                           WHERE semester = ? AND year = ? GROUP BY status");
    $stmt->execute([$semester, $year]);
    $counts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0, // approved + doc_submitted is active/upcoming
        'completed' => 0,
        'cancelled' => 0
    ];

    foreach ($counts_raw as $row) {
        $st = $row['status'];
        $count = (int)$row['count'];
        if ($st !== 'cancelled') {
            $stats['total'] += $count;
        }
        if ($st === 'pending') {
            $stats['pending'] = $count;
        } elseif ($st === 'approved' || $st === 'doc_submitted') {
            $stats['approved'] += $count;
        } elseif ($st === 'completed') {
            $stats['completed'] = $count;
        } elseif ($st === 'cancelled') {
            $stats['cancelled'] = $count;
        }
    }

    // 2. Department breakdown
    $stmt_dept = $pdo->prepare("SELECT t.department, COUNT(b.id) as count,
                                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                                SUM(CASE WHEN b.status IN ('approved', 'doc_submitted') THEN 1 ELSE 0 END) as upcoming_count,
                                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_count
                                FROM supervision_bookings b 
                                JOIN teachers t ON b.teacher_id = t.id 
                                WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'
                                GROUP BY t.department
                                ORDER BY count DESC");
    $stmt_dept->execute([$semester, $year]);
    $departments = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

    // 3. Score averages by aspect/category
    // Fetch all submitted evaluations
    $stmt_evals = $pdo->prepare("SELECT e.* FROM supervision_evaluations e 
                                 JOIN supervision_bookings b ON e.booking_id = b.id 
                                 WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'");
    $stmt_evals->execute([$semester, $year]);
    $evaluations = $stmt_evals->fetchAll(PDO::FETCH_ASSOC);

    // Calculations for Document Evaluations (แผนการสอน)
    $doc_count = 0;
    $unit_sum = 0; // 19 items
    $plan_sum = 0; // 25 items (21 + 4 sub-items)

    // Calculations for Classroom Observations (สังเกตการณ์เรียนรู้)
    $class_count = 0;
    $class_p1_sum = 0; // 6 items
    $class_p2_sum = 0; // 13 items
    $class_p3_sum = 0; // 4 items
    $class_p4_sum = 0; // 2 items
    $class_p5_sum = 0; // 2 items
    $class_p6_sum = 0; // 4 items

    foreach ($evaluations as $e) {
        // Document evaluation calculation
        if (!empty($e['doc_evaluated_at'])) {
            $doc_count++;
            
            // Part 1: unit structure (1-19)
            $u_sum = 0;
            for ($i = 1; $i <= 19; $i++) {
                $u_sum += (int)$e["unit_score_$i"];
            }
            $unit_sum += ($u_sum / 19);

            // Part 2: lesson plan structure (1-21 + 22_1..4)
            $p_sum = 0;
            for ($i = 1; $i <= 21; $i++) {
                $p_sum += (int)$e["plan_score_$i"];
            }
            for ($i = 1; $i <= 4; $i++) {
                $p_sum += (int)$e["plan_score_22_$i"];
            }
            $plan_sum += ($p_sum / 25);
        }

        // Classroom evaluation calculation
        if (!empty($e['class_evaluated_at'])) {
            $class_count++;

            // P1 (1-6)
            $p1 = 0;
            for ($i = 1; $i <= 6; $i++) $p1 += (int)$e["class_score_$i"];
            $class_p1_sum += ($p1 / 6);

            // P2 (7-19)
            $p2 = 0;
            for ($i = 7; $i <= 19; $i++) $p2 += (int)$e["class_score_$i"];
            $class_p2_sum += ($p2 / 13);

            // P3 (20-23)
            $p3 = 0;
            for ($i = 20; $i <= 23; $i++) $p3 += (int)$e["class_score_$i"];
            $class_p3_sum += ($p3 / 4);

            // P4 (24-25)
            $p4 = 0;
            for ($i = 24; $i <= 25; $i++) $p4 += (int)$e["class_score_$i"];
            $class_p4_sum += ($p4 / 2);

            // P5 (26-27)
            $p5 = 0;
            for ($i = 26; $i <= 27; $i++) $p5 += (int)$e["class_score_$i"];
            $class_p5_sum += ($p5 / 2);

            // P6 (28-31)
            $p6 = 0;
            for ($i = 28; $i <= 31; $i++) $p6 += (int)$e["class_score_$i"];
            $class_p6_sum += ($p6 / 4);
        }
    }

    $averages = [
        'doc' => [
            'count' => $doc_count,
            'unit_avg' => $doc_count > 0 ? round($unit_sum / $doc_count, 2) : 0,
            'plan_avg' => $doc_count > 0 ? round($plan_sum / $doc_count, 2) : 0,
            'overall_avg' => $doc_count > 0 ? round((($unit_sum + $plan_sum) / 2) / $doc_count, 2) : 0,
        ],
        'class' => [
            'count' => $class_count,
            'p1_avg' => $class_count > 0 ? round($class_p1_sum / $class_count, 2) : 0,
            'p2_avg' => $class_count > 0 ? round($class_p2_sum / $class_count, 2) : 0,
            'p3_avg' => $class_count > 0 ? round($class_p3_sum / $class_count, 2) : 0,
            'p4_avg' => $class_count > 0 ? round($class_p4_sum / $class_count, 2) : 0,
            'p5_avg' => $class_count > 0 ? round($class_p5_sum / $class_count, 2) : 0,
            'p6_avg' => $class_count > 0 ? round($class_p6_sum / $class_count, 2) : 0,
            'overall_avg' => $class_count > 0 ? round((($class_p1_sum + $class_p2_sum + $class_p3_sum + $class_p4_sum + $class_p5_sum + $class_p6_sum) / 6) / $class_count, 2) : 0,
        ]
    ];

    // 4. Calendar information
    // Get all scheduled dates from config mapping
    $schedule_dates = [];
    foreach ($supervision_schedules as $dept_name => $info) {
        foreach ($info['dates'] as $d) {
            $schedule_dates[$d] = [
                'date' => $d,
                'department' => $dept_name,
                'bookings_count' => 0,
                'bookings' => []
            ];
        }
    }

    // Query active bookings and map to dates
    $stmt_bk = $pdo->prepare("SELECT b.*, 
                              t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.department as t_dept
                              FROM supervision_bookings b 
                              JOIN teachers t ON b.teacher_id = t.id 
                              WHERE b.semester = ? AND b.year = ? AND b.status != 'cancelled'
                              ORDER BY b.booking_date ASC, b.booking_period ASC");
    $stmt_bk->execute([$semester, $year]);
    $bookings = $stmt_bk->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $b) {
        $b_date = $b['booking_date'];
        $teacher_name = trim(($b['t_prefix'] ?? '') . $b['t_first'] . ' ' . $b['t_last']);
        
        $b_detail = [
            'id' => $b['id'],
            'teacher_name' => $teacher_name,
            'department' => $b['t_dept'],
            'subject_code' => $b['subject_code'],
            'subject_name' => $b['subject_name'],
            'classroom' => $b['classroom'],
            'period' => $b['booking_period'],
            'status' => $b['status']
        ];

        if (!isset($schedule_dates[$b_date])) {
            $schedule_dates[$b_date] = [
                'date' => $b_date,
                'department' => $b['t_dept'] . ' (จองนอกตารางหลัก)',
                'bookings_count' => 0,
                'bookings' => []
            ];
        }

        $schedule_dates[$b_date]['bookings_count']++;
        $schedule_dates[$b_date]['bookings'][] = $b_detail;
    }

    // Sort schedule dates key wise
    ksort($schedule_dates);
    $calendar_data = array_values($schedule_dates);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'departments' => $departments,
        'averages' => $averages,
        'calendar' => $calendar_data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
