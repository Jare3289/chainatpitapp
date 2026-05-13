-- ====================================================
-- Migration: 20260513_timetable.sql
-- สร้างตาราง timetable สำหรับเก็บข้อมูลตารางสอน
-- คาบ 0–11 (12 คาบ), รองรับทุกวันในสัปดาห์
-- ====================================================

CREATE TABLE IF NOT EXISTS `timetable` (
    `id`            INT             NOT NULL AUTO_INCREMENT,
    `teacher_id`    INT             NOT NULL COMMENT 'FK → teachers.id',
    `day_of_week`   TINYINT(1)      NOT NULL COMMENT '1=จันทร์ 2=อังคาร 3=พุธ 4=พฤหัส 5=ศุกร์ 6=เสาร์ 7=อาทิตย์',
    `period`        TINYINT(2)      NOT NULL COMMENT 'คาบที่ 0–11',
    `subject_name`  VARCHAR(200)    NOT NULL COMMENT 'ชื่อวิชา',
    `subject_code`  VARCHAR(50)     DEFAULT NULL COMMENT 'รหัสวิชา',
    `grade_level`   VARCHAR(20)     DEFAULT NULL COMMENT 'ชั้น เช่น ม.1 ม.4',
    `class_name`    VARCHAR(30)     DEFAULT NULL COMMENT 'ห้องเรียน เช่น 1/1 4/2',
    `room_location` VARCHAR(100)    DEFAULT NULL COMMENT 'ห้องที่สอน (อาคาร/ห้อง)',
    `academic_year` VARCHAR(10)     DEFAULT NULL COMMENT 'ปีการศึกษา เช่น 2568',
    `semester`      TINYINT(1)      DEFAULT 1,
    `note`          VARCHAR(200)    DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_teacher`    (`teacher_id`),
    INDEX `idx_day_period` (`day_of_week`, `period`),
    INDEX `idx_class`      (`grade_level`, `class_name`),
    CONSTRAINT `fk_timetable_teacher`
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

