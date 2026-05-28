-- Migration: 20260518_add_email_columns
-- Adds email column to users and students tables (safe on servers where it already exists)

-- users.email
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL AFTER `username`;

-- students.email
ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL;

-- teachers.signature: signature is a base64 PNG data-URL (~100-500 KB)
-- VARCHAR(255) silently truncates it → broken image after save
ALTER TABLE `teachers`
    MODIFY COLUMN `signature` MEDIUMTEXT DEFAULT NULL;

-- Clear any signatures that were silently truncated by the old VARCHAR(255) limit
-- (real signatures are at least 1 KB = 1000+ chars as base64; anything shorter is corrupted)
UPDATE `teachers`
    SET signature = NULL
    WHERE signature IS NOT NULL AND LENGTH(signature) < 1000;
