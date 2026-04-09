param(
  [string]$ServerHost = "89.117.188.10",
  [int]$Port = 65002,
  [string]$User = "u989061032",
  [Parameter(Mandatory = $true)][string]$SshPassword,
  [string]$AppRoot = "/home/u989061032/domains/hitechgroup.in/public_html/hrx",
  [switch]$ImportDatabase,
  [string]$SqlFile = "",
  [string]$DbName = "u989061032_hrx",
  [string]$DbUser = "u989061032_hrx",
  [string]$DbPassword = ""
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot | Split-Path -Parent)

$plink = "C:\Program Files\PuTTY\plink.exe"
$pscp = "C:\Program Files\PuTTY\pscp.exe"
if (-not (Test-Path $plink)) { throw "plink not found at $plink" }
if (-not (Test-Path $pscp)) { throw "pscp not found at $pscp" }

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$artifactDir = "deployment/hostinger/artifacts"
New-Item -ItemType Directory -Path $artifactDir -Force | Out-Null
$archive = "$artifactDir/hrx-release-$stamp.tar.gz"

Write-Host "[1/7] Building release archive..."
tar -czf $archive --exclude-from=deployment/hostinger/exclude.lst .

Write-Host "[2/7] Uploading release archive and deploy script..."
$remoteArchiveTarget = "${User}@${ServerHost}:/tmp/hrx-release.tar.gz"
$remoteScriptTarget = "${User}@${ServerHost}:/tmp/hrx-remote-deploy.sh"
& $pscp -P $Port -pw $SshPassword $archive $remoteArchiveTarget | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Archive upload failed." }
& $pscp -P $Port -pw $SshPassword "deployment/hostinger/remote_deploy.sh" $remoteScriptTarget | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Remote deploy script upload failed." }

Write-Host "[3/7] Running remote deployment..."
$deployCmd = "chmod +x /tmp/hrx-remote-deploy.sh && bash /tmp/hrx-remote-deploy.sh '$AppRoot' '/tmp/hrx-release.tar.gz'"
& $plink -ssh -P $Port -l $User -pw $SshPassword $ServerHost $deployCmd
if ($LASTEXITCODE -ne 0) { throw "Remote deployment failed." }

if ($ImportDatabase) {
  if (-not $SqlFile) {
    Write-Host "[4/7] SQL file not provided. Exporting from local .env database..."
    & "$PSScriptRoot/export-db.ps1"
    $SqlFile = Get-ChildItem "$artifactDir/hrx-data-*.sql" | Sort-Object LastWriteTime -Descending | Select-Object -First 1 -ExpandProperty FullName
  }

  if (-not (Test-Path $SqlFile)) { throw "SQL file not found: $SqlFile" }
  if (-not $DbPassword) { throw "DbPassword is required when -ImportDatabase is used." }

  $remoteSql = "/tmp/hrx-data-$stamp.sql"
  Write-Host "[5/7] Uploading SQL dump..."
  $remoteSqlTarget = "${User}@${ServerHost}:${remoteSql}"
  & $pscp -P $Port -pw $SshPassword $SqlFile $remoteSqlTarget | Out-Null
  if ($LASTEXITCODE -ne 0) { throw "SQL upload failed." }

  Write-Host "[6/7] Backing up production DB and importing new data..."
  $dbCmd = @"
mkdir -p '$AppRoot/deployment/db-backups'
mysqldump -u'$DbUser' -p'$DbPassword' '$DbName' > '$AppRoot/deployment/db-backups/pre-import-$stamp.sql'
mysql -u'$DbUser' -p'$DbPassword' '$DbName' < '$remoteSql'
rm -f '$remoteSql'
"@
  & $plink -ssh -P $Port -l $User -pw $SshPassword $ServerHost $dbCmd
  if ($LASTEXITCODE -ne 0) { throw "Remote database import failed." }
}

Write-Host "[7/7] Done. Deployment completed."
