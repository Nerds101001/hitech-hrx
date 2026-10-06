# After a restart, TallyPrime opens but waits for a person to load the company (type its password). Nothing can pull
# data until that is done. This watcher, started at sign-in by start-server.ps1:
#   1. keeps checking whether the company is loaded (the proven check inside `hrx:tally-push`: it fails fast and
#      cleanly with "not loaded" until someone logs in);
#   2. emails ai@rustx.com a reminder if it is still waiting after 3 minutes (and again at 45 min and 2 h);
#   3. the moment the company is loaded, runs ONE catch-up sync (the last 3 days -- idempotent, so a repeat is
#      harmless) instead of leaving the data stale until the next 2-hourly slot.
# It never logs in to Tally and never handles a password: a person still types it. Read-only against Tally.
#
# One run per Windows boot: a state file records the outcome, and a named mutex stops two copies running at once, so
# the 2-hourly sync tasks (which also call start-server.ps1) cannot pile up watchers. Gives up after 4 hours; the
# normal 2-hourly sync keeps trying regardless. Log: C:\HRX-Automation\logs\tally-watch-<date>.log
#
# The parameters exist so a test can run it in seconds against a throwaway state file.
param(
    [int]$PollSeconds = 45,
    [int]$FirstReminderSeconds = 180,
    [int[]]$LaterReminderSeconds = @(2700, 7200),
    [int]$MaxMinutes = 240,
    [string]$StatePath = 'C:\HRX-Automation\watch-tally.state.json',
    [string]$Company = 'HI TECH INTERNATIONAL- (From 1 Apr 2025)',
    [switch]$NoMail      # test only: log "would email" instead of sending
)

$root     = 'C:\HRX-Automation'
$php      = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$app      = 'D:\liveserver'
$gwHost   = '192.168.2.195'
$gwPort   = 9080
$gateway  = "http://${gwHost}:${gwPort}"
$company  = $Company
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
            if ($prev.boot -eq $boot) { exit 0 }
        } catch {}
    }
    function Save-State($result) { @{ boot = $boot; result = $result; at = (Get-Date -Format 's') } | ConvertTo-Json | Set-Content -Path $StatePath -Encoding utf8 }

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

    Log "Watcher started (boot $boot). Waiting for the company to be loaded in Tally."
    $start     = Get-Date
    $reminders = @($FirstReminderSeconds) + $LaterReminderSeconds
    $sent      = 0
    $otherFail = 0

    while (((Get-Date) - $start).TotalMinutes -lt $MaxMinutes) {
        $elapsed = ((Get-Date) - $start).TotalSeconds
        $state = $null      # ready | notloaded | unreachable | tallyclosed | serverdown | other
        $detail = ''

        if (-not (Get-Process tally -ErrorAction SilentlyContinue)) {
            $state = 'tallyclosed'
            if ($elapsed -gt 600) { Log 'TallyPrime is not running after 10 minutes - stopping (someone closed it, or it did not start).'; Save-State 'tally-not-running'; exit 0 }
        }
        elseif (-not (Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue)) {
            $state = 'serverdown'      # the HRX web server is still starting; it can take a minute or two after a restart
        }
        elseif (Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match 'hrx:tally-push' }) {
            $state = 'busy'            # a scheduled sync is running right now; do not stack a second pull on Tally
        }
        elseif (-not (Test-Port $gwHost $gwPort)) {
            $state = 'unreachable'; $detail = "nothing is listening on $gwHost`:$gwPort"
        }
        else {
            Set-Location $app
            $from = (Get-Date).AddDays(-3).ToString('yyyy-MM-dd'); $to = (Get-Date).ToString('yyyy-MM-dd')
            $text = (& $php artisan hrx:tally-push "--from=$from" "--to=$to" "--gateway-url=$gateway" "--gateway-company=$company" 2>&1 | Out-String)
            $code = $LASTEXITCODE
            if ($code -eq 0) { $state = 'ready' }
            elseif ($text -match 'not loaded|Refusing to read') { $state = 'notloaded'; $detail = 'the company is not loaded' }
            elseif ($text -match 'Cannot reach Tally|timed out|cURL error') { $state = 'unreachable'; $detail = 'Tally did not answer' }
            else { $state = 'other'; $detail = (($text -split "`r?`n" | Where-Object { $_.Trim() } | Select-Object -Last 3) -join ' | ') }

            if ($state -eq 'ready') {
                "===== $(Get-Date -Format 's')  catch-up after sign-in ($from .. $to) =====" | Out-File -FilePath (Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))) -Append -Encoding utf8
                $text | Out-File -FilePath (Join-Path $logDir ("tally-sync-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))) -Append -Encoding utf8
                Log ("Company is loaded - catch-up sync done: " + (($text -split "`r?`n" | Where-Object { $_ -match '^Done\.' } | Select-Object -First 1)))
                Save-State 'done'
                exit 0
            }
        }

        if ($state -eq 'other') {
            $otherFail++
            Log "Probe failed in an unexpected way ($otherFail/3): $detail"
            if ($otherFail -ge 3) {
                $logName = 'tally-watch-{0}.log' -f (Get-Date -Format 'yyyy-MM-dd')
                Send-Mail 'HRX: Tally catch-up sync failed' @(
                    'The company looks loaded in Tally, but the catch-up sync failed three times in a row.',
                    '', "Last messages: $detail", '',
                    "The normal 2-hourly sync will keep trying. If this repeats, check C:\HRX-Automation\logs\$logName.")
                Save-State 'failed'; exit 0
            }
        } else { $otherFail = 0 }

        # a reminder only when Tally itself is the thing being waited for
        if ((@('notloaded', 'unreachable') -contains $state) -and ($sent -lt $reminders.Count) -and ($elapsed -ge $reminders[$sent])) {
            $sent++
            if ($state -eq 'notloaded') {
                Send-Mail 'HRX: TallyPrime is waiting for its password' @(
                    "TallyPrime is open on this PC but the company ""$company"" is not loaded yet - it is most likely waiting for the login.",
                    '', 'Please sign in to the company in Tally. HRX will then run a catch-up sync by itself within a minute.',
                    '', "(Reminder $sent of $($reminders.Count); this PC was restarted and signed in $([int]($elapsed / 60)) minutes ago.)")
            } else {
                Send-Mail 'HRX: TallyPrime is not answering' @(
                    "TallyPrime is running on this PC but is not answering on its data port ($gwPort): $detail.",
                    '', 'Please look at the Tally window - a dialog box or a login screen is probably open. HRX will carry on by itself once Tally answers.',
                    '', "(Reminder $sent of $($reminders.Count).)")
            }
        }

        if ($state -ne 'tallyclosed' -and $state -ne 'serverdown' -and $state -ne 'busy') { Log "waiting: $state $detail" }
        Start-Sleep -Seconds $PollSeconds
    }

    Log "Gave up after $MaxMinutes minutes; the 2-hourly sync will keep trying."
    Save-State 'gave-up'
}
finally {
    try { $mutex.ReleaseMutex() } catch {}
    $mutex.Dispose()
}
