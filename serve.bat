@echo off
title Hospital Management - PHP server
REM Double-click this file (CMD). In PowerShell:  cd C:\DBMS ; .\serve.ps1

setlocal EnableDelayedExpansion
cd /d "%~dp0"

call "%~dp0ensure-mysql.bat"

if defined HOSPITAL_PHP if exist "!HOSPITAL_PHP!" (
    set "PHP_EXE=!HOSPITAL_PHP!"
    goto run
)

where php >nul 2>&1 && (
    set "PHP_EXE=php"
    goto run
)

for %%P in (
    "C:\xampp\php\php.exe"
    "D:\xampp\php\php.exe"
    "E:\xampp\php\php.exe"
    "C:\php\php.exe"
) do (
    if exist %%~P (
        set "PHP_EXE=%%~P"
        goto run
    )
)

for /d %%D in ("C:\wamp64\bin\php\php*") do (
    if exist "%%D\php.exe" (
        set "PHP_EXE=%%D\php.exe"
        goto run
    )
)

for /d %%D in ("C:\laragon\bin\php\php-*") do (
    if exist "%%D\php.exe" (
        set "PHP_EXE=%%D\php.exe"
        goto run
    )
)

for /d %%D in ("C:\MAMP\bin\php\php*") do (
    if exist "%%D\php.exe" (
        set "PHP_EXE=%%D\php.exe"
        goto run
    )
)

echo PHP not found. Install XAMPP or set env var HOSPITAL_PHP to your php.exe path.
pause
exit /b 1

:run
echo.
echo  Hospital Management — PHP is running
echo  Open: http://127.0.0.1:5500
echo  Press Ctrl+C to stop.
echo.

start "" "http://127.0.0.1:5500"
"%PHP_EXE%" -S 127.0.0.1:5500 -t "%CD%"

pause
