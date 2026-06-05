# prepare_deploy.ps1
# PowerShell deployment packaging script for CNP APP.
# This script copies only necessary files and zips them.

$SourceDir = "."
$DistDir = ".\deploy_dist"
$ZipPath = ".\cnp_deploy_package_clean.zip"

Write-Host "--------------------------------------------------------" -ForegroundColor Cyan
Write-Host "Starting CNP APP deployment package creation..." -ForegroundColor Cyan
Write-Host "--------------------------------------------------------" -ForegroundColor Cyan

# 1. Clean up old build outputs
if (Test-Path $DistDir) { 
    Write-Host "[1/6] Cleaning up old temporary folder..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force $DistDir 
}
if (Test-Path $ZipPath) { 
    Write-Host "[1/6] Cleaning up old ZIP file..." -ForegroundColor Yellow
    Remove-Item -Force $ZipPath 
}

# 2. Create directory structure
Write-Host "[2/6] Creating deployment directory structure..." -ForegroundColor Yellow
New-Item -ItemType Directory -Force -Path $DistDir | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\api" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\inc" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\views" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\css" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\js" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\img" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\uploads\students" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\uploads\teachers" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\public\uploads\import" | Out-Null
New-Item -ItemType Directory -Force -Path "$DistDir\uploads\supervision" | Out-Null

# 3. Copy main folders and files
Write-Host "[3/6] Copying core application code and assets..." -ForegroundColor Yellow
Copy-Item -Recurse -Force "api\*" "$DistDir\api"
Copy-Item -Recurse -Force "inc\*" "$DistDir\inc"
Copy-Item -Recurse -Force "views\*" "$DistDir\views"
Copy-Item -Recurse -Force "public\css\*" "$DistDir\public\css"
Copy-Item -Recurse -Force "public\js\*" "$DistDir\public\js"
Copy-Item -Recurse -Force "public\img\*" "$DistDir\public\img"

# Copy root config files
$rootFiles = @("index.php", "config.php", ".htaccess", ".env.example", "config_deploy.php")
foreach ($file in $rootFiles) {
    if (Test-Path $file) {
        Copy-Item -Force $file "$DistDir\"
    }
}

# Copy .plesk if exists
if (Test-Path ".plesk") {
    New-Item -ItemType Directory -Force -Path "$DistDir\.plesk" | Out-Null
    Copy-Item -Recurse -Force ".plesk\*" "$DistDir\.plesk"
}

# 4. Create placeholder .gitkeep files for uploads
Write-Host "[4/6] Creating placeholder files for uploads directories..." -ForegroundColor Yellow
New-Item -ItemType File -Force -Path "$DistDir\public\uploads\students\.gitkeep" | Out-Null
New-Item -ItemType File -Force -Path "$DistDir\public\uploads\teachers\.gitkeep" | Out-Null
New-Item -ItemType File -Force -Path "$DistDir\public\uploads\import\.gitkeep" | Out-Null
New-Item -ItemType File -Force -Path "$DistDir\uploads\supervision\.gitkeep" | Out-Null

# 5. Clean up local test files and debug scripts
Write-Host "[5/6] Removing local test files and debug scripts..." -ForegroundColor Yellow
Get-ChildItem -Path "$DistDir\api" -Filter "test_*.php" -Recurse | Remove-Item -Force
# 5.1 Clean up test profile photos in public/img/profiles to save space (reduce zip from 416MB to <10MB)
Write-Host "[5.1/6] Cleaning up test profiles photos in public/img/profiles..." -ForegroundColor Yellow
if (Test-Path "$DistDir\public\img\profiles") {
    Get-ChildItem -Path "$DistDir\public\img\profiles" -File | Remove-Item -Force
    New-Item -ItemType File -Force -Path "$DistDir\public\img\profiles\.gitkeep" | Out-Null
}
Get-ChildItem -Path "$DistDir\api" -Filter "debug_*.php" -Recurse | Remove-Item -Force
Get-ChildItem -Path "$DistDir\api" -Filter "diagnose.php" -Recurse | Remove-Item -Force
Get-ChildItem -Path "$DistDir\api" -Filter "test-db.php" -Recurse | Remove-Item -Force
if (Test-Path "$DistDir\views\auto_login.php") { Remove-Item -Force "$DistDir\views\auto_login.php" }

# 6. Compress folder to ZIP using tar.exe (robust against file locks)
Write-Host "[6/6] Compressing deploy folder to ZIP package using tar..." -ForegroundColor Yellow
if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath -ErrorAction SilentlyContinue }
tar.exe -a -c -f $ZipPath -C $DistDir .

# Clean up temp folder with a slight delay to let file watchers release handles
Start-Sleep -Seconds 2
Remove-Item -Recurse -Force $DistDir -ErrorAction SilentlyContinue

Write-Host "========================================================" -ForegroundColor Green
Write-Host "Zip package created successfully!" -ForegroundColor Green
Write-Host "Path: $ZipPath" -ForegroundColor Green
if (Test-Path $ZipPath) {
    Write-Host "Size: $(( (Get-Item $ZipPath).Length / 1MB ).ToString('0.00')) MB" -ForegroundColor Green
} else {
    Write-Host "Error: ZIP file was not created." -ForegroundColor Red
}
Write-Host "========================================================" -ForegroundColor Green
