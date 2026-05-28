# Session Log — 2026-05-22

สรุปการแก้ไขและฟีเจอร์ใหม่ทั้งหมดที่เกิดขึ้นในช่วง 2026-05-18 ถึง 2026-05-22  
(รวมทั้งส่วนที่แก้เองและส่วนที่แก้ใน session นี้)

---

## A. ฟีเจอร์ใหม่

### A1. ระบบประชาสัมพันธ์ (Public Relations Module)
**ไฟล์ใหม่:**
- `api/public_relations.php` — CRUD API พร้อม approval workflow (pending → approved/rejected) รองรับ category, visibility (all/teacher/student/admin), การกรองตาม role, และการ approve/reject โดย admin
- `views/public_relations.html` — UI หน้าประชาสัมพันธ์ แสดงบทความ/ประกาศ, แยก tab pending/approved, การสร้างบทความใหม่
- `sql/migrations/20260518_public_relations.sql` — schema ตาราง `public_relations` (id, title, content, author_id, status ENUM, category, visibility, created_at, updated_at)
- `api/notifications.php` — เพิ่ม alert_pr_pending สำหรับ admin เมื่อมีบทความรออนุมัติ

### A2. ระบบสถานะการลงทะเบียนนักเรียน (Enrollment Status)
**ไฟล์ใหม่:**
- `sql/migrations/20260520_student_enrollment_status.sql` — เพิ่ม column `enrollment_status` ENUM('enrolled','transferred','graduated','withdrawn','deceased') DEFAULT 'enrolled' ในตาราง students

**ไฟล์แก้ไข:**
- `api/admin/students.php` — กรองตาม enrollment_status, แสดงสถานะในรายการ
- `api/admin/at_risk_students.php` — exclude withdrawn/transferred ออกจากรายงาน at-risk
- `views/admin_students.html` — dropdown กรองสถานะ, badge สถานะในตาราง

### A3. ลบนักเรียน (Delete Student API)
**ไฟล์ใหม่:**
- `api/admin/delete_student.php` — admin ลบได้ทุกคน, ครูลบได้เฉพาะนักเรียนในห้องที่ตนเป็นที่ปรึกษา, ใช้ advisory room variants ในการตรวจสิทธิ์

### A4. จัดการรูปนักเรียน (Photo Management Utilities)
**ไฟล์ใหม่:**
- `api/admin/fix_photo_db.php` — สแกนรูปในดิสก์ที่ชื่อไฟล์ตรงกับ student_id แล้ว sync เข้า DB (admin only)
- `api/admin/migrate_photos.php` — copy รูปจาก `/cnpapp/public/` ไปยัง `/public/` สำหรับ root deployment
- `api/admin/sync_student_photos.php` — (แก้เพิ่มเติม) admin-only, realpath() validate path, pre-fetch student map, timestamp ใน filename ป้องกัน browser cache

### A5. สรุปจำนวนนักเรียน (Student Count Summary)
**ไฟล์ใหม่:**
- `api/admin/student_count_summary.php` — จำนวนนักเรียนแบ่งตาม class/gender/status (exclude withdrawn), ใช้สำหรับ dashboard widgets

### A6. อัปเดตเลขที่แบบ Bulk (Update Class Numbers)
**ไฟล์ใหม่:**
- `api/admin/update_class_numbers.php` — รับ CSV update `number_in_class` แบบ bulk, auto-detect column headers ("เลขที่", "student_id", etc.)

### A7. Local Font Hosting (TH Sarabun PSK)
**ไฟล์ใหม่:**
- `public/css/fonts.css` — host TH Sarabun PSK (NECTEC authentic) + Sarabun ใน `public/fonts/` แทน Google Fonts เพื่อใช้งาน offline และ print ได้ถูกต้อง
- `public/fonts/` — ไฟล์ font .woff2/.ttf

**ไฟล์แก้ไข (link fonts.css):**
- `views/daily_attendance_print.html`
- `views/student_public_service_print.html`
- `views/teacher_public_service_report.html`
- และไฟล์ print อื่นๆ

### A8. CLAUDE.md (Project Instructions)
**ไฟล์ใหม่:**
- `CLAUDE.md` — คู่มือโปรเจกต์สำหรับ Claude Code: โครงสร้างไฟล์, patterns, common tasks, gotchas

### A9. DESIGN.md (Design System Docs)
**ไฟล์ใหม่:**
- `docs/DESIGN.md` — เอกสาร design system: CSS tokens, color palette, component patterns, house color theming

---

## B. Bug Fixes

### B1. `api/timetable.php` — นักเรียนดูครูที่ปรึกษาไม่เจอ
**ปัญหา:** query `WHERE r.classroom_code = ?` ใช้ค่า studentClass ตรงๆ — ถ้า students.class_name เป็น "601" แต่ rooms.classroom_code เก็บ "6/1" จะ match ไม่ได้  
**แก้:** เปลี่ยนเป็น `WHERE r.classroom_code IN (?,?,...)` โดยใช้ `cnp_classroom_code_variants()` ให้ครอบคลุมทุกรูปแบบ (601 / 6/1 / ม.6/1)

### B2. `api/public_service.php` — คำขอ approver_id NULL หายไป
**ปัญหา:** Teacher default view กรองแค่ `approver_id = user_id` → record ที่ student submit โดยไม่ระบุครู (approver_id IS NULL) ไม่มีครูเห็นเลย  
**แก้:** คืน combined condition `approver_id = ? OR (approver_id IS NULL AND class_name IN advisory_room_variants)` — ครูที่ปรึกษาเห็น record ที่ไม่ได้ระบุครูของห้องตัวเองด้วย

### B3. `api/notifications.php` — Badge count ผิด + admin เห็น alert เกิน
**ปัญหา:** Backend ส่ง alert_ps_pending ให้ admin ด้วย แต่ frontend filter ออก → badge count > จำนวน item จริง  
**แก้:**
- แยก Teacher / Admin เป็น block อิสระ
- Admin รับเฉพาะ alert_pr_pending (ไม่มี alert_ps_pending)
- Teacher: PS count ใช้ advisory room OR logic ตรงกับ fix B2
- คำนวณ `$varsR` ครั้งเดียว ใช้ทั้ง PS count และ homeroom check

### B4. `api/admin/upload-students.php` — Import CSV แก้หลายจุด
- รองรับชื่อ column หลายรูปแบบ ("เลขที่", "ลำดับที่", "no", "number")
- skip-field (ไม่ skip-row) เมื่อ validation fail บาง field
- pre-fetch student map ก่อน loop เพื่อประสิทธิภาพ
- UPDATE by DB id ไม่ใช่ student_id เพื่อป้องกัน duplicate

### B5. `api/admin/departments.php` — ตัด admin ออกจากจำนวนครู
**ปัญหา:** query นับ users ที่มี role=teacher แต่รวม admin ด้วย  
**แก้:** exclude admin roles ออกจาก COUNT

### B6. `api/get_teachers_list.php` — ข้อมูลครูไม่ครบ
**แก้:** exclude admins, เพิ่ม fields: teachers_id, department, classroom, faculty

### B7. `api/teacher/credit_recent.php` — ชื่อครูใน credit log ไม่มี prefix
**แก้:** JOIN teachers (ไม่ใช่ users) เพื่อดึง first_name_th, last_name_th, prefix ครบ

### B8. `api/admin/upload-classes.php` — Room import ผิดชื่อตาราง
**แก้:** `classrooms` → `rooms` (ชื่อตารางที่ถูกต้อง), เพิ่ม dry_run mode ตรวจก่อน import จริง

### B9. `api/admin/students.php` — Filter และ sort เพิ่มเติม
**แก้:** เพิ่ม filter enrollment_status, ปรับ sort order, เพิ่ม total count ใน response

### B10. `views/admin_sysadmins.html` — ข้อมูล hardcode
**แก้:** โหลดจาก API แทนข้อมูลที่ hardcode ไว้ในไฟล์

---

## C. Security & Reliability

### C1. `config.php` — Private Session Directory (แก้ session หลุด)
**ปัญหา:** php.ini ตั้ง gc_maxlifetime=1440 (24 นาที) — phpMyAdmin trigger GC บน shared `C:\xampp\tmp` → ลบ session ของ cnpapp ที่ idle > 24 นาที  
**แก้:** เพิ่ม session save path แยกเป็น `C:\xampp\tmp\cnpapp\` ทำให้ GC ของแอปอื่นแตะ session เราไม่ได้, และ config.php ตั้ง maxlifetime=5184000 (60 วัน) ใช้เฉพาะ directory นี้เสมอ

### C2. `inc/security.php` — Token Rotation Issue-Before-Delete
**ปัญหา:** ลำดับเดิม delete-then-issue — ถ้า INSERT ล้มเหลว token เก่าหายแล้ว ต้อง login ใหม่  
**แก้:** สลับเป็น issue-then-delete — ถ้า issue ล้มเหลว token เดิมยังใช้ได้ ไม่สูญเสีย session

### C3. `api/admin/update_student.php` — Email Domain + Field Whitelist
**แก้:** enforce `@chainatpit.ac.th` domain, เพิ่ม fields ใหม่ใน whitelist (enrollment_status, email, และ fields จาก migration ล่าสุด)

### C4. `api/admin/sync_student_photos.php` — Path Traversal Protection
**แก้:** ใช้ `realpath()` + เช็คว่า path อยู่ใน allowed directory, admin-only endpoint

---

## D. UI / UX Improvements

### D1. `views/academic_calendar.html` — Past Events แสดงจางลง
**Feature:** กิจกรรมที่ผ่านมาแล้วยังแสดงสีแต่ opacity ลดลง  
**CSS:** `.m-past:not(.m-today) { opacity: 0.35; filter: saturate(0.6); }` และ `.holiday-item.is-past { opacity: 0.42; }`  
**ครอบคลุม:** Matrix table, Agenda month grid, Holiday summary list  
**Side fix:** renderMatrix count calculation ใช้ baseClass (ป้องกัน holiday+today ถูกนับเป็นวันเรียน)

### D2. `public/js/main.js` — Notification + Profile UI Redesign
- Notification modal redesign: แสดง icon สี, action alert แยกจาก DB notifications
- Profile dropdown redesign: avatar, role badge, logout button
- เพิ่ม public_relations ใน nav menu ทุก role
- เพิ่ม route handler สำหรับ public_relations.html

### D3. `public/js/importModal.js` — Import CSV UX
- force .csv extension (ไม่รับ .xlsx ตรงๆ — ต้อง convert ก่อน)
- label ชัดเจนขึ้น: "ไฟล์ CSV" แทน "ไฟล์"
- warning เมื่อ detect คอลัมน์ "เลขที่" ที่อาจมีค่าซ้ำ

### D4. `views/admin_public_service.html` — Card → Compact Table
**แก้:** จาก card layout เปลี่ยนเป็น compact table view เพื่อแสดงข้อมูลได้มากขึ้นต่อหน้าจอ

### D5. `views/admin_students.html` — Student Management Upgrades
- เพิ่ม filter enrollment_status dropdown
- badge สถานะในตาราง (ลาออก/ย้าย/จบการศึกษา)
- ปรับ modal ให้รองรับ field ใหม่

### D6. `views/timetable.html` — Timetable View
- เพิ่ม tab Room (ห้องเรียน) นอกจาก Class/Teacher
- cross-view: นักเรียนดูตาราง teacher/room ได้, ครูดูตาราง class/room ได้
- ปรับ UI ให้ responsive

### D7. `views/credit_score_history.html` — Credit History View
- ปรับ layout และ filter ตาม role
- teacher เห็นประวัติของนักเรียนในห้องตัวเอง

### D8. `views/admin_timetable.html` — Admin Timetable Management
- drag-to-assign period, conflict detection
- filter by teacher/class/room

### D9. `views/at_risk_students.html` — At-Risk Student Report
- exclude enrolled_status != 'enrolled'
- เพิ่ม filter และ export

### D10. `views/teacher_supervision.html` — Teacher Supervision View
- แสดงสถิติการดูแลนักเรียน
- ปรับตาม advisory room

### D11. Views แก้ไขรายละเอียด (Font/Style/Minor fixes)
- `views/admin_classes.html` — class list ปรับ UI
- `views/admin_dashboard.html` — widget count สถานะ enrollment
- `views/admin_public_service_stats.html` — stats table
- `views/attendance_daily.html` — เพิ่ม enrollment_status filter
- `views/attendance_subject.html` — subject attendance ปรับ UI
- `views/credit_score_manage.html` — admin manage credit UI
- `views/monthly_stats.html` — monthly report ปรับ chart
- `views/student_credit_history.html` — student view credit log
- `views/student_dashboard.html` — dashboard widget ใหม่
- `views/student_public_service.html` — student PS view
- `views/student_public_service_print.html` — print + local font
- `views/subject_attendance_stats.html` — stats by subject
- `views/teacher_dashboard.html` — teacher home ปรับ widgets
- `views/teacher_public_service.html` — teacher PS approval view
- `views/teacher_public_service_report.html` — report + local font
- `views/today_overview.html` — today's overview widget
- `views/daily_attendance_print.html` — print + local font

### D12. `public/css/style.css` — Style Updates
- CSS token updates (สีใหม่, spacing)
- เพิ่ม utility classes สำหรับ enrollment_status badges
- print media query updates

---

## E. API Improvements

### E1. `api/student/profile.php` — Student Profile API
- รองรับ enrollment_status ใน response
- เพิ่ม field validation สำหรับ student-editable fields

### E2. `api/teacher/attendance.php` — Teacher Attendance API
- เพิ่ม enrollment_status filter: exclude นักเรียนที่ไม่ enrolled ออกจาก attendance list

### E3. `api/admin/teachers.php` — Teacher List API
- เพิ่ม fields: department, faculty, advisory_room
- pagination improvements

### E4. `api/admin/dashboard_stats.php` — Dashboard Stats
- เพิ่ม enrollment_status breakdown ใน student counts
- แยก active vs inactive

### E5. `api/admin/daily_attendance_stats.php` — Daily Attendance Stats
- exclude non-enrolled students จากสถิติ

### E6. `api/admin/detailed-room-report.php` — Room Report
- เพิ่ม enrollment_status filter

### E7. `api/admin/executive_overview.php` — Executive Overview
- เพิ่ม enrollment breakdown สำหรับ exec dashboard

### E8. `api/admin/get_next_teacher_id.php` — Auto Teacher ID
- ปรับ sequence เพื่อหลีกเลี่ยง collision กับ ID ที่มีอยู่แล้ว

### E9. `api/admin/download-template.php` — CSV Template Download
- เพิ่ม column enrollment_status ใน template

---

## F. สรุปไฟล์ทั้งหมด

### ไฟล์ใหม่ (New — Untracked)
| ไฟล์ | ประเภท | คำอธิบาย |
|------|--------|-----------|
| `CLAUDE.md` | Docs | Project instructions สำหรับ Claude Code |
| `api/admin/delete_student.php` | API | ลบนักเรียน (admin/ครูที่ปรึกษา) |
| `api/admin/fix_photo_db.php` | API | Sync รูปดิสก์ → DB |
| `api/admin/migrate_photos.php` | API | Copy รูปสำหรับ deployment |
| `api/admin/student_count_summary.php` | API | สรุปจำนวนนักเรียน |
| `api/admin/update_class_numbers.php` | API | Bulk update เลขที่ CSV |
| `api/public_relations.php` | API | CRUD ประชาสัมพันธ์ + approval |
| `docs/DESIGN.md` | Docs | Design system documentation |
| `docs/SESSION_LOG_2026-05-18.md` | Docs | Session log ก่อนหน้า |
| `docs/SESSION_LOG_2026-05-22.md` | Docs | ไฟล์นี้ |
| `public/css/fonts.css` | CSS | Local font hosting |
| `public/fonts/` | Assets | TH Sarabun PSK + Sarabun font files |
| `sql/migrations/20260518_public_relations.sql` | SQL | Schema public_relations table |
| `sql/migrations/20260520_student_enrollment_status.sql` | SQL | enrollment_status column |
| `views/public_relations.html` | View | หน้าประชาสัมพันธ์ |

### ไฟล์แก้ไข (Modified)
| ไฟล์ | หมวด |
|------|------|
| `config.php` | Security — private session dir |
| `inc/security.php` | Security — token rotation order |
| `api/timetable.php` | Bug fix — classroom code variants |
| `api/public_service.php` | Bug fix — orphaned approver_id |
| `api/notifications.php` | Bug fix — badge count, role separation |
| `api/admin/update_student.php` | Security — field whitelist, email domain |
| `api/admin/upload-students.php` | Bug fix — column variants, skip-field |
| `api/admin/upload-classes.php` | Bug fix — table name, dry_run |
| `api/admin/sync_student_photos.php` | Security — realpath, admin-only |
| `api/admin/students.php` | Feature — enrollment_status filter |
| `api/admin/teachers.php` | Feature — more fields |
| `api/admin/departments.php` | Bug fix — exclude admin from count |
| `api/admin/dashboard_stats.php` | Feature — enrollment breakdown |
| `api/admin/daily_attendance_stats.php` | Feature — exclude non-enrolled |
| `api/admin/detailed-room-report.php` | Feature — enrollment filter |
| `api/admin/executive_overview.php` | Feature — enrollment breakdown |
| `api/admin/download-template.php` | Feature — enrollment_status column |
| `api/admin/get_next_teacher_id.php` | Fix — sequence collision |
| `api/admin/at_risk_students.php` | Fix — exclude non-enrolled |
| `api/get_teachers_list.php` | Fix — exclude admin, more fields |
| `api/student/profile.php` | Feature — enrollment_status |
| `api/teacher/attendance.php` | Feature — enrollment filter |
| `api/teacher/credit_recent.php` | Bug fix — teacher name with prefix |
| `public/css/style.css` | UI — tokens, badges, print |
| `public/js/main.js` | UI — notification, profile dropdown, nav |
| `public/js/importModal.js` | UX — CSV validation, labels |
| `views/academic_calendar.html` | Feature — past events fade |
| `views/admin_classes.html` | UI — minor updates |
| `views/admin_dashboard.html` | Feature — enrollment widgets |
| `views/admin_public_service.html` | UI — card → table view |
| `views/admin_public_service_stats.html` | UI — stats table |
| `views/admin_students.html` | Feature — enrollment filter, badges |
| `views/admin_sysadmins.html` | Fix — dynamic load from API |
| `views/admin_timetable.html` | Feature — drag-assign, conflict detect |
| `views/at_risk_students.html` | Fix — enrollment filter |
| `views/attendance_daily.html` | Feature — enrollment filter |
| `views/attendance_subject.html` | UI — minor updates |
| `views/credit_score_history.html` | UI — role-based view |
| `views/credit_score_manage.html` | UI — admin manage |
| `views/daily_attendance_print.html` | Fix — local font |
| `views/monthly_stats.html` | UI — chart update |
| `views/student_credit_history.html` | UI — student view |
| `views/student_dashboard.html` | Feature — new widgets |
| `views/student_public_service.html` | UI — minor updates |
| `views/student_public_service_print.html` | Fix — local font |
| `views/subject_attendance_stats.html` | UI — minor updates |
| `views/teacher_dashboard.html` | Feature — widgets update |
| `views/teacher_public_service.html` | UI — approval view |
| `views/teacher_public_service_report.html` | Fix — local font |
| `views/teacher_supervision.html` | Feature — supervision stats |
| `views/timetable.html` | Feature — Room tab, cross-view |
| `views/today_overview.html` | UI — overview widget |

---

**รวม:** 15 ไฟล์ใหม่ + 52 ไฟล์แก้ไข = **67 ไฟล์**
