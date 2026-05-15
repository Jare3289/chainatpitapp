-- ============================================================
-- One-time repair: timetable import เข้าผิดลำดับคอลัมน์
--
-- ก่อนแก้ (ผิด):
--   grade_level   = "101" / "1/1"   (จริงๆ ควรเป็นรหัสห้อง)
--   class_name    = ข้อความอาคาร    (จริงๆ ควรเป็นรหัสห้อง)
--   room_location = ว่าง            (จริงๆ ควรเป็นชื่ออาคาร)
--
-- หลังแก้:
--   grade_level   = "ม.1" - "ม.6"
--   class_name    = "101" - "699"  (รหัสห้อง 3 หลัก)
--   room_location = ข้อความอาคาร
--
-- ⚠️ Backup ก่อนรัน — แท็บ Export > Quick > เลือกตาราง timetable
-- ============================================================

START TRANSACTION;

-- ----- 1. สลับคอลัมน์: room_location ← class_name, class_name ← grade_level
UPDATE timetable
SET
    room_location = NULLIF(TRIM(class_name), ''),
    class_name    = NULLIF(TRIM(grade_level), ''),
    grade_level   = NULL
WHERE
    grade_level IS NOT NULL
    AND TRIM(grade_level) <> ''
    AND (
        grade_level REGEXP '^[1-6][0-9]{2}$'
        OR grade_level REGEXP '^[1-6]/[0-9]+$'
        OR grade_level REGEXP '^ม\\.[1-6]/[0-9]+$'
    );

-- ----- 2. แปลง class_name รูปแบบ "1/1" → "101", "ม.1/1" → "101"
UPDATE timetable
SET class_name = CONCAT(
        SUBSTRING_INDEX(REPLACE(class_name, 'ม.', ''), '/', 1),
        LPAD(SUBSTRING_INDEX(class_name, '/', -1), 2, '0')
    )
WHERE class_name REGEXP '^(ม\\.)?[1-6]/[0-9]+$';

-- ----- 3. เติม grade_level จาก class_name (ตัวแรกของรหัส 3 หลัก = ระดับชั้น)
UPDATE timetable
SET grade_level = CONCAT('ม.', LEFT(class_name, 1))
WHERE class_name REGEXP '^[1-6][0-9]{2}$'
  AND (grade_level IS NULL OR grade_level = '');

COMMIT;

-- ----- ตรวจผลลัพธ์ -----
-- SELECT grade_level, class_name, room_location, COUNT(*) AS rows
-- FROM timetable
-- GROUP BY grade_level, class_name, room_location
-- ORDER BY class_name LIMIT 30;
