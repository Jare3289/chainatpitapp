-- ============================================================
-- Migration: 20260608_supervision_tables
-- ระบบนิเทศการสอน — สร้างตารางทั้งหมดที่จำเป็น
-- ปลอดภัย: ใช้ CREATE TABLE IF NOT EXISTS / ADD COLUMN (safe to re-run)
-- ============================================================

-- 1. ตารางจองการนิเทศ
CREATE TABLE IF NOT EXISTS `supervision_bookings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มคอลัมน์ที่อาจขาดใน supervision_bookings (ถ้ามีแล้วจะ error ให้ข้ามได้)
ALTER TABLE `supervision_bookings` ADD COLUMN IF NOT EXISTS `teacher_position` VARCHAR(100) NOT NULL DEFAULT 'ครู';
ALTER TABLE `supervision_bookings` ADD COLUMN IF NOT EXISTS `academic_standing` VARCHAR(100) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ';
ALTER TABLE `supervision_bookings` ADD COLUMN IF NOT EXISTS `lesson_topic` VARCHAR(255) DEFAULT NULL;

-- 2. ตารางผลการประเมิน (แบบ 41 ข้อ)
CREATE TABLE IF NOT EXISTS `supervision_evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `evaluator_teacher_id` INT NOT NULL,
  `evaluator_role` VARCHAR(50) NOT NULL,
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
  `unit_score_1` INT DEFAULT 0, `unit_score_2` INT DEFAULT 0, `unit_score_3` INT DEFAULT 0,
  `unit_score_4` INT DEFAULT 0, `unit_score_5` INT DEFAULT 0, `unit_score_6` INT DEFAULT 0,
  `unit_score_7` INT DEFAULT 0, `unit_score_8` INT DEFAULT 0, `unit_score_9` INT DEFAULT 0,
  `unit_score_10` INT DEFAULT 0, `unit_score_11` INT DEFAULT 0, `unit_score_12` INT DEFAULT 0,
  `unit_score_13` INT DEFAULT 0, `unit_score_14` INT DEFAULT 0, `unit_score_15` INT DEFAULT 0,
  `unit_score_16` INT DEFAULT 0, `unit_score_17` INT DEFAULT 0, `unit_score_18` INT DEFAULT 0,
  `unit_score_19` INT DEFAULT 0,
  `plan_score_1` INT DEFAULT 0, `plan_score_2` INT DEFAULT 0, `plan_score_3` INT DEFAULT 0,
  `plan_score_4` INT DEFAULT 0, `plan_score_5` INT DEFAULT 0, `plan_score_6` INT DEFAULT 0,
  `plan_score_7` INT DEFAULT 0, `plan_score_8` INT DEFAULT 0, `plan_score_9` INT DEFAULT 0,
  `plan_score_10` INT DEFAULT 0, `plan_score_11` INT DEFAULT 0, `plan_score_12` INT DEFAULT 0,
  `plan_score_13` INT DEFAULT 0, `plan_score_14` INT DEFAULT 0, `plan_score_15` INT DEFAULT 0,
  `plan_score_16` INT DEFAULT 0, `plan_score_17` INT DEFAULT 0, `plan_score_18` INT DEFAULT 0,
  `plan_score_19` INT DEFAULT 0, `plan_score_20` INT DEFAULT 0, `plan_score_21` INT DEFAULT 0,
  `plan_score_22_1` INT DEFAULT 0, `plan_score_22_2` INT DEFAULT 0,
  `plan_score_22_3` INT DEFAULT 0, `plan_score_22_4` INT DEFAULT 0,
  `unit_integration` TEXT DEFAULT NULL,
  `plan_integration` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `booking_evaluator` (`booking_id`, `evaluator_teacher_id`),
  INDEX (`booking_id`),
  INDEX (`evaluator_teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางเอกสารที่อัปโหลด (PDF แผนการสอน 4 ประเภท)
CREATE TABLE IF NOT EXISTS `supervision_docs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL UNIQUE,
  `doc_subject_structure` VARCHAR(500) DEFAULT NULL,
  `doc_unit_structure`    VARCHAR(500) DEFAULT NULL,
  `doc_unit_plan`         VARCHAR(500) DEFAULT NULL,
  `doc_lesson_plan`       VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตารางยืนยันการอ่านเอกสาร (กรรมการกด "รับทราบ")
CREATE TABLE IF NOT EXISTS `supervision_doc_reads` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
