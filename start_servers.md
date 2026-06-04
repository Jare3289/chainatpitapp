# การเริ่มต้น Local Server (Port 8000, 8005 และ phpMyAdmin)

เนื่องจากระบบนี้รันผ่าน Terminal โดยไม่ใช้ XAMPP คุณสามารถเปิดเซิร์ฟเวอร์ด้วย **PHP Built-in Server** ได้ดังนี้ครับ

## วิธีที่ 1: รันผ่าน Terminal ทีละหน้าต่าง

เปิด Terminal (PowerShell หรือ CMD) แยกกัน 3 หน้าต่าง แล้วรันคำสั่งตามนี้:

**หน้าต่างที่ 1 (แอปหลัก - Port 8000):**
```powershell
cd d:\cnpapp
php -S localhost:8000
```

**หน้าต่างที่ 2 (แอปส่วนเสริม - Port 8005):**
```powershell
cd d:\cnpapp
php -S localhost:8005
```

**หน้าต่างที่ 3 (phpMyAdmin - Port 8080):**
```powershell
php -S localhost:8080 -t D:\phpMyAdmin-5.2.3-all-languages
```

จากนั้นสามารถเข้าใช้งานได้ที่:
- แอปหลัก: `http://localhost:8000/`
- แอปเสริม: `http://localhost:8005/`
- ฐานข้อมูล: `http://localhost:8080/`

---

## วิธีที่ 2: รันทั้งหมดในคลิกเดียวผ่าน Batch File (แนะนำ)

คุณสามารถสร้างไฟล์ `start_all.bat` ไว้ในโปรเจกต์ (คลิกขวา > New > Text Document > เปลี่ยนชื่อเป็น `start_all.bat`) แล้วใส่โค้ดด้านล่างนี้ลงไป:

```bat
@echo off
echo Starting PHP Development Servers...

:: รันแอปหลักที่ Port 8000
start /b php -S localhost:8000

:: รันแอปเสริมที่ Port 8005
start /b php -S localhost:8005

:: รัน phpMyAdmin ที่ Port 8080 (ชี้ไปที่โฟลเดอร์ของ phpMyAdmin โดยตรง)
start /b php -S localhost:8080 -t D:\phpMyAdmin-5.2.3-all-languages

echo.
echo ===================================
echo Servers are running!
echo [1] Main App: http://localhost:8000
echo [2] Sub App:  http://localhost:8005
echo [3] Database: http://localhost:8080
echo ===================================
echo.
echo (Press any key to close this window, servers will keep running in background)
pause > nul
```

เมื่อคุณต้องการรันโปรเจกต์ในวันพรุ่งนี้ ก็แค่ดับเบิลคลิกที่ไฟล์ `start_all.bat` นี้ทีเดียวจบเลยครับ ระบบจะเปิดให้ทั้ง 3 ตัวพร้อมกัน
