<?php
/**
 * api/supervisor/confirm_doc_read.php
 * Evaluators click "Confirm Read" for a specific document.
 */
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
$doc_type = isset($data['doc_type']) ? trim($data['doc_type']) : '';

$allowed_doc_types = [
    'doc_subject_structure',
    'doc_unit_structure',
    'doc_unit_plan',
    'doc_lesson_plan'
];

if ($booking_id <= 0 || !in_array($doc_type, $allowed_doc_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get teacher_id of the evaluator
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_id = $stmt->fetchColumn();

    if (!$teacher_id) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ?");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        $teacher_id = $stmt->fetchColumn();
    }

    if (!$teacher_id) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Teacher record not found']);
        exit;
    }

    // Determine role of this evaluator in the booking
    $stmt_book = $pdo->prepare("SELECT peer_teacher_id, head_teacher_id, academic_teacher_id FROM supervision_bookings WHERE id = ?");
    $stmt_book->execute([$booking_id]);
    $booking = $stmt_book->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    $role = '';
    if ($booking['peer_teacher_id'] == $teacher_id) $role = 'peer';
    elseif ($booking['head_teacher_id'] == $teacher_id) $role = 'head';
    elseif ($booking['academic_teacher_id'] == $teacher_id) $role = 'academic';

    if (empty($role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'คุณไม่ใช่กรรมการในคิวนิเทศนี้']);
        exit;
    }

    $read_column = str_replace('doc_', 'read_', $doc_type);

    // Upsert the read receipt
    $stmt_check = $pdo->prepare("SELECT id FROM supervision_doc_reads WHERE booking_id = ? AND evaluator_id = ?");
    $stmt_check->execute([$booking_id, $teacher_id]);
    $exists = $stmt_check->fetch();

    if ($exists) {
        $stmt_update = $pdo->prepare("UPDATE supervision_doc_reads SET $read_column = NOW() WHERE booking_id = ? AND evaluator_id = ?");
        $stmt_update->execute([$booking_id, $teacher_id]);
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO supervision_doc_reads (booking_id, evaluator_id, role, $read_column) VALUES (?, ?, ?, NOW())");
        $stmt_insert->execute([$booking_id, $teacher_id, $role]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการรับทราบเรียบร้อยแล้ว'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
