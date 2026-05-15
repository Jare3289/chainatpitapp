-- ============================================================
-- Migration 20260510_normalize_schema  (patched for production)
-- ============================================================

-- ---------- 1. Pre-fix: admin user ที่ role_id NULL ----------
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin')
WHERE role_id IS NULL AND role = 'admin';

-- ---------- 2. DROP dead table ----------
DROP TABLE IF EXISTS classes;

-- ---------- 3. เพิ่มคอลัมน์ FK (IF NOT EXISTS — ปลอดภัยถ้ารันซ้ำ) ----------
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS room_id INT NULL,
  ADD COLUMN IF NOT EXISTS grade_level_id INT NULL;

ALTER TABLE attendance
  ADD COLUMN IF NOT EXISTS room_id INT NULL;

ALTER TABLE teachers
  ADD COLUMN IF NOT EXISTS advisory_room_id INT NULL;

-- Index (ignore error ถ้ามีอยู่แล้ว)
ALTER TABLE students
  ADD INDEX IF NOT EXISTS idx_students_room_id (room_id),
  ADD INDEX IF NOT EXISTS idx_students_grade_level_id (grade_level_id);

ALTER TABLE attendance
  ADD INDEX IF NOT EXISTS idx_attendance_room_id (room_id);

ALTER TABLE teachers
  ADD INDEX IF NOT EXISTS idx_teachers_advisory_room_id (advisory_room_id);

-- ---------- 4. Backfill (ใช้ class_name แทน room) ----------
UPDATE students s
JOIN rooms r ON s.class_name = r.classroom_code
SET s.room_id = r.id
WHERE s.class_name IS NOT NULL AND s.class_name <> '';

UPDATE students s
JOIN grade_levels g ON s.grade_level = g.grade_name
SET s.grade_level_id = g.id
WHERE s.grade_level IS NOT NULL AND s.grade_level <> '';

UPDATE attendance a
JOIN rooms r ON a.class_name = r.classroom_code
SET a.room_id = r.id
WHERE a.class_name IS NOT NULL AND a.class_name <> '';

-- ---------- 5. public_service_records.student_id ----------
ALTER TABLE public_service_records
  MODIFY COLUMN student_id INT NULL;

-- ---------- 6. FK Constraints (drop ก่อนถ้ามีอยู่แล้ว เพื่อให้รันซ้ำได้) ----------

ALTER TABLE students
  DROP FOREIGN KEY IF EXISTS fk_students_room,
  DROP FOREIGN KEY IF EXISTS fk_students_grade_level;
ALTER TABLE students
  ADD CONSTRAINT fk_students_room
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_students_grade_level
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE attendance
  DROP FOREIGN KEY IF EXISTS fk_attendance_room,
  DROP FOREIGN KEY IF EXISTS fk_attendance_student;
ALTER TABLE attendance
  ADD CONSTRAINT fk_attendance_room
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_attendance_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE teachers
  DROP FOREIGN KEY IF EXISTS fk_teachers_advisory_room;
ALTER TABLE teachers
  ADD CONSTRAINT fk_teachers_advisory_room
    FOREIGN KEY (advisory_room_id) REFERENCES rooms(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE rooms
  DROP FOREIGN KEY IF EXISTS fk_rooms_teacher;
ALTER TABLE rooms
  ADD CONSTRAINT fk_rooms_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE point_transactions
  DROP FOREIGN KEY IF EXISTS fk_pt_student,
  DROP FOREIGN KEY IF EXISTS fk_pt_item;
ALTER TABLE point_transactions
  ADD CONSTRAINT fk_pt_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_pt_item
    FOREIGN KEY (item_id) REFERENCES point_items(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE point_items
  DROP FOREIGN KEY IF EXISTS fk_pi_category;
ALTER TABLE point_items
  ADD CONSTRAINT fk_pi_category
    FOREIGN KEY (category_id) REFERENCES point_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE student_evaluations
  DROP FOREIGN KEY IF EXISTS fk_se_student;
ALTER TABLE student_evaluations
  ADD CONSTRAINT fk_se_student
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE notifications
  DROP FOREIGN KEY IF EXISTS fk_notif_user;
ALTER TABLE notifications
  ADD CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE users
  DROP FOREIGN KEY IF EXISTS fk_users_role;
ALTER TABLE users
  ADD CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE sub_departments
  DROP FOREIGN KEY IF EXISTS fk_subdept_dept;
ALTER TABLE sub_departments
  ADD CONSTRAINT fk_subdept_dept
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE executives
  DROP FOREIGN KEY IF EXISTS fk_exec_teacher;
ALTER TABLE executives
  ADD CONSTRAINT fk_exec_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL ON UPDATE CASCADE;
