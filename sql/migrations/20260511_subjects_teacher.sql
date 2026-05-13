-- ============================================================
-- Migration 20260511_subjects_teacher
-- Ensure subjects table has teacher_id column (idempotent — safe to re-run)
-- ============================================================

SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subjects'
      AND COLUMN_NAME = 'teacher_id'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE subjects ADD COLUMN teacher_id INT NULL, ADD INDEX idx_subjects_teacher (teacher_id)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
