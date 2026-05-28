# Session Log — 2026-05-27 ถึง 2026-05-28

สรุปงานทั้งหมดที่ทำในช่วง 2 วันนี้ (ต่อเนื่องจาก SESSION_LOG_2026-05-22.md)

---

## A. Views — ปรับปรุง UI / UX

### A1. `views/student_profile.html` — ปรับ 4 จุดหลัก

**① ความสัมพันธ์ในครอบครัว — เปลี่ยนจาก `<select>` → Radio Rating Scale Table**

เดิม: dropdown 6 รายการ (`rel_father`, `rel_mother`, `rel_siblings`, `rel_grandparents`, `rel_guardian`, `rel_others`)  
ใหม่: ตารางแนวนอน 6 แถว × 5 ตัวเลือก (สนิทสนม / เฉย ๆ / ห่างเหิน / ขัดแย้ง / ไม่มี)

- เพิ่ม CSS: `.rel-scale-table`, `.rel-dot`, `.rel-radio-cell`, `.rel-scale-wrap`
- View-mode: `pointer-events: none` + ซ่อน unchecked dot ด้วย `opacity: 0`
- `fetchProfile()` รองรับ radio อยู่แล้ว (`inputs[0].type === 'radio'` → set `r.checked`)
- `FormData` capture เฉพาะ radio ที่ checked — `saveProfile()` ไม่ต้องแก้
- ลบ `rel_*` ออกจาก `populateStaticSelects()` map

**② BMI — เพิ่ม label แปลผล**

เพิ่ม `<span id="bmiLabel">` ข้าง input; `calcBMI()` อัปเดต text + class:
- < 18.5 → ⚠️ ผอม (text-warning)
- 18.5–22.9 → ✅ ปกติ (text-success)
- 23.0–24.9 → ⚡ น้ำหนักเกิน (text-warning)
- 25.0–29.9 → 🔴 อ้วน / ≥30 → 🔴 อ้วนมาก (text-danger)

**③ Bottom Save Bar**

`#bottomSaveBar` (fixed bottom) — แสดงเฉพาะ edit mode; มีปุ่ม "ยกเลิก" + "บันทึกข้อมูล"  
`toggleEditMode(editing)` เพิ่ม: `document.getElementById('bottomSaveBar').style.display = editing ? 'block' : 'none'`

**④ ส่วนการศึกษา — lock ทุก field ยกเว้น `number_in_class`**

---

### A2. `views/daily_attendance_print.html` — Print thead/tfoot

ปัญหา: header ตารางหาย, row สรุปลอยกลางหน้า  
แก้ใน `@media print`:

```css
thead { display: table-header-group; }   /* ซ้ำทุกหน้า */
tfoot { display: table-row-group; }      /* ไหลตาม อยู่ท้ายข้อมูลจริง */
.footer-grid, .signatures-section { page-break-inside: avoid; }
```

ยืนยัน: `#unreportedList` ใช้ JS populate จาก API อยู่แล้ว ไม่ต้องแก้ logic

---

### A3. `views/monthly_stats.html` — Tab รายสัปดาห์ + Export PDF

**Tab Switcher:** รายเดือน | รายสัปดาห์ (toolbar เดียวกัน)

**รายสัปดาห์ (`#weeklySection`):**
- Week Navigator: `← สัปดาห์ก่อน` | label วันที่ | `สัปดาห์ถัดไป →` | `[สัปดาห์นี้]`
- KPI Cards: จำนวนนักเรียน / มา / ขาด-ลา / เฉลี่ย %
- ตารางสรุปรายห้อง

**กลไก (ไม่ต้องมี API ใหม่):**  
Fetch room list จาก monthly overview → `Promise.all` fetch รายละเอียดทุกห้อง → filter วันในสัปดาห์ client-side → aggregate

**อื่น ๆ:**
- `onMonthYearChange()` routing ถูก tab
- `navigateToRoom()` ส่ง `&from=${activeTab}`; `backToOverview()` อ่าน `from` กลับมา
- ปุ่ม Export PDF + print CSS ซ่อน sidebar/tabs

---

### A4. `views/attendance_report.html` — Tab สรุปรายสัปดาห์-เดือน + Export PDF

**Tab ที่ 3 "สรุปรายสัปดาห์-เดือน" (`#pane-summary`):**

- Filter: เดือน, ปี, ห้อง (sync กับ filter หลัก)
- KPI Cards: นักเรียนเฉลี่ย / มา / ขาด / %
- ตารางรายวัน + ตารางรายสัปดาห์ (จัดกลุ่มด้วย Monday key)

**ฟังก์ชันใหม่:** `initSummaryFilters()`, `onSummaryTabActive()`, `syncAllSummarySelects()`, `loadSummaryData()`

---

### A5. `views/admin_public_service_stats.html` — Tab "ครูที่เซ็นอนุมัติ"

**Tab ที่ 4:** ตารางแสดงรายชื่อครู | จำนวนอนุมัติ (+ progress bar % share) | รออนุมัติ | วันล่าสุด

- Search box กรองชื่อครูแบบ real-time (`filterTeacherTable()`)
- `renderTeacherSignoffs(allRecords)` — filter `status === 'approved'`, group by `approver_name`, sort desc
- ดึงจาก `allRecords` ที่โหลดมาแล้ว — ไม่ต้องเรียก API ใหม่
- `approver_name` มาจาก JOIN teachers ใน `api/public_service.php` อยู่แล้ว

---

### A6. `views/student_public_service.html` — CRUD ครบ + Responsive Mobile

**CRUD Buttons บนรายการ (status = 'pending' เท่านั้น):**
- ✏️ แก้ไข (`editActivity(id)`) — โหลดข้อมูลเดิมเข้า modal, เปลี่ยน submit label
- 🗑️ ลบ (`deleteActivity(id)`) — confirm dialog → DELETE API

**Edit flow:**  
`<input type="hidden" name="id" id="req-id">` ใน form; ถ้า `isEdit` ส่ง `data.full_edit = true`  
หลัง save รีเซ็ต form + submit button กลับเป็น "บันทึกและส่ง"

**Responsive Mobile Table:**  
`@media (max-width: 768px)` — `thead: display: none`, `tr` เป็น card block,  
`td::before { content: attr(data-label) }` แสดง label ก่อนค่า

---

### A7. `views/teacher_public_service.html` — Batch Delete + Detail Modal

**Batch Delete:**
- Checkbox ทุกแถว + "เลือกทั้งหมด"
- `batchDelete()` — confirm → DELETE API พร้อมกัน (Promise.all)
- `btn-batch-delete`, `btn-batch-clear` CSS

**Detail Modal:**
- คลิกแถว → `detailModal` แสดงรายละเอียดครบ
- ปุ่ม รับรอง / ปฏิเสธ ใน modal (ไม่ต้อง expand inline)

**ลบรายการเดี่ยว:** `deleteRecord(id, e)` — ลบและ update UI ทันทีโดยไม่ต้อง reload

**Table design ใหม่:** `ps-table` (border-collapse:separate, row spacing 5px) แทนตารางเดิม  
`renderRow(r)` — generate แถวพร้อม status badge (✅ รับรอง / ❌ ปฏิเสธ / ⏳ รอรับรอง)

---

### A8. `views/credit_score_history.html` — Redesign ใหม่ทั้งหน้า

เดิม: layout เก่า ไม่มี filter  
ใหม่:

**Filter Bar:**
- `filterType` — ประเภทคะแนน (ทั้งหมด / เพิ่ม / ลด)
- `filterDate` — ช่วงเวลา (ทั้งหมด / เดือนนี้ / 3 เดือน / 6 เดือน)
- `applyFilters()` — filter `behaviorData` client-side

**History Table (`.history-table`):**  
`initBehaviorSelect()` โหลด behavior categories → `loadHistory()` ดึงประวัติ → `applyFilters()` render

**Edit Modal (`.modal-premium`):**  
Admin แก้ไขรายการ credit ได้ → PUT API → อัปเดต table ทันที

**Visual:**  
glass-effect filter bar, table hover highlight, modal-premium (border-radius: 20px)

---

### A9. `views/admin_students.html` — Delete, Count Summary, Pagination

**ปุ่มลบนักเรียน:**  
`deleteStudent(id)` — SweetAlert confirm → DELETE `api/admin/delete_student.php` → ลบออกจาก `allStudents[]` และ re-render

**Enrollment Status Badge:**  
แสดง badge สีแดงถ้า `enrollment_status !== 'กำลังศึกษา'` ใน student card

**Count Summary (`loadCountSummary()`):**  
`buildCard(lv)` สร้าง card ต่อระดับชั้น แสดงจำนวนนักเรียนแต่ละห้อง + status

**Update Class Numbers Modal (`openUpdateClassNumbers()`):**  
Inline modal สำหรับ bulk upload CSV เลขที่; `doUpdateClassNumbers()` ส่งไป `api/admin/update_class_numbers.php`

**Pagination:** `renderPaginationControls(totalItems, totalPages)` + `changePage(page)`

---

## B. API ที่แก้ไข

### B1. `api/student/profile.php` — Photo Upload + Editable Whitelist

**Photo Upload:**
```php
if (!empty($data['photo_base64'])) {
    // decode base64 → save to public/img/profiles/
    $data['photo'] = 'public/img/profiles/' . $fname;
}
```

**Student-Editable Fields Whitelist:**  
เพิ่ม `$allowed` array — นักเรียนแก้ได้เฉพาะ: `photo`, `prefix`, `first_name_th`, `last_name_th`, `full_name_th`, `time_spent_together`, `allowance_source`, `allowance_per_day`, `rel_father`, `rel_mother`, `rel_siblings`, `rel_grandparents`, `rel_guardian`, `rel_others`, ... (personal + health fields)

**อื่น ๆ:**
- `Content-Type: application/json; charset=utf-8`
- เอา `cnp_csrf_verify()` ออกจาก GET (GET ไม่ต้องมี CSRF)
- `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE` ทุก response

---

### B2. `api/admin/delete_student.php` — Cascade Delete ครบทุกตาราง

ปัญหาเดิม: FK RESTRICT ทำให้ error ถ้านักเรียนมีประวัติ  
แก้ใหม่ — ลำดับการลบใน 1 transaction:

| ขั้น | ตาราง | หมายเหตุ |
|------|--------|----------|
| 1 | `attendance` | FK RESTRICT |
| 2 | `attendance_subjects` | FK RESTRICT (try/catch ถ้าไม่มีตาราง) |
| 3 | `point_transactions` | FK RESTRICT |
| 4 | `student_evaluations` | FK RESTRICT (try/catch) |
| 5 | `public_service_records` | ไม่มี FK แต่ต้องทำความสะอาด |
| 6 | `notifications` | ON DELETE CASCADE (ลบล่วงหน้า) |
| 7 | `auth_tokens` | ON DELETE CASCADE (ลบล่วงหน้า) |
| 8 | ไฟล์รูปภาพ | `basename()` + `realpath()` ป้องกัน path traversal |
| 9 | `students` | แถวหลัก |
| 10 | `users` | user account |

เพิ่ม `s.photo` ใน SELECT query เพื่อดึง path รูปก่อนลบ

---

## C. ไฟล์วางแผน / เอกสาร

### C1. `docs/flutter_migration_plan.md` — แผน Flutter Migration

เอกสารแผนการย้าย frontend จาก PHP/HTML → Flutter (multi-platform: Web + Android + iOS)

**สรุปสาระสำคัญ:**

| หัวข้อ | เนื้อหา |
|--------|---------|
| Architecture | Flutter frontend → PHP Backend (เดิม) → MySQL |
| CORS | เพิ่ม `Access-Control-Allow-Origin` headers ใน API |
| Auth | ใช้ `dio_cookie_manager` จัดการ session cookie หรืออัปเกรดเป็น JWT |
| CSRF | Flutter ดึง token จาก `api/csrf.php` แนบ `X-CSRF-Token` header ใน POST/DELETE |
| Facial Recognition | แนวทาง 1: Cloud AI (AWS Rekognition, Google Vision) / แนวทาง 2: Python + OpenCV บน server |
| iOS Build | ใช้เครื่อง Mac เพื่อน หรือ Codemagic/GitHub Actions CI (500 นาที/เดือน ฟรี) |
| งบประมาณปีแรก | ~7,000–9,000 บาท (domain + server + Google Play $25 + Apple Dev $99/ปี) |

---

## D. สรุปไฟล์ทั้งหมด (27-28 พ.ค. 2569)

### ไฟล์แก้ไข (Modified)
| ไฟล์ | การเปลี่ยนแปลงหลัก |
|------|-------------------|
| `views/student_profile.html` | Radio rating scale, BMI label, bottom save bar, education lock |
| `views/daily_attendance_print.html` | Print thead/tfoot CSS |
| `views/monthly_stats.html` | Weekly tab, PDF export |
| `views/attendance_report.html` | Weekly-monthly summary tab, PDF export |
| `views/admin_public_service_stats.html` | Teacher sign-off tab |
| `views/student_public_service.html` | CRUD edit/delete, mobile responsive |
| `views/teacher_public_service.html` | Batch delete, detail modal, ps-table |
| `views/credit_score_history.html` | Redesign: filter bar, history table, edit modal |
| `views/admin_students.html` | Delete button, count summary, pagination |
| `api/student/profile.php` | Photo upload (base64), editable whitelist, charset fix |
| `api/admin/delete_student.php` | Cascade delete ทุกตาราง + รูปภาพ |

### ไฟล์ใหม่ (Untracked)
| ไฟล์ | คำอธิบาย |
|------|----------|
| `docs/flutter_migration_plan.md` | แผน Flutter migration + facial recognition + cost estimate |
| `docs/SESSION_LOG_2026-05-28.md` | ไฟล์นี้ |

**รวม: 11 ไฟล์แก้ไข + 2 ไฟล์ใหม่ = 13 ไฟล์**

---

## E. Design Decisions

| เรื่อง | การตัดสินใจ |
|--------|-------------|
| Weekly summary API | ใช้ endpoint เดิม + `Promise.all` ไม่ต้องสร้าง API ใหม่ |
| Radio rel-scale view mode | CSS `opacity:0` ซ่อน dot แทนการ render HTML 2 ชุด |
| Photo upload นักเรียน | base64 ใน JSON body (ไม่ใช้ multipart) เพราะ student API รับ JSON อยู่แล้ว |
| Cascade delete order | ลบ FK RESTRICT ก่อนเสมอ; CASCADE ลบล่วงหน้าเพื่อความชัดเจน |
| Photo delete timing | ลบไฟล์ก่อน DB commit — acceptable tradeoff (photo re-upload ได้) |
| flutter_migration_plan | เอกสารวางแผนล่วงหน้า ไม่ใช่ implementation จริง — backend PHP ยังคงใช้ต่อ |
