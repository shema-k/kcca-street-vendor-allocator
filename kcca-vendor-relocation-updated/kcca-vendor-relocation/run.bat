@echo off
setlocal

rem Run the KCCA Vendor Relocation ^& Space Allocation System on Windows
rem without typing anything. Double-click this file.
rem
rem Requirements on PATH: php, mariadbd (or mysqld), mariadb-install-db, mysql.
rem Install PHP 8+ and MariaDB 10.4+ first (e.g. via Chocolatey or the
rem official installers) and make sure their bin folders are on PATH.

cd /d "%~dp0"

where php >nul 2>&1
if errorlevel 1 (
    echo PHP was not found on PATH. Install PHP 8.0+ and try again.
    pause
    exit /b 1
)

rem Use TCP on Windows (no Unix socket).
set "KCCA_DB_SOCKET="
set "KCCA_DB_HOST=127.0.0.1"
set "KCCA_DB_PORT=3307"

set "DBHOME=%USERPROFILE%\.kcca-vrsp\db"
set "DATADIR=%DBHOME%\data"
set "RUNDIR=%DBHOME%\run"
set "TMPDIR_DB=%DBHOME%\tmp"
if not exist "%DATADIR%" mkdir "%DATADIR%"
if not exist "%RUNDIR%" mkdir "%RUNDIR%"
if not exist "%TMPDIR_DB%" mkdir "%TMPDIR_DB%"

rem Locate a MariaDB/MySQL server binary.
set "DBBIN="
where mariadbd >nul 2>&1 && set "DBBIN=mariadbd"
if "%DBBIN%"=="" where mysqld >nul 2>&1 && set "DBBIN=mysqld"
if "%DBBIN%"=="" (
    echo MariaDB/MySQL server was not found on PATH.
    pause
    exit /b 1
)

where mysql >nul 2>&1
if errorlevel 1 (
    echo The mysql client was not found on PATH.
    pause
    exit /b 1
)

rem Start the server if it is not already answering.
mysql -h 127.0.0.1 -P 3307 -u kcca -pkcca_dev_2026 -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    mysql -h 127.0.0.1 -P 3307 -u root -e "SELECT 1" >nul 2>&1
    if errorlevel 1 (
        if not exist "%DATADIR%\mysql" (
            echo Creating a new database directory...
            where mariadb-install-db >nul 2>&1
            if errorlevel 1 (
                echo mariadb-install-db not found on PATH.
                pause
                exit /b 1
            )
            mariadb-install-db --datadir="%DATADIR%" --auth-root-authentication-method=normal --skip-test-db >nul 2>&1
        )
        echo Starting MariaDB...
        start /B %DBBIN% --datadir="%DATADIR%" --port=3307 --bind-address=127.0.0.1 --tmpdir="%TMPDIR_DB%" --console >"%DBHOME%\mariadb.log" 2>&1
        rem Wait for it to answer.
        for /L %%i in (1,1,30) do (
            timeout /t 1 >nul 2>&1
            mysql -h 127.0.0.1 -P 3307 -u root -e "SELECT 1" >nul 2>&1
            if not errorlevel 1 goto dbup
        )
        echo MariaDB did not start. See %DBHOME%\mariadb.log
        pause
        exit /b 1
    )
)
:dbup

rem Create the application database and user (idempotent).
mysql -h 127.0.0.1 -P 3307 -u root 2>nul <NUL
mysql -h 127.0.0.1 -P 3307 -u root -e "CREATE DATABASE IF NOT EXISTS kcca_vendor_relocation CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS 'kcca'@'localhost' IDENTIFIED BY 'kcca_dev_2026'; CREATE USER IF NOT EXISTS 'kcca'@'127.0.0.1' IDENTIFIED BY 'kcca_dev_2026'; GRANT ALL PRIVILEGES ON kcca_vendor_relocation.* TO 'kcca'@'localhost'; GRANT ALL PRIVILEGES ON kcca_vendor_relocation.* TO 'kcca'@'127.0.0.1'; FLUSH PRIVILEGES;" >nul 2>&1

rem Seed once if empty.
set "COUNT=0"
for /f "delims=" %%C in ('php -r "require \"lib/bootstrap.php\"; echo (int) Database::value(\"SELECT COUNT(*) FROM markets\");" 2^>nul') do set "COUNT=%%C"
if "%COUNT%"=="0" (
    echo  The database is empty. Seeding it - this takes about 20 seconds.
    php database/seed.php
)

rem Pick a free port starting at 8080.
set "PORT=8080"
:portloop
netstat -an | findstr /R ":%PORT% " >nul 2>&1
if not errorlevel 1 (
    set /a PORT+=1
    if %PORT% gtr 8099 ( echo No free port found. & pause & exit /b 1 )
    goto portloop
)

echo.
echo  KCCA VRSP is starting on http://localhost:%PORT%
echo  Sign in: admin / KCCA VRSP 2026
echo  Portal:  http://localhost:%PORT%/pages/portal.php
echo.

rem Open the browser once the server answers.
start "" cmd /c "for /L %%i in (1,1,40) do (curl -s -o nul http://localhost:%PORT%/pages/portal.php && start http://localhost:%PORT%/pages/portal.php && exit)"

rem Serve (this window stays open; close it to stop).
php -S 0.0.0.0:%PORT% -t .
endlocal
