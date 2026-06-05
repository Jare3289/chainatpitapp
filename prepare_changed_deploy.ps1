# prepare_changed_deploy.ps1
# Creates a ZIP package containing only the files modified today, preserving directory structure.

$ChangedFiles = @(
    "admin_students.html",
    "views\admin_students.html",
    "views\student_profile.html",
    "api\notifications.php",
    "api\public_relations.php",
    "api\teacher\supervision_book.php",
    "api\admin\supervision_admin.php",
    "api\teacher\supervision_upload_docs.php",
    "api\teacher\supervision_evaluate.php",
    "api\teacher\supervision_post_teach.php",
    "api\teacher\supervision_cancel.php"
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

# 3. Compress folder to ZIP using tar.exe
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
