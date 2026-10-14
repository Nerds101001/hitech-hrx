<#
  Pick-a-month Tally -> live-server sync.

  Double-click backfill-tally.cmd (or run this script) to get a menu: choose a month (or a custom
  range, or the whole financial year so far) and it pulls those days from Tally and pushes them to
  wherever TALLY_PUSH_TARGET_URL points (currently the live server). Read-only on Tally. Re-running a
  month is safe -- it replaces, never duplicates.

  Non-interactive too (defaults to Hi Tech; add -Company kif for the other one):
     powershell -File backfill-tally.ps1 -Month 2026-05
     powershell -File backfill-tally.ps1 -From 2026-04-01 -To 2026-06-30
     powershell -File backfill-tally.ps1 -FullYear
     powershell -File backfill-tally.ps1 -Company kif -FullYear
#>
param(
  [string]$Month = "",       # "YYYY-MM" : sync that whole month
  [string]$From  = "",       # or an explicit range (chunked into <=31-day pieces automatically)
  [string]$To    = "",
  [switch]$FullYear,         # the current financial year so far
  [switch]$DryRun,           # pull and show counts, send nothing
  [ValidateSet('hitech', 'kif')]
  [string]$Company = ""      # which company (config/services.php tally.companies); interactive mode asks if omitted
)

$ErrorActionPreference = 'Stop'
$php     = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
$appDir  = "D:\liveserver"
$gateway = "http://192.168.2.195:9080"
# Just labels for the menu -- the actual Tally company name lives in config/services.php (tally.companies),
# read from .env, so this list only ever needs a new label here, never the exact Tally name.
$companyLabels = @{ hitech = 'Hi Tech'; kif = 'Keep It Fresh' }

if (-not (Test-Path $php))    { Write-Host "PHP not found at $php" -ForegroundColor Red; exit 1 }
if (-not (Test-Path $appDir)) { Write-Host "App folder not found at $appDir" -ForegroundColor Red; exit 1 }

if (-not $Company) {
  if ($Month -or $From -or $FullYear) {
    $Company = 'hitech'   # a non-interactive caller that didn't say -Company gets the old default, unchanged
  } else {
    Write-Host ""
    Write-Host "Which company?" -ForegroundColor Gray
    Write-Host "   1. Hi Tech"
    Write-Host "   2. Keep It Fresh (KIF)"
    $pick = Read-Host "Enter a number"
    $Company = if ($pick -eq '2') { 'kif' } else { 'hitech' }
  }
}

# Where are we pushing to? (read it from .env so the menu can show it)
$target = (Select-String -Path (Join-Path $appDir '.env') -Pattern '^TALLY_PUSH_TARGET_URL=(.+)$' |
           Select-Object -First 1).Matches.Groups[1].Value
if (-not $target) { $target = '(not set)' }

function Financial-Year-Start([datetime]$d) {
  if ($d.Month -ge 4) { return [datetime]::new($d.Year, 4, 1) } else { return [datetime]::new($d.Year - 1, 4, 1) }
}

# Run the push for one date range, splitting into <=31-day chunks (the command's per-run cap).
function Sync-Range([datetime]$from, [datetime]$to) {
  if ($to -gt (Get-Date).Date) { $to = (Get-Date).Date }
  if ($from -gt $to) { Write-Host "Nothing to do: $($from.ToString('yyyy-MM-dd')) is after $($to.ToString('yyyy-MM-dd'))." -ForegroundColor Yellow; return }
  Write-Host ""
  Write-Host ("Syncing {0}: {1} -> {2}   to   {3}" -f $companyLabels[$Company], $from.ToString('yyyy-MM-dd'), $to.ToString('yyyy-MM-dd'), $target) -ForegroundColor Cyan
  Push-Location $appDir
  try {
    $chunkStart = $from
    while ($chunkStart -le $to) {
      $chunkEnd = $chunkStart.AddDays(30)
      if ($chunkEnd -gt $to) { $chunkEnd = $to }
      $args = @('artisan','hrx:tally-push', "--company=$Company",
                "--from=$($chunkStart.ToString('yyyy-MM-dd'))",
                "--to=$($chunkEnd.ToString('yyyy-MM-dd'))",
                "--gateway-url=$gateway")
      if ($DryRun) { $args += '--dry-run' }
      & $php @args
      if ($LASTEXITCODE -ne 0) {
        Write-Host "A chunk stopped early (slow day or a rejected push). Re-run this same option to continue from where it left off." -ForegroundColor Yellow
        break
      }
      $chunkStart = $chunkEnd.AddDays(1)
    }
  } finally { Pop-Location }
}

# ---------------------------------------------------------------- non-interactive paths
if ($Month) {
  $m = [datetime]::ParseExact($Month + '-01', 'yyyy-MM-dd', $null)
  Sync-Range $m $m.AddMonths(1).AddDays(-1); Write-Host ""; return
}
if ($From) {
  $f = [datetime]::ParseExact($From, 'yyyy-MM-dd', $null)
  $t = if ($To) { [datetime]::ParseExact($To, 'yyyy-MM-dd', $null) } else { $f }
  Sync-Range $f $t; Write-Host ""; return
}
if ($FullYear) {
  $start = Financial-Year-Start (Get-Date)
  Sync-Range $start (Get-Date).Date; Write-Host ""; return
}

# ---------------------------------------------------------------- interactive menu
while ($true) {
  $today = (Get-Date).Date
  $fyStart = Financial-Year-Start $today
  $months = @()
  $m = $fyStart
  while ($m -le $today) { $months += $m; $m = $m.AddMonths(1) }

  Write-Host ""
  Write-Host "============================================================" -ForegroundColor DarkCyan
  Write-Host ("  Tally -> live sync      company: {0}      pushing to: {1}" -f $companyLabels[$Company], $target) -ForegroundColor White
  Write-Host "============================================================" -ForegroundColor DarkCyan
  Write-Host "  Pick a month to sync to the live server:" -ForegroundColor Gray
  for ($i = 0; $i -lt $months.Count; $i++) {
    $lbl = $months[$i].ToString('MMMM yyyy')
    $rng = $months[$i].ToString('dd MMM') + ' - ' + ([Math]::Min($months[$i].AddMonths(1).AddDays(-1).Day, 31))
    Write-Host ("   {0,2}. {1}" -f ($i + 1), $lbl)
  }
  Write-Host ""
  Write-Host ("   {0,2}. Entire financial year so far ({1} - today)" -f ($months.Count + 1), $fyStart.ToString('MMM yyyy'))
  Write-Host ("   {0,2}. Custom date range" -f ($months.Count + 2))
  Write-Host ("   {0,2}. Today only" -f ($months.Count + 3))
  Write-Host "    0. Quit"
  Write-Host ""
  $choice = Read-Host "Enter a number"

  if ($choice -eq '0' -or $choice -eq '') { break }
  $n = 0; if (-not [int]::TryParse($choice, [ref]$n)) { Write-Host "Not a number." -ForegroundColor Red; continue }

  if ($n -ge 1 -and $n -le $months.Count) {
    $sel = $months[$n - 1]
    Sync-Range $sel $sel.AddMonths(1).AddDays(-1)
  }
  elseif ($n -eq $months.Count + 1) {
    Sync-Range $fyStart $today
  }
  elseif ($n -eq $months.Count + 2) {
    $f = Read-Host "From (YYYY-MM-DD)"; $t = Read-Host "To   (YYYY-MM-DD)"
    try { Sync-Range ([datetime]::ParseExact($f,'yyyy-MM-dd',$null)) ([datetime]::ParseExact($t,'yyyy-MM-dd',$null)) }
    catch { Write-Host "Bad date. Use YYYY-MM-DD." -ForegroundColor Red }
  }
  elseif ($n -eq $months.Count + 3) {
    Sync-Range $today $today
  }
  else { Write-Host "No such option." -ForegroundColor Red; continue }

  Write-Host ""
  Write-Host "Done. Pick another, or 0 to quit." -ForegroundColor Green
}
