-- Migration: 20260529_add_missing_student_columns
-- เพิ่มคอลัมน์ที่อยู่ใน profile form แต่อาจไม่มีใน DB
-- ใช้ IF NOT EXISTS — ปลอดภัย รันซ้ำได้

ALTER TABLE students
  -- Social media / contact
  ADD COLUMN IF NOT EXISTS `instagram`           VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `facebook`            VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `line_id`             VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `student_id_card`     VARCHAR(20)  NULL COMMENT 'เลขบัตรประชาชนนักเรียน',

  -- Location extras
  ADD COLUMN IF NOT EXISTS `location_landmark`   TEXT         NULL COMMENT 'จุดสังเกตบ้าน',
  ADD COLUMN IF NOT EXISTS `village_headman`     VARCHAR(100) NULL COMMENT 'ผู้ใหญ่บ้าน',
  ADD COLUMN IF NOT EXISTS `subdistrict_headman` VARCHAR(100) NULL COMMENT 'กำนัน',
  ADD COLUMN IF NOT EXISTS `address_status`      VARCHAR(50)  NULL COMMENT 'สถานะที่อยู่ (เหมือนทะเบียนบ้าน/ต่างจากทะเบียนบ้าน)',

  -- Family counts
  ADD COLUMN IF NOT EXISTS `total_family_members`   INT NULL,
  ADD COLUMN IF NOT EXISTS `male_members`            INT NULL,
  ADD COLUMN IF NOT EXISTS `female_members`          INT NULL,
  ADD COLUMN IF NOT EXISTS `full_siblings_male`      INT NULL,
  ADD COLUMN IF NOT EXISTS `full_siblings_female`    INT NULL,
  ADD COLUMN IF NOT EXISTS `half_siblings_male`      INT NULL,
  ADD COLUMN IF NOT EXISTS `half_siblings_female`    INT NULL,

  -- Parent welfare / family status
  ADD COLUMN IF NOT EXISTS `f_welfare`         VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `f_family_status`   VARCHAR(50)  NULL,
  ADD COLUMN IF NOT EXISTS `m_welfare`         VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `m_family_status`   VARCHAR(50)  NULL,
  ADD COLUMN IF NOT EXISTS `guardian_relation` VARCHAR(100) NULL,

  -- Travel / school distance
  ADD COLUMN IF NOT EXISTS `travel_time`  INT          NULL COMMENT 'เวลาเดินทาง (นาที)',

  -- Relationship (ชื่อใหม่)
  ADD COLUMN IF NOT EXISTS `rel_brothers`  VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS `rel_sisters`   VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS `rel_relatives` VARCHAR(50) NULL;
