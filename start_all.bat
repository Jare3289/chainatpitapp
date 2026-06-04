@echo off
title CNP App Server Starter
echo ===================================================
echo   Starting PHP Development Servers & Databases...
echo ===================================================
echo.

:: 1. Start MySQL database
echo [*] Starting MySQL Database...
start /b "" "C:\xampp\mysql_start.bat"
timeout /t 2 > nul

:: 2. Start Apache Server (for phpMyAdmin)
echo [*] Starting Apache Server (for phpMyAdmin)...
start /b "" "C:\xampp\apache_start.bat"
timeout /t 2 > nul

:: 3. Start PHP Built-in Server for the Main App (Port 8000)
echo [*] Starting Main App on http://localhost:8000 ...
start /b "" "C:\xampp\php\php.exe" -S localhost:8000

:: 4. Start PHP Built-in Server for the Sub App (Port 8005)
echo [*] Starting Sub App on http://localhost:8005 ...
start /b "" "C:\xampp\php\php.exe" -S localhost:8005

echo.
echo ===================================================
echo   Servers are running!
echo ===================================================
echo   [1] phpMyAdmin: http://localhost/phpmyadmin/
echo   [2] Main App  : http://localhost:8000/
echo   [3] Sub App   : http://localhost:8005/
echo ===================================================
echo.
echo Press any key to exit this script. (Servers will keep running in the background)
pause > nul
