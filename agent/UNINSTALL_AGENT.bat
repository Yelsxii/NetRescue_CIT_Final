@echo off
title NetRescue Agent Uninstaller
net session >nul 2>&1
if %errorlevel% neq 0 (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Start-Process cmd.exe -Verb RunAs -ArgumentList '/c ""%~f0""'"
    exit /b
)
schtasks.exe /Delete /TN "NetRescue Agent" /F >nul 2>&1
rmdir /S /Q "%ProgramData%\NetRescueAgent" >nul 2>&1
echo.
echo NetRescue Agent removed.
pause
