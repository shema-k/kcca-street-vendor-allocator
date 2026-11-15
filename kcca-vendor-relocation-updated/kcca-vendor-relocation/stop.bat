@echo off
rem Stop the KCCA web server and the bundled MariaDB.
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM mariadbd.exe >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1
echo Stopped.
pause
