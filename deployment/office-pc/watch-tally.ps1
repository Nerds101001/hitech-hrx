# After a restart, TallyPrime opens but waits for a person to load each company (type its password). Nothing
# can pull data for a company until that is done -- and the two companies (Hi Tech, KIF) are loaded
# independently, so one can come up well before the other. This watcher, started at sign-in by
# start-server.ps1:
#   1. keeps checking, PER COMPANY, whether it is loaded (the proven check inside `hrx:tally-push`: it fails
#      fast and cleanly with "not loaded" until someone logs that company in);
#   2. emails ai@rustx.com a reminder if a given company is still waiting after 3 minutes (and again at 45
#      min and 2 h) -- each company reminds independently, so a slow second login does not get silently
#      forgotten just because the first company came up fine;
#   3. the moment a company is loaded, runs ONE catch-up sync for IT (the last 3 days -- idempotent, so a
#      repeat is harmless) instead of leaving its data stale until the next 2-hourly slot, and then stops
#      watching that company while continuing to wait for any others still pending.
# It never logs in to Tally and never handles a password: a person still types it. Read-only against Tally.
#
# One run per Windows boot: a state file records each company's outcome, and a named mutex stops two copies
# running at once, so the 2-hourly sync tasks (which also call start-server.ps1) cannot pile up watchers.
# Gives up after 4 hours; the normal 2-hourly sync keeps trying regardless for whichever company is still not
# loaded. Log: C:\HRX-Automation\logs\tally-watch-<date>.log
#
# The parameters exist so a test can run it in seconds against a throwaway state file.
param(
    [int]$PollSeconds = 45,
    [int]$FirstReminderSeconds = 180,
    [int[]]$LaterReminderSeconds = @(2700, 7200),
    [int]$MaxMinutes = 240,
    [string]$StatePath = 'C:\HRX-Automation\watch-tally.state.json',
    [string[]]$Companies = @('hitech', 'kif'),   # the --company codes from config/services.php tally.companies
    [switch]$NoMail      # test only: log "would email" instead of sending
)

$root     = 'C:\HRX-Automation'
$php      = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$app      = 'D:\liveserver'
$gwHost   = '192.168.2.195'
$gwPort   = 9080
$gateway  = "http://${gwHost}:${gwPort}"
$logDir   = "$root\logs"
New-Item -ItemType Directory -Path $logDir -Force | Out-Null
function Log($m) { "$(Get-Date -Format 's')  $m" | Out-File -FilePath (Join-Path $logDir ("tally-watch-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))) -Append -Encoding utf8 }

# ---- one copy at a time (per state file, so a test never collides with the real one)
$sha = [System.Security.Cryptography.SHA1]::Create()
$tag = -join ($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($StatePath))[0..3] | ForEach-Object { $_.ToString('x2') })
$mutex = New-Object System.Threading.Mutex($false, "Global\HRXTallyWatcher-$tag")
if (-not $mutex.WaitOne(0)) { exit 0 }

try {
    # ---- one outcome per boot
    $boot = (Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToString('s')
    if (Test-Path $StatePath) {
        try {
            $prev = Get-Content $StatePath -Raw | ConvertFrom-Json
            if ($prev.boot -eq $boot -and -not ($prev.PSObject.Properties.Name -contains 'pending')) { exit 0 }
            if ($prev.boot -eq $boot -and $prev.pending -is [array] -and $prev.pending.Count -eq 0) { exit 0 }
        } catch {}
    }
    function Save-State($pending, $results) {
        @{ boot = $boot; pending = @($pending); results = $results; at = (Get-Date -Format 's') } | ConvertTo-Json | Set-Content -Path $StatePath -Encoding utf8
    }

    function Test-Port($h, $p, $ms = 2500) {
        $c = New-Object System.Net.Sockets.TcpClient
        try { $a = $c.BeginConnect($h, $p, $null, $null); if ($a.AsyncWaitHandle.WaitOne($ms)) { $c.EndConnect($a); return $true }; return $false }
        catch { return $false } finally { $c.Close() }
    }

    function Send-Mail($subject, $lines) {
        if ($NoMail) { Log "would email (test, not sent): $subject"; return }
        $f = Join-Path $env:TEMP ("tally-watch-mail-{0}.txt" -f [guid]::NewGuid().ToString('N'))
        ($lines -join "`r`n") | Set-Content -Path $f -Encoding utf8
        try {
            $out = & $php "$root\notify-tally.php" $subject $f 2>&1 | Out-String
            Log ("mail '{0}': {1}" -f $subject, $out.Trim())
        } catch { Log "mail failed: $($_.Exception.Message)" }
        finally { Remove-Item $f -Force -ErrorAction SilentlyContinue }
    }

    Log ("Watcher started (boot $boot). Waiting for " + ($Companies -join ', ') + " to be loaded in Tally.")
    $start   = Get-Date
    $results = @{}
    # Per-company reminder/failure tracking, independent of every other company's progress.
    $pending = @{}
    foreach ($c in $Companies) { $pending[$c] = @{ sent = 0; otherFail = 0 } }

    while ($pending.Count -gt 0 -and ((Get-Date) - $start).TotalMinutes -lt $MaxMinutes) {
        $elapsed = ((Get-Date) - $start).TotalSeconds
        $reminders = @($FirstReminderSeconds) + $LaterReminderSeconds

        if (-not (Get-Process tally -ErrorAction SilentlyContinue)) {
            if ($elapsed -gt 600) {
                Log 'TallyPrime is not running after 10 minutes - stopping (someone closed it, or it did not start).'
                foreach ($c in @($pending.Keys)) { $results[$c] = 'tally-not-running' }
                Save-State @() $results
                exit 0
            }
            Start-Sleep -Seconds $PollSeconds; continue
        }
        if (-not (Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue)) {
            Start-Sleep -Seconds $PollSeconds; continue   # the HRX web server is still starting after a restart
        }
        if (Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match 'hrx:tally-push' }) {
            Start-Sleep -Seconds $PollSeconds; continue   # a scheduled sync is running right now; do not stack a second pull on Tally
        }
        if (-not (Test-Port $gwHost $gwPort)) {
            Log "waiting: unreachable nothing is listening on $gwHost`:$gwPort"
            Start-Sleep -Seconds $PollSeconds; continue
        }

        Set-Location $app
        foreach ($company in @($pending.Keys)) {
            $from = (Get-Date).AddDays(-3).ToString('yyyy-MM-dd'); $to = (Get-Date).ToString('yyyy-MM-dd')
            $text = (& $php artisan hrx:tally-push --company=$company "--from=$from" "--to=$to" "--gateway-url=$gateway" 2>&1 | Out-String)
            $code = $LASTEXITCODE
            $state = $null; $detail = ''
            if ($code -eq 0) { $state = 'ready' }
            elseif ($text -match 'not loaded|Refusing to read') { $state = 'notloaded'; $detail = 'the company is not loaded' }
            elseif ($text -match 'Cannot reach Tally|timed out|cURL error') { $state = 'unreachable'; $detail = 'Tally did not answer' }
            else { $state = 'other'; $detail = (($text -split "`r?`n" | Where-Object { $_.Trim() } | Select-Object -Last 3) -join ' | ') }

            if ($state -eq 'ready') {
                "===== $(Get-Date -Format 's')  catch-up after sign-in [$company] ($from .. $to) =====" | Out-File -FilePath (Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))) -Append -Encoding utf8
                $text | Out-File -FilePath (Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))) -Append -Encoding utf8
                Log ("[$company] loaded - catch-up sync done: " + (($text -split "`r?`n" | Where-Object { $_ -match '^Done\.' } | Select-Object -First 1)))
                $results[$company] = 'done'
                $pending.Remove($company)
                continue
            }

            if ($state -eq 'other') {
                $pending[$company].otherFail++
                Log "[$company] probe failed in an unexpected way ($($pending[$company].otherFail)/3): $detail"
                if ($pending[$company].otherFail -ge 3) {
                    $logName = 'tally-watch-{0}.log' -f (Get-Date -Format 'yyyy-MM-dd')
                    Send-Mail "HRX: Tally catch-up sync failed [$company]" @(
                        "The $company company looks loaded in Tally, but its catch-up sync failed three times in a row.",
                        '', "Last messages: $detail", '',
                        "The normal 2-hourly sync will keep trying. If this repeats, check C:\HRX-Automation\logs\$logName.")
                    $results[$company] = 'failed'
                    $pending.Remove($company)
                    continue
                }
            } else { $pending[$company].otherFail = 0 }

            # a reminder only when Tally itself is the thing being waited for, for THIS company
            if ((@('notloaded', 'unreachable') -contains $state) -and ($pending[$company].sent -lt $reminders.Count) -and ($elapsed -ge $reminders[$pending[$company].sent])) {
                $pending[$company].sent++
                $n = $pending[$company].sent
                if ($state -eq 'notloaded') {
                    Send-Mail "HRX: TallyPrime is waiting for its password [$company]" @(
                        "TallyPrime is open on this PC but the $company company is not loaded yet - it is most likely waiting for the login.",
                        '', 'Please sign in to that company in Tally. HRX will then run a catch-up sync by itself within a minute.',
                        '', "(Reminder $n of $($reminders.Count); this PC was restarted and signed in $([int]($elapsed / 60)) minutes ago.)")
                } else {
                    Send-Mail "HRX: TallyPrime is not answering [$company]" @(
                        "TallyPrime is running on this PC but is not answering for $company on its data port ($gwPort): $detail.",
                        '', 'Please look at the Tally window - a dialog box or a login screen is probably open. HRX will carry on by itself once Tally answers.',
                        '', "(Reminder $n of $($reminders.Count).)")
                }
            }

            Log "[$company] waiting: $state $detail"
        }

        Save-State @($pending.Keys) $results
        if ($pending.Count -gt 0) { Start-Sleep -Seconds $PollSeconds }
    }

    if ($pending.Count -gt 0) {
        Log ("Gave up after $MaxMinutes minutes on: " + (($pending.Keys) -join ', ') + "; the 2-hourly sync will keep trying.")
        foreach ($c in @($pending.Keys)) { $results[$c] = 'gave-up' }
    }
    Save-State @() $results
}
finally {
    try { $mutex.ReleaseMutex() } catch {}
    $mutex.Dispose()
}
