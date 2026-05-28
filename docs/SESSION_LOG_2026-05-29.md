# Session Log — 2026-05-29

สรุปงานทั้งหมดที่ทำในวันนี้

---

## A. Deploy ขึ้น Production Server (chainatpit.com)

### A1. 403 Forbidden — ครั้งแรก
**สาเหตุ:** `.htaccess` ใช้ syntax Apache 2.2 (`Order allow,deny` / `Deny from all`)  
Plesk รัน Apache 2.4 ซึ่งต้องใช้ `Require all denied` แทน  
**แก้:** เปลี่ยน `.htaccess` เป็น Apache 2.4 syntax + เพิ่ม block `*.zip` และ `*.sql`

```apache
<Files ".env">
    Require all denied
</Files>
<Files "*.zip">
    Require all denied
</Files>
<Files "*.sql">
    Require all denied
</Files>
```

---

### A2. `.env` บน server มี DB_HOST ซ้ำ 2 อัน
**สาเหตุ:** `.env` มีทั้ง production block และ local XAMPP block  
PHP อ่านค่าสุดท้าย → DB_HOST=127.0.0.1, DB_PASS='' → connect fail  
**แก้:** inject production-only `.env` ใน deploy zip

---

### A3. ไฟล์อันตรายบน server
พบไฟล์ที่ไม่ควรมีบน production:
- `cnp_deploy_clean.zip` — มี `.env` real credentials อยู่ข้างใน
- `scratch/` — 12 debug/admin scripts
- `test.php`, `check_cats.php`, `clean_cats.php`
- `cookies.txt` — มี session token จริง (`cnp_auth`, `PHPSESSID`)

**แก้:** ตัดออกจาก deploy zip ทั้งหมด

---

### A4. 403 Forbidden — Permission Denied บน `.htaccess`
**Error:** `(13)Permission denied: AH00529: unable to check htaccess file`  
**สาเหตุ:** zip ที่สร้างบน Windows ไม่เก็บ Unix permissions → extract แล้วได้ permission ผิด  
**แก้ครั้งแรก:** แก้ใน Plesk File Manager → Change Permissions → 755 (directory) + 644 (ไฟล์)  
**แก้ถาวร:** ฝัง Unix permissions ใน zip ตอนสร้าง

```python
PERM_FILE = 0o644 << 16   # -rw-r--r--
PERM_DIR  = 0o755 << 16   # drwxr-xr-x
item.external_attr = PERM_DIR if is_dir else PERM_FILE
```

---

### A5. PHP Warning — open_basedir restriction
**Error:** `is_dir(): open_basedir restriction in effect. File(/var/lib/php/sessions/cnpapp)`  
**สาเหตุ:** `config.php` ใช้ `ini_get('session.save_path')` ซึ่งชี้ไป `/var/lib/php/sessions` ที่ Plesk ไม่อนุญาต  
Plesk อนุญาตแค่ `/var/www/vhosts/chainatpit.com/:/tmp/`  
**แก้:** เปลี่ยนใช้ `sys_get_temp_dir()` แทน

```php
// เดิม
$_cnp_sess_dir = rtrim(ini_get('session.save_path'), '/\\') . DIRECTORY_SEPARATOR . 'cnpapp';

// ใหม่ — ทำงานได้ทั้ง XAMPP (Windows) และ Plesk (Linux /tmp)
$_cnp_sess_dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cnpapp';
```

---

### A6. "บันทึกข้อมูลไม่สำเร็จ" — Missing Column
**Error log:** `[profile.php] POST SQLSTATE[42S22]: Column not found: 1054 Unknown column 'instagram' in 'SET'`  
**สาเหตุ:** คอลัมน์ `instagram` ไม่มีใน production DB (เพิ่มใน form แต่ไม่มี migration)  
**แก้:** รัน migration ใน phpMyAdmin

```sql
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS `instagram` VARCHAR(100) NULL,
  -- + อีกหลาย column ที่ IF NOT EXISTS (ปลอดภัย รันซ้ำได้)
```

Migration file: `sql/migrations/20260529_add_missing_student_columns.sql`

---

## B. Features ใหม่ใน student_profile.html

### B1. Input HH:MM สำหรับ 3 ช่อง
เปลี่ยนจาก `type="number"` → `type="text"` รูปแบบ `HH:MM`

| Field | เก่า | ใหม่ | เก็บใน DB |
|-------|------|------|-----------|
| `time_spent_together` | `number step=0.5` | `"1:30"` | decimal hours (1.5) |
| `social_media_usage` | `number step=0.5` | `"2:00"` | decimal hours (2.0) |
| `travel_time` | `number` | `"0:45"` | minutes (45) |

- Auto-format: พิมพ์ `130` → แปลงเป็น `1:30` อัตโนมัติ
- Load: convert decimal/minutes → HH:MM แสดงบนหน้าจอ
- Save: convert HH:MM → ตัวเลขก่อนส่ง API

แก้ label `travel_time` จาก "ใช้เวลาอยู่กับครอบครัวต่อวัน (นาที)" → "เวลาเดินทาง (ชม.:นาที)"

---

### B2. COVID Vaccine — เปลี่ยนจาก Select → Radio Buttons
```html
<!-- เดิม -->
<select name="covid_vaccine">...</select>

<!-- ใหม่ -->
<div class="btn-group-modern flex-wrap">
    ยังไม่ได้ฉีด · 1 เข็ม · 2 เข็ม · 3 เข็ม · 4 เข็ม · 5+ เข็ม
</div>
```

---

### B3. ปุ่มเช็คอิน — Distance Check + Reminder
Logic ใหม่หลังกด geolocation:

1. คำนวณระยะห่างจากโรงเรียน (Haversine, school: 15.1878, 100.1245)
2. **≤ 800 ม.** → เตือน "คุณอาจยังอยู่ใกล้โรงเรียน (Xm) — กดอีกครั้งเมื่อกลับบ้าน"
3. **> 800 ม.** → แจ้งสำเร็จ + reminder "กรุณากดอีกครั้งเมื่อกลับถึงบ้าน" (ปิดเอง 6 วิ)
4. ปุ่มเปลี่ยนเป็น 🟢 "เช็คอินแล้ว ✓" ชั่วคราว → กลับเป็นปกติหลัง dismiss

---

## C. Migration Files

| ไฟล์ | รายละเอียด |
|------|-----------|
| `sql/migrations/20260529_add_rel_time_columns.sql` | ADD rel_brothers, rel_sisters, rel_relatives, travel_time |
| `sql/migrations/20260529_add_missing_student_columns.sql` | ADD instagram + อีก 24 column ที่อาจหายไป (IF NOT EXISTS) |

---

## D. Deploy Process ที่ถูกต้อง (สำหรับครั้งถัดไป)

1. สร้าง zip ด้วย Python โดยฝัง Unix permissions: `external_attr = 0o644 << 16` (file) / `0o755 << 16` (dir)
2. inject `.env` production-only (ไม่มี local XAMPP block)
3. ตัดออก: `cookies.txt`, `scratch/`, `docs/`, `CLAUDE.md`, `*.zip`, `test.php`, debug scripts
4. Upload → Extract ทับ `httpdocs/` ได้เลย ไม่ต้องแก้ permissions
5. รัน migration SQL ใน phpMyAdmin ถ้ามีการเพิ่ม column ใหม่

---

## E. ไฟล์ที่แก้ไข

| ไฟล์ | การเปลี่ยนแปลง |
|------|----------------|
| `.htaccess` | Apache 2.4 syntax + block *.zip/*.sql |
| `config.php` | session save path ใช้ `sys_get_temp_dir()` |
| `views/student_profile.html` | HH:MM inputs, COVID radio, check-in 800m |
| `sql/migrations/20260529_add_rel_time_columns.sql` | ใหม่ |
| `sql/migrations/20260529_add_missing_student_columns.sql` | ใหม่ |
