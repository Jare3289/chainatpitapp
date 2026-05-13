-- ============================================================
-- Migration 20260510_propagate_renames
-- Goal: เมื่อ rename master row (rooms.classroom_code หรือ grade_levels.grade_name)
--       ให้ legacy text columns ใน students/attendance อัปเดตตามอัตโนมัติ
--
-- ผลลัพธ์: rename "มัธยมศึกษาปีที่ 1" → "ม.1" ที่ master table ที่เดียวก็พอ
-- code ทั้ง 16 ไฟล์ที่ยังอ่านจาก text column จะเห็นชื่อใหม่ทันที
-- (เสริมจาก ON UPDATE CASCADE ของ FK ที่ดูแลด้าน id อยู่แล้ว)
-- ============================================================

DROP TRIGGER IF EXISTS rooms_propagate_code;
DROP TRIGGER IF EXISTS grade_levels_propagate_name;

DELIMITER $$

CREATE TRIGGER rooms_propagate_code
AFTER UPDATE ON rooms FOR EACH ROW
BEGIN
    IF NEW.classroom_code <> OLD.classroom_code THEN
        UPDATE students   SET room       = NEW.classroom_code WHERE room       = OLD.classroom_code;
        UPDATE attendance SET class_name = NEW.classroom_code WHERE class_name = OLD.classroom_code;
    END IF;
END$$

CREATE TRIGGER grade_levels_propagate_name
AFTER UPDATE ON grade_levels FOR EACH ROW
BEGIN
    IF NEW.grade_name <> OLD.grade_name THEN
        UPDATE students SET grade_level = NEW.grade_name WHERE grade_level = OLD.grade_name;
        UPDATE rooms    SET grade_level = NEW.grade_name WHERE grade_level = OLD.grade_name;
    END IF;
END$$

DELIMITER ;
