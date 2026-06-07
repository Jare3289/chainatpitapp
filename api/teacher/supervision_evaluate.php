<?php
/**
 * api/teacher/supervision_evaluate.php
 * Saves score evaluations for either documents (lesson plans) or classrooms.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
require_once '../../inc/supervision_notify.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$eval_type  = isset($data['eval_type']) ? trim($data['eval_type']) : ''; // 'doc' or 'class'

if ($booking_id <= 0 || ($eval_type !== 'doc' && $eval_type !== 'class')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'พารามิเตอร์ประเมินผลไม่ถูกต้อง']);
    exit;
}

try {
    $is_admin = ($_SESSION['role'] === 'admin');
    $evaluator_id = 0;
    $role = '';

    // Fetch booking first
    $stmt = $pdo->prepare("SELECT * FROM supervision_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจองนี้']);
        exit;
    }

    if ($is_admin) {
        $admin_role_param = isset($data['role']) ? trim($data['role']) : '';
        if (!in_array($admin_role_param, ['peer', 'head', 'academic'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'แอดมินต้องระบุพารามิเตอร์บทบาทผู้ประเมิน (role) ในการลงประเมิน']);
            exit;
        }
        $role = $admin_role_param;
        if ($role === 'peer') {
            $evaluator_id = $booking['peer_teacher_id'];
        } elseif ($role === 'head') {
            $evaluator_id = $booking['head_teacher_id'];
        } elseif ($role === 'academic') {
            $evaluator_id = $booking['academic_teacher_id'];
        }
        
        if (!$evaluator_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ยังไม่มีการระบุคณะกรรมการสำหรับตำแหน่งผู้ประเมินนี้ ไม่สามารถทำการลงคะแนนได้']);
            exit;
        }
    } else {
        // 1. Get evaluator's teacher ID
        $stmt_me = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt_me->execute([$user_id]);
        $me = $stmt_me->fetch(PDO::FETCH_ASSOC);

        if (!$me) {
            $stmt_me = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ?");
            $stmt_me->execute([$_SESSION['username'], $_SESSION['username']]);
            $me = $stmt_me->fetch(PDO::FETCH_ASSOC);
        }

        if (!$me) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Teacher record not found']);
            exit;
        }

        $evaluator_id = $me['id'];

        if ((int)$booking['peer_teacher_id'] === $evaluator_id) {
            $role = 'peer';
        } elseif ((int)$booking['head_teacher_id'] === $evaluator_id) {
            $role = 'head';
        } elseif ($booking['academic_teacher_id'] && (int)$booking['academic_teacher_id'] === $evaluator_id) {
            $role = 'academic';
        }

        if (empty($role)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'คุณไม่ได้เป็นคณะกรรมการประเมินสำหรับการจองนี้']);
            exit;
        }
    }

    // 3. Upsert evaluation record
    $stmt = $pdo->prepare("SELECT id FROM supervision_evaluations WHERE booking_id = ? AND evaluator_teacher_id = ?");
    $stmt->execute([$booking_id, $evaluator_id]);
    $eval_record = $stmt->fetch();

    if (!$eval_record) {
        $stmt_insert = $pdo->prepare("INSERT INTO supervision_evaluations (booking_id, evaluator_teacher_id, evaluator_role) VALUES (?, ?, ?)");
        $stmt_insert->execute([$booking_id, $evaluator_id, $role]);
    }

    if ($eval_type === 'doc') {
        $unit_scores = [];
        for ($i = 1; $i <= 19; $i++) {
            $val = isset($data["unit_score_$i"]) ? (int)$data["unit_score_$i"] : 0;
            if ($val < 0 || $val > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "คะแนนตอนที่ 1 ข้อที่ $i ต้องอยู่ระหว่าง 0 - 5 คะแนน"]);
                exit;
            }
            $unit_scores[$i] = $val;
        }

        $plan_scores = [];
        for ($i = 1; $i <= 21; $i++) {
            $val = isset($data["plan_score_$i"]) ? (int)$data["plan_score_$i"] : 0;
            if ($val < 0 || $val > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "คะแนนตอนที่ 2 ข้อที่ $i ต้องอยู่ระหว่าง 0 - 5 คะแนน"]);
                exit;
            }
            $plan_scores[$i] = $val;
        }

        $plan_score_22 = [];
        for ($i = 1; $i <= 4; $i++) {
            $val = isset($data["plan_score_22_$i"]) ? (int)$data["plan_score_22_$i"] : 0;
            if ($val < 0 || $val > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "คะแนนตอนที่ 2 ข้อที่ 22.$i ต้องอยู่ระหว่าง 0 - 5 คะแนน"]);
                exit;
            }
            $plan_score_22[$i] = $val;
        }

        $unit_integration = isset($data['unit_integration']) ? trim($data['unit_integration']) : '';
        $plan_integration = isset($data['plan_integration']) ? trim($data['plan_integration']) : '';
        $doc_comments = isset($data['doc_comments']) ? trim($data['doc_comments']) : '';

        // For backwards compatibility:
        $doc_score_1 = $unit_scores[1];
        $doc_score_2 = $unit_scores[2];
        $doc_score_3 = $unit_scores[3];
        $doc_score_4 = $unit_scores[4];
        $doc_score_5 = $unit_scores[5];

        // Prepare UPDATE query
        $update_cols = [
            "doc_score_1 = ?", "doc_score_2 = ?", "doc_score_3 = ?", "doc_score_4 = ?", "doc_score_5 = ?"
        ];
        $params = [$doc_score_1, $doc_score_2, $doc_score_3, $doc_score_4, $doc_score_5];

        for ($i = 1; $i <= 19; $i++) {
            $update_cols[] = "unit_score_$i = ?";
            $params[] = $unit_scores[$i];
        }
        for ($i = 1; $i <= 21; $i++) {
            $update_cols[] = "plan_score_$i = ?";
            $params[] = $plan_scores[$i];
        }
        for ($i = 1; $i <= 4; $i++) {
            $update_cols[] = "plan_score_22_$i = ?";
            $params[] = $plan_score_22[$i];
        }

        $update_cols[] = "unit_integration = ?";
        $params[] = $unit_integration;

        $update_cols[] = "plan_integration = ?";
        $params[] = $plan_integration;

        $update_cols[] = "doc_comments = ?";
        $params[] = $doc_comments;

        $update_cols[] = "doc_evaluated_at = CURRENT_TIMESTAMP";

        // Add WHERE parameters
        $params[] = $booking_id;
        $params[] = $evaluator_id;

        $query_str = "UPDATE supervision_evaluations SET " . implode(", ", $update_cols) . " WHERE booking_id = ? AND evaluator_teacher_id = ?";
        $stmt_update = $pdo->prepare($query_str);
        $stmt_update->execute($params);

        $msg = 'บันทึกการประเมินแผนการจัดการเรียนรู้ (ตรวจแผน) เรียบร้อยแล้ว';

    } elseif ($eval_type === 'class') {
        // Retrieve and validate classroom scores (0-5 for each of 31 items)
        $scores = [];
        $update_cols = [];
        for ($i = 1; $i <= 31; $i++) {
            $scores[$i] = isset($data["class_score_$i"]) ? (int)$data["class_score_$i"] : 0;
            if ($scores[$i] < 0 || $scores[$i] > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "คะแนนข้อที่ $i ต้องอยู่ระหว่าง 0 - 5 คะแนน"]);
                exit;
            }
            $update_cols[] = "class_score_$i = ?";
        }
        $class_comments = isset($data['class_comments']) ? trim($data['class_comments']) : '';

        $update_cols[] = "class_comments = ?";
        $update_cols[] = "class_evaluated_at = CURRENT_TIMESTAMP";

        $query_str = "UPDATE supervision_evaluations SET " . implode(", ", $update_cols) . " WHERE booking_id = ? AND evaluator_teacher_id = ?";
        $stmt_update = $pdo->prepare($query_str);
        
        $params = array_values($scores);
        $params[] = $class_comments;
        $params[] = $booking_id;
        $params[] = $evaluator_id;

        $stmt_update->execute($params);

        $msg = 'บันทึกการประเมินการจัดการเรียนรู้ในห้องเรียนเรียบร้อยแล้ว';
    }

    // Send notifications
    try {
        require_once '../../inc/notifications.php';
        // Get evaluator full name
        $stmt_ev_name = $pdo->prepare("SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = ?");
        $stmt_ev_name->execute([$evaluator_id]);
        $evaluator_full_name = $stmt_ev_name->fetchColumn() ?: $_SESSION['username'];

        // Get evaluatee details
        $stmt_bk = $pdo->prepare("SELECT subject_code, subject_name,
            (SELECT user_id FROM teachers WHERE id = b.teacher_id) as t_user_id
            FROM supervision_bookings b WHERE b.id = ?");
        $stmt_bk->execute([$booking_id]);
        $bk_info = $stmt_bk->fetch();

        if ($bk_info) {
            // Notify evaluator
            cnp_notify($pdo, (int)$user_id, 'บันทึกการประเมินสำเร็จ 🎉', $msg, 'teacher_supervision.html', 'bi-check-circle-fill', '#10b981', 'supervision');

            // Notify evaluatee
            if ($bk_info['t_user_id']) {
                if ($eval_type === 'doc') {
                    $msg_eval = "แผนการจัดการเรียนรู้วิชา " . $bk_info['subject_name'] . " (" . $bk_info['subject_code'] . ") ของคุณได้รับการประเมินตรวจแผนโดย อ. " . $evaluator_full_name . " แล้ว";
                    cnp_notify($pdo, (int)$bk_info['t_user_id'], 'แผนการสอนได้รับการตรวจประเมิน 📝', $msg_eval, 'teacher_supervision.html', 'bi-file-earmark-check', '#3b82f6', 'supervision');
                } else {
                    $msg_eval = "การจัดกิจกรรมการเรียนรู้ในห้องเรียนวิชา " . $bk_info['subject_name'] . " (" . $bk_info['subject_code'] . ") ของคุณได้รับการประเมินโดย อ. " . $evaluator_full_name . " แล้ว";
                    cnp_notify($pdo, (int)$bk_info['t_user_id'], 'ได้รับการประเมินการสอนในห้องเรียน 🏫', $msg_eval, 'teacher_supervision.html', 'bi-mortarboard-fill', '#3b82f6', 'supervision');
                }
            }
        }
    } catch (Exception $ex) {}

    echo json_encode([
        'success' => true,
        'message' => $msg
    ]);

    // Notify the evaluated teacher
    try {
        $ids = supervisionBookingUserIds($pdo, $booking_id);
        $evalTypeLabel = ($eval_type === 'doc') ? 'แผนการจัดการเรียนรู้' : 'การจัดการเรียนรู้ในชั้นเรียน';
        $notifMsg = "กรรมการประเมินได้บันทึกผลการประเมิน {$evalTypeLabel} ของท่าน (จอง #{$booking_id}) เรียบร้อยแล้ว";
        supervisionNotify($pdo, [$ids['teacher_user_id']], 'ผลการประเมินนิเทศการสอน', $notifMsg, 'supervision_booking.html');
    } catch (Throwable $e_n) {
        error_log('[supervision_evaluate notify] ' . $e_n->getMessage());
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
