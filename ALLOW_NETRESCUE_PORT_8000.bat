@echo off
title NetRescue Central Firewall Setup
net session >nul 2>&1
if %errorlevel% neq 0 (
    powershell.exe -NoProfile -Command "Start-Process cmd.exe -Verb RunAs -ArgumentList '/c ""%~f0""'"
    exit /b
)
netsh advfirewall firewall delete rule name="NetRescue Central TCP 8000" >nul 2>&1
netsh advfirewall firewall add rule name="NetRescue Central TCP 8000" dir=in action=allow protocol=TCP localport=8000
echo.
echo Windows Firewall now allows inbound TCP port 8000 for NetRescue Central.
pause
