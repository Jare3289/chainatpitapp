@echo off
title CNP App Server Starter
echo ===================================================
echo   Starting PHP Development Servers & Databases...
echo ===================================================
echo.

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
