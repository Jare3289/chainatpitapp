<?php
require 'config.php';

try {
    $stmt = $pdo->query("SELECT id, subject_name FROM supervision_bookings WHERE subject_code = '' OR subject_code IS NULL");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fixedCount = 0;
    foreach ($bookings as $b) {
        $name_value = trim($b['subject_name']);
        
        // If the subject_name is actually a code (e.g., ท32101), look it up in subjects table
        $stmt_subj = $pdo->prepare("SELECT subject_code, subject_name FROM subjects WHERE subject_code = ? LIMIT 1");
        $stmt_subj->execute([$name_value]);
        $subj = $stmt_subj->fetch(PDO::FETCH_ASSOC);
        
        if ($subj) {
            // Update the booking with correct code and name
            $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET subject_code = ?, subject_name = ? WHERE id = ?");
            $stmt_update->execute([$subj['subject_code'], $subj['subject_name'], $b['id']]);
            $fixedCount++;
        } else {
            // If it doesn't exist in subjects table, just swap them so at least subject_code has the code
            $stmt_update = $pdo->prepare("UPDATE supervision_bookings SET subject_code = ?, subject_name = ? WHERE id = ?");
            $stmt_update->execute([$name_value, $name_value, $b['id']]);
            $fixedCount++;
        }
    }

    echo json_encode(['success' => true, 'fixed' => $fixedCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
