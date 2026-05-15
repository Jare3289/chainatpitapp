DELIMITER $$

DROP TRIGGER IF EXISTS rooms_propagate_code$$
DROP TRIGGER IF EXISTS grade_levels_propagate_name$$

CREATE TRIGGER rooms_propagate_code
AFTER UPDATE ON rooms FOR EACH ROW
BEGIN
    IF NEW.classroom_code <> OLD.classroom_code THEN
        UPDATE students   SET class_name = NEW.classroom_code WHERE class_name = OLD.classroom_code;
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
