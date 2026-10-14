@echo off
REM Double-click this to open the "pick a month and sync to live" menu.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0backfill-tally.ps1"
echo.
pause
