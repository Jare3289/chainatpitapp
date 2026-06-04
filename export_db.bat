@echo off
cd /d "%~dp0"
echo ==========================================
echo Database Export Tool (Forced Mode)
echo ==========================================
echo Exporting local database to SQL file...
echo Please wait, this may take a moment...
echo.

"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe" -u root -p1189900208011Jr. --databases admin_cnpapp > "admin_cnpapp (3).sql"

echo.
echo Export Finished! "admin_cnpapp (3).sql" has been updated.
echo You can now commit and push this file to GitHub.
pause
