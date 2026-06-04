<?php
/**
 * api/teacher/supervision_get_docs.php
 * Fetch the uploaded docs and their read status for a specific booking.
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

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

try {
    // Check if booking exists
    $stmt = $pdo->prepare("SELECT * FROM supervision_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    // Get docs
    $stmt_docs = $pdo->prepare("SELECT * FROM supervision_docs WHERE booking_id = ?");
    $stmt_docs->execute([$booking_id]);
    $docs = $stmt_docs->fetch(PDO::FETCH_ASSOC) ?: [
        'doc_subject_structure' => null,
        'doc_unit_structure' => null,
        'doc_unit_plan' => null,
        'doc_lesson_plan' => null
    ];

    // Get read receipts
    $stmt_reads = $pdo->prepare("
        SELECT r.*, 
            CONCAT(t.prefix, t.first_name_th, ' ', t.last_name_th) as evaluator_name
        FROM supervision_doc_reads r
        JOIN teachers t ON r.evaluator_id = t.id
        WHERE r.booking_id = ?
    ");
    $stmt_reads->execute([$booking_id]);
    $reads = $stmt_reads->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'booking_status' => $booking['status'],
        'docs' => $docs,
        'reads' => $reads
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
