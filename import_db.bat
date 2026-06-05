@echo off
cd /d "%~dp0"
echo ==========================================
echo Database Import Tool (Forced Mode)
echo ==========================================
echo Cleaning up old database and importing fresh data...
echo Please wait, this may take a minute...
echo.

"C:\Program Files\MariaDB 12.3\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS admin_cnpapp; CREATE DATABASE admin_cnpapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"C:\Program Files\MariaDB 12.3\bin\mysql.exe" --init-command="SET SESSION innodb_strict_mode=0;" -u root -f admin_cnpapp < "admin_cnpapp (3).sql"

echo.
echo Import Finished! You can now close this window.
pause
