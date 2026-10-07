# Runs the daily Tally -> HRX sync (today's invoices, sale orders, deliveries). Called every 2 hours during
# work hours by Task Scheduler ("HRX - Tally Sync"). Read-only against Tally; writes only to the local HRX
# server at TALLY_PUSH_TARGET_URL (config/tally_push.php), which is this same PC.
#
# If Tally isn't open yet, or the company isn't loaded, this fails cleanly and just tries again next run --
# it does not retry in a loop or alert anyone; check the log if a whole day looks empty.

$logDir = "C:\HRX-Automation\logs"
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$log = Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

# Make sure the server this pushes to is actually up before trying.
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\HRX-Automation\start-server.ps1"
Start-Sleep -Seconds 2

$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
Set-Location "D:\liveserver"

"===== $(Get-Date -Format 's') =====" | Out-File -FilePath $log -Append -Encoding utf8
& $php artisan hrx:tally-push --gateway-url=http://192.168.2.195:9080 "--gateway-company=HI TECH INTERNATIONAL- (From 1 Apr 2025)" 2>&1 | Out-File -FilePath $log -Append -Encoding utf8
