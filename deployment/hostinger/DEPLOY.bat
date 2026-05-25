@echo off
cd /d "%~dp0..\.."
powershell -ExecutionPolicy Bypass -File deployment\hostinger\deploy.ps1
pause
