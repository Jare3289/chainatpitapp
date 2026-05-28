-- ============================================================
-- Migration 20260510_sync_triggers
-- วิธีรันใน phpMyAdmin: ใช้แท็บ "Import" → เลือกไฟล์นี้ → Go
-- (ห้ามใช้แท็บ SQL แบบ paste โดยตรง — ต้อง Import เพื่อให้ DELIMITER ทำงาน)
-- ============================================================

DELIMITER //

DROP TRIGGER IF EXISTS students_sync_fk_insert//
DROP TRIGGER IF EXISTS students_sync_fk_update//
DROP TRIGGER IF EXISTS attendance_sync_fk_insert//
DROP TRIGGER IF EXISTS attendance_sync_fk_update//

CREATE TRIGGER students_sync_fk_insert
BEFORE INSERT ON students FOR EACH ROW
BEGIN
    IF NEW.class_name IS NOT NULL AND NEW.class_name <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
    IF NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' AND NEW.grade_level_id IS NULL THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END//

CREATE TRIGGER students_sync_fk_update
BEFORE UPDATE ON students FOR EACH ROW
BEGIN
    IF (NEW.class_name <> OLD.class_name OR (NEW.class_name IS NOT NULL AND OLD.class_name IS NULL))
       AND NEW.class_name IS NOT NULL AND NEW.class_name <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
    IF (NEW.grade_level <> OLD.grade_level OR (NEW.grade_level IS NOT NULL AND OLD.grade_level IS NULL))
       AND NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END//

CREATE TRIGGER attendance_sync_fk_insert
BEFORE INSERT ON attendance FOR EACH ROW
BEGIN
    IF NEW.class_name IS NOT NULL AND NEW.class_name <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END//

CREATE TRIGGER attendance_sync_fk_update
BEFORE UPDATE ON attendance FOR EACH ROW
BEGIN
    IF (NEW.class_name <> OLD.class_name OR (NEW.class_name IS NOT NULL AND OLD.class_name IS NULL))
       AND NEW.class_name IS NOT NULL AND NEW.class_name <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END//

DELIMITER ;
