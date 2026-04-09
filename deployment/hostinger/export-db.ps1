param(
  [string]$OutputFile = "",
  [string]$EnvFile = ".env",
  [string]$DumpBinary = "",
  [string]$DatabaseName = ""
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $EnvFile)) {
  throw "Env file not found: $EnvFile"
}

$envMap = @{}
Get-Content $EnvFile | ForEach-Object {
  if ($_ -match "^\s*#") { return }
  if ($_ -match "^\s*$") { return }
  $idx = $_.IndexOf("=")
  if ($idx -lt 1) { return }
  $k = $_.Substring(0, $idx).Trim()
  $v = $_.Substring($idx + 1).Trim().Trim('"')
  $envMap[$k] = $v
}

if (-not $DumpBinary) {
  $dumpBasePath = ""
  if ($envMap.ContainsKey("DB_MYSQL_DUMP_PATH")) {
    $dumpBasePath = $envMap["DB_MYSQL_DUMP_PATH"]
  }
  $candidate = Join-Path $dumpBasePath "mysqldump.exe"
  if ($candidate -and (Test-Path $candidate)) {
    $DumpBinary = $candidate
  } else {
    $fallback = Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter mysqldump.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
    if (-not $fallback) {
      throw "mysqldump.exe not found. Pass -DumpBinary explicitly."
    }
    $DumpBinary = $fallback
  }
}

if (-not $OutputFile) {
  $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
  $OutputFile = "deployment/hostinger/artifacts/hrx-data-$stamp.sql"
}

$outDir = Split-Path -Parent $OutputFile
if ($outDir) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }

$dbHost = $envMap["DB_HOST"]
$dbPort = $envMap["DB_PORT"]
$dbUser = $envMap["DB_USERNAME"]
$dbPass = $envMap["DB_PASSWORD"]
$dbName = if ($DatabaseName) { $DatabaseName } else { $envMap["DB_DATABASE"] }

if (-not $dbName) { throw "DB_DATABASE is missing in $EnvFile" }

Write-Host "Exporting database '$dbName' to $OutputFile ..."

$args = @(
  "--host=$dbHost",
  "--port=$dbPort",
  "--user=$dbUser",
  "--password=$dbPass",
  "--default-character-set=utf8mb4",
  "--single-transaction",
  "--quick",
  "--routines",
  "--events",
  "--triggers",
  "--hex-blob",
  $dbName
)

$errFile = "$OutputFile.err"
$proc = Start-Process -FilePath $DumpBinary -ArgumentList $args -NoNewWindow -Wait -PassThru -RedirectStandardOutput $OutputFile -RedirectStandardError $errFile
if ($proc.ExitCode -ne 0) {
  $errText = if (Test-Path $errFile) { Get-Content $errFile | Out-String } else { "Unknown mysqldump error." }
  throw "mysqldump failed (exit code $($proc.ExitCode)): $errText"
}
if (Test-Path $errFile) { Remove-Item $errFile -Force }

Write-Host "Database export complete: $OutputFile"
