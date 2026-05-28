-- Migration: 20260529_add_rel_time_columns
-- เพิ่มคอลัมน์ความสัมพันธ์ครอบครัวชื่อใหม่ + เวลาเดินทาง
-- (ชื่อเดิม: rel_siblings, rel_guardian, rel_others → ชื่อใหม่: rel_brothers, rel_sisters, rel_relatives)

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS `rel_brothers`  VARCHAR(50) NULL COMMENT 'ความสัมพันธ์กับพี่/น้องชาย',
  ADD COLUMN IF NOT EXISTS `rel_sisters`   VARCHAR(50) NULL COMMENT 'ความสัมพันธ์กับพี่/น้องสาว',
  ADD COLUMN IF NOT EXISTS `rel_relatives` VARCHAR(50) NULL COMMENT 'ความสัมพันธ์กับญาติพี่น้อง',
  ADD COLUMN IF NOT EXISTS `travel_time`   INT         NULL COMMENT 'เวลาเดินทาง (นาที)';
