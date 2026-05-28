-- Migration: Add enrollment_status to students table
-- 2026-05-20

ALTER TABLE `students`
  ADD COLUMN `enrollment_status` ENUM('กำลังศึกษา','พักการเรียน','ลาออก','จำหน่าย')
    NOT NULL DEFAULT 'กำลังศึกษา'
    AFTER `is_active`;

ALTER TABLE `students`
  ADD INDEX `idx_enrollment_status` (`enrollment_status`);
