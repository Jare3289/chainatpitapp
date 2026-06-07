# 📦 คู่มือการอัปโหลดขึ้น Server — CNP APP

> **Patch:** `cnp_patch_20260605.zip`  
> **วันที่:** 5 มิถุนายน 2568  
> **ขนาดไฟล์:** ~93 KB

---

## 🗂️ ไฟล์ที่รวมอยู่ใน Patch นี้

| ไฟล์ | การเปลี่ยนแปลง |
|------|--------------|
| `api/public_relations.php` | รองรับ upload รูปภาพ (multipart), ลบไฟล์รูปเมื่อลบโพสต์ |
| `views/public_relations.html` | UI อัปโหลดรูป drag-drop, thumbnail ในการ์ด, hero image ใน modal |
| `views/teacher_profile.html` | แจ้งเตือนและบังคับอัปเดตโปรไฟล์ช่วงนิเทศการสอน |
| `public/js/main.js` | ล็อก Sidebar สำหรับครูที่โปรไฟล์ไม่ครบ |
| `api/me.php` | อัปเดต session ข้อมูลผู้ใช้ |
| `api/notifications.php` | ระบบแจ้งเตือน |
| `api/admin/organize_students.php` | จัดการนักเรียน |
| `api/admin/update_student.php` | อัปเดตข้อมูลนักเรียน |
| `api/admin/upload-students.php` | นำเข้านักเรียน |
| `views/attendance_daily.html` | บันทึกการเข้าแถว |
| `views/attendance_subject.html` | บันทึกการเข้าเรียน |
| `.htaccess` | Cache-busting headers สำหรับ JS/CSS |
| `public/uploads/pr_images/` | 📁 โฟลเดอร์ใหม่สำหรับเก็บรูปข่าวประชาสัมพันธ์ |

---

## 🚀 วิธีอัปโหลดขึ้น Server

### วิธีที่ 1 — ผ่าน Plesk File Manager (แนะนำ)

1. **เข้า Plesk Panel** → `Files` → ไปที่โฟลเดอร์ root ของเว็บ (เช่น `httpdocs/` หรือ `public_html/`)
2. คลิก **Upload** แล้วเลือกไฟล์ `cnp_patch_20260605.zip`
3. หลัง upload สำเร็จ → คลิกขวาที่ไฟล์ ZIP → **Extract** → เลือก Extract ทับโฟลเดอร์ปัจจุบัน
4. ยืนยันการทับไฟล์เดิม (Overwrite existing files)
5. ลบไฟล์ `cnp_patch_20260605.zip` ออกจาก server เมื่อเสร็จแล้ว

---

### วิธีที่ 2 — ผ่าน FTP/SFTP (FileZilla)

1. เปิด **FileZilla** → เชื่อมต่อ server ด้วย Host, Username, Password, Port
2. ฝั่ง Local (ซ้าย): ไปที่ `d:\chainatpitapp\`
3. ฝั่ง Remote (ขวา): ไปที่ root ของเว็บ (เช่น `/httpdocs/` หรือ `/public_html/`)
4. อัปโหลดไฟล์ตามรายการนี้ทีละไฟล์ (ทับของเดิม):

```
api/public_relations.php
api/me.php
api/notifications.php
api/admin/organize_students.php
api/admin/update_student.php
api/admin/upload-students.php
views/public_relations.html
views/teacher_profile.html
views/attendance_daily.html
views/attendance_subject.html
public/js/main.js
.htaccess
```

5. สร้างโฟลเดอร์ **`public/uploads/pr_images/`** บน server (ถ้ายังไม่มี)
6. ตั้ง Permission โฟลเดอร์ `pr_images/` เป็น **`755`**

---

### วิธีที่ 3 — ผ่าน SSH (สำหรับผู้ดูแลระบบ)

```bash
# 1. อัปโหลด ZIP ไปที่ server ก่อน (จากเครื่อง local)
scp cnp_patch_20260605.zip user@yourserver.com:/httpdocs/

# 2. SSH เข้า server
ssh user@yourserver.com

# 3. แตกไฟล์ทับโฟลเดอร์เดิม
cd /httpdocs
unzip -o cnp_patch_20260605.zip

# 4. ตั้ง permission โฟลเดอร์ uploads
chmod 755 public/uploads/pr_images/
chown www-data:www-data public/uploads/pr_images/   # ปรับตาม web server user

# 5. ลบ ZIP ออก
rm cnp_patch_20260605.zip
```

---

## ⚠️ สิ่งที่ต้องตรวจสอบบน Server

### 1. Permission โฟลเดอร์ `pr_images/`
โฟลเดอร์นี้ต้องให้ PHP เขียนไฟล์ได้:
```
public/uploads/pr_images/  →  permission: 755 (หรือ 775)
```

### 2. PHP upload settings (ถ้ารูปใหญ่ไม่ยอม upload)
ตรวจสอบใน `php.ini` หรือ `.htaccess`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
```

### 3. ไม่ต้อง migrate ฐานข้อมูล
คอลัมน์ `image_path` มีอยู่แล้วในตาราง `public_relations` ✅

---

## ✅ การตรวจสอบหลังอัปโหลด

หลังอัปโหลดเสร็จ ให้ทดสอบตามนี้:

- [ ] เปิดหน้า **ประชาสัมพันธ์** แล้ว reload (Ctrl+Shift+R)
- [ ] ลองสร้างข่าวใหม่ พร้อมแนบรูปภาพ
- [ ] ตรวจว่ารูปแสดงในการ์ดข่าวและ modal รายละเอียด
- [ ] ล็อกอินด้วยบัญชีครูที่โปรไฟล์ไม่ครบ ตรวจว่าขึ้น popup แจ้งเตือน
- [ ] ตรวจ Console ใน DevTools ว่าไม่มี error

---

## 🗑️ Rollback (ถ้ามีปัญหา)

หากต้องการย้อนกลับ ให้ restore ไฟล์เดิมจาก backup บน server หรือ Git repository ก่อนหน้า patch นี้

---

*สร้างโดย Antigravity AI — CNP App Patch 20260605*
