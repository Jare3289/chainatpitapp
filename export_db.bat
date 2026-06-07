@echo off
cd /d "%~dp0"
echo ==========================================
echo Database Export Tool (Forced Mode)
echo ==========================================
echo Exporting local database to SQL file...
echo Please wait, this may take a moment...
echo.

"C:\Program Files\MariaDB 12.3\bin\mysqldump.exe" -u root --databases admin_cnpapp > "admin_cnpapp (3).sql"

echo.
echo Export Finished! "admin_cnpapp (3).sql" has been updated.
echo You can now commit and push this file to GitHub.
pause
