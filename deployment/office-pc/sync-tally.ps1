# Runs the daily Tally -> HRX sync (today's invoices, sale orders, deliveries) for every company in
# $companies below. Called every 2 hours during work hours by Task Scheduler ("HRX - Tally Sync"). Read-only
# against Tally; writes only to the local HRX server at TALLY_PUSH_TARGET_URL (config/tally_push.php), which
# is this same PC.
#
# Each company is independent: if one isn't loaded in Tally yet (or Tally itself isn't open), that company's
# pull fails cleanly and the next one is still tried -- a missing KIF does not stop Hi Tech's sync, or the
# other way round. Neither retries in a loop or alerts anyone; check the log if a whole day looks empty for
# one of them.

$logDir = "C:\HRX-Automation\logs"
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$log = Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

# Make sure the server this pushes to is actually up before trying.
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\HRX-Automation\start-server.ps1"
Start-Sleep -Seconds 2

$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
Set-Location "D:\liveserver"

# --company is just the short code tagged onto every row pushed; the exact Tally company name it resolves to
# lives in config/services.php (tally.companies), read from this same server's own .env -- so adding a third
# company later is a one-line config change here and in config/services.php, not a script rewrite.
$companies = @('hitech', 'kif')

"===== $(Get-Date -Format 's') =====" | Out-File -FilePath $log -Append -Encoding utf8
foreach ($company in $companies) {
    "--- company=$company ---" | Out-File -FilePath $log -Append -Encoding utf8
    & $php artisan hrx:tally-push --company=$company --gateway-url=http://192.168.2.195:9080 2>&1 | Out-File -FilePath $log -Append -Encoding utf8
}
