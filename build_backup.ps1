# PowerShell Script to Create a Secure Backup Repository
# Run this script to generate a clean version of the codebase under d:\live_server_secure_backup

$Source = "d:\live_server"
$Target = "d:\live_server_secure_backup"

Write-Host "Starting backup process..." -ForegroundColor Cyan
Write-Host "Source: $Source"
Write-Host "Target: $Target"

# 1. Ensure Target directory exists and is clean
if (Test-Path $Target) {
    Write-Host "Cleaning existing target directory: $Target" -ForegroundColor Yellow
    Remove-Item -Path $Target -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Force -Path $Target | Out-Null


# 2. Exclude folders and files during copy
# We exclude node_modules, vendor, local storage dumps, git config, and all raw data sheets/secrets
$ExcludeDirs = @(
    ".git", 
    "node_modules", 
    "vendor", 
    "bootstrap/cache", 
    "storage/framework/cache", 
    "storage/framework/sessions", 
    "storage/framework/views",
    "storage/app/public/uploads",
    "storage/logs",
    ".agent",
    ".agents",
    ".claude"
)

$ExcludeFiles = @(
    ".env", 
    ".env.live", 
    ".env.live-backup",
    "*.xlsx", 
    "*.xls", 
    "*.csv", 
    "*.tsv", 
    "*.pdf", 
    "*.docx", 
    "*.zip", 
    "*.txt", 
    "build_backup.ps1",
    "check_*.php",
    "fix_*.php",
    "seed_*.php",
    "live_check.php",
    "recalc_*.php",
    "decoded_*.php",
    "decoded.php",
    "decode.php",
    "decode_model.php",
    "drop_tables.php",
    "inspect_user.php",
    "local_test.php",
    "nagender.php",
    "remote_replace_480.php",
    "replace_480*.php",
    "show_dinesh.php",
    "tinker_script.php",
    "live_data_export.json",
    "crm_active_employees.json",
    "mapping_result.json",
    "permissions_list.json"
)


Write-Host "Copying files using Robocopy..." -ForegroundColor Cyan
$robocopyArgs = @(
    $Source,
    $Target,
    "/E",
    "/XD"
) + $ExcludeDirs + @("/XF") + $ExcludeFiles + @("/NFL", "/NDL", "/NJH", "/NJS")

# Run robocopy (robocopy exit codes 0-7 indicate success/copied files)
$process = Start-Process robocopy -ArgumentList $robocopyArgs -Wait -NoNewWindow -PassThru
if ($process.ExitCode -ge 8) {
    Write-Error "Robocopy failed with exit code $($process.ExitCode)"
    exit
}

# 3. Create .gitignore inside target to ensure exclusions persist
$gitignoreContent = @"
/node_modules
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.env.production
.env.live
.phpunit.result.cache
docker-compose.override.yml
.DS_Store
Thumbs.db

# Exclude any newly generated sql dumps or data sheets
*.sql
*.xlsx
*.csv
*.tsv
*.zip
*.pdf
*.docx
"@

Set-Content -Path "$Target\.gitignore" -Value $gitignoreContent

# 4. Copy .env.example as template
if (Test-Path "$Source\.env.example") {
    Copy-Item -Path "$Source\.env.example" -Destination "$Target\.env.example" -Force
}

# 4.5. Run python script to inject locks and obfuscate target AppServiceProvider.php
Write-Host "Running Python obfuscation on target..." -ForegroundColor Cyan
& python "$Source\obfuscate_backup.py"

# 5. Initialize Git Repository in Target
Write-Host "Initializing Git repository in target and pushing to GitHub..." -ForegroundColor Cyan
Set-Location -Path $Target

try {
    git init
    git config user.name "Hitech HRX Backup Manager"
    git config user.email "support@hitechgroup.in"
    git add .
    git commit -m "Initial commit of secure backup version (excludes raw data and API keys)"
    
    # Configure online backup repository remote
    git remote remove origin 2>$null
    git remote add origin https://github.com/csenerds-web/hitechhrx.git
    
    # Push to online master branch
    Write-Host "Pushing secure backup to GitHub..." -ForegroundColor Cyan
    git push -u origin master --force
    
    Write-Host "Git repository initialized, committed, and pushed successfully!" -ForegroundColor Green
} catch {
    Write-Host "Warning: Git commands failed. Ensure git is installed, in your PATH, and you have access to the repository." -ForegroundColor Yellow
}

# Go back to source
Set-Location -Path $Source

Write-Host "Backup completed successfully!" -ForegroundColor Green
Write-Host "Local secure folder path: $Target" -ForegroundColor Green
Write-Host "Online repository link: https://github.com/csenerds-web/hitechhrx" -ForegroundColor Green

