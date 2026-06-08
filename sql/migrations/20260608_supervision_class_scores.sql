-- ============================================================
-- Migration: 20260608_supervision_class_scores
-- เพิ่มคอลัมน์ class_score_11 ถึง class_score_31 ใน supervision_evaluations
-- ปลอดภัย: ADD COLUMN IF NOT EXISTS (รันซ้ำได้)
-- ============================================================

ALTER TABLE `supervision_evaluations`
  ADD COLUMN IF NOT EXISTS `class_score_11` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_12` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_13` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_14` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_15` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_16` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_17` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_18` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_19` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_20` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_21` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_22` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_23` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_24` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_25` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_26` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_27` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_28` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_29` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_30` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `class_score_31` INT DEFAULT 0;
