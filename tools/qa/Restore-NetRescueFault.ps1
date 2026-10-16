param([Parameter(Mandatory = $true)][string]$StatePath)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
function Invoke-TargetDhcpRenew([string]$Alias) {
    & "$env:SystemRoot\System32\ipconfig.exe" /renew $Alias | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'DHCP renewal returned a nonzero exit code during rollback.' }
}

function Invoke-TargetDnsCommand([string[]]$CommandArguments) {
    $output = & "$env:SystemRoot\System32\netsh.exe" @CommandArguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw ('Family-specific DNS rollback failed: ' + ($output | Out-String).Trim()) }
}

function Restore-TargetDnsFamily([int]$Index, [string]$Family, $Saved) {
    if ($Family -notin @('IPv4', 'IPv6') -or $null -eq $Saved -or $Saved.Automatic -isnot [bool]) { throw 'Missing or invalid saved DNS family settings.' }
    $context = $Family.ToLowerInvariant()
    if ($Saved.Automatic) {
        Invoke-TargetDnsCommand -CommandArguments @('interface', $context, 'set', 'dnsservers', ('name=' + $Index), 'source=dhcp')
        return
    }
    $servers = @($Saved.Servers)
    if (-not $servers.Count) { throw ('Cannot safely restore empty manual ' + $Family + ' DNS settings.') }
    $addressFamily = if ($Family -eq 'IPv4') { [Net.Sockets.AddressFamily]::InterNetwork } else { [Net.Sockets.AddressFamily]::InterNetworkV6 }
    foreach ($server in $servers) {
        $address = $null
        if (-not [Net.IPAddress]::TryParse([string]$server, [ref]$address) -or $address.AddressFamily -ne $addressFamily) { throw ('Invalid saved ' + $Family + ' DNS server.') }
    }
    Invoke-TargetDnsCommand -CommandArguments @('interface', $context, 'set', 'dnsservers', ('name=' + $Index), 'source=static', ('address=' + $servers[0]), 'validate=no')
    for ($position = 1; $position -lt $servers.Count; $position++) {
        Invoke-TargetDnsCommand -CommandArguments @('interface', $context, 'add', 'dnsservers', ('name=' + $Index), ('address=' + $servers[$position]), ('index=' + ($position + 1)), 'validate=no')
    }
}

function Assert-TargetDnsRestored([int]$Index, [string]$Guid, $Dns) {
    # Verify both families after every mutation. A per-family CIM InputObject
    # does not prevent ResetServerAddresses from resetting the other family.
    $families = @()
    foreach ($family in @('IPv4', 'IPv6')) {
        $saved = $Dns.$family
        $protocol = if ($family -eq 'IPv4') { 'Tcpip' } else { 'Tcpip6' }
        $path = 'HKLM:\SYSTEM\CurrentControlSet\Services\' + $protocol + '\Parameters\Interfaces\{' + $Guid.Trim('{}') + '}'
        $configuration = Get-ItemProperty -LiteralPath $path -ErrorAction Stop
        $automatic = [string]::IsNullOrWhiteSpace([string]$configuration.NameServer)
        if ($automatic -ne $saved.Automatic) { throw ($family + ' DNS mode was not restored. The rollback task is retained.') }
        $actual = @((Get-DnsClientServerAddress -InterfaceIndex $Index -AddressFamily $family -ErrorAction Stop).ServerAddresses)
        $expected = @($saved.Servers)
        $serversMatched = $actual.Count -eq $expected.Count
        if ($serversMatched) {
            for ($position = 0; $position -lt $expected.Count; $position++) {
                if ([Net.IPAddress]::Parse([string]$actual[$position]).ToString() -ne [Net.IPAddress]::Parse([string]$expected[$position]).ToString()) {
                    $serversMatched = $false
                }
            }
        }
        if (-not $saved.Automatic -and -not $serversMatched) { throw ($family + ' manual DNS server list or order was not restored.') }
        # Automatic DNS can legitimately receive a changed server list. Record
        # its baseline comparison, while requiring exact manual server restore.
        $families += [pscustomobject]@{ Family = $family; Automatic = $automatic; ModeMatched = $true;
            ServersMatched = $serversMatched; ServerMatchRequired = -not $saved.Automatic }
    }
    return [pscustomobject]@{ VerifiedAt = [DateTime]::UtcNow.ToString('o'); Families = $families }
}

$fileLock = [IO.File]::Open(($StatePath + '.lock'), [IO.FileMode]::OpenOrCreate, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
try {
$state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
if ($state.Version -ne 1 -or $state.TaskName -notmatch '^NetRescueQA-[a-f0-9]{32}$') { throw 'Unrecognized QA rollback state.' }
if ($state.RestoredAt) {
    Unregister-ScheduledTask -TaskName $state.TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Output 'This QA run was already restored.'; return
}

$failures = @()
foreach ($name in @($state.FirewallRuleNames)) {
    try {
        if ($name -notlike ($state.TaskName + '-*')) { throw 'A firewall rule name does not belong to this QA run.' }
        $rule = Get-NetFirewallRule -Name $name -ErrorAction Stop
        if ($rule) { $rule | Remove-NetFirewallRule -ErrorAction Stop }
    } catch {
        if ($_.CategoryInfo.Category -ne [Management.Automation.ErrorCategory]::ObjectNotFound) { $failures += $_.Exception.Message }
    }
}

try {
    # Match the saved hardware GUID, even if the adapter was renamed/reindexed.
    $adapter = @(Get-NetAdapter -IncludeHidden -ErrorAction Stop | Where-Object {
        ([string]$_.InterfaceGuid).Trim('{}') -eq ([string]$state.AdapterGuid).Trim('{}')
    })
    if ($adapter.Count -ne 1) { throw 'The saved adapter cannot be identified uniquely. Rollback state is retained.' }
    $index = $adapter[0].InterfaceIndex
    if ($state.Fault -eq 'AdapterDisabled' -and $state.WasEnabled) {
        $adapter[0] | Enable-NetAdapter -Confirm:$false -ErrorAction Stop
    }
    if ($state.Fault -in @('NoIpv4', 'GatewayMissing')) {
        Invoke-TargetDhcpRenew $adapter[0].Name
    }
    if ($state.Fault -eq 'GatewayMissing') {
        # Let DHCP restore its route. An invented static route could survive a
        # later network change and keep pointing at the previous Wi-Fi router.
        $currentRoutes = @(Get-NetRoute -InterfaceIndex $index -AddressFamily IPv4 -PolicyStore ActiveStore -ErrorAction Stop | Where-Object {
            $_.DestinationPrefix -eq '0.0.0.0/0' -and $_.NextHop -and $_.NextHop -ne '0.0.0.0'
        })
        if (-not $currentRoutes.Count) { throw 'DHCP has not restored an IPv4 default gateway. Retry rollback or inspect the router; no static route was invented.' }
    }
    if ($state.Fault -eq 'DnsServer') {
        $dnsFailures = @()
        foreach ($family in @('IPv4', 'IPv6')) {
            try { Restore-TargetDnsFamily $index $family $state.Dns.$family }
            catch { $dnsFailures += $_.Exception.Message }
        }
        try { Clear-DnsClientCache -ErrorAction Stop } catch { $dnsFailures += $_.Exception.Message }
        try {
            $dnsVerification = Assert-TargetDnsRestored $index ([string]$adapter[0].InterfaceGuid) $state.Dns
            $dnsVerification | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path (Split-Path $StatePath) 'dns-restored.json') -Encoding UTF8
        } catch { $dnsFailures += $_.Exception.Message }
        if ($dnsFailures.Count) { throw ($dnsFailures -join '; ') }
    }
} catch { $failures += $_.Exception.Message }

if ($failures.Count) {
    $failures | Set-Content -LiteralPath (Join-Path (Split-Path $StatePath) 'restore-errors.txt') -Encoding UTF8
    throw ('Rollback needs attention: ' + ($failures -join '; ') + '. State and task are retained.')
}
$state.RestoredAt = [DateTime]::UtcNow.ToString('o')
$state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $StatePath -Encoding UTF8
Unregister-ScheduledTask -TaskName $state.TaskName -Confirm:$false -ErrorAction SilentlyContinue
Write-Output ('Restored QA changes for ' + $adapter[0].Name + '. Run NetRescue Check to verify connectivity; rollback command completion alone is not proof.')
} finally { $fileLock.Dispose() }
