-- Migration: เพิ่มคอลัมน์ profile_confirmed_at ในตาราง teachers
-- ใช้บังคับให้ครูยืนยันข้อมูลพื้นฐานก่อนใช้งานระบบ
-- รัน: mysql -u root admin_cnpapp < sql/migrations/add_profile_confirmed_at.sql

ALTER TABLE teachers
    ADD COLUMN IF NOT EXISTS profile_confirmed_at DATETIME DEFAULT NULL
        COMMENT 'ครั้งล่าสุดที่ครูกดบันทึกโปรไฟล์ (ต้องทำทุกปีการศึกษา)';

-- รีเซ็ตการยืนยันของครูทุกคน (บังคับยืนยันใหม่):
-- UPDATE teachers SET profile_confirmed_at = NULL;

-- ยืนยันว่าครูบางคนผ่านแล้ว (ถ้าต้องการ):
-- UPDATE teachers SET profile_confirmed_at = NOW() WHERE id IN (1, 2, 3);
