# Refreshes credit limit / GSTIN / PAN / contact from Tally's ledger master, for every company in $companies
# below. Runs once a day (this data doesn't change hour to hour the way sales does) -- Task Scheduler
# ("HRX - Tally Party Sync").
#
# Also runs hrx:tally-push-ledgers here, piggybacking on this same daily schedule rather than a separate
# Task Scheduler entry: that command pulls Tally's WHOLE ledger master (every account, not just customers
# the way hrx:tally-push-parties is scoped) into tally_ledgers -- the table the Sales Report's Tally mapping,
# the backfill command, and the Tally Explorer ledger page all read from. It was previously only ever run
# once by hand for 'hitech' and never for 'kif', so every kif-side customer (SARAN KUMAR, VEGWINGS AGRO, ...)
# had real sales correctly attributed in tally_invoices but no tally_ledgers row to pull GSTIN/PAN/contact
# from - found 2026-10-29 when the owner pointed out SARAN KUMAR does have a proper ledger in Tally, under
# Keep It Fresh.

$logDir = "C:\HRX-Automation\logs"
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$log = Join-Path $logDir ("tally-parties-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

& powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\HRX-Automation\start-server.ps1"
Start-Sleep -Seconds 2

$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
Set-Location "D:\liveserver"

$companies = @('hitech', 'kif')

"===== $(Get-Date -Format 's') =====" | Out-File -FilePath $log -Append -Encoding utf8
foreach ($company in $companies) {
    "--- company=$company (parties) ---" | Out-File -FilePath $log -Append -Encoding utf8
    & $php artisan hrx:tally-push-parties --company=$company --gateway-url=http://192.168.2.195:9080 2>&1 | Out-File -FilePath $log -Append -Encoding utf8
    "--- company=$company (ledgers) ---" | Out-File -FilePath $log -Append -Encoding utf8
    & $php artisan hrx:tally-push-ledgers --company=$company --gateway-url=http://192.168.2.195:9080 2>&1 | Out-File -FilePath $log -Append -Encoding utf8
}
