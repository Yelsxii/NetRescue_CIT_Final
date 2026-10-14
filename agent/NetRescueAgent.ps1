param(
    [switch]$Once
)

$ErrorActionPreference = 'Stop'

$AgentVersion = '2.3.0'
$BaseDir = Join-Path $env:ProgramData 'NetRescueAgent'
$ConfigPath = Join-Path $BaseDir 'config.json'
$LogPath = Join-Path $BaseDir 'agent.log'

function Write-AgentLog([string]$Message) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Add-Content -Path $LogPath -Value $line -Encoding UTF8
}

function Load-AgentConfig {
    if (-not (Test-Path $ConfigPath)) {
        throw "Agent configuration not found at $ConfigPath. Re-run INSTALL_AGENT.bat."
    }
    return Get-Content $ConfigPath -Raw | ConvertFrom-Json
}

function Save-AgentConfig($Config) {
    $Config | ConvertTo-Json -Depth 6 | Set-Content -Path $ConfigPath -Encoding UTF8
}

function Invoke-AgentApi {
    param(
        [string]$Method,
        [string]$Path,
        $Body = $null,
        [switch]$NoToken
    )

    $cfg = Load-AgentConfig
    $headers = @{ 'Accept' = 'application/json' }
    if (-not $NoToken) {
        $headers['Authorization'] = "Bearer $($cfg.token)"
    }

    $uri = ($cfg.server_url.TrimEnd('/')) + $Path
    if ($null -eq $Body) {
        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -TimeoutSec 15
    }

    $json = $Body | ConvertTo-Json -Depth 8 -Compress
    return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType 'application/json' -Body $json -TimeoutSec 20
}

function Get-NetworkMetrics {
    $agentCfg = Load-AgentConfig
    $dnsDomain = if ($agentCfg.dns_test_domain) { [string]$agentCfg.dns_test_domain } else { 'example.com' }
    $target = if ($agentCfg.internet_target) { [string]$agentCfg.internet_target } else { '1.1.1.1:443' }
    $targetParts = $target.Split(':')
    $internetHost = $targetParts[0]
    $internetPort = if ($targetParts.Count -gt 1) { [int]$targetParts[1] } else { 443 }

    $errors = @()
    $interfaceName = $null
    $localIp = $null
    $gatewayIp = $null
    $dnsServers = @()
    $adapterAvailable = $false
    $adapterState = 'missing'
    $addressSource = 'unknown'
    $dnsSource = 'unknown'
    $gatewayReachable = $false
    $gatewayLatency = $null
    $internetReachable = $false
    $internetLatency = $null
    $dnsOperational = $false
    $dnsLatency = $null
    $dnsAddresses = @()
    $internetTarget = $null

    try {
        $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.NextHop -and $_.NextHop -ne '0.0.0.0' } |
            Sort-Object RouteMetric, InterfaceMetric |
            Select-Object -First 1

        if ($route) {
            $cfg = Get-NetIPConfiguration -InterfaceIndex $route.InterfaceIndex -ErrorAction SilentlyContinue
        } else {
            $cfg = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
                Where-Object { $_.NetAdapter.Status -eq 'Up' } |
                Select-Object -First 1
        }

        $adapterState = 'missing'
        $addressSource = 'unknown'
        $dnsSource = 'unknown'
        if ($cfg) {
            $adapterAvailable = $true
            $adapterState = 'up'
            $interfaceName = $cfg.InterfaceAlias
            $localIp = @($cfg.IPv4Address | ForEach-Object { $_.IPAddress } | Where-Object { $_ -and $_ -notlike '127.*' }) | Select-Object -First 1
            $gatewayIp = @($cfg.IPv4DefaultGateway | ForEach-Object { $_.NextHop } | Where-Object { $_ }) | Select-Object -First 1
            $dnsServers = @($cfg.DNSServer.ServerAddresses | Where-Object { $_ } | Select-Object -Unique)
            $ipif = Get-NetIPInterface -InterfaceIndex $cfg.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue
            if ($ipif) { $addressSource = $(if ([string]$ipif.Dhcp -eq 'Enabled') { 'dhcp' } else { 'static' }) }
            $adapter = Get-NetAdapter -InterfaceIndex $cfg.InterfaceIndex -ErrorAction SilentlyContinue
            if ($adapter.InterfaceGuid) {
                $reg = Get-ItemProperty -Path "HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters\Interfaces\$($adapter.InterfaceGuid)" -ErrorAction SilentlyContinue
                if ($reg) { $dnsSource = $(if ([string]$reg.NameServer -and [string]$reg.NameServer.Trim()) { 'static' } else { 'dhcp' }) }
            }
        } else {
            $physical = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.HardwareInterface -eq $true -and $_.Name -match 'Wi-?Fi|Wireless|WLAN|Ethernet' })
            $disabled = @($physical | Where-Object { $_.Status -eq 'Disabled' } | Select-Object -First 1)
            $disconnected = @($physical | Where-Object { $_.Status -in @('Disconnected', 'Lower Layer Down') } | Select-Object -First 1)
            if ($disabled) {
                $adapterState = 'disabled'
                $interfaceName = $disabled[0].Name
            } elseif ($disconnected) {
                $adapterState = 'disconnected'
                $interfaceName = $disconnected[0].Name
            }
        }
    } catch {
        $errors += "Configuration: $($_.Exception.Message)"
    }

    if ($adapterAvailable -and $gatewayIp) {
        try {
            $pings = @(Test-Connection -ComputerName $gatewayIp -Count 2 -ErrorAction SilentlyContinue)
            $times = @($pings | Where-Object { $_.StatusCode -eq 0 } | ForEach-Object { [double]$_.ResponseTime })
            if ($times.Count -gt 0) {
                $gatewayReachable = $true
                $gatewayLatency = [Math]::Round(($times | Measure-Object -Average).Average, 2)
            }
        } catch {
            $errors += "Gateway: $($_.Exception.Message)"
        }
    }

    if ($gatewayReachable) {
        try {
            $client = New-Object System.Net.Sockets.TcpClient
            $watch = [System.Diagnostics.Stopwatch]::StartNew()
            $task = $client.ConnectAsync($internetHost, $internetPort)
            $connected = $task.Wait(2500) -and $client.Connected
            $watch.Stop()
            if ($connected) {
                $internetReachable = $true
                $internetLatency = [Math]::Round($watch.Elapsed.TotalMilliseconds, 2)
                $internetTarget = "$internetHost`:$internetPort"
            }
            $client.Close()
        } catch {
            $errors += "Internet: $($_.Exception.Message)"
        }
    }

    if ($internetReachable) {
        try {
            $watch = [System.Diagnostics.Stopwatch]::StartNew()
            $records = @(Resolve-DnsName -Name $dnsDomain -Type A -DnsOnly -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress })
            $watch.Stop()
            $dnsAddresses = @($records | Select-Object -ExpandProperty IPAddress -Unique)
            if ($dnsAddresses.Count -gt 0) {
                $dnsOperational = $true
                $dnsLatency = [Math]::Round($watch.Elapsed.TotalMilliseconds, 2)
            }
        } catch {
            $errors += "DNS: $($_.Exception.Message)"
        }
    }

    return @{
        platform_supported = $true
        adapter_available = $adapterAvailable
        adapter_state = $adapterState
        address_source = $addressSource
        dns_source = $dnsSource
        interface_name = $interfaceName
        local_ip = $localIp
        gateway_ip = $gatewayIp
        dns_servers = @($dnsServers)
        gateway_reachable = $gatewayReachable
        gateway_latency_ms = $gatewayLatency
        internet_reachable = $internetReachable
        internet_latency_ms = $internetLatency
        internet_target = $internetTarget
        dns_operational = $dnsOperational
        dns_latency_ms = $dnsLatency
        dns_addresses = @($dnsAddresses)
        probe_errors = @($errors)
    }
}

function Send-Heartbeat {
    $os = Get-CimInstance Win32_OperatingSystem
    $payload = @{
        hostname = $env:COMPUTERNAME
        display_name = $env:COMPUTERNAME
        operating_system = $os.Caption
        os_version = $os.Version
        agent_version = $AgentVersion
    }
    return Invoke-AgentApi -Method 'POST' -Path '/api/agent/heartbeat' -Body $payload
}

function Send-Health($metrics) {
    return Invoke-AgentApi -Method 'POST' -Path '/api/agent/health' -Body $metrics
}

function Resolve-RepairAdapterName($command) {
    $name = ''
    if ($command.payload -and $command.payload.interface_name) { $name = [string]$command.payload.interface_name }
    if ($name -notmatch '^[A-Za-z0-9 ._()-]{1,64}$') { throw 'The adapter name is not valid.' }
    return $name
}

function Execute-AgentCommand($command) {
    $successful = $false
    $output = ''
    $errorText = ''

    try {
        switch ($command.command_code) {
            'flush_dns' {
                $output = (& ipconfig.exe /flushdns 2>&1 | Out-String).Trim()
                $successful = $LASTEXITCODE -eq 0
            }
            'renew_dhcp' {
                $release = $false
                if ($command.payload -and $command.payload.release) { $release = $true }
                if ($release) {
                    $output = (& ipconfig.exe /release 2>&1 | Out-String).Trim()
                    Start-Sleep -Milliseconds 400
                    $output += "`n" + (& ipconfig.exe /renew 2>&1 | Out-String).Trim()
                } else {
                    $output = (& ipconfig.exe /renew 2>&1 | Out-String).Trim()
                }
                $successful = $LASTEXITCODE -eq 0
            }
            'enable_adapter' {
                $adapterName = Resolve-RepairAdapterName $command
                Enable-NetAdapter -Name $adapterName -Confirm:$false
                $output = "Enabled $adapterName"
                $successful = $true
            }
            'restart_adapter' {
                $adapterName = Resolve-RepairAdapterName $command
                Restart-NetAdapter -Name $adapterName -Confirm:$false
                $output = "Restarted $adapterName"
                $successful = $true
            }
            default {
                $errorText = "Unsupported command: $($command.command_code)"
            }
        }
    } catch {
        $errorText = $_.Exception.Message
    }

    Start-Sleep -Milliseconds $(if ($command.command_code -eq 'renew_dhcp') { 1800 } else { 650 })
    $afterMetrics = Get-NetworkMetrics

    $payload = @{
        successful = $successful
        output = $output
        error = $errorText
        metrics = $afterMetrics
    }

    Invoke-AgentApi -Method 'POST' -Path "/api/agent/commands/$($command.id)/result" -Body $payload | Out-Null
}

function Test-CentralUrl([string]$Url) {
    try {
        $info = Invoke-RestMethod -Uri (($Url.TrimEnd('/')) + '/api/agent/discover') -TimeoutSec 4
        return $info.service -eq 'netrescue'
    } catch {
        return $false
    }
}

function Find-CentralOnLan {
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.NextHop -and $_.NextHop -ne '0.0.0.0' } |
        Sort-Object RouteMetric, InterfaceMetric |
        Select-Object -First 1
    if (-not $route) { return $null }

    $address = Get-NetIPAddress -InterfaceIndex $route.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -and $_.IPAddress -notlike '169.254.*' } |
        Select-Object -First 1
    if (-not $address) { return $null }

    $localIp = [string]$address.IPAddress
    $localGateway = [string]$route.NextHop
    $octets = $localIp.Split('.')
    if ($octets.Count -ne 4) { return $null }
    $prefix = "$($octets[0]).$($octets[1]).$($octets[2])"

    $pool = [runspacefactory]::CreateRunspacePool(1, 48)
    $pool.Open()
    $jobs = @()
    foreach ($hostNumber in 1..254) {
        $candidate = "$prefix.$hostNumber"
        if ($candidate -eq $localIp) { continue }
        $pipe = [powershell]::Create()
        $pipe.RunspacePool = $pool
        [void]$pipe.AddScript({
            param($ip)
            $client = New-Object System.Net.Sockets.TcpClient
            try {
                $iar = $client.BeginConnect($ip, 8000, $null, $null)
                if (-not $iar.AsyncWaitHandle.WaitOne(150)) { return $null }
                $client.EndConnect($iar)
                return $ip
            } catch {
                return $null
            } finally {
                $client.Close()
            }
        }).AddArgument($candidate)
        $jobs += [pscustomobject]@{ Pipe = $pipe; Handle = $pipe.BeginInvoke() }
    }

    $open = @()
    foreach ($job in $jobs) {
        $value = $job.Pipe.EndInvoke($job.Handle)
        if ($value -and $value[0]) { $open += [string]$value[0] }
        $job.Pipe.Dispose()
    }
    $pool.Close()
    $pool.Dispose()

    foreach ($ip in $open) {
        try {
            $info = Invoke-RestMethod -Uri "http://$ip:8000/api/agent/discover" -TimeoutSec 3
            if ($info.service -eq 'netrescue' -and [string]$info.gateway -eq $localGateway -and $info.url) {
                return [string]$info.url
            }
        } catch {
        }
    }

    return $null
}

function Sync-CentralServer {
    $cfg = Load-AgentConfig
    if (Test-CentralUrl ([string]$cfg.server_url)) { return }

    Write-AgentLog 'Saved Central address did not answer. Looking for this laptop on the current Wi-Fi.'
    $found = Find-CentralOnLan
    if (-not $found) {
        throw 'NetRescue Central is not on this same router.'
    }

    $cfg.server_url = $found
    Save-AgentConfig $cfg
    Write-AgentLog "Central address updated to $found"
}

function Invoke-AgentCycle {
    Sync-CentralServer
    $heartbeat = Send-Heartbeat
    if ($heartbeat.probe_config) {
        $cfg = Load-AgentConfig
        $cfg | Add-Member -NotePropertyName dns_test_domain -NotePropertyValue $heartbeat.probe_config.dns_test_domain -Force
        $cfg | Add-Member -NotePropertyName internet_target -NotePropertyValue $heartbeat.probe_config.internet_target -Force
        Save-AgentConfig $cfg
    }

    $metrics = Get-NetworkMetrics
    $health = Send-Health $metrics
    Write-AgentLog "Health sent: $($health.health_score)% $($health.diagnosis_title)"

    $response = Invoke-AgentApi -Method 'GET' -Path '/api/agent/commands'
    foreach ($command in @($response.commands)) {
        Write-AgentLog "Executing approved command $($command.command_code) (#$($command.id))."
        Execute-AgentCommand $command
    }
}

New-Item -ItemType Directory -Force -Path $BaseDir | Out-Null

if ($Once) {
    try {
        Write-AgentLog 'Agent cycle starting.'
        Invoke-AgentCycle
        Write-AgentLog 'Agent cycle completed.'
        exit 0
    } catch {
        Write-AgentLog "ERROR: $($_.Exception.Message)"
        exit 1
    }
}

Write-AgentLog 'Agent monitor started. It keeps checking while Windows is awake.'
while ($true) {
    try {
        Invoke-AgentCycle
    } catch {
        Write-AgentLog "ERROR: $($_.Exception.Message) The next check will retry."
    }
    Start-Sleep -Seconds 60
}
