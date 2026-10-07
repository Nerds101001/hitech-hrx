@echo off
rem ONE-TIME SETUP. Right-click this file and choose "Run as administrator".
rem
rem Makes MySQL, the HRX web server (port 8001) and the Cloudflare tunnel start by themselves as soon as Windows
rem boots -- BEFORE anybody signs in. (The "HRX Start" shortcut in the Startup folder only fires at sign-in.)
rem
rem TallyPrime is a desktop program and cannot be shown before sign-in, so the boot task starts everything except
rem Tally (-NoTally). The sign-in shortcut opens Tally; someone still types its company password.
rem
rem Windows only lets an administrator create a task that runs at boot, which is why this cannot be done from a
rem normal session. The task runs as SYSTEM, 30 seconds after boot, once per boot.
schtasks /create /tn "HRX - Start at Boot" /tr "powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File C:\HRX-Automation\start-server.ps1 -NoTally" /sc ONSTART /delay 0000:30 /ru SYSTEM /rl HIGHEST /f
if %errorlevel%==0 (
  echo.
  echo Done. HRX will now start by itself every time this PC boots, even before anyone signs in.
  echo To remove it later:  schtasks /delete /tn "HRX - Start at Boot" /f
) else (
  echo.
  echo That did not work. Close this window, right-click the file again and choose "Run as administrator".
)
echo.
pause
