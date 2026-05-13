-- ============================================================
-- Migration 20260510_normalize_schema
-- Goal: เชื่อมโยงตาราง room/grade_level/student ผ่าน FK จริง
--
-- สิ่งที่ทำ:
--   1. ซ่อมข้อมูล orphan ก่อน (admin user role_id NULL)
--   2. DROP table `classes` (0 rows, dead)
--   3. เพิ่มคอลัมน์ FK ใน students/attendance/teachers
--   4. Backfill จาก string match ที่มีอยู่
--   5. เพิ่ม FK constraints ทั้งหมดที่เหลือ
--
-- ปลอดภัย:
--   - เก็บคอลัมน์ string เดิมไว้ (students.room, students.grade_level,
--     attendance.class_name, teachers.classroom) ถ้า rollback ต้อง revert ก็ได้
--   - ใช้ ON DELETE RESTRICT เป็น default เพื่อกัน history หาย
-- ============================================================

START TRANSACTION;

-- ---------- 1. Pre-fix: admin user ที่ role_id NULL ----------
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin')
WHERE role_id IS NULL AND role = 'admin';

COMMIT;

-- ---------- 2. DROP dead table ----------
DROP TABLE IF EXISTS classes;

-- ---------- 3. เพิ่มคอลัมน์ FK ----------
ALTER TABLE students
  ADD COLUMN room_id INT NULL AFTER room,
  ADD COLUMN grade_level_id INT NULL AFTER grade_level,
  ADD INDEX idx_students_room_id (room_id),
  ADD INDEX idx_students_grade_level_id (grade_level_id);

ALTER TABLE attendance
  ADD COLUMN room_id INT NULL AFTER class_name,
  ADD INDEX idx_attendance_room_id (room_id);

ALTER TABLE teachers
  ADD COLUMN advisory_room_id INT NULL AFTER classroom,
  ADD INDEX idx_teachers_advisory_room_id (advisory_room_id);

-- ---------- 4. Backfill ----------
UPDATE students s
JOIN rooms r ON s.room = r.classroom_code
SET s.room_id = r.id
WHERE s.room IS NOT NULL AND s.room <> '';

UPDATE students s
JOIN grade_levels g ON s.grade_level = g.grade_name
SET s.grade_level_id = g.id
WHERE s.grade_level IS NOT NULL AND s.grade_level <> '';

UPDATE attendance a
JOIN rooms r ON a.class_name = r.classroom_code
SET a.room_id = r.id
WHERE a.class_name IS NOT NULL AND a.class_name <> '';

-- teachers.classroom ตอนนี้ว่างหมด ไม่มีอะไรให้ backfill
-- (ค่า advisory_room_id จะกรอกผ่านหน้า admin หลัง migration)

-- ---------- 5. public_service_records.student_id (varchar → int) ----------
-- 0 rows → ALTER TYPE ปลอดภัย
ALTER TABLE public_service_records
  MODIFY COLUMN student_id INT NULL;

-- ---------- 6. FK Constraints ----------

-- Students
ALTER TABLE students
  ADD CONSTRAINT fk_students_room
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_students_grade_level
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Attendance
ALTER TABLE attendance
  ADD CONSTRAINT fk_attendance_room
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_attendance_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Teachers
ALTER TABLE teachers
  ADD CONSTRAINT fk_teachers_advisory_room
    FOREIGN KEY (advisory_room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Rooms (ครูประจำห้อง)
ALTER TABLE rooms
  ADD CONSTRAINT fk_rooms_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Point system
ALTER TABLE point_transactions
  ADD CONSTRAINT fk_pt_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_pt_item
    FOREIGN KEY (item_id) REFERENCES point_items(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE point_items
  ADD CONSTRAINT fk_pi_category
    FOREIGN KEY (category_id) REFERENCES point_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Student evaluations
ALTER TABLE student_evaluations
  ADD CONSTRAINT fk_se_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Notifications
ALTER TABLE notifications
  ADD CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Users → Roles
ALTER TABLE users
  ADD CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Sub-departments
ALTER TABLE sub_departments
  ADD CONSTRAINT fk_subdept_dept
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Executives → Teachers
ALTER TABLE executives
  ADD CONSTRAINT fk_exec_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL ON UPDATE CASCADE;
