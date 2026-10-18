<#
One fault per invocation. Run in Administrator Windows PowerShell on the laptop.
Inspect is read-only. Apply arms an independent rollback task before mutation.
With -ExerciseApp, use the real CSRF-protected Check/Repair routes once, record
their HTML and database evidence, restore in finally, then run a closing Check.
#>
[CmdletBinding()]
param(
    [ValidateSet('Inspect', 'Apply', 'Restore')][string]$Mode = 'Inspect',
    [ValidateSet('AdapterDisabled', 'NoIpv4', 'GatewayMissing', 'GatewayTestsBlocked', 'GatewayPingBlocked', 'WanTargetsBlocked', 'DnsServer')][string]$Fault,
    [ValidatePattern('^[A-Za-z0-9 ._()\-]{1,64}$')][string]$InterfaceAlias = 'Wi-Fi',
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$PhpPath = 'php',
    [ValidateRange(180, 1800)][int]$RollbackSeconds = 600,
    [string]$StatePath,
    [switch]$ExerciseApp
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))

function Get-AppEvidence {
    $text = & $PhpPath (Join-Path $PSScriptRoot 'read-evidence.php')
    if ($LASTEXITCODE -ne 0) { throw 'Could not read NetRescue evidence through PHP.' }
    return (($text | Out-String) | ConvertFrom-Json)
}

function Invoke-AppAction([string]$Path, [string]$OutputPath) {
    # Re-read a real form token before each request. No CSRF bypass or QA route.
    $page = Invoke-WebRequest -Uri ($BaseUrl + '/') -WebSession $script:qaSession -UseBasicParsing -TimeoutSec 30
    $token = [regex]::Match($page.Content, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
    if (-not $token) { throw 'NetRescue did not return a CSRF token. No action was sent.' }
    $watch = [Diagnostics.Stopwatch]::StartNew()
    $response = Invoke-WebRequest -Uri ($BaseUrl + $Path) -Method Post -Body @{ _token = $token } -WebSession $script:qaSession -UseBasicParsing -TimeoutSec 480
    $watch.Stop()
    if ($OutputPath) { [IO.File]::WriteAllText($OutputPath, $response.Content) }
    return [pscustomobject]@{ Path = $Path; ElapsedMs = $watch.ElapsedMilliseconds; Status = [int]$response.StatusCode }
}

function Save-AppEvidence([string]$Name, [string]$Directory) {
    $evidence = Get-AppEvidence
    $evidence | ConvertTo-Json -Depth 20 | Set-Content -LiteralPath (Join-Path $Directory ($Name + '.json')) -Encoding UTF8
    return $evidence
}

function Get-DnsBaseline($Adapter) {
    $result = @{}
    foreach ($family in @('IPv4', 'IPv6')) {
        $protocol = if ($family -eq 'IPv4') { 'Tcpip' } else { 'Tcpip6' }
        $guid = '{' + ([string]$Adapter.InterfaceGuid).Trim('{}') + '}'
        $path = 'HKLM:\SYSTEM\CurrentControlSet\Services\' + $protocol + '\Parameters\Interfaces\' + $guid
        $manual = (Get-ItemProperty -LiteralPath $path -Name NameServer -ErrorAction SilentlyContinue).NameServer
        $servers = @((Get-DnsClientServerAddress -InterfaceIndex $Adapter.InterfaceIndex -AddressFamily $family -ErrorAction Stop).ServerAddresses)
        if ([string]$manual -and -not $servers.Count) { throw 'Manual DNS has no readable servers; the test cannot guarantee restoration.' }
        $result[$family] = @{ Automatic = [string]::IsNullOrWhiteSpace([string]$manual); Servers = $servers }
    }
    return $result
}

function New-SecureRunDirectory([string]$RunId) {
    $directory = Join-Path $env:ProgramData ('NetRescueQA\' + $RunId)
    $null = New-Item -ItemType Directory -Path $directory -Force
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
        $identity = New-Object Security.Principal.SecurityIdentifier($sid)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $directory -AclObject $acl
    return $directory
}

function Add-QABlock([string]$Name, [string[]]$Addresses, [string]$Protocol = 'Any') {
    $parameters = @{ Name = $Name; DisplayName = $Name; Group = $script:qaState.TaskName; Direction = 'Outbound';
        Action = 'Block'; Profile = 'Any'; Enabled = 'True'; InterfaceAlias = $InterfaceAlias;
        RemoteAddress = $Addresses; Protocol = $Protocol; ErrorAction = 'Stop' }
    if ($Protocol -eq 'ICMPv4') { $parameters.IcmpType = 8 }
    $null = New-NetFirewallRule @parameters
}

function Get-QAIpv4Snapshot($Adapter) {
    # A new .NET snapshot avoids retaining the pre-release adapter configuration.
    $matches = @([Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() | Where-Object {
        ([string]$_.Id).Trim('{}') -eq ([string]$Adapter.InterfaceGuid).Trim('{}')
    })
    if ($matches.Count -ne 1 -or $matches[0].Name -ne $Adapter.Name) { throw 'The selected adapter changed identity while observing IPv4 configuration.' }
    $current = $matches[0]
    $properties = $current.GetIPProperties()
    $addresses = @($properties.UnicastAddresses | Where-Object {
        $_.Address.AddressFamily -eq [Net.Sockets.AddressFamily]::InterNetwork
    } | ForEach-Object {
        [pscustomobject]@{ IPAddress=$_.Address.ToString(); PrefixOrigin=[string]$_.PrefixOrigin;
            AddressState=[string]$_.DuplicateAddressDetectionState }
    })
    $usable = @($addresses | Where-Object {
        $_.IPAddress -notlike '127.*' -and $_.IPAddress -ne '0.0.0.0' -and $_.IPAddress -notlike '169.254.*' -and
        $_.AddressState -notin @('Invalid', 'Tentative', 'Duplicate')
    } | ForEach-Object { $_.IPAddress })
    return [pscustomobject]@{ CapturedAt=[DateTime]::UtcNow.ToString('o'); InterfaceAlias=$current.Name;
        AdapterGuid=$current.Id; AdapterState=[string]$current.OperationalStatus;
        DhcpEnabled=$properties.GetIPv4Properties().IsDhcpEnabled; Addresses=$addresses; UsableAddresses=$usable;
        GatewayAddresses=@($properties.GatewayAddresses | Where-Object { $_.Address.AddressFamily -eq [Net.Sockets.AddressFamily]::InterNetwork } | ForEach-Object { $_.Address.ToString() }) }
}

function Assert-QANoIpv4Baseline($Snapshot) {
    $usable = @($Snapshot.UsableAddresses)
    $dhcpAddresses = @($Snapshot.Addresses | Where-Object { $_.IPAddress -in $usable -and $_.PrefixOrigin -eq 'Dhcp' })
    $manualAddresses = @($Snapshot.Addresses | Where-Object { $_.PrefixOrigin -eq 'Manual' })
    if ($Snapshot.AdapterState -ne 'Up' -or -not $Snapshot.DhcpEnabled -or $usable.Count -ne 1 -or $dhcpAddresses.Count -ne 1 -or $manualAddresses.Count) {
        throw 'NoIpv4 requires one usable DHCP IPv4 address on the selected Up adapter, without additional manual IPv4 addresses. Nothing was released.'
    }
}

function Invoke-TargetDhcpRelease([string]$Alias) {
    $watch = [Diagnostics.Stopwatch]::StartNew()
    $output = & "$env:SystemRoot\System32\ipconfig.exe" /release $Alias 2>&1
    $exitCode = $LASTEXITCODE
    $watch.Stop()
    return [pscustomobject]@{ ExitCode=$exitCode; Output=($output | Out-String).Trim(); ElapsedMs=$watch.ElapsedMilliseconds }
}

function Invoke-QANoIpv4Fault($Adapter, [string]$Directory) {
    $release = Invoke-TargetDhcpRelease $Adapter.Name
    $release | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $Directory 'noipv4-release.json') -Encoding UTF8
    $snapshot = Get-QAIpv4Snapshot $Adapter
    $snapshot | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $Directory 'noipv4-after-release.json') -Encoding UTF8
    if ($release.ExitCode -ne 0) { throw ('The targeted DHCP release failed: ' + $release.Output) }
    if ($snapshot.AdapterState -ne 'Up' -or -not $snapshot.DhcpEnabled -or @($snapshot.UsableAddresses).Count) {
        throw 'NoIpv4 was not reproduced: the selected adapter is not Up with DHCP and no usable IPv4 address. Review noipv4-release.json and noipv4-after-release.json; this run will be restored.'
    }
    Write-Output ('NoIpv4 confirmed at ' + $snapshot.CapturedAt + ': ' + $Adapter.Name + ' is Up with DHCP and no usable IPv4 address.')
}

function Apply-QAFault($Adapter, $Routes, [string[]]$Targets) {
    switch ($Fault) {
        'AdapterDisabled' { $Adapter | Disable-NetAdapter -Confirm:$false -ErrorAction Stop }
        'NoIpv4' { Invoke-QANoIpv4Fault $Adapter $script:qaRunDirectory }
        'GatewayMissing' { $Routes | Remove-NetRoute -Confirm:$false -ErrorAction Stop }
        'DnsServer' {
            Set-DnsClientServerAddress -InterfaceIndex $Adapter.InterfaceIndex -ServerAddresses @('192.0.2.53', '2001:db8::53') -ErrorAction Stop
            Clear-DnsClientCache -ErrorAction Stop
        }
        'GatewayPingBlocked' { Add-QABlock $script:qaState.FirewallRuleNames[0] @($Routes[0].NextHop) 'ICMPv4' }
        'WanTargetsBlocked' { Add-QABlock $script:qaState.FirewallRuleNames[0] $Targets }
        'GatewayTestsBlocked' { Add-QABlock $script:qaState.FirewallRuleNames[0] @($Targets + @($Routes | ForEach-Object { $_.NextHop }) | Select-Object -Unique) }
    }
}

function Assert-QAGatewayRoutes($Routes, $PersistentRoutes, $DhcpConfiguration) {
    # Windows can label a DHCP gateway NetMgmt. Match its DHCP gateway settings
    # instead of treating that protocol label alone as proof of a manual route.
    # Renewal cannot reproduce arbitrary manual routes or customized metrics.
    $active = @($Routes)
    $persistent = @($PersistentRoutes)
    $dhcpGateways = @($DhcpConfiguration.DhcpDefaultGateway | Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) })
    $manualGateways = @($DhcpConfiguration.DefaultGateway | Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) })
    if ($active.Count -ne 1 -or $persistent.Count -ne 0 -or $DhcpConfiguration.EnableDHCP -ne 1 -or
        $dhcpGateways.Count -ne 1 -or $manualGateways.Count -ne 0 -or
        [string]$active[0].NextHop -ne [string]$dhcpGateways[0] -or
        [string]$active[0].Protocol -notin @('Dhcp', 'NetMgmt') -or [int]$active[0].RouteMetric -ne 0) {
        throw 'GatewayMissing requires one unmodified DHCP default gateway with no manual or persistent default routes. This baseline is ambiguous; no route was removed.'
    }
}

if ($env:OS -ne 'Windows_NT') { throw 'Run this runner on the Windows laptop. This session does not change a remote laptop.' }
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Open Windows PowerShell as Administrator.' }

if ($Mode -eq 'Restore') {
    if (-not $StatePath) { throw 'Restore requires the exact -StatePath printed by Apply.' }
    & (Join-Path $PSScriptRoot 'Restore-NetRescueFault.ps1') -StatePath $StatePath
    return
}

$uri = [uri]$BaseUrl
if ($uri.Scheme -ne 'http' -or $uri.Host -notin @('127.0.0.1', 'localhost', '[::1]', '::1') -or $uri.UserInfo -or $uri.AbsolutePath -ne '/' -or $uri.Query -or $uri.Fragment) {
    throw 'Use the local NetRescue root URL, such as http://127.0.0.1:8000.'
}
$BaseUrl = $BaseUrl.TrimEnd('/')
if (-not (Get-Command $PhpPath -ErrorAction SilentlyContinue)) { throw 'PHP was not found. Supply -PhpPath C:\xampp\php\php.exe if needed.' }
$adapters = @(Get-NetAdapter -ErrorAction Stop | Where-Object { $_.Name -eq $InterfaceAlias })
if ($adapters.Count -ne 1) { throw 'The exact adapter alias was not found uniquely.' }
$adapter = $adapters[0]
$ipInterface = Get-NetIPInterface -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 -ErrorAction Stop
$routes = @(Get-NetRoute -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 -PolicyStore ActiveStore -ErrorAction Stop | Where-Object { $_.DestinationPrefix -eq '0.0.0.0/0' })
$dns = Get-DnsBaseline $adapter
$evidence = Get-AppEvidence
if ($Mode -eq 'Inspect') {
    [pscustomobject]@{ Adapter = $adapter.Name; State = [string]$adapter.Status; Dhcp = [string]$ipInterface.Dhcp;
        DefaultRoutes = @($routes | Select-Object NextHop, RouteMetric); Dns = $dns; App = $evidence } | ConvertTo-Json -Depth 20
    return
}
if (-not $Fault) { throw 'Apply requires one -Fault. Nothing was changed.' }
if ($ExerciseApp -and $RollbackSeconds -lt 600) { throw '-ExerciseApp needs at least 600 rollback seconds so verification can finish first.' }
if ($evidence.settings.monitoring_enabled) { throw 'Pause scheduled checks in NetRescue Settings before fault QA to keep each measurement tied to one test.' }
$alreadyArmed = @(Get-ScheduledTask -TaskName 'NetRescueQA-*' -ErrorAction SilentlyContinue)
if ($alreadyArmed.Count) { throw 'A QA rollback is already armed. Restore that test before injecting another fault.' }
$up = @([Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() | Where-Object {
    $_.OperationalStatus -eq 'Up' -and $_.NetworkInterfaceType -ne 'Loopback' -and $_.Supports([Net.NetworkInformation.NetworkInterfaceComponent]::IPv4)
})
if ($up.Count -ne 1 -or $up[0].Name -ne $InterfaceAlias -or $adapter.Status -ne 'Up') {
    throw 'This runner requires exactly one active IPv4 adapter and no active VPN/alternate path. Use an isolated lab for multiple adapters.'
}
if (-not $routes.Count) { throw 'A baseline IPv4 default route is required.' }
if ($Fault -in @('NoIpv4', 'GatewayMissing') -and [string]$ipInterface.Dhcp -ne 'Enabled') { throw 'This test requires DHCP; manual IP settings will not be replaced.' }
if ($Fault -eq 'NoIpv4') { Assert-QANoIpv4Baseline (Get-QAIpv4Snapshot $adapter) }
if ($Fault -eq 'GatewayMissing') {
    $persistentRoutes = @(Get-NetRoute -PolicyStore PersistentStore -ErrorAction Stop | Where-Object { $_.InterfaceIndex -eq $adapter.InterfaceIndex -and $_.DestinationPrefix -eq '0.0.0.0/0' })
    $guid = '{' + ([string]$adapter.InterfaceGuid).Trim('{}') + '}'
    $dhcpConfiguration = Get-ItemProperty -LiteralPath ('HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters\Interfaces\' + $guid) -ErrorAction Stop
    Assert-QAGatewayRoutes $routes $persistentRoutes $dhcpConfiguration
}

$script:qaSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-AppAction '/health-checks' ''
$baseline = Get-AppEvidence
if ($baseline.check.diagnosis_code -ne 'healthy' -or $baseline.check.interface_name -ne $InterfaceAlias) { throw 'The app must freshly report this adapter healthy before injecting a new fault.' }
$targets = @($baseline.settings.internet_targets | ForEach-Object {
    if ($_ -notmatch '^(\d{1,3}(?:\.\d{1,3}){3}):\d+$') { throw 'Expected numeric IPv4 Internet targets.' }
    $Matches[1]
} | Select-Object -Unique)
if (-not $targets.Count) { throw 'No external targets are configured.' }

$runId = [guid]::NewGuid().ToString('N')
$directory = New-SecureRunDirectory $runId
$script:qaRunDirectory = $directory
$StatePath = Join-Path $directory 'state.json'
$restoreScript = Join-Path $directory 'Restore-NetRescueFault.ps1'
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'Restore-NetRescueFault.ps1') -Destination $restoreScript
$script:qaState = [pscustomobject]@{
    Version = 1; TaskName = 'NetRescueQA-' + $runId; Fault = $Fault; AdapterGuid = [string]$adapter.InterfaceGuid;
    InterfaceAlias = $adapter.Name; WasEnabled = $true; Dhcp = [string]$ipInterface.Dhcp; Dns = $dns;
    DefaultRoutes = @($routes | Select-Object NextHop, RouteMetric); FirewallRuleNames = @('NetRescueQA-' + $runId + '-block');
    CreatedAt = [DateTime]::UtcNow.ToString('o'); RollbackDueAt = [DateTime]::UtcNow.AddSeconds($RollbackSeconds).ToString('o'); RestoredAt = $null
}
$script:qaState | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $StatePath -Encoding UTF8
$baseline | ConvertTo-Json -Depth 20 | Set-Content -LiteralPath (Join-Path $directory 'baseline.json') -Encoding UTF8
$exe = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$arguments = '-NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $restoreScript + '" -StatePath "' + $StatePath + '"'
$action = New-ScheduledTaskAction -Execute $exe -Argument $arguments
$trigger = New-ScheduledTaskTrigger -Once -At ([DateTime]::Parse($script:qaState.RollbackDueAt).ToLocalTime())
$taskPrincipal = New-ScheduledTaskPrincipal -UserId 'S-1-5-18' -LogonType ServiceAccount -RunLevel Highest
$taskSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 4) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
$null = Register-ScheduledTask -TaskName $script:qaState.TaskName -Action $action -Trigger $trigger -Principal $taskPrincipal -Settings $taskSettings -ErrorAction Stop
$null = Get-ScheduledTask -TaskName $script:qaState.TaskName -ErrorAction Stop
Write-Output ('Rollback armed. StatePath: ' + $StatePath)
Write-Output ('Safety rollback due: ' + $script:qaState.RollbackDueAt + ' (UTC).')
if ($Fault -eq 'NoIpv4' -and -not $ExerciseApp) {
    Write-Output 'Keep the dashboard ready: Windows can reacquire IPv4 itself. After confirmation, click Run Check and the offered Run repair promptly; review the incident details after repair.'
}

$applied = $false
$steps = @()
try {
    Apply-QAFault $adapter $routes $targets
    $applied = $true
    Write-Output ('Applied ' + $Fault + ' to ' + $InterfaceAlias + '. Open ' + $BaseUrl + ' to observe the incident.')
    if (-not $ExerciseApp) { Write-Output 'Now click Run Check in NetRescue, then Run repair once if offered. This runner has not requested a post-injection Check or repair. Restore this exact StatePath after the demo.' }
    if ($ExerciseApp) {
        $steps += Invoke-AppAction '/health-checks' (Join-Path $directory 'detected.html')
        $detected = Save-AppEvidence 'detected' $directory
        $expected = switch ($Fault) {
            'AdapterDisabled' { @('adapter_disabled') }
            'NoIpv4' { @('ip_configuration_missing', 'dhcp_issue') }
            'GatewayMissing' { @('gateway_missing') }
            'GatewayTestsBlocked' { @('gateway_unreachable') }
            'GatewayPingBlocked' { @('gateway_ping_unanswered') }
            'WanTargetsBlocked' { @('wan_unreachable') }
            'DnsServer' { @('dns_failure') }
        }
        if ($detected.check.diagnosis_code -notin $expected) { throw ('Fault was not reproduced as expected. Observed: ' + $detected.check.diagnosis_code + '. No repair was requested.') }
        if ($detected.active_incident.can_repair) {
            $incidentId = [int]$detected.active_incident.id
            $steps += Invoke-AppAction ('/incidents/' + $incidentId + '/recover') (Join-Path $directory 'repair-result.html')
            $after = Save-AppEvidence 'after-repair' $directory
            if ($after.latest_recovery.incident_id -ne $incidentId) { throw 'No matching repair result was recorded.' }
            Write-Output $after.latest_recovery.outcome_summary
        } else {
            Write-Output ('Human action expected: ' + $detected.active_incident.next_step)
        }
    }
} finally {
    # Evidence I/O must never prevent restoration after a partial injection.
    try { ConvertTo-Json -InputObject @($steps) -Depth 8 | Set-Content -LiteralPath (Join-Path $directory 'http-timings.json') -Encoding UTF8 }
    catch { Write-Warning ('Timing evidence could not be saved: ' + $_.Exception.Message) }
    if ($ExerciseApp -or -not $applied) {
        & $restoreScript -StatePath $StatePath
        if ($ExerciseApp) {
            $null = Invoke-AppAction '/health-checks' (Join-Path $directory 'restored.html')
            $closing = Save-AppEvidence 'restored' $directory
            Write-Output ('Closing diagnosis: ' + $closing.check.diagnosis_code + '. Evidence: ' + $directory)
            if ($closing.check.diagnosis_code -ne 'healthy') { Write-Warning 'Settings restoration finished, but the final check is not healthy. Review restored.json before continuing.' }
        }
    }
}
