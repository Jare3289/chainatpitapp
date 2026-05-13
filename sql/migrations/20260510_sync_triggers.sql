-- ============================================================
-- Migration 20260510_sync_triggers
-- Goal: เมื่อ legacy text column (`students.room`, `students.grade_level`,
--       `attendance.class_name`) ถูกเขียนค่าใหม่ ให้ FK column ติดตามอัตโนมัติ
--
-- ทำให้ code 16 ไฟล์เดิมที่ INSERT/UPDATE โดยอ้างคอลัมน์ string ยังทำงานได้
-- และ FK integrity ไม่หลุด — ไม่ต้อง refactor ทั้งหมดในรอบเดียว
-- ============================================================

DROP TRIGGER IF EXISTS students_sync_fk_insert;
DROP TRIGGER IF EXISTS students_sync_fk_update;
DROP TRIGGER IF EXISTS attendance_sync_fk_insert;
DROP TRIGGER IF EXISTS attendance_sync_fk_update;

DELIMITER $$

CREATE TRIGGER students_sync_fk_insert
BEFORE INSERT ON students FOR EACH ROW
BEGIN
    IF NEW.room IS NOT NULL AND NEW.room <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.room LIMIT 1);
    END IF;
    IF NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' AND NEW.grade_level_id IS NULL THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END$$

CREATE TRIGGER students_sync_fk_update
BEFORE UPDATE ON students FOR EACH ROW
BEGIN
    IF (NEW.room <> OLD.room OR (NEW.room IS NOT NULL AND OLD.room IS NULL))
       AND NEW.room IS NOT NULL AND NEW.room <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.room LIMIT 1);
    END IF;
    IF (NEW.grade_level <> OLD.grade_level OR (NEW.grade_level IS NOT NULL AND OLD.grade_level IS NULL))
       AND NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END$$

CREATE TRIGGER attendance_sync_fk_insert
BEFORE INSERT ON attendance FOR EACH ROW
BEGIN
    IF NEW.class_name IS NOT NULL AND NEW.class_name <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END$$

CREATE TRIGGER attendance_sync_fk_update
BEFORE UPDATE ON attendance FOR EACH ROW
BEGIN
    IF (NEW.class_name <> OLD.class_name OR (NEW.class_name IS NOT NULL AND OLD.class_name IS NULL))
       AND NEW.class_name IS NOT NULL AND NEW.class_name <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END$$

DELIMITER ;
