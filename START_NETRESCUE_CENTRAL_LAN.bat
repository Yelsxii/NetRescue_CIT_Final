@echo off
title NetRescue CIT
cd /d "%~dp0"
echo ============================================================
echo NetRescue CIT - LAN Mode
echo ============================================================
echo.
echo Starting Laravel on all network interfaces...
echo Keep this window open.
echo.
start "NetRescue CIT Scheduler" cmd /k "cd /d "%~dp0" && php artisan schedule:work"
php artisan serve --host=0.0.0.0 --port=8000
