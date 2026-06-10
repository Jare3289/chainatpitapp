# Session Log — 2026-06-10

## สรุปการแก้ไข

### 1. `api/admin/subjects.php` — แก้ query แสดงรายวิชา

**ปัญหา:** วิชาทั้งหมดแสดงสีเทา (opacity 0.45) หมดเลย เพราะ `in_timetable` = 0 ทุกวิชา

**สาเหตุ:** `timetable.subject_name` เก็บ **รหัสวิชา** (เช่น `อ32101`) ไม่ใช่ชื่อวิชาภาษาไทย แต่ query เดิม JOIN ด้วย `subjects.subject_name` ซึ่งคนละค่ากัน

**แก้ไข (GET query):**
- เปลี่ยน JOIN condition เป็น `tbl.tbl_code = s.subject_code` (เทียบ timetable code กับ subjects code)
- เพิ่ม COALESCE fallback ครูจาก timetable ถ้า subjects.teacher_id ว่าง
- เพิ่ม `in_timetable` flag (1/0)

**แก้ไข (sync_from_timetable):**
- เปลี่ยน JOIN ให้ตรง `s.subject_code = tt.tbl_code`

---

### 2. `api/timetable.php` — แก้ query หลัก + เพิ่ม endpoints

**ปัญหา:** ชื่อวิชาและรหัสวิชาสลับกัน เพราะ `timetable.subject_code` เป็น NULL ทั้งหมด (4303 rows)

**แก้ไข:**
- เพิ่ม `LEFT JOIN subjects sub ON sub.subject_code = t.subject_name`
- `subject_name` ตอนนี้ return ชื่อภาษาไทย (เช่น "ภาษาอังกฤษ 3")
- `subject_code` ตอนนี้ return รหัส (เช่น "อ32101")

**เพิ่ม endpoints:**
- `?get_classes=1` — คืนรายชื่อห้องทั้งหมด (ใช้ใน swap simulation)
- `?get_teachers=1` — คืนรายชื่อครูทั้งหมดในตาราง (ใช้ใน swap simulation)

---

### 3. `views/timetable.html` — แก้หลายจุด

**a) Subject display mode toggle:**
- ย้าย toggle ขึ้นมาบนสุดของ `paneTimetable` (เดิมอยู่ล่างสุด)
- แก้ mode `code`: ถ้าไม่มีรหัสวิชา → fallback แสดงชื่อวิชาแทน (ไม่แสดงช่องว่าง)
- แก้ทั้ง desktop และ mobile rendering

**b) Swap simulation redesign:**
- ออกแบบใหม่เป็น layout บน-ล่าง (เดิม ซ้าย-ขวา)
- เปลี่ยนจาก text input เป็น `<select>` dropdown
- ฝั่งที่ 1 / ฝั่งที่ 2 แต่ละฝั่งเลือกได้: ตารางตัวเอง / ตารางเรียน / ตารางสอนครูอื่น
- JS: `ensureSwapCaches()`, `populateSwapSelect()`, `onSwapModeChange()`, `onSwapSelectChange()`, `loadSwapSelf()`

**c) LOCKED_SLOTS (คาบ 8 วันพฤหัส):**
- เพิ่มตรวจสอบ `isTeacherSelf` ใน `buildMap`
- ถ้าครูดูตารางตัวเอง → ไม่ inject slot ลูกเสือเนตรนารี (แสดงตามที่สอนจริง)
- ถ้าดู view แบบห้องเรียน/นักเรียน → ยังคง inject ตามเดิม

---

### 4. `views/admin_subjects.html` — renderTable

- วิชาที่ `in_timetable = 0` แสดง opacity 0.45 พร้อม badge "ไม่มีในตาราง"
- วิชาที่อยู่ในตาราง แต่ยังไม่ระบุครู → แสดง "⚠ ยังไม่ระบุครู" (สีเหลือง)
- วิชาปกติ → แสดงชื่อครูปกติ

---

### 5. `views/admin_students.html` — แก้ 2 จุด

**a) ID card validation:**
- ลบ `pattern="\d{13}" title="กรุณากรอกตัวเลข 13 หลัก"` ออก
- ใช้ `maxlength="13" inputmode="numeric"` แทน (ไม่มี browser popup รบกวน)

**b) เพศกำเนิด auto-sync:**
- เพิ่ม `onchange="syncPrefixToBirthSex(this)"` ที่ select คำนำหน้า
- ฟังก์ชัน `syncPrefixToBirthSex()` map:
  - นาย / เด็กชาย / ด.ช. → ชาย
  - นางสาว / นาง / เด็กหญิง / ด.ญ. / น.ส. → หญิง

---

### 6. Database — แก้ชื่อครู

```sql
UPDATE teachers SET first_name_th = 'เพ็ญณิการ์' 
WHERE first_name_th = 'เพ็ญนิการ์' AND last_name_th = 'ยอดเกิด';
-- 1 row updated
```

---

## ไฟล์ที่แก้ในแชตนี้

| ไฟล์ | การเปลี่ยนแปลง |
|------|---------------|
| `api/timetable.php` | subjects JOIN, get_classes, get_teachers endpoints |
| `api/admin/subjects.php` | in_timetable flag, sync query fix |
| `views/timetable.html` | toggle, swap redesign, LOCKED_SLOTS fix, subject display |
| `views/admin_subjects.html` | renderTable: opacity + badge |
| `views/admin_students.html` | id_card validation, prefix→birth_sex sync |

---

## แก้เพิ่มหลัง deploy ขึ้น server

### A. `api/timetable.php` — dual JOIN (server/local ข้อมูลต่างกัน)

**ปัญหา:** server เก็บชื่อภาษาไทยใน `timetable.subject_name` (ต่างจาก local ที่เก็บรหัส) → JOIN แบบเดิมไม่ตรง → ได้แต่ชื่อ ไม่มีรหัสวิชา

**แก้ไข:** เปลี่ยนจาก JOIN เดียวเป็นสอง LEFT JOIN:
- `sc` — JOIN by code: `sc.subject_code = t.subject_name`
- `sn` — JOIN by name: `sn.subject_name = t.subject_name AND sc.id IS NULL`

```sql
COALESCE(sc.subject_name, sn.subject_name, t.subject_name) AS subject_name,
COALESCE(sc.subject_code, sn.subject_code)                 AS subject_code
```

รองรับทั้งสองรูปแบบ ไม่ว่าจะ import ข้อมูลแบบไหน

---

### B. `api/admin/subjects.php` — dual JOIN + sync fix

**ปัญหา 1:** `in_timetable` = 0 ทุกวิชาบน server เหมือนกัน → วิชาขึ้นสีเทาหมด

**แก้ไข GET query:** เปลี่ยน JOIN condition เป็น `OR`:
```sql
tbl ON tbl.tbl_key = s.subject_code OR tbl.tbl_key = s.subject_name
```

**ปัญหา 2:** ปุ่มซิงค์ครูขึ้น "ระบบขัดข้องชั่วคราว"

**สาเหตุ:** OR ใน JOIN ของ UPDATE ทำให้ subject row หนึ่งอาจ match timetable สองแถว → MySQL สับสน → PDOException

**แก้ไข sync query:** ให้ subquery group by `s2.id` ก่อน เพื่อให้ผลออกมา 1 row ต่อวิชาเสมอ:
```sql
JOIN (
    SELECT s2.id AS sid, MIN(tc.user_id) AS teacher_user_id
    FROM subjects s2
    JOIN timetable t ON (t.subject_name = s2.subject_code OR t.subject_name = s2.subject_name)
    JOIN teachers tc ON tc.id = t.teacher_id
    ...
    GROUP BY s2.id
) tt ON s.id = tt.sid
```

---

## หมายเหตุ

- `timetable.subject_name` อาจเก็บรหัส (local) หรือชื่อภาษาไทย (server) ขึ้นอยู่กับวิธี import — ทุก query ที่ JOIN ตารางนี้ต้องรองรับทั้งสองแบบ
- `timetable.subject_code` เป็น NULL ทั้งหมดใน local — ไม่ต้องไปใช้
- ครู เพ็ญณิการ์ ยอดเกิด (teachers.id = 181) ยังไม่มีข้อมูลตารางสอน ต้องให้ admin กรอกเพิ่ม
- แก้ชื่อครู SQL ต้องรันบน **server DB** ด้วย ไม่ใช่แค่ local
