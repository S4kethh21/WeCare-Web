@echo off
REM Starts PHP built-in server for Cursor/VS Code Run task.
REM If nothing is found:
REM   - Install XAMPP from https://www.apachefriends.org/  OR
REM   - Set user env var HOSPITAL_PHP = full path to php.exe (then restart Cursor)
REM   - Or add the folder that contains php.exe to your Windows PATH

setlocal EnableDelayedExpansion
cd /d "%~dp0"

call "%~dp0ensure-mysql.bat"

if defined HOSPITAL_PHP if exist "!HOSPITAL_PHP!" (
    echo Using PHP: !HOSPITAL_PHP!
    "!HOSPITAL_PHP!" -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

where php >nul 2>&1 && (
    php -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

REM XAMPP / WAMP / Laragon / common folders
for %%P in (
    "C:\xampp\php\php.exe"
    "D:\xampp\php\php.exe"
    "E:\xampp\php\php.exe"
    "C:\php\php.exe"
) do if exist %%~P (
    echo Using %%~P
    %%~P -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

for /d %%D in ("C:\wamp64\bin\php\php*") do if exist "%%D\php.exe" (
    echo Using %%D\php.exe
    "%%D\php.exe" -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

for /d %%D in ("C:\laragon\bin\php\php-*") do if exist "%%D\php.exe" (
    echo Using %%D\php.exe
    "%%D\php.exe" -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

for /d %%D in ("C:\MAMP\bin\php\php*") do if exist "%%D\php.exe" (
    echo Using %%D\php.exe
    "%%D\php.exe" -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

for /d %%D in ("%LocalAppData%\Programs\PHP\*") do if exist "%%D\php.exe" (
    echo Using %%D\php.exe
    "%%D\php.exe" -S 127.0.0.1:5500 -t "%CD%"
    exit /b %ERRORLEVEL%
)

echo.
echo ========== PHP was not found ==========
echo No php.exe on PATH and not in usual XAMpp/WAMP/Laragon folders.
echo.
echo Fix ONE of these:
echo  A^) Install XAMPP ^(includes PHP^): https://www.apachefriends.org/
echo     Then either restart Cursor or set env var:
echo       HOSPITAL_PHP=C:\xampp\php\php.exe
echo.
echo  B^) Copy your php.exe full path into Windows Environment Variables as HOSPITAL_PHP
echo     ^(User variables^), restart Cursor, Run again.
echo.
echo  C^) Skip this script: start Apache in XAMPP and open:
echo       http://localhost/DBMS/
echo     ^(copy this project folder into C:\xampp\htdocs\DBMS^)
echo ========================================
exit /b 1
