@echo off
setlocal
title NetRescue Agent Installer
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting Administrator permission...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell.exe -Verb RunAs -ArgumentList '-NoLogo -NoProfile -ExecutionPolicy Bypass -File ""%~dp0Install-Agent.ps1""'"
    exit /b
)
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-Agent.ps1"
endlocal
