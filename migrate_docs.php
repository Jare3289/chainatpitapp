<?php
require 'config.php';
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS supervision_docs (
        booking_id INT PRIMARY KEY,
        doc_subject_structure VARCHAR(255) NULL,
        doc_unit_structure VARCHAR(255) NULL,
        doc_unit_plan VARCHAR(255) NULL,
        doc_lesson_plan VARCHAR(255) NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES supervision_bookings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS supervision_doc_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        evaluator_id INT NOT NULL,
        role VARCHAR(50) NOT NULL,
        read_subject_structure DATETIME NULL,
        read_unit_structure DATETIME NULL,
        read_unit_plan DATETIME NULL,
        read_lesson_plan DATETIME NULL,
        UNIQUE KEY unique_evaluator (booking_id, evaluator_id),
        FOREIGN KEY (booking_id) REFERENCES supervision_bookings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo json_encode(["success" => true, "message" => "Tables created successfully"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
