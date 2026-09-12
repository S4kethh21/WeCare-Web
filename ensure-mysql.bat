@echo off
REM Auto-start XAMPP MySQL and import schema.sql when the project DB is not set up.
REM Optional: SKIP_HOSPITAL_MYSQL=1  |  HOSPITAL_MYSQL_PASS=yourpassword  |  XAMPP_HOME=C:\xampp

setlocal EnableDelayedExpansion
if defined SKIP_HOSPITAL_MYSQL exit /b 0

set "ROOT=%~dp0"
set "XAMPP="
if defined XAMPP_HOME if exist "!XAMPP_HOME!\mysql\bin\mysql.exe" set "XAMPP=!XAMPP_HOME!"
if not defined XAMPP if exist "C:\xampp\mysql\bin\mysql.exe" set "XAMPP=C:\xampp"
if not defined XAMPP if exist "D:\xampp\mysql\bin\mysql.exe" set "XAMPP=D:\xampp"
if not defined XAMPP if exist "E:\xampp\mysql\bin\mysql.exe" set "XAMPP=E:\xampp"

if not defined XAMPP (
    echo [Hospital] No XAMPP MySQL under C:\ D:\ E:\xampp. Set XAMPP_HOME or start MySQL yourself.
    exit /b 0
)

set "MYSQL=!XAMPP!\mysql\bin\mysql.exe"
set "MYSQL_START=!XAMPP!\mysql_start.bat"

if defined HOSPITAL_MYSQL_PASS (
    set "MYSQL_AUTH=-uroot -p!HOSPITAL_MYSQL_PASS!"
) else (
    set "MYSQL_AUTH=-uroot"
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if errorlevel 1 (
    echo [Hospital] Starting MySQL ^(XAMPP^)...
    if exist "!MYSQL_START!" (
        REM start = new window; "call" would block forever while mysqld runs
        start "XAMPP MySQL" /MIN "!MYSQL_START!"
        timeout /t 4 /nobreak >NUL
    ) else (
        echo [Hospital] mysql_start.bat not found. Start MySQL from XAMPP Control Panel.
    )
) else (
    echo [Hospital] MySQL already running.
)

set "WAIT=0"
:waitmysql
"!MYSQL!" !MYSQL_AUTH! -e "SELECT 1" 2>NUL
if not errorlevel 1 goto :mysqlup
set /a WAIT+=1
if !WAIT! GEQ 45 (
    echo [Hospital] MySQL not ready. Open XAMPP Control Panel and click Start next to MySQL.
    exit /b 0
)
timeout /t 1 /nobreak >NUL
goto :waitmysql

:mysqlup
REM If database or core tables are missing, import schema.sql
"!MYSQL!" !MYSQL_AUTH! hospital_management -e "DESC doctors;" 2>NUL
if not errorlevel 1 goto :done

echo [Hospital] Importing schema.sql ^(first-time setup^)...
"!MYSQL!" !MYSQL_AUTH! < "!ROOT!schema.sql"
if errorlevel 1 (
    echo [Hospital] Import failed. Open phpMyAdmin and import schema.sql manually.
    exit /b 0
)
echo [Hospital] Database hospital_management is ready.

:done
exit /b 0
