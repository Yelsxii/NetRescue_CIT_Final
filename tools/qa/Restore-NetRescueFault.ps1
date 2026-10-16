param([Parameter(Mandatory = $true)][string]$StatePath)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
function Invoke-TargetDhcpRenew([string]$Alias) {
    & "$env:SystemRoot\System32\ipconfig.exe" /renew $Alias | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'DHCP renewal returned a nonzero exit code during rollback.' }
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
        foreach ($family in @('IPv4', 'IPv6')) {
            $saved = $state.Dns.$family
            $entry = Get-DnsClientServerAddress -InterfaceIndex $index -AddressFamily $family -ErrorAction Stop
            if ($saved.Automatic) {
                $entry | Set-DnsClientServerAddress -ResetServerAddresses -ErrorAction Stop
            } elseif (@($saved.Servers).Count) {
                $entry | Set-DnsClientServerAddress -ServerAddresses @($saved.Servers) -ErrorAction Stop
            } else {
                throw ('Cannot safely restore empty manual ' + $family + ' DNS settings.')
            }
        }
        Clear-DnsClientCache -ErrorAction Stop
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
