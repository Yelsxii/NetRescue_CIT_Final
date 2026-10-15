@echo off
cd /d "%~dp0"
echo NetRescue checks only this computer. Run this file as Administrator for repairs.
start "NetRescue CIT Local Web" cmd /k "php artisan serve --no-reload --host=127.0.0.1 --port=8000"
start "NetRescue CIT Local Scheduler" cmd /k "php artisan schedule:work"
timeout /t 2 >nul
start "" http://127.0.0.1:8000
