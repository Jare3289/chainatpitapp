-- Clear all student nicknames
-- Run this in phpMyAdmin or mysql CLI
-- Date: 2026-05-15

UPDATE students SET nickname = NULL WHERE nickname IS NOT NULL AND nickname != '';

-- Verify result:
-- SELECT COUNT(*) FROM students WHERE nickname IS NOT NULL AND nickname != '';
