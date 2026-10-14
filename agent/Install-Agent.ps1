param(
    [string]$ServerUrl,
    [string]$EnrollmentCode,
    [switch]$Unattended
)

$ErrorActionPreference = 'Stop'
$AgentVersion = '2.3.0'
$SourceDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BaseDir = Join-Path $env:ProgramData 'NetRescueAgent'
$ConfigPath = Join-Path $BaseDir 'config.json'
$AgentPath = Join-Path $BaseDir 'NetRescueAgent.ps1'
$TaskName = 'NetRescue Agent'

function Finish-WithError([string]$Message) {
    Write-Host ''
    Write-Host "ERROR: $Message" -ForegroundColor Red
    if (-not $Unattended) {
        Read-Host 'Press Enter to close'
    }
    exit 1
}

try {
    if (-not $ServerUrl) {
        if ($Unattended) {
            throw 'The preconfigured Central URL is missing. Download a fresh one-click installer from NetRescue Central.'
        }
        $ServerUrl = Read-Host 'NetRescue Central URL (example: http://192.168.1.7:8000)'
    }

    if (-not $EnrollmentCode) {
        if ($Unattended) {
            throw 'The deployment authorization is missing. Download a fresh one-click installer from NetRescue Central.'
        }
        $EnrollmentCode = Read-Host 'Enrollment code shown in NetRescue Central'
    }

    $ServerUrl = $ServerUrl.Trim().TrimEnd('/')
    $EnrollmentCode = $EnrollmentCode.Trim()

    if ($ServerUrl -notmatch '^https?://') {
        throw 'Server URL must start with http:// or https://.'
    }

    Write-Host ''
    Write-Host 'Connecting this computer to NetRescue Central...' -ForegroundColor Cyan

    # Verify the central Laravel app can be reached before changing the endpoint.
    try {
        Invoke-WebRequest -UseBasicParsing -Uri "$ServerUrl/up" -TimeoutSec 10 | Out-Null
    } catch {
        throw "NetRescue Central could not be reached at $ServerUrl. Make sure Central is running in LAN mode and its firewall allows the configured port."
    }

    New-Item -ItemType Directory -Force -Path $BaseDir | Out-Null
    Copy-Item (Join-Path $SourceDir 'NetRescueAgent.ps1') $AgentPath -Force

    $uuidPath = Join-Path $BaseDir 'device.uuid'
    if (Test-Path $uuidPath) {
        $deviceUuid = (Get-Content $uuidPath -Raw).Trim()
    } else {
        $deviceUuid = [guid]::NewGuid().ToString()
        Set-Content -Path $uuidPath -Value $deviceUuid -Encoding ASCII
    }

    $os = Get-CimInstance Win32_OperatingSystem
    $gatewayIp = $null
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.NextHop -and $_.NextHop -ne '0.0.0.0' } |
        Sort-Object RouteMetric, InterfaceMetric |
        Select-Object -First 1
    if ($route) { $gatewayIp = [string]$route.NextHop }

    $registerBody = @{
        enrollment_code = $EnrollmentCode
        device_uuid = $deviceUuid
        hostname = $env:COMPUTERNAME
        display_name = $env:COMPUTERNAME
        operating_system = $os.Caption
        os_version = $os.Version
        agent_version = $AgentVersion
        gateway_ip = $gatewayIp
    } | ConvertTo-Json -Compress

    try {
        $response = Invoke-RestMethod `
            -Uri "$ServerUrl/api/agent/register" `
            -Method POST `
            -ContentType 'application/json' `
            -Headers @{ Accept = 'application/json' } `
            -Body $registerBody `
            -TimeoutSec 20
    } catch {
        $details = $_.ErrorDetails.Message
        if (-not $details) { $details = $_.Exception.Message }
        throw "Central rejected endpoint registration. $details"
    }

    if (-not $response.token) {
        throw 'Central did not return an endpoint authentication token.'
    }

    $config = @{
        server_url = $ServerUrl
        device_uuid = $deviceUuid
        token = $response.token
        agent_version = $AgentVersion
        dns_test_domain = $response.probe_config.dns_test_domain
        internet_target = $response.probe_config.internet_target
    } | ConvertTo-Json -Depth 4

    Set-Content -Path $ConfigPath -Value $config -Encoding UTF8

    # One resident process checks about once a minute. It starts at boot, starts
    # again after wake if Windows ended it, and Windows restarts it if it stops.
    # schtasks is only the fallback, using a path that has no spaces.
    $taskArgument = "-NoLogo -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$AgentPath`""
    $taskReady = $false

    try {
        $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $taskArgument
        $triggers = @(New-ScheduledTaskTrigger -AtStartup)
        try {
            $wakeClass = Get-CimClass -ClassName MSFT_TaskEventTrigger -Namespace Root/Microsoft/Windows/TaskScheduler
            $wakeTrigger = New-CimInstance -CimClass $wakeClass -ClientOnly
            $wakeTrigger.Enabled = $true
            $wakeTrigger.Subscription = "<QueryList><Query Id='0' Path='System'><Select Path='System'>*[System[Provider[@Name='Microsoft-Windows-Power-Troubleshooter'] and (EventID=1)]]</Select></Query></QueryList>"
            $triggers += $wakeTrigger
        } catch {
            Write-Host 'The wake trigger could not be added. The agent still starts at boot and keeps running.'
        }
        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
        $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)
        $settings.ExecutionTimeLimit = 'PT0S'
        Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $triggers -Principal $principal -Settings $settings -Force | Out-Null
        $taskReady = $true
    } catch {
        $taskCommand = "powershell.exe -NoLogo -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File $AgentPath"
        & schtasks.exe /Create /TN $TaskName /SC ONSTART /TR $taskCommand /RU SYSTEM /RL HIGHEST /F | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $taskReady = $true
        }
    }

    if (-not $taskReady) {
        throw 'Windows could not create the automatic NetRescue Agent scheduled task.'
    }

    Write-Host 'Running the first real network check...' -ForegroundColor Cyan
    & powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File $AgentPath -Once

    if ($LASTEXITCODE -ne 0) {
        throw "The agent was registered, but its first monitoring cycle failed. Check $BaseDir\agent.log."
    }

    Start-ScheduledTask -TaskName $TaskName

    Write-Host ''
    Write-Host 'NetRescue Agent installed successfully.' -ForegroundColor Green
    Write-Host "Device: $env:COMPUTERNAME"
    Write-Host "Central: $ServerUrl"
    Write-Host 'The agent stays running in the background.'
    Write-Host 'It checks this computer about once a minute while Windows is awake, including after a restart or after the PC wakes.'
    Write-Host 'Run this installer again only when you want to replace the agent. Sleep does not require a new install.'
    Write-Host ''

    if (-not $Unattended) {
        Read-Host 'Press Enter to close'
    }

    exit 0
} catch {
    Finish-WithError $_.Exception.Message
}
