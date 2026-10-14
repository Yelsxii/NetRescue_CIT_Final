@echo off
cd /d "%~dp0"
start "NetRescue CIT Web" cmd /k "php artisan serve --host=0.0.0.0 --port=8000"
start "NetRescue CIT Scheduler" cmd /k "php artisan schedule:work"
timeout /t 2 >nul
start "" http://127.0.0.1:8000
