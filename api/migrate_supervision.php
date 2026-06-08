<?php
/**
 * api/migrate_supervision.php
 * Database migration to create tables for the Teacher Supervision System.
 */
require_once '../config.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>ระบบไมเกรชัน - ระบบนิเทศการสอน</h2>";

try {
    // 1. Create supervision_bookings table
    $sql_bookings = "CREATE TABLE IF NOT EXISTS `supervision_bookings` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `teacher_id` INT NOT NULL,
      `semester` INT NOT NULL DEFAULT 1,
      `year` INT NOT NULL DEFAULT 2569,
      `subject_code` VARCHAR(50) NOT NULL,
      `subject_name` VARCHAR(255) NOT NULL,
      `classroom` VARCHAR(100) NOT NULL,
      `room_number` VARCHAR(100) NOT NULL,
      `lesson_topic` VARCHAR(255) DEFAULT NULL,
      `booking_date` DATE NOT NULL,
      `booking_period` INT NOT NULL,
      `peer_teacher_id` INT NOT NULL,
      `head_teacher_id` INT NOT NULL,
      `academic_teacher_id` INT DEFAULT NULL,
      `teacher_position` VARCHAR(100) NOT NULL DEFAULT 'ครู',
      `academic_standing` VARCHAR(100) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ',
      `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
      `evaluation_purpose` VARCHAR(255) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ',
      `lesson_plan_doc` VARCHAR(255) DEFAULT NULL,
      `post_teaching_record` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`teacher_id`),
      INDEX (`booking_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql_bookings);
    
    // Add teacher_position if it doesn't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE `supervision_bookings` ADD COLUMN `teacher_position` VARCHAR(100) NOT NULL DEFAULT 'ครู' AFTER `head_teacher_id`");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Add academic_standing if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE `supervision_bookings` ADD COLUMN `academic_standing` VARCHAR(100) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ' AFTER `teacher_position`");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Add lesson_topic if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE `supervision_bookings` ADD COLUMN `lesson_topic` VARCHAR(255) DEFAULT NULL AFTER `room_number`");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    echo "<p style='color:green;'>✓ สร้าง/ปรับปรุงตาราง supervision_bookings สำเร็จ</p>";

    // 2. Create supervision_evaluations table
    $sql_evaluations = "CREATE TABLE IF NOT EXISTS `supervision_evaluations` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `booking_id` INT NOT NULL,
      `evaluator_teacher_id` INT NOT NULL,
      `evaluator_role` VARCHAR(50) NOT NULL, -- 'peer', 'head', 'academic'
      `doc_score_1` INT DEFAULT 0,
      `doc_score_2` INT DEFAULT 0,
      `doc_score_3` INT DEFAULT 0,
      `doc_score_4` INT DEFAULT 0,
      `doc_score_5` INT DEFAULT 0,
      `doc_comments` TEXT DEFAULT NULL,
      `doc_evaluated_at` TIMESTAMP NULL DEFAULT NULL,
      `class_score_1` INT DEFAULT 0,
      `class_score_2` INT DEFAULT 0,
      `class_score_3` INT DEFAULT 0,
      `class_score_4` INT DEFAULT 0,
      `class_score_5` INT DEFAULT 0,
      `class_score_6` INT DEFAULT 0,
      `class_score_7` INT DEFAULT 0,
      `class_score_8` INT DEFAULT 0,
      `class_score_9` INT DEFAULT 0,
      `class_score_10` INT DEFAULT 0,
      `class_comments` TEXT DEFAULT NULL,
      `class_evaluated_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `booking_evaluator` (`booking_id`, `evaluator_teacher_id`),
      INDEX (`booking_id`),
      INDEX (`evaluator_teacher_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql_evaluations);
    echo "<p style='color:green;'>✓ สร้างตาราง supervision_evaluations สำเร็จ</p>";

    // Add new columns to supervision_evaluations for the 41-question form
    // unit_score_1 to unit_score_19
    for ($i = 1; $i <= 19; $i++) {
        try {
            $pdo->exec("ALTER TABLE `supervision_evaluations` ADD COLUMN `unit_score_$i` INT DEFAULT 0");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
    }
    
    // plan_score_1 to plan_score_21
    for ($i = 1; $i <= 21; $i++) {
        try {
            $pdo->exec("ALTER TABLE `supervision_evaluations` ADD COLUMN `plan_score_$i` INT DEFAULT 0");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
    }
    
    // plan_score_22_1 to plan_score_22_4
    for ($i = 1; $i <= 4; $i++) {
        try {
            $pdo->exec("ALTER TABLE `supervision_evaluations` ADD COLUMN `plan_score_22_$i` INT DEFAULT 0");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
    }
    
    // unit_integration, plan_integration
    try {
        $pdo->exec("ALTER TABLE `supervision_evaluations` ADD COLUMN `unit_integration` TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    try {
        $pdo->exec("ALTER TABLE `supervision_evaluations` ADD COLUMN `plan_integration` TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    echo "<p style='color:green;'>✓ ปรับปรุงโครงสร้างคอลัมน์ประเมินผลแผน 41 ข้อ สำเร็จ</p>";

    // 3. Create supervision_docs table (เก็บ path ไฟล์ PDF ทั้ง 4 ประเภท)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `supervision_docs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `booking_id` INT NOT NULL UNIQUE,
      `doc_subject_structure` VARCHAR(500) DEFAULT NULL,
      `doc_unit_structure`    VARCHAR(500) DEFAULT NULL,
      `doc_unit_plan`         VARCHAR(500) DEFAULT NULL,
      `doc_lesson_plan`       VARCHAR(500) DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (`booking_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p style='color:green;'>✓ สร้างตาราง supervision_docs สำเร็จ</p>";

    // 4. Create supervision_doc_reads table (เก็บว่ากรรมการอ่านเอกสารแล้วหรือยัง)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `supervision_doc_reads` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `booking_id` INT NOT NULL,
      `evaluator_id` INT NOT NULL,
      `role` VARCHAR(20) NOT NULL,
      `read_subject_structure` TIMESTAMP NULL DEFAULT NULL,
      `read_unit_structure`    TIMESTAMP NULL DEFAULT NULL,
      `read_unit_plan`         TIMESTAMP NULL DEFAULT NULL,
      `read_lesson_plan`       TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `booking_evaluator` (`booking_id`, `evaluator_id`),
      INDEX (`booking_id`),
      INDEX (`evaluator_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p style='color:green;'>✓ สร้างตาราง supervision_doc_reads สำเร็จ</p>";

    // Create upload directory for documents if not exists
    $upload_dir = '../uploads/supervision';
    if (!is_dir($upload_dir)) {
        if (mkdir($upload_dir, 0777, true)) {
            echo "<p style='color:green;'>✓ สร้างโฟลเดอร์สำหรับอัปโหลดแผนการสอนสำเร็จ (uploads/supervision/)</p>";
        } else {
            echo "<p style='color:orange;'>! ไม่สามารถสร้างโฟลเดอร์ uploads/supervision/ ได้ กรุณาสร้างโฟลเดอร์นี้และตั้งสิทธิ์การเขียน</p>";
        }
    } else {
        echo "<p style='color:green;'>✓ โฟลเดอร์ uploads/supervision/ มีอยู่แล้ว</p>";
    }

    echo "<h3 style='color:blue;'>ไมเกรชันเสร็จสมบูรณ์!</h3>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ เกิดข้อผิดพลาดในการประมวลผลคำสั่งฐานข้อมูล: " . htmlspecialchars($e->getMessage()) . "</p>";
}
