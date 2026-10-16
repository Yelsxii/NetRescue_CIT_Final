# Run production injection/restore logic against fake Windows cmdlets only.
# No elevated permission, real firewall, adapter change or app request is used.
$ErrorActionPreference = 'Stop'
$qa = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../tools/qa'))
$runner = Join-Path $qa 'Invoke-NetRescueFault.ps1'
$restore = Join-Path $qa 'Restore-NetRescueFault.ps1'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($runner, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($name in @('Add-QABlock', 'Apply-QAFault', 'Invoke-AppAction')) {
    $node = $ast.Find({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq $name }, $true)
    if (-not $node) { throw ('Production function missing: ' + $name) }
    . ([scriptblock]::Create($node.Extent.Text))
}
$script:passed = 0
function Assert-Test([bool]$Condition, [string]$Description) {
    if (-not $Condition) { throw $Description }
    $script:passed++; Write-Output ('PASS: ' + $Description)
}
$global:qaCalls = @()
function Disable-NetAdapter { [CmdletBinding(SupportsShouldProcess=$true)]param([Parameter(ValueFromPipeline=$true)]$InputObject)
    process { $global:qaCalls += @{ Kind = 'disable'; Target = $InputObject.Name } }
}
function Enable-NetAdapter { [CmdletBinding(SupportsShouldProcess=$true)]param([Parameter(ValueFromPipeline=$true)]$InputObject)
    process { $global:qaCalls += @{ Kind = 'enable'; Target = $InputObject.Name } }
}
function Remove-NetRoute { [CmdletBinding(SupportsShouldProcess=$true)]param([Parameter(ValueFromPipeline=$true)]$InputObject)
    process { $global:qaCalls += @{ Kind = 'remove-route'; Target = $InputObject.InterfaceIndex } }
}
function Set-DnsClientServerAddress { [CmdletBinding()]param($InterfaceIndex, [string[]]$ServerAddresses, [switch]$ResetServerAddresses, [Parameter(ValueFromPipeline=$true)]$InputObject)
    process { $global:qaCalls += @{ Kind = 'dns'; Index = $InterfaceIndex; Servers = $ServerAddresses; Reset = [bool]$ResetServerAddresses; Family = $InputObject.AddressFamily } }
}
function Clear-DnsClientCache { [CmdletBinding()]param() $global:qaCalls += @{ Kind = 'clear-dns' } }
function New-NetFirewallRule { [CmdletBinding()]param($Name, $DisplayName, $Group, $Direction, $Action, $Profile, $Enabled, $InterfaceAlias, $RemoteAddress, $Protocol, $IcmpType)
    $global:qaCalls += @{ Kind = 'firewall'; Name = $Name; Group = $Group; Target = $InterfaceAlias; Addresses = @($RemoteAddress); Protocol = $Protocol; IcmpType = $IcmpType }
}
$InterfaceAlias = 'Wi-Fi'
$adapter = [pscustomobject]@{ Name = 'Wi-Fi'; InterfaceIndex = 9; InterfaceGuid = '00000000-0000-0000-0000-000000000001' }
$routes = @([pscustomobject]@{ NextHop = '192.168.1.1'; RouteMetric = 10; InterfaceIndex = 9 })
$targets = @('1.1.1.1', '8.8.8.8')
$script:qaState = @{ TaskName = 'NetRescueQA-' + ('a' * 32); FirewallRuleNames = @('NetRescueQA-' + ('a' * 32) + '-block') }
foreach ($case in @('AdapterDisabled', 'GatewayMissing', 'DnsServer', 'GatewayPingBlocked', 'WanTargetsBlocked', 'GatewayTestsBlocked')) {
    $Fault = $case; $global:qaCalls = @()
    Apply-QAFault $adapter $routes $targets
    switch ($case) {
        'AdapterDisabled' { Assert-Test ($global:qaCalls.Count -eq 1 -and $global:qaCalls[0].Target -eq 'Wi-Fi') 'Disable affects only the selected adapter.' }
        'GatewayMissing' { Assert-Test ($global:qaCalls.Count -eq 1 -and $global:qaCalls[0].Target -eq 9) 'Gateway removal affects only supplied adapter routes.' }
        'DnsServer' { Assert-Test ($global:qaCalls.Count -eq 2 -and $global:qaCalls[0].Index -eq 9 -and $global:qaCalls[0].Servers -contains '192.0.2.53' -and $global:qaCalls[0].Servers -contains '2001:db8::53') 'DNS injection is adapter-scoped and covers both address families.' }
        'GatewayPingBlocked' { Assert-Test ($global:qaCalls.Count -eq 1 -and $global:qaCalls[0].Protocol -eq 'ICMPv4' -and $global:qaCalls[0].IcmpType -eq 8 -and $global:qaCalls[0].Addresses.Count -eq 1 -and $global:qaCalls[0].Addresses[0] -eq '192.168.1.1') 'Ping-only injection leaves the external TCP targets unblocked.' }
        'WanTargetsBlocked' { Assert-Test ($global:qaCalls.Count -eq 1 -and $global:qaCalls[0].Addresses.Count -eq 2 -and $global:qaCalls[0].Target -eq 'Wi-Fi') 'WAN simulation blocks only configured targets on the selected adapter.' }
        'GatewayTestsBlocked' { Assert-Test ($global:qaCalls.Count -eq 1 -and $global:qaCalls[0].Addresses.Count -eq 3 -and $global:qaCalls[0].Addresses -contains '192.168.1.1') 'Gateway/path simulation combines exact gateway and test target IPs.' }
    }
}

# Exercise the actual HTTP action function using a fake request boundary.
$BaseUrl = 'http://127.0.0.1:8000'; $script:qaSession = $null
$script:httpCalls = @(); $script:missingToken = $false
function Invoke-WebRequest { [CmdletBinding()]param($Uri, $Method, $Body, $WebSession, [switch]$UseBasicParsing, $TimeoutSec)
    $script:httpCalls += @{ Uri = $Uri; Method = $Method; Body = $Body }
    [pscustomobject]@{ StatusCode = 200; Content = $(if ($script:missingToken) { '<html></html>' } else { '<input type="hidden" name="_token" value="fixture-token">' }) }
}
$null = Invoke-AppAction '/incidents/7/recover' ''
Assert-Test ($script:httpCalls.Count -eq 2 -and $script:httpCalls[1].Uri -eq ($BaseUrl + '/incidents/7/recover') -and $script:httpCalls[1].Body._token -eq 'fixture-token') 'App exercise uses the real POST path and a fresh CSRF token.'
$script:missingToken = $true; $script:httpCalls = @(); $caught = $false
try { $null = Invoke-AppAction '/health-checks' '' } catch { $caught = $true }
Assert-Test ($caught -and $script:httpCalls.Count -eq 1) 'Missing CSRF token prevents the mutation request.'

# Actual restore script, with Windows cmdlets replaced at the OS boundary.
function Get-NetAdapter { [CmdletBinding()]param([switch]$IncludeHidden) return $adapter }
function Get-NetFirewallRule { [CmdletBinding()]param($Name) return [pscustomobject]@{ Name = $Name } }
function Remove-NetFirewallRule { [CmdletBinding()]param([Parameter(ValueFromPipeline=$true)]$InputObject)
    process { $global:qaCalls += @{ Kind = 'remove-firewall'; Name = $InputObject.Name } }
}
function Get-NetRoute { [CmdletBinding()]param($InterfaceIndex, $AddressFamily, $PolicyStore) return [pscustomobject]@{ DestinationPrefix = '0.0.0.0/0'; NextHop = '192.168.1.1' } }
function Get-DnsClientServerAddress { [CmdletBinding()]param($InterfaceIndex, $AddressFamily) return [pscustomobject]@{ AddressFamily = $AddressFamily } }
function Unregister-ScheduledTask { [CmdletBinding(SupportsShouldProcess=$true)]param($TaskName) $global:qaCalls += @{ Kind = 'unregister'; Name = $TaskName } }
$directory = Join-Path ([IO.Path]::GetTempPath()) ('nr-rollback-fixture-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $directory
try {
    # Only the native ipconfig process boundary is replaced; run the complete
    # production rollback body and its actual state/error/cleanup handling.
    $restoreAst = [Management.Automation.Language.Parser]::ParseFile($restore, [ref]$tokens, [ref]$errors)
    $renewDefinition = $restoreAst.Find({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq 'Invoke-TargetDhcpRenew' }, $true)
    $restoreBody = [IO.File]::ReadAllText($restore).Replace($renewDefinition.Extent.Text, 'function Invoke-TargetDhcpRenew([string]$Alias) { $global:qaCalls += @{ Kind = "renew"; Target = $Alias } }')
    $fixtureRestore = Join-Path $directory 'restore-fixture.ps1'
    Set-Content -LiteralPath $fixtureRestore -Value $restoreBody
    foreach ($case in @('AdapterDisabled', 'NoIpv4', 'GatewayMissing', 'DnsServer', 'WanTargetsBlocked')) {
        $path = Join-Path $directory ($case + '.json')
        $state = @{ Version = 1; Fault = $case; TaskName = $script:qaState.TaskName; AdapterGuid = $adapter.InterfaceGuid;
            WasEnabled = $true; DefaultRoutes = $routes; FirewallRuleNames = $script:qaState.FirewallRuleNames; RestoredAt = $null;
            Dns = @{ IPv4 = @{ Automatic = $false; Servers = @('192.168.1.2') }; IPv6 = @{ Automatic = $true; Servers = @() } } }
        $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
        $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        $saved = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
        Assert-Test ([bool]$saved.RestoredAt -and @($global:qaCalls | Where-Object { $_.Kind -eq 'unregister' }).Count -eq 1) ('Rollback completes and removes its task: ' + $case)
        if ($case -in @('NoIpv4', 'GatewayMissing')) {
            $renewCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'renew' })
            Assert-Test ($renewCalls.Count -eq 1 -and $renewCalls[0].Target -eq 'Wi-Fi') 'DHCP rollback renews only the saved adapter and never invents a static route.'
        }
        if ($case -eq 'DnsServer') {
            $dnsCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'dns' })
            Assert-Test ($dnsCalls.Count -eq 2 -and $dnsCalls[0].Servers[0] -eq '192.168.1.2' -and -not $dnsCalls[0].Reset -and $dnsCalls[1].Reset) 'Rollback restores static IPv4 DNS and automatic IPv6 DNS independently.'
        }
        $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        Assert-Test (@($global:qaCalls | Where-Object { $_.Kind -ne 'unregister' }).Count -eq 0) ('Repeated rollback makes no network changes: ' + $case)
    }
    # A missing target must retain rollback evidence and must never enable another.
    $state.Fault = 'AdapterDisabled'; $state.RestoredAt = $null; $state.AdapterGuid = 'another-guid'
    $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
    $global:qaCalls = @(); $caught = $false
    try { $null = & $fixtureRestore -StatePath $path } catch { $caught = $true }
    Assert-Test ($caught -and @($global:qaCalls | Where-Object { $_.Kind -in @('enable', 'unregister') }).Count -eq 0 -and -not (Get-Content -LiteralPath $path -Raw | ConvertFrom-Json).RestoredAt) 'An absent saved adapter retains the task/state and never enables another adapter.'
} finally { Remove-Item -LiteralPath $directory -Recurse -Force }
Write-Output ('Completed ' + $script:passed + ' synthetic fault runner checks. Windows DHCP, Task Scheduler and real firewall timing still need laptop testing.')
