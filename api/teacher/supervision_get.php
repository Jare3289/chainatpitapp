<?php
/**
 * api/teacher/supervision_get.php
 * Fetches the current supervision status for the logged-in teacher (as evaluatee)
 * and all active supervision duties (where the teacher is an evaluator).
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$semester = 1;
$year = 2569;

try {
    // 1. Get teacher id
    $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $stmt = $pdo->prepare("SELECT id, department, sub_department FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$me) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Teacher record not found']);
        exit;
    }

    $teacher_id = $me['id'];

    // 2. Fetch my booking
    $stmt = $pdo->prepare("SELECT b.*, 
        t.prefix as teacher_prefix, t.first_name_th as teacher_first, t.last_name_th as teacher_last, t.department as teacher_dept,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.peer_teacher_id) as peer_name,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.head_teacher_id) as head_name,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.academic_teacher_id) as academic_name
        FROM supervision_bookings b 
        JOIN teachers t ON b.teacher_id = t.id
        WHERE b.teacher_id = ? AND b.semester = ? AND b.year = ? AND b.status != 'cancelled'
        ORDER BY b.id DESC LIMIT 1");
    $stmt->execute([$teacher_id, $semester, $year]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    $current_step = 1;
    $evaluations_status = [];
    $my_scores = null;

    if ($booking) {
        $booking_id = $booking['id'];

        // Fetch evaluations for my booking
        $stmt_evals = $pdo->prepare("SELECT e.*, 
            t.prefix, t.first_name_th, t.last_name_th, t.photo
            FROM supervision_evaluations e 
            JOIN teachers t ON e.evaluator_teacher_id = t.id
            WHERE e.booking_id = ?");
        $stmt_evals->execute([$booking_id]);
        $evals = $stmt_evals->fetchAll(PDO::FETCH_ASSOC);

        $peer_eval = null;
        $head_eval = null;
        $academic_eval = null;

        foreach ($evals as $ev) {
            $ev['evaluator_name'] = trim(($ev['prefix'] ?? '') . $ev['first_name_th'] . ' ' . $ev['last_name_th']);
            if ((int)$ev['evaluator_teacher_id'] === (int)$booking['peer_teacher_id']) {
                $peer_eval = $ev;
            } elseif ((int)$ev['evaluator_teacher_id'] === (int)$booking['head_teacher_id']) {
                $head_eval = $ev;
            } elseif ($booking['academic_teacher_id'] && (int)$ev['evaluator_teacher_id'] === (int)$booking['academic_teacher_id']) {
                $academic_eval = $ev;
            }
        }

        $evaluations_status = [
            'peer' => [
                'name' => $booking['peer_name'],
                'doc_done' => !empty($peer_eval['doc_evaluated_at']),
                'class_done' => !empty($peer_eval['class_evaluated_at']),
                'eval' => $peer_eval
            ],
            'head' => [
                'name' => $booking['head_name'],
                'doc_done' => !empty($head_eval['doc_evaluated_at']),
                'class_done' => !empty($head_eval['class_evaluated_at']),
                'eval' => $head_eval
            ],
            'academic' => [
                'name' => $booking['academic_name'] ?? 'รอมอบหมายคณะกรรมการวิชาการ',
                'doc_done' => !empty($academic_eval['doc_evaluated_at']),
                'class_done' => !empty($academic_eval['class_evaluated_at']),
                'eval' => $academic_eval
            ]
        ];

        // Determine current step
        if ($booking['status'] === 'pending') {
            $current_step = 1; // จองคิว (รออนุมัติ)
        } elseif ($booking['status'] === 'approved') {
            $current_step = 2; // ได้รับอนุมัติ, รอส่งแผน
        } elseif ($booking['status'] === 'doc_submitted' || $booking['status'] === 'completed') {
            // Check if all 3 evaluators evaluated document
            $total_evaluators_count = $booking['academic_teacher_id'] ? 3 : 2;
            
            $doc_done_count = 0;
            $class_done_count = 0;
            
            if (!empty($peer_eval['doc_evaluated_at'])) $doc_done_count++;
            if (!empty($head_eval['doc_evaluated_at'])) $doc_done_count++;
            if (!empty($academic_eval['doc_evaluated_at'])) $doc_done_count++;

            if (!empty($peer_eval['class_evaluated_at'])) $class_done_count++;
            if (!empty($head_eval['class_evaluated_at'])) $class_done_count++;
            if (!empty($academic_eval['class_evaluated_at'])) $class_done_count++;

            if ($doc_done_count < $total_evaluators_count) {
                $current_step = 3; // อยู่ระหว่างประเมินตรวจแผน
            } elseif ($class_done_count < $total_evaluators_count) {
                $current_step = 4; // ประเมินนิเทศในห้องเรียน
            } elseif (empty($booking['post_teaching_record'])) {
                $current_step = 5; // รอเขียนบันทึกหลังสอน
            } else {
                $current_step = 6; // เสร็จสิ้นกระบวนการทั้งหมด
            }
        }
    }

    // 3. Fetch my evaluations duties (where I am Peer, Head, or Academic evaluator)
    // Only fetch bookings in active stages: approved, doc_submitted, completed
    $stmt_duties = $pdo->prepare("SELECT b.*, 
        t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.photo as t_photo, t.department as t_dept,
        e.doc_evaluated_at, e.class_evaluated_at,
        e.doc_comments, e.class_comments,
        e.doc_score_1, e.doc_score_2, e.doc_score_3, e.doc_score_4, e.doc_score_5,
        e.unit_score_1, e.unit_score_2, e.unit_score_3, e.unit_score_4, e.unit_score_5,
        e.unit_score_6, e.unit_score_7, e.unit_score_8, e.unit_score_9, e.unit_score_10,
        e.unit_score_11, e.unit_score_12, e.unit_score_13, e.unit_score_14, e.unit_score_15,
        e.unit_score_16, e.unit_score_17, e.unit_score_18, e.unit_score_19,
        e.plan_score_1, e.plan_score_2, e.plan_score_3, e.plan_score_4, e.plan_score_5,
        e.plan_score_6, e.plan_score_7, e.plan_score_8, e.plan_score_9, e.plan_score_10,
        e.plan_score_11, e.plan_score_12, e.plan_score_13, e.plan_score_14, e.plan_score_15,
        e.plan_score_16, e.plan_score_17, e.plan_score_18, e.plan_score_19, e.plan_score_20,
        e.plan_score_21, e.plan_score_22_1, e.plan_score_22_2, e.plan_score_22_3, e.plan_score_22_4,
        e.class_score_1, e.class_score_2, e.class_score_3, e.class_score_4, e.class_score_5,
        e.class_score_6, e.class_score_7, e.class_score_8, e.class_score_9, e.class_score_10,
        e.class_score_11, e.class_score_12, e.class_score_13, e.class_score_14, e.class_score_15,
        e.class_score_16, e.class_score_17, e.class_score_18, e.class_score_19, e.class_score_20,
        e.class_score_21, e.class_score_22, e.class_score_23, e.class_score_24, e.class_score_25,
        e.class_score_26, e.class_score_27, e.class_score_28, e.class_score_29, e.class_score_30,
        e.class_score_31,
        e.unit_integration, e.plan_integration
        FROM supervision_bookings b
        JOIN teachers t ON b.teacher_id = t.id
        LEFT JOIN supervision_evaluations e ON b.id = e.booking_id AND e.evaluator_teacher_id = ?
        WHERE (b.peer_teacher_id = ? OR b.head_teacher_id = ? OR b.academic_teacher_id = ?) 
          AND b.semester = ? AND b.year = ? AND b.status IN ('approved', 'doc_submitted', 'completed')
        ORDER BY b.booking_date ASC");
    $stmt_duties->execute([$teacher_id, $teacher_id, $teacher_id, $teacher_id, $semester, $year]);
    $raw_duties = $stmt_duties->fetchAll(PDO::FETCH_ASSOC);

    $duties = [];
    foreach ($raw_duties as $d) {
        $role = '';
        if ((int)$d['peer_teacher_id'] === $teacher_id) $role = 'peer';
        elseif ((int)$d['head_teacher_id'] === $teacher_id) $role = 'head';
        elseif ((int)$d['academic_teacher_id'] === $teacher_id) $role = 'academic';

        $role_th = '';
        if ($role === 'peer') $role_th = 'ครูผู้ร่วมนิเทศ';
        elseif ($role === 'head') $role_th = 'ครูผู้นิเทศ (หัวหน้า/รอง)';
        elseif ($role === 'academic') $role_th = 'คณะกรรมการวิชาการ';

        $booking_id = $d['id'];
        
        $evaluators_req = 0;
        if ($d['peer_teacher_id']) $evaluators_req++;
        if ($d['head_teacher_id']) $evaluators_req++;
        if ($d['academic_teacher_id']) $evaluators_req++;
        
        // Fetch how many evaluators have fully read
        $stmt_reads = $pdo->prepare("SELECT COUNT(*) FROM supervision_doc_reads 
            WHERE booking_id = ? AND read_subject_structure IS NOT NULL 
            AND read_unit_structure IS NOT NULL AND read_unit_plan IS NOT NULL AND read_lesson_plan IS NOT NULL");
        $stmt_reads->execute([$booking_id]);
        $fully_read_evaluators = $stmt_reads->fetchColumn();
        $all_docs_read_by_everyone = ($fully_read_evaluators >= $evaluators_req && $evaluators_req > 0);

        // Check if I have read
        $stmt_my_read = $pdo->prepare("SELECT 1 FROM supervision_doc_reads 
            WHERE booking_id = ? AND evaluator_id = ? 
            AND read_subject_structure IS NOT NULL 
            AND read_unit_structure IS NOT NULL AND read_unit_plan IS NOT NULL AND read_lesson_plan IS NOT NULL");
        $stmt_my_read->execute([$booking_id, $teacher_id]);
        $i_have_read = (bool)$stmt_my_read->fetchColumn();

        // Check if all 4 docs are uploaded
        $stmt_docs = $pdo->prepare("SELECT * FROM supervision_docs WHERE booking_id = ?");
        $stmt_docs->execute([$booking_id]);
        $docs_row = $stmt_docs->fetch(PDO::FETCH_ASSOC);
        $docs_uploaded = false;
        if ($docs_row && $docs_row['doc_subject_structure'] && $docs_row['doc_unit_structure'] && $docs_row['doc_unit_plan'] && $docs_row['doc_lesson_plan']) {
            $docs_uploaded = true;
        }

        $duty_item = [
            'id' => $d['id'],
            'teacher_name' => trim(($d['t_prefix'] ?? '') . $d['t_first'] . ' ' . $d['t_last']),
            'teacher_photo' => $d['t_photo'],
            'department' => $d['t_dept'],
            'teacher_position' => $d['teacher_position'] ?? 'ครู',
            'academic_standing' => $d['academic_standing'] ?? 'ไม่มีวิทยฐานะ',
            'evaluation_purpose' => $d['evaluation_purpose'] ?? 'ไม่มีวิทยฐานะ',
            'lesson_topic' => $d['lesson_topic'] ?? '',
            'subject_code' => $d['subject_code'],
            'subject_name' => $d['subject_name'],
            'classroom' => $d['classroom'],
            'room_number' => $d['room_number'],
            'booking_date' => $d['booking_date'],
            'booking_period' => $d['booking_period'],
            'status' => $d['status'],
            'role' => $role,
            'role_th' => $role_th,
            'doc_done' => !empty($d['doc_evaluated_at']),
            'class_done' => !empty($d['class_evaluated_at']),
            'doc_evaluated_at' => $d['doc_evaluated_at'],
            'class_evaluated_at' => $d['class_evaluated_at'],
            'doc_score_1' => $d['doc_score_1'] ?? null,
            'doc_score_2' => $d['doc_score_2'] ?? null,
            'doc_score_3' => $d['doc_score_3'] ?? null,
            'doc_score_4' => $d['doc_score_4'] ?? null,
            'doc_score_5' => $d['doc_score_5'] ?? null,
            'doc_comments' => $d['doc_comments'] ?? null,
            'docs_uploaded' => $docs_uploaded,
            'all_docs_read_by_everyone' => $all_docs_read_by_everyone,
            'i_have_read' => $i_have_read,
            'unit_integration' => $d['unit_integration'] ?? null,
            'plan_integration' => $d['plan_integration'] ?? null
        ];

        for ($i = 1; $i <= 19; $i++) {
            $duty_item["unit_score_$i"] = $d["unit_score_$i"] !== null ? (int)$d["unit_score_$i"] : null;
        }
        for ($i = 1; $i <= 21; $i++) {
            $duty_item["plan_score_$i"] = $d["plan_score_$i"] !== null ? (int)$d["plan_score_$i"] : null;
        }
        for ($i = 1; $i <= 4; $i++) {
            $duty_item["plan_score_22_$i"] = $d["plan_score_22_$i"] !== null ? (int)$d["plan_score_22_$i"] : null;
        }
        for ($i = 1; $i <= 31; $i++) {
            $duty_item["class_score_$i"] = $d["class_score_$i"] !== null ? (int)$d["class_score_$i"] : null;
        }

        $duties[] = $duty_item;
    }

    echo json_encode([
        'success' => true,
        'my_teacher_id' => $teacher_id,
        'booking' => $booking,
        'current_step' => $current_step,
        'evaluations_status' => $evaluations_status,
        'duties' => $duties
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
