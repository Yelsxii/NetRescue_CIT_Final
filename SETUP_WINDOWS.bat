@echo off
setlocal
cd /d "%~dp0"
echo.
echo ============================================
echo   NetRescue CIT - Windows Setup Helper
echo ============================================
echo.
where php >nul 2>nul || (echo [ERROR] PHP is not in PATH.& pause & exit /b 1)
where composer >nul 2>nul || (echo [ERROR] Composer is not in PATH.& pause & exit /b 1)

if not exist .env (
  copy .env.example .env >nul
  echo [OK] Created .env from .env.example
)

echo [1/4] Installing PHP dependencies...
call composer install || goto :fail

echo [2/4] Generating application key...
php artisan key:generate || goto :fail

echo.
echo IMPORTANT: Create MySQL database "netrescue_db" in phpMyAdmin before continuing.
echo Confirm .env MySQL values are correct.
pause

echo [3/4] Running migrations and seed...
php artisan migrate --seed || goto :fail

echo [4/4] Running tests...
php artisan test || goto :fail

echo.
echo [DONE] Setup completed.
echo Run START_NETRESCUE.bat as Administrator to start local monitoring.
pause
exit /b 0

:fail
echo.
echo [ERROR] Setup stopped because a command failed. Read the message above.
pause
exit /b 1
