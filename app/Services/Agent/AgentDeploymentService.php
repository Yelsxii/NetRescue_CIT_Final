<?php

namespace App\Services\Agent;

use App\Models\Device;
use App\Services\Network\NetworkProbeService;
use Illuminate\Http\Request;

class AgentDeploymentService
{
    public function __construct(
        private readonly NetworkProbeService $networkProbe,
    ) {
    }

    /**
     * Resolve the URL that endpoint PCs on the LAN should use to reach
     * NetRescue Central. A configured environment override wins, followed by
     * the browser host when it is already a LAN address, the last measured
     * Central Host IPv4, and finally a fresh Windows configuration probe.
     */
    public function centralUrl(Request $request): string
    {
        $configured = trim((string) config('netrescue.agent.central_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = $request->getScheme() ?: 'http';
        $port = $request->getPort() ?: 8000;
        $requestHost = $request->getHost();

        if ($this->isUsableLanHost($requestHost)) {
            return $this->buildUrl($scheme, $requestHost, $port);
        }

        $presence = app(LanPresenceService::class)->snapshot();

        if ($this->isUsableLanHost((string) ($presence['local_ip'] ?? ''))) {
            return $this->buildUrl($scheme, (string) $presence['local_ip'], $port);
        }

        $localDevice = Device::localHost();
        if ($this->isUsableLanHost((string) $localDevice->last_ip)) {
            return $this->buildUrl($scheme, (string) $localDevice->last_ip, $port);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $configuration = $this->networkProbe->collectConfiguration();
            $localIp = (string) ($configuration['local_ip'] ?? '');

            if ($this->isUsableLanHost($localIp)) {
                return $this->buildUrl($scheme, $localIp, $port);
            }
        }

        $resolvedHost = gethostbyname(gethostname() ?: 'localhost');
        if ($this->isUsableLanHost($resolvedHost)) {
            return $this->buildUrl($scheme, $resolvedHost, $port);
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /**
     * Build the preconfigured Windows launcher downloaded from the Devices
     * page. The user only double-clicks this file and approves UAC; no server
     * URL or enrollment code has to be typed on the endpoint.
     */
    public function installerBatch(string $centralUrl, string $enrollmentCode): string
    {
        $centralUrl = rtrim($centralUrl, '/');
        $safeUrl = str_replace(['%', '"'], ['%%', ''], $centralUrl);
        $safeCode = str_replace(['%', '"'], ['%%', ''], $enrollmentCode);

        $template = <<<'BAT'
@echo off
setlocal EnableExtensions
title NetRescue Agent - One-Click Installer

set "NR_SERVER=__NR_SERVER__"
set "NR_ENROLLMENT=__NR_ENROLLMENT__"
set "NR_TEMP=%TEMP%\NetRescueAgentSetup"

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting Administrator permission...
    powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo.
echo ================================================
echo        NetRescue Agent - One-Click Setup
echo ================================================
echo.
echo Central server: %NR_SERVER%
echo Computer: %COMPUTERNAME%
echo.
echo No Laravel, XAMPP, Composer, MySQL, or manual code entry is required.
echo.

if not exist "%NR_TEMP%" mkdir "%NR_TEMP%" >nul 2>&1

echo [1/3] Downloading the deployment scripts from NetRescue Central...
powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference='Stop'; $ProgressPreference='SilentlyContinue'; Invoke-WebRequest -UseBasicParsing -Uri '%NR_SERVER%/downloads/netrescue-agent/install-script' -OutFile '%NR_TEMP%\Install-Agent.ps1'; Invoke-WebRequest -UseBasicParsing -Uri '%NR_SERVER%/downloads/netrescue-agent/agent-script' -OutFile '%NR_TEMP%\NetRescueAgent.ps1'"

if %errorlevel% neq 0 (
    echo.
    echo ERROR: This PC could not download the NetRescue Agent from %NR_SERVER%.
    echo Make sure NetRescue Central is running in LAN mode and Windows Firewall allows port 8000.
    echo.
    pause
    exit /b 1
)

echo [2/3] Registering this computer automatically...
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%NR_TEMP%\Install-Agent.ps1" -ServerUrl "%NR_SERVER%" -EnrollmentCode "%NR_ENROLLMENT%" -Unattended

if %errorlevel% neq 0 (
    echo.
    echo ERROR: NetRescue Agent installation did not complete.
    echo The deployment authorization may have expired, or Central may be unreachable.
    echo Download a fresh One-Click Installer from Devices and try again.
    echo.
    pause
    exit /b 1
)

echo [3/3] Installation complete.
echo.
echo This PC is now enrolled in NetRescue Central and will report automatically.
echo You may close this window.
timeout /t 5 /nobreak >nul
endlocal
BAT;

        return str_replace(
            ['__NR_SERVER__', '__NR_ENROLLMENT__'],
            [$safeUrl, $safeCode],
            $template
        );
    }

    private function isUsableLanHost(string $host): bool
    {
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return !str_starts_with($host, '169.254.');
        }

        // A DNS/host name can also be valid on a managed LAN.
        return (bool) preg_match('/^[a-z0-9][a-z0-9.-]*$/i', $host);
    }

    private function buildUrl(string $scheme, string $host, int $port): string
    {
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $portPart = $defaultPort ? '' : ':'.$port;

        return "{$scheme}://{$host}{$portPart}";
    }
}
