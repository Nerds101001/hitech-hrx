@echo off
setlocal enabledelayedexpansion
title Hitech HRX Server Diagnostic & Starter

echo ======================================================
echo   Hitech HRX - Development Server Starter
echo ======================================================

:: --- PHP DETECTION ---
set PHP_BIN=php
where /q php
if %ERRORLEVEL% neq 0 (
    echo [!] PHP not found in PATH. Searching in Laragon...
    for /d %%D in ("C:\laragon\bin\php\php-*") do (
        if exist "%%D\php.exe" (
            set PHP_BIN="%%D\php.exe"
        )
    )
    if "!PHP_BIN!"=="php" (
        echo [X] ERROR: PHP not found in PATH or C:\laragon\bin\php.
        echo Please ensure Laragon is installed or PHP is in your PATH.
        pause
        exit /b
    ) else (
        echo [+] Found PHP at !PHP_BIN!
    )
) else (
    echo [+] PHP is available in your PATH.
)

:: --- NODE/NPM DETECTION ---
where /q node
if %ERRORLEVEL% neq 0 (
    echo [X] ERROR: Node.js not found in your PATH.
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b
)
echo [+] Node.js is available in your PATH.

:: Increase Node memory limit for stable indexing/builds
set NODE_OPTIONS=--max-old-space-size=4096

:: --- START SERVERS ---
echo.
echo [+] Starting Laravel Server (http://127.0.0.1:8000)...
start "Laravel Server" cmd /k "!PHP_BIN! artisan serve"

echo [+] Starting Vite Dev Server (Frontend Assets)...
start "Vite Server" cmd /k "npm run dev"

echo [+] Starting Laravel Reverb (Websockets)...
start "Reverb Server" cmd /k "!PHP_BIN! artisan reverb:start"

echo.
echo ======================================================
echo   All servers are starting in separate windows.
echo   Check each window for specific errors if apps don't load.
echo ======================================================
echo.
pause
