@echo off
rem ONE-TIME SETUP. Right-click this file and choose "Run as administrator".
rem It makes HRX (web server, Tally launch, and the Cloudflare tunnel + emailed address) start every time you sign in to Windows.
rem Windows only lets an administrator create a task that runs at sign-in, which is why this can't be done from a normal session.
schtasks /create /tn "HRX - Start Local Server" /tr "powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File C:\HRX-Automation\start-server.ps1" /sc ONLOGON /f
if %errorlevel%==0 (
  echo.
  echo Done. HRX will now start by itself every time you sign in.
) else (
  echo.
  echo That did not work. Close this window, right-click the file again and choose "Run as administrator".
)
echo.
pause
