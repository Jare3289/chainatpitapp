# prepare_changed_deploy.ps1
# Creates a ZIP package containing only the files modified today, preserving directory structure.

$ChangedFiles = @(
    "api\admin\organize_students.php",
    "api\admin\update_student.php",
    "api\admin\upload-students.php",
    "api\notifications.php",
    "api\me.php",
    "api\public_relations.php",
    "public\js\main.js",
    "views\attendance_daily.html",
    "views\attendance_subject.html",
    "views\teacher_profile.html",
    "views\public_relations.html",
    ".htaccess"
)

# Folders to copy entirely (all contents)
$ChangedFolders = @(
    "public\uploads\pr_images"
)

$DistDir = ".\changed_dist"
$ZipPath = ".\cnp_patch_20260605.zip"

Write-Host "--------------------------------------------------------" -ForegroundColor Cyan
Write-Host "Creating patch package with only modified files..." -ForegroundColor Cyan
Write-Host "--------------------------------------------------------" -ForegroundColor Cyan

# 1. Clean up old build outputs
if (Test-Path $DistDir) { 
    Write-Host "Cleaning up old temporary folder..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force $DistDir 
}
if (Test-Path $ZipPath) { 
    Write-Host "Cleaning up old patch ZIP file..." -ForegroundColor Yellow
    Remove-Item -Force $ZipPath 
}

# 2. Copy modified files and preserve directory structures
Write-Host "Copying only modified files..." -ForegroundColor Yellow
foreach ($file in $ChangedFiles) {
    if (Test-Path $file) {
        $destFile = Join-Path $DistDir $file
        $destDir = Split-Path $destFile
        if (!(Test-Path $destDir)) {
            New-Item -ItemType Directory -Force -Path $destDir | Out-Null
        }
        Copy-Item -Force $file $destFile
        Write-Host "  [+] $file" -ForegroundColor Gray
    } else {
        Write-Host "  [!] Warning: File $file not found!" -ForegroundColor Red
    }
}

# 3. Copy entire folders (preserve structure)
Write-Host "Copying folders..." -ForegroundColor Yellow
foreach ($folder in $ChangedFolders) {
    if (Test-Path $folder) {
        $destFolder = Join-Path $DistDir $folder
        if (!(Test-Path $destFolder)) {
            New-Item -ItemType Directory -Force -Path $destFolder | Out-Null
        }
        Copy-Item -Recurse -Force "$folder\*" $destFolder
        Write-Host "  [+] $folder\" -ForegroundColor Gray
    } else {
        Write-Host "  [!] Warning: Folder $folder not found, creating empty placeholder..." -ForegroundColor Yellow
        $destFolder = Join-Path $DistDir $folder
        New-Item -ItemType Directory -Force -Path $destFolder | Out-Null
        # Create a .gitkeep so the folder exists in ZIP
        New-Item -ItemType File -Force -Path (Join-Path $destFolder ".gitkeep") | Out-Null
    }
}

# 4. Compress folder to ZIP using tar.exe
Write-Host "Compressing patch folder to ZIP package..." -ForegroundColor Yellow
tar.exe -a -c -f $ZipPath -C $DistDir .

# Clean up temp folder
Start-Sleep -Seconds 1
Remove-Item -Recurse -Force $DistDir -ErrorAction SilentlyContinue

Write-Host "========================================================" -ForegroundColor Green
Write-Host "Patch package (cnp_patch_20260605.zip) created successfully!" -ForegroundColor Green
if (Test-Path $ZipPath) {
    Write-Host "Size: $(( (Get-Item $ZipPath).Length / 1KB ).ToString('0.00')) KB" -ForegroundColor Green
}
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Files included in this patch:" -ForegroundColor Cyan
foreach ($f in $ChangedFiles) { Write-Host "  - $f" -ForegroundColor White }
foreach ($f in $ChangedFolders) { Write-Host "  - $f\ (folder)" -ForegroundColor White }
