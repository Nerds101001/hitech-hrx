@echo off
title Hitech HRX Starter
echo ======================================================
echo   Hitech HRX - One-Click Server Starter
echo ======================================================

:: 1. PHP Detection
set PHP_BIN=C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe
if not exist "%PHP_BIN%" (
    set PHP_BIN=php
    where /q php
    if %ERRORLEVEL% neq 0 (
        for /d %%D in ("C:\laragon\bin\php\php-*") do (
            if exist "%%D\php.exe" (
                set PHP_BIN="%%D\php.exe"
            )
        )
    )
)

:: 2. Check if PHP is found
if "%PHP_BIN%"=="php" (
    where /q php
    if %ERRORLEVEL% neq 0 (
        echo [X] ERROR: PHP was not found on your system!
        echo Please ensure Laragon is running or PHP is in your PATH.
        pause
        exit /b
    )
)

echo [+] Using PHP path: %PHP_BIN%

:: 3. Launch Services in Separate Windows
echo [+] Starting Laravel Server on http://localhost:8001...
start "Laravel Server (Port 8001)" cmd /k "%PHP_BIN% artisan serve --port=8001"

echo [+] Starting Vite Asset Server...
start "Vite Asset Server" cmd /k "npm run dev"

echo [+] Starting Laravel Reverb Websockets...
start "Reverb Websockets" cmd /k "%PHP_BIN% artisan reverb:start"

echo.
echo ======================================================
echo   All servers launched!
echo   👉 App URL: http://localhost:8001
echo ======================================================
echo.
timeout /t 5
