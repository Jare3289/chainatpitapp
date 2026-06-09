@echo off
title CNP App Server Starter
echo ===================================================
echo   Starting PHP Development Servers and Databases...
echo ===================================================
echo.

:: Check and Run MariaDB Database Server (Port 3306)
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

:: Run main app on Port 8000
echo [App] Starting Main App on Port 8000...
start /b php -S localhost:8000

:: Run sub app on Port 8005
echo [App] Starting Sub App on Port 8005...
start /b php -S localhost:8005

:: Run phpMyAdmin on Port 8080
echo [DB] Starting phpMyAdmin on Port 8080...
start /b php -S localhost:8080 -t D:\phpMyAdmin-5.2.3-all-languages

echo.
echo ===================================
echo Servers are running!
echo [1] Main App: http://localhost:8000
echo [2] Sub App:  http://localhost:8005
echo [3] phpMyAdmin: http://localhost:8080
echo [4] Database: Port 3306 (Running)
echo ===================================
echo.
echo (Press any key to close this window, servers will keep running in background)
pause > nul
