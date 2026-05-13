-- ============================================================
--  ปฏิทินการศึกษา - SQLite Schema (cnpapp)
--  ระบบนี้ใช้ SQLite ผ่าน database.js
--  หากต้องการใช้กับ MySQL/phpMyAdmin ให้ใช้ไฟล์นี้เป็นแนวทาง
-- ============================================================

-- [SQLite] ตารางนี้ถูกสร้างอัตโนมัติใน database.js แล้ว
-- แต่หากต้องการรีเซ็ตข้อมูลให้รันคำสั่งด้านล่างใน SQLite Browser หรือผ่าน API /api/admin/reset-calendar

-- 1. ลบข้อมูลเก่า (ถ้ามี)
DELETE FROM academic_calendar WHERE academic_year = '2569';
DELETE FROM holidays;

-- 2. ข้อมูลภาคเรียน ปีการศึกษา 2569
--    (2026 AD = 2569 BE / 2027 AD = 2570 BE)
INSERT INTO academic_calendar (academic_year, semester, start_date, end_date) VALUES
    ('2569', 1, '2026-05-18', '2026-10-13'),
    ('2569', 2, '2026-11-02', '2027-03-31');

-- 3. วันหยุดราชการปี 2569 (ค.ศ. 2026–2027)
INSERT INTO holidays (date, name, type) VALUES
    ('2026-05-01', 'วันแรงงานแห่งชาติ',                             'holiday'),
    ('2026-05-13', 'วันวิสาขบูชา',                                  'holiday'),
    ('2026-05-22', 'วันหยุดชดเชยวันวิสาขบูชา',                       'holiday'),
    ('2026-06-03', 'วันเฉลิมพระชนมพรรษาฯ พระบรมราชินี',      'holiday'),
    ('2026-07-28', 'วันเฉลิมพระชนมพรรษา ร.10',                  'holiday'),
    ('2026-07-29', 'วันอาสาฬหบูชา',                                  'holiday'),
    ('2026-07-30', 'วันเข้าพรรษา',                                   'holiday'),
    ('2026-08-12', 'วันแม่แห่งชาติ',                                 'holiday'),
    ('2026-10-13', 'วันนวมินทรมหาราช',                               'holiday'),
    ('2026-10-23', 'วันปิยมหาราช',                                   'holiday'),
    ('2026-12-05', 'วันพ่อแห่งชาติ',                                 'holiday'),
    ('2026-12-07', 'วันหยุดชดเชยวันพ่อแห่งชาติ',                    'holiday'),
    ('2026-12-10', 'วันรัฐธรรมนูญ',                                  'holiday'),
    ('2026-12-31', 'วันสิ้นปี',                                      'holiday'),
    ('2027-01-01', 'วันขึ้นปีใหม่',                                  'holiday'),
    ('2027-02-11', 'วันมาฆบูชา',                                     'holiday'),
    ('2027-02-12', 'วันหยุดชดเชยวันมาฆบูชา',                        'holiday');

-- ============================================================
--  หากต้องการซิงค์ข้อมูลผ่าน API ให้ไปที่หน้า admin_calendar
--  แล้วกดปุ่ม "ซิงค์ค่าเริ่มต้น 2569" แทนการรัน SQL โดยตรง
-- ============================================================

-- ─── MySQL Version (สำหรับ phpMyAdmin บน cnpapp database) ───
-- USE cnpapp;
-- 
-- CREATE TABLE IF NOT EXISTS `academic_calendar` (
--   `id`            INT AUTO_INCREMENT PRIMARY KEY,
--   `academic_year` VARCHAR(4)  NOT NULL COMMENT 'ปีการศึกษา พ.ศ.',
--   `semester`      TINYINT(1)  NOT NULL COMMENT '1 หรือ 2',
--   `start_date`    DATE        NOT NULL,
--   `end_date`      DATE        NOT NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- CREATE TABLE IF NOT EXISTS `holidays` (
--   `id`   INT AUTO_INCREMENT PRIMARY KEY,
--   `date` DATE         NOT NULL UNIQUE,
--   `name` VARCHAR(255) NOT NULL,
--   `type` ENUM('holiday','special','compensatory') NOT NULL DEFAULT 'holiday'
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
