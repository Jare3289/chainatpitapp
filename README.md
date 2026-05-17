# CNP APP - ระบบบริหารจัดการข้อมูลนักเรียนและบุคลากร
### โรงเรียนชัยนาทพิทยาคม (Chainat Pitthayakom School)

CNP APP คือเว็บแอปพลิเคชันยุคใหม่ที่ออกแบบมาเพื่ออำนวยความสะดวกในการบริหารจัดการข้อมูลนักเรียน ครู และบุคลากรภายในโรงเรียน โดยเน้นประสบการณ์ผู้ใช้ (UX/UI) ที่ทันสมัย รองรับการแสดงผลทุกอุปกรณ์ (Responsive Design) และระบบจัดการข้อมูลที่แม่นยำ

---

## 🌟 คุณสมบัติเด่น (Key Features)

### 1. ระบบจัดการนักเรียน (Student Management)
- **Scrollable Grouped Profile:** ข้อมูลกว่า 130 รายการจัดเรียงต่อเนื่องแบ่งเป็น 6 กลุ่ม (การศึกษา, ส่วนบุคคล, ที่อยู่, ครอบครัว, สมาชิก, สุขภาพ) ไม่ใช้แท็บ เลื่อนดูได้ในหน้าเดียว
- **Profile Completeness Tracker:** แสดงเปอร์เซ็นต์ความสมบูรณ์ของข้อมูลด้วย Progress Bar แบบเรียลไทม์ พร้อมจำนวนรายการที่ยังขาดหาย
- **Modern ID Badge System:** บัตรประจำตัวนักเรียนดิจิทัล แสดงชื่อ TH/EN พร้อม QR Code และข้อมูลระดับชั้น/ห้อง
- **House Colors System (คณะสี):** ระบบสีประจำคณะ (Pink, Red, Green, Yellow) ที่เปลี่ยนธีมสีของหน้าโปรไฟล์อัตโนมัติตามข้อมูลจริง
- **Smart Address & Geolocation:** ระบบที่อยู่แบบ Cascading Dropdowns พร้อมปุ่ม One-click Sync ที่อยู่ตามทะเบียนบ้าน และการเก็บพิกัด Lat/Long
- **Social Media Integration:** รองรับการจัดเก็บ Instagram (IG), Line, Facebook และช่องทางการติดต่อสมัยใหม่

### 2. ระบบจัดการครูและบุคลากร (Teacher Management)
- **Unified Profile Design:** หน้าโปรไฟล์ครูใช้ดีไซน์เดียวกับนักเรียนทุกประการ (same CSS, same layout, same UX pattern) ไม่ใช้แท็บ เลื่อนดูแบบกลุ่มเหมือนกัน
- **Digital Signature:** ระบบลงนามดิจิทัล (E-Signature) สำหรับการรับรองเอกสารอิเล็กทรอนิกส์
- **Teacher ID Badge:** บัตรครูที่เปลี่ยนสีธีมตามคณะสีที่สังกัด เพิ่มความภูมิใจและเอกลักษณ์ในองค์กร

### 3. ระบบบริหารจัดการสำหรับ Admin (Admin Dashboard)
- **Unified Card System:** บัตรนักเรียนและบัตรครูใช้ดีไซน์ Modern Portrait ชุดเดียวกัน เพิ่มความสวยงามและเป็นหนึ่งเดียวของระบบ
- **Professional Record Modal:** หน้าต่างจัดการข้อมูลนักเรียนแบบแท็บ (Tabbed Modal) พร้อมระบบ **QR Code** และ **Completeness Bar**
- **Flattened Filtering Architecture:** ระบบแสดงผลแบบ Flat List (ไม่แบ่งกลุ่มห้อง) เพื่อความคล่องตัวในการค้นหาและกรองข้อมูลระดับสูง
- **Click-to-Call Integration:** แอดมินสามารถคลิกที่เบอร์โทรศัพท์เพื่อโทรออกได้ทันทีผ่านเบอร์โทรที่ฟอร์แมตอัตโนมัติ (tel: links)

---

## 🛠 วิธีการติดตั้ง (Installation)

### 1. ความต้องการของระบบ
- **Web Server:** XAMPP, Laragon หรือ Apache
- **PHP:** เวอร์ชั่น 8.0 ขึ้นไป
- **Database:** MySQL / MariaDB

### 2. ขั้นตอนการติดตั้ง
1. คัดลอกโฟลเดอร์โครงการไปไว้ใน `htdocs` (สำหรับ XAMPP)
2. สร้างฐานข้อมูลใหม่ใน phpMyAdmin (แนะนำชื่อ `cnp_app`)
3. นำเข้าไฟล์ SQL จากโฟลเดอร์ `/sql` (ถ้ามี) หรือรัน Schema ที่จัดเตรียมไว้
4. แก้ไขไฟล์ `config.php` เพื่อตั้งค่าการเชื่อมต่อฐานข้อมูล:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ชื่อฐานข้อมูล');
   define('DB_USER', 'root');
   define('DB_PASS', 'รหัสผ่าน');
   ```

---

## 🚀 วิธีรันโค้ด
1. เปิดเบราว์เซอร์ไปที่ `http://localhost/cnpapp`
2. เข้าสู่ระบบด้วยบัญชีผู้ใช้:
   - **Admin:** (ระบุตามฐานข้อมูล)
   - **Student:** รหัสนักเรียน / รหัสผ่านเริ่มต้น (เช่น cnp12345)
   - **Teacher:** ชื่อผู้ใช้ / รหัสผ่าน

---

## 📁 โครงสร้างโฟลเดอร์ (Project Structure)
รายละเอียดการทำงานเชิงลึกของแต่ละส่วนสามารถอ่านได้ในเอกสารเหล่านี้ (รวมอยู่ในโฟลเดอร์ `docs/`):
- [ARCHITECTURE.md](./docs/ARCHITECTURE.md) - สถาปัตยกรรมและตรรกะการทำงาน
- [DATABASE.md](./docs/DATABASE.md) - โครงสร้างฐานข้อมูลอย่างละเอียด
- [SYSTEM_REVIEW.md](./docs/SYSTEM_REVIEW.md) - บทสรุปและทบทวนระบบทั้งหมด (Ultimate Guide)
- [MODERNIZATION_2026.md](./docs/MODERNIZATION_2026.md) - บันทึกการปรับปรุงระบบครั้งใหญ่ (May 2026)
- [SESSION_LOG_2026-05-17.md](./docs/SESSION_LOG_2026-05-17.md) - สรุปการปรับปรุง UI และระบบอัปโหลดรูปภาพ (May 17, 2026)

- `/api`: จัดเก็บ API Endpoints ทั้งหมด (PHP) แยกตามสิทธิ์ผู้ใช้
- `/inc`: ไฟล์ส่วนกลาง เช่น การเชื่อมต่อฐานข้อมูล (Database) และระบบความปลอดภัย (Security)
- `/public`: ไฟล์สาธารณะ (CSS, JS, Images, Uploads)
- `/views`: ไฟล์หน้าจอผู้ใช้ (HTML/JavaScript)
- `/sql`: ไฟล์โครงสร้างฐานข้อมูล

---

> [!TIP]
> เพื่อการแสดงผลที่ถูกต้องที่สุด แนะนำให้ใช้งานบนเบราว์เซอร์สมัยใหม่ เช่น Google Chrome หรือ Microsoft Edge และทำการ Hard Refresh (`Ctrl + F5`) เมื่อมีการอัปเดตไฟล์ CSS/JS ใหม่ๆ
