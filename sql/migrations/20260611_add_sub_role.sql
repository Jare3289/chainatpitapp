-- Migration: Add sub_role to users table
-- sub_role = 'supervision' → admin that only sees the supervision system
ALTER TABLE users ADD COLUMN sub_role VARCHAR(50) DEFAULT NULL AFTER role;
