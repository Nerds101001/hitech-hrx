@echo off
REM ---------------------------------------------------------------------------
REM  HRX Tally jobs poller -- runs on the office PC (where Tally is open) every
REM  couple of minutes via Task Scheduler. It asks the live server whether any
REM  sync was requested from the Tally Push admin page (a month backfill, an
REM  outstanding refresh, or a customer-ledger refresh), runs it against Tally,
REM  and reports the result back. When nothing is queued it makes one tiny call
REM  to the live server and exits -- it never touches Tally unless there's work.
REM
REM  To change how often it runs, or to stop it:
REM     schtasks /change /tn "HRX Tally Jobs Poller" /ri 5      (every 5 minutes)
REM     schtasks /delete /tn "HRX Tally Jobs Poller" /f         (remove it)
REM ---------------------------------------------------------------------------
setlocal
set PHP=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
set APP=D:\liveserver
set GATEWAY=http://192.168.2.195:9080
set COMPANY=HI TECH INTERNATIONAL- (From 1 Apr 2025)

cd /d "%APP%"
"%PHP%" artisan hrx:tally-jobs-poll --gateway-url=%GATEWAY% --gateway-company="%COMPANY%" >> "%APP%\storage\logs\tally-jobs-poll.log" 2>&1
endlocal
