# Ensures MySQL and the local HRX web server (php artisan serve, port 8001) are both running.
# Safe to run repeatedly -- checks each before starting it, never starts a duplicate.
# Called at user sign-in (Startup-folder shortcut "HRX Start"), at boot (Task Scheduler: "HRX - Start at Boot",
# with -NoTally), by every Tally sync run, and can be re-run any time by hand.
#
# -NoTally : skip launching TallyPrime. The boot task runs before anyone is signed in, in a session with no desktop,
#            where a GUI app would start invisibly and be unusable. Tally is opened by the sign-in shortcut instead.
param([switch]$NoTally)

$logDir = "C:\HRX-Automation\logs"
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$log = Join-Path $logDir "server.log"
function Log($msg) { "$(Get-Date -Format 's')  $msg" | Out-File -FilePath $log -Append -Encoding utf8 }

# 1. MySQL
$mysqlRunning = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqlRunning) {
    Log "MySQL not running -- starting it."
    $mysqlDir = "C:\laragon\bin\mysql\mysql-8.4.3-winx64"
    Start-Process -FilePath "$mysqlDir\bin\mysqld.exe" -ArgumentList "--defaults-file=$mysqlDir\my.ini" -WorkingDirectory $mysqlDir -WindowStyle Hidden
    Start-Sleep -Seconds 5
} else {
    Log "MySQL already running."
}

# 2. Web server (php artisan serve on 8001)
$portInUse = $null
try { $portInUse = Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue } catch {}
# A cold start takes a minute or more before port 8001 answers, and two callers can arrive in that window (the boot
# task and the sign-in shortcut do exactly that). Port-not-listening alone would start a second server, which then
# falls back to 8002 and leaves a stray process -- so a serve process that is already starting counts as running.
$serving = Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match 'artisan"?\s+serve' -and $_.CommandLine -match '--port=8001' }
if (-not $portInUse -and $serving) {
    Log "Port 8001 not up yet, but php artisan serve (pid $(@($serving)[0].ProcessId)) is already starting -- not starting another."
} elseif (-not $portInUse) {
    Log "Port 8001 not listening -- starting php artisan serve."
    $php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
    Start-Process -FilePath $php -ArgumentList "artisan", "serve", "--port=8001" -WorkingDirectory "D:\liveserver" -WindowStyle Hidden
} else {
    Log "Port 8001 already listening."
}

# 3. TallyPrime itself -- the actual source the sync scripts pull from. Just launches the app; it does NOT
# select the company or enter the TallyVault/Security Control password. That's deliberate: this script runs
# unattended, and a password has no business living in a script or a scheduled task. If TallyPrime's own
# "Load companies on startup" (F1 > Settings > Startup) is turned on, launching it here will get you straight
# to the password prompt with the right company already queued up -- someone still has to type the password.
$tallyRunning = Get-Process tally -ErrorAction SilentlyContinue
if ($NoTally) {
    Log "Boot run (-NoTally): TallyPrime is left for the sign-in shortcut."
} elseif (-not $tallyRunning) {
    Log "TallyPrime not running -- starting it (company load / password still needs a person)."
    Start-Process -FilePath "C:\Program Files\TallyPrime\tally.exe" -WorkingDirectory "C:\Program Files\TallyPrime"
} else {
    Log "TallyPrime already running."
}

# 3b. The Tally watcher: after a restart it reminds ai@rustx.com by email while Tally is still waiting for its password,
# and runs one catch-up sync the moment the company is loaded. Detached and wrapped, like the tunnel below; it exits
# straight away if one is already running or this Windows boot has already been handled, so the 2-hourly sync tasks
# that also call this script cannot stack copies. Skipped for the boot task (-NoTally): nobody is signed in then.
try {
    if (-not $NoTally -and (Test-Path "C:\HRX-Automation\watch-tally.ps1")) {
        Start-Process -FilePath "powershell.exe" -WindowStyle Hidden -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'C:\HRX-Automation\watch-tally.ps1')
        Log "Tally watcher launched (see tally-watch-<date>.log)."
    }
} catch {
    Log "Could not start the Tally watcher: $($_.Exception.Message)"
}

# 4. The Cloudflare tunnel to this server, and the email of its (new) address to ai@rustx.com after a restart.
# Started detached and wrapped, so a tunnel problem can never delay or break the callers of this script (the Tally
# syncs). start-tunnel.ps1 is idempotent, refuses to open an ungated tunnel, and honours C:\HRX-Automation\tunnel.disabled.
try {
    if ((Test-Path "C:\HRX-Automation\start-tunnel.ps1") -and -not (Test-Path "C:\HRX-Automation\tunnel.disabled")) {
        Start-Process -FilePath "powershell.exe" -WindowStyle Hidden -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'C:\HRX-Automation\start-tunnel.ps1')
        Log "Tunnel check started (see tunnel-script.log)."
    }
} catch {
    Log "Could not start the tunnel check: $($_.Exception.Message)"
}
