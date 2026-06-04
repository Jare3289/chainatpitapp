<?php
/**
 * api/teacher/supervision_evaluate.php
 * Saves score evaluations for either documents (lesson plans) or classrooms.
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
    // 1. Get evaluator's teacher ID
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$me) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Teacher record not found']);
        exit;
    }

    $evaluator_id = $me['id'];

    // 2. Verify that logged-in teacher is a supervisor for this booking
    $stmt = $pdo->prepare("SELECT * FROM supervision_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจองนี้']);
        exit;
    }

    $role = '';
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

    echo json_encode([
        'success' => true,
        'message' => $msg
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
