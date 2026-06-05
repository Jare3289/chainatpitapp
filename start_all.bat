@echo off
title CNP App Server Starter
echo ===================================================
echo   Starting PHP Development Servers & Databases...
echo ===================================================
echo.

:: ตรวจสอบและรัน MariaDB Database Server (Port 3306)
netstat -ano | findstr 0.0.0.0:3306 > nul
if %errorlevel% neq 0 (
    netstat -ano | findstr [::]:3306 > nul
    if %errorlevel% neq 0 (
        echo [DB] Starting MariaDB Database Server...
        start /b "" "C:\Program Files\MariaDB 12.3\bin\mysqld.exe" --defaults-file="C:\Program Files\MariaDB 12.3\data\my.ini" --console
        timeout /t 3 > nul
    ) else (
        echo [DB] Database Server is already running.
    )
) else (
    echo [DB] Database Server is already running.
)

:: รันแอปหลักที่ Port 8000
echo [App] Starting Main App on Port 8000...
start /b php -S localhost:8000

:: รันแอปเสริมที่ Port 8005
echo [App] Starting Sub App on Port 8005...
start /b php -S localhost:8005

:: รัน phpMyAdmin ที่ Port 8080 (ปิดไว้เนื่องจากไม่มี XAMPP หากต้องการใช้งานสามารถดาวน์โหลดแยกและเปิดใช้ได้)
:: start /b php -S localhost:8080 -t D:\phpMyAdmin-5.2.3-all-languages

echo.
echo ===================================
echo Servers are running!
echo [1] Main App: http://localhost:8000
echo [2] Sub App:  http://localhost:8005
echo [3] Database: Port 3306 (Running)
echo ===================================
echo.
echo (Press any key to close this window, servers will keep running in background)
pause > nul
