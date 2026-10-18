# Run production injection/restore logic against fake Windows cmdlets only.
# No elevated permission, real firewall, adapter change or app request is used.
$ErrorActionPreference = 'Stop'
$qa = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../tools/qa'))
$runner = Join-Path $qa 'Invoke-NetRescueFault.ps1'
$restore = Join-Path $qa 'Restore-NetRescueFault.ps1'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($runner, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($name in @('Add-QABlock', 'Apply-QAFault', 'Invoke-AppAction', 'Assert-QAGatewayRoutes',
    'Get-QAIpv4Snapshot', 'Assert-QANoIpv4Baseline', 'Invoke-TargetDhcpRelease', 'Invoke-QANoIpv4Fault')) {
    $node = $ast.Find({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq $name }, $true)
    if (-not $node) { throw ('Production function missing: ' + $name) }
    . ([scriptblock]::Create($node.Extent.Text))
}
$script:passed = 0
function Assert-Test([bool]$Condition, [string]$Description) {
    if (-not $Condition) { throw $Description }
    $script:passed++; Write-Output ('PASS: ' + $Description)
}
$dhcpGateway = [pscustomobject]@{ NextHop = '192.168.1.1'; RouteMetric = 0; Protocol = 'NetMgmt' }
$dhcpConfiguration = [pscustomobject]@{ EnableDHCP = 1; DhcpDefaultGateway = @('192.168.1.1'); DefaultGateway = @() }
Assert-QAGatewayRoutes @($dhcpGateway) @() $dhcpConfiguration
Assert-Test $true 'A Windows NetMgmt route matching its DHCP gateway is eligible for route QA.'
foreach ($case in @('ManualActive', 'PersistentDefault', 'MultipleDefaults', 'CustomMetric', 'ManualGateway', 'MissingDhcpGateway', 'DhcpDisabled', 'UnknownProtocol')) {
    $candidate = [pscustomobject]@{ NextHop = '192.168.1.1'; RouteMetric = 0; Protocol = 'NetMgmt' }
    $configuration = [pscustomobject]@{ EnableDHCP = 1; DhcpDefaultGateway = @('192.168.1.1'); DefaultGateway = @() }
    $activeDefaults = @($candidate); $persistentDefaults = @()
    switch ($case) {
        'ManualActive' { $candidate.NextHop = '192.168.1.254' }
        'PersistentDefault' { $persistentDefaults = @($candidate) }
        'MultipleDefaults' { $activeDefaults += [pscustomobject]@{ NextHop = '192.168.1.254'; RouteMetric = 0; Protocol = 'NetMgmt' } }
        'CustomMetric' { $candidate.RouteMetric = 25 }
        'ManualGateway' { $configuration.DefaultGateway = @('192.168.1.1') }
        'MissingDhcpGateway' { $configuration.DhcpDefaultGateway = @() }
        'DhcpDisabled' { $configuration.EnableDHCP = 0 }
        'UnknownProtocol' { $candidate.Protocol = 'Other' }
    }
    $caught = $false
    try { Assert-QAGatewayRoutes $activeDefaults $persistentDefaults $configuration } catch { $caught = $true }
    Assert-Test $caught ('Gateway QA refuses an ambiguous or custom route baseline: ' + $case)
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

# NoIpv4 uses fixture-only snapshot and native-command boundaries. Keep the
# production orchestration/assertion functions, but never run real ipconfig.
function New-FixtureIpv4Snapshot {
    [pscustomobject]@{
        CapturedAt = '2026-10-18T05:00:00.0000000Z'
        InterfaceAlias = $adapter.Name
        AdapterGuid = [string]$adapter.InterfaceGuid
        AdapterState = 'Up'
        DhcpEnabled = $true
        Addresses = @([pscustomobject]@{ IPAddress = '192.168.1.5'; PrefixOrigin = 'Dhcp'; AddressState = 'Preferred' })
        UsableAddresses = @('192.168.1.5')
        GatewayAddresses = @('192.168.1.1')
    }
}
$noIpv4Baseline = New-FixtureIpv4Snapshot
Assert-QANoIpv4Baseline $noIpv4Baseline
Assert-Test $true 'NoIpv4 accepts one usable DHCP-origin IPv4 on the Up DHCP adapter.'
foreach ($case in @('ManualPrimary', 'ManualSecondary', 'ManualApipaSecondary', 'ExtraDhcpAddress', 'DhcpDisabled', 'AdapterDown', 'NoUsableAddress', 'MissingAddressEvidence')) {
    $candidate = New-FixtureIpv4Snapshot
    switch ($case) {
        'ManualPrimary' { $candidate.Addresses[0].PrefixOrigin = 'Manual' }
        'ManualSecondary' {
            $candidate.Addresses += [pscustomobject]@{ IPAddress = '192.168.1.6'; PrefixOrigin = 'Manual'; AddressState = 'Preferred' }
            $candidate.UsableAddresses += '192.168.1.6'
        }
        'ManualApipaSecondary' {
            $candidate.Addresses += [pscustomobject]@{ IPAddress = '169.254.1.2'; PrefixOrigin = 'Manual'; AddressState = 'Preferred' }
        }
        'ExtraDhcpAddress' {
            $candidate.Addresses += [pscustomobject]@{ IPAddress = '192.168.1.6'; PrefixOrigin = 'Dhcp'; AddressState = 'Preferred' }
            $candidate.UsableAddresses += '192.168.1.6'
        }
        'DhcpDisabled' { $candidate.DhcpEnabled = $false }
        'AdapterDown' { $candidate.AdapterState = 'Disconnected' }
        'NoUsableAddress' { $candidate.Addresses = @(); $candidate.UsableAddresses = @() }
        'MissingAddressEvidence' { $candidate.Addresses = @() }
    }
    $caught = $false
    try { Assert-QANoIpv4Baseline $candidate } catch { $caught = $true }
    Assert-Test $caught ('NoIpv4 refuses an unsafe or ambiguous baseline before release: ' + $case)
}
function Invoke-TargetDhcpRelease([string]$Alias) {
    $global:qaCalls += @{ Kind = 'release'; Target = $Alias }
    return $script:qaReleaseResult
}
function Get-QAIpv4Snapshot($Adapter) {
    $global:qaCalls += @{ Kind = 'ipv4-snapshot'; Target = $Adapter.Name; Guid = [string]$Adapter.InterfaceGuid }
    return $script:qaAfterReleaseSnapshot
}
$noIpv4Directory = Join-Path ([IO.Path]::GetTempPath()) ('nr-noipv4-fixture-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $noIpv4Directory
try {
    foreach ($case in @('Missing', 'Apipa', 'StillUsable', 'DhcpDisabled', 'AdapterDown', 'ReleaseFailed')) {
        $caseDirectory = Join-Path $noIpv4Directory $case
        $null = New-Item -ItemType Directory -Path $caseDirectory
        $script:qaRunDirectory = $caseDirectory
        $script:qaReleaseResult = [pscustomobject]@{ ExitCode = 0; Output = 'Fixture targeted release output'; ElapsedMs = 12 }
        $script:qaAfterReleaseSnapshot = New-FixtureIpv4Snapshot
        $script:qaAfterReleaseSnapshot.Addresses = @()
        $script:qaAfterReleaseSnapshot.UsableAddresses = @()
        $script:qaAfterReleaseSnapshot.GatewayAddresses = @()
        switch ($case) {
            'Apipa' {
                $script:qaAfterReleaseSnapshot.Addresses = @([pscustomobject]@{
                    IPAddress = '169.254.1.2'; PrefixOrigin = 'WellKnown'; AddressState = 'Preferred'
                })
            }
            'StillUsable' { $script:qaAfterReleaseSnapshot = New-FixtureIpv4Snapshot }
            'DhcpDisabled' { $script:qaAfterReleaseSnapshot.DhcpEnabled = $false }
            'AdapterDown' { $script:qaAfterReleaseSnapshot.AdapterState = 'Disconnected' }
            'ReleaseFailed' {
                $script:qaReleaseResult.ExitCode = 5
                $script:qaReleaseResult.Output = 'Fixture release failed: Access is denied.'
            }
        }
        $Fault = 'NoIpv4'; $global:qaCalls = @(); $caught = $false; $failureMessage = ''
        try { Apply-QAFault $adapter $routes $targets } catch { $caught = $true; $failureMessage = $_.Exception.Message }
        $releaseCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'release' })
        $snapshotCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'ipv4-snapshot' })
        Assert-Test ($releaseCalls.Count -eq 1 -and $releaseCalls[0].Target -eq $adapter.Name) ('NoIpv4 releases only the selected adapter exactly once: ' + $case)
        Assert-Test ($snapshotCalls.Count -eq 1 -and $snapshotCalls[0].Target -eq $adapter.Name -and $snapshotCalls[0].Guid -eq [string]$adapter.InterfaceGuid) ('NoIpv4 reads the selected hardware after release: ' + $case)
        $releaseEvidence = Get-Content -LiteralPath (Join-Path $caseDirectory 'noipv4-release.json') -Raw | ConvertFrom-Json
        $snapshotEvidence = Get-Content -LiteralPath (Join-Path $caseDirectory 'noipv4-after-release.json') -Raw | ConvertFrom-Json
        Assert-Test ($releaseEvidence.ExitCode -eq $script:qaReleaseResult.ExitCode -and $releaseEvidence.Output -eq $script:qaReleaseResult.Output -and $releaseEvidence.ElapsedMs -eq 12) ('NoIpv4 preserves the native release status, output, and timing: ' + $case)
        Assert-Test ($snapshotEvidence.AdapterGuid -eq [string]$adapter.InterfaceGuid -and $snapshotEvidence.InterfaceAlias -eq $adapter.Name -and @($snapshotEvidence.UsableAddresses).Count -eq @($script:qaAfterReleaseSnapshot.UsableAddresses).Count) ('NoIpv4 preserves the observed post-release configuration: ' + $case)
        if ($case -in @('Missing', 'Apipa')) {
            Assert-Test (-not $caught) ('NoIpv4 accepts the reproduced missing/APIPA condition: ' + $case)
        } elseif ($case -eq 'ReleaseFailed') {
            Assert-Test $caught 'A nonzero targeted release exit cannot be reported as a successful injection.'
        } else {
            Assert-Test ($caught -and $failureMessage -match 'not reproduced') ('A zero exit without the intended fault is rejected as not reproduced: ' + $case)
        }
        Assert-Test (@($global:qaCalls | Where-Object { $_.Kind -notin @('release', 'ipv4-snapshot') }).Count -eq 0) ('NoIpv4 does not change DNS, manual addressing, adapter state, or repeatedly hold the fault: ' + $case)
    }
} finally {
    $resolvedNoIpv4Directory = [IO.Path]::GetFullPath($noIpv4Directory)
    $resolvedTemporaryRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    if (-not $resolvedNoIpv4Directory.StartsWith($resolvedTemporaryRoot, [StringComparison]::OrdinalIgnoreCase) -or
        [IO.Path]::GetFileName($resolvedNoIpv4Directory) -notmatch '^nr-noipv4-fixture-[a-f0-9]{32}$') {
        throw 'Refusing to clean an unexpected NoIpv4 fixture directory.'
    }
    Remove-Item -LiteralPath $resolvedNoIpv4Directory -Recurse -Force
}

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
function Get-DnsClientServerAddress { [CmdletBinding()]param($InterfaceIndex, $AddressFamily)
    return [pscustomobject]@{ AddressFamily = $AddressFamily; ServerAddresses = @($global:qaDnsState[$AddressFamily].Servers) }
}
function Get-ItemProperty { [CmdletBinding()]param($LiteralPath)
    $family = if ($LiteralPath -like '*\Tcpip6\*') { 'IPv6' } else { 'IPv4' }
    $manual = if ($global:qaDnsState[$family].Automatic) { '' } else { $global:qaDnsState[$family].Servers -join ',' }
    return [pscustomobject]@{ NameServer = $manual }
}
function Invoke-FixtureDnsCommand([string[]]$CommandArguments) {
    $global:qaCalls += @{ Kind = 'dns-netsh'; Arguments = @($CommandArguments) }
    $family = if ($CommandArguments[1] -eq 'ipv4') { 'IPv4' } else { 'IPv6' }
    if ($global:qaDnsFailure -eq 'Command' -and $family -eq 'IPv4') { throw 'Fixture native DNS command failed.' }
    if ($CommandArguments -contains 'source=dhcp') {
        $global:qaDnsState[$family] = @{ Automatic = $true; Servers = @() }
        # Simulate command completion that failed to preserve the other family.
        if ($family -eq 'IPv6' -and $global:qaDnsFailure -eq 'Mode') { $global:qaDnsState.IPv4.Automatic = $true }
        if ($family -eq 'IPv6' -and $global:qaDnsFailure -eq 'Servers') { $global:qaDnsState.IPv4.Servers = @('192.0.2.53') }
    } else {
        $server = @($CommandArguments | Where-Object { $_ -like 'address=*' })[0].Substring(8)
        if ($CommandArguments[2] -eq 'set') { $global:qaDnsState[$family] = @{ Automatic = $false; Servers = @($server) } }
        else { $global:qaDnsState[$family].Servers += $server }
    }
}
function Unregister-ScheduledTask { [CmdletBinding(SupportsShouldProcess=$true)]param($TaskName) $global:qaCalls += @{ Kind = 'unregister'; Name = $TaskName } }
$directory = Join-Path ([IO.Path]::GetTempPath()) ('nr-rollback-fixture-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $directory
try {
    # Only native ipconfig/netsh process boundaries are replaced; run the complete
    # production rollback body and its actual state/error/cleanup handling.
    $restoreAst = [Management.Automation.Language.Parser]::ParseFile($restore, [ref]$tokens, [ref]$errors)
    $renewDefinition = $restoreAst.Find({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq 'Invoke-TargetDhcpRenew' }, $true)
    $dnsDefinition = $restoreAst.Find({ param($n) $n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq 'Invoke-TargetDnsCommand' }, $true)
    $restoreBody = [IO.File]::ReadAllText($restore).Replace($renewDefinition.Extent.Text, 'function Invoke-TargetDhcpRenew([string]$Alias) { $global:qaCalls += @{ Kind = "renew"; Target = $Alias } }')
    $restoreBody = $restoreBody.Replace($dnsDefinition.Extent.Text, 'function Invoke-TargetDnsCommand([string[]]$CommandArguments) { Invoke-FixtureDnsCommand -CommandArguments $CommandArguments }')
    $fixtureRestore = Join-Path $directory 'restore-fixture.ps1'
    Set-Content -LiteralPath $fixtureRestore -Value $restoreBody
    foreach ($case in @('AdapterDisabled', 'NoIpv4', 'GatewayMissing', 'DnsServer', 'WanTargetsBlocked')) {
        $path = Join-Path $directory ($case + '.json')
        $state = @{ Version = 1; Fault = $case; TaskName = $script:qaState.TaskName; AdapterGuid = $adapter.InterfaceGuid;
            WasEnabled = $true; DefaultRoutes = $routes; FirewallRuleNames = $script:qaState.FirewallRuleNames; RestoredAt = $null;
            Dns = @{ IPv4 = @{ Automatic = $false; Servers = @('192.168.1.2') }; IPv6 = @{ Automatic = $true; Servers = @() } } }
        $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
        $global:qaDnsState = @{ IPv4 = @{ Automatic = $false; Servers = @('192.0.2.53') }; IPv6 = @{ Automatic = $false; Servers = @('2001:db8::53') } }
        $global:qaDnsFailure = ''; $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        $saved = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
        Assert-Test ([bool]$saved.RestoredAt -and @($global:qaCalls | Where-Object { $_.Kind -eq 'unregister' }).Count -eq 1) ('Rollback completes and removes its task: ' + $case)
        if ($case -in @('NoIpv4', 'GatewayMissing')) {
            $renewCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'renew' })
            Assert-Test ($renewCalls.Count -eq 1 -and $renewCalls[0].Target -eq 'Wi-Fi') 'DHCP rollback renews only the saved adapter and never invents a static route.'
        }
        if ($case -eq 'DnsServer') {
            $dnsCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'dns-netsh' })
            Assert-Test ($dnsCalls.Count -eq 2 -and $dnsCalls[0].Arguments[1] -eq 'ipv4' -and $dnsCalls[0].Arguments -contains 'name=9' -and $dnsCalls[0].Arguments -contains 'address=192.168.1.2' -and $dnsCalls[1].Arguments[1] -eq 'ipv6' -and $dnsCalls[1].Arguments -contains 'source=dhcp') 'Rollback uses family-scoped commands for static IPv4 DNS and automatic IPv6 DNS.'
            $dnsVerification = Get-Content -LiteralPath (Join-Path $directory 'dns-restored.json') -Raw | ConvertFrom-Json
            Assert-Test ($dnsVerification.Families.Count -eq 2 -and @($dnsVerification.Families | Where-Object { -not $_.ModeMatched -or -not $_.ServersMatched }).Count -eq 0) 'DNS rollback records both verified family modes and baseline server comparisons.'
        }
        $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        Assert-Test (@($global:qaCalls | Where-Object { $_.Kind -ne 'unregister' }).Count -eq 0) ('Repeated rollback makes no network changes: ' + $case)
    }
    foreach ($dnsCase in @('ManualV4AutomaticV6', 'AutomaticV4ManualV6', 'BothManual', 'BothAutomatic')) {
        $path = Join-Path $directory ($dnsCase + '.json')
        $state.Fault = 'DnsServer'; $state.AdapterGuid = $adapter.InterfaceGuid; $state.RestoredAt = $null
        $state.Dns.IPv4 = @{ Automatic = $dnsCase -in @('AutomaticV4ManualV6', 'BothAutomatic'); Servers = @() }
        $state.Dns.IPv6 = @{ Automatic = $dnsCase -in @('ManualV4AutomaticV6', 'BothAutomatic'); Servers = @() }
        if (-not $state.Dns.IPv4.Automatic) { $state.Dns.IPv4.Servers = @('192.168.1.2', '192.168.1.3') }
        if (-not $state.Dns.IPv6.Automatic) { $state.Dns.IPv6.Servers = @('2001:db8::1', '2001:db8::2') }
        $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
        $global:qaDnsState = @{ IPv4 = @{ Automatic = $false; Servers = @('192.0.2.53') }; IPv6 = @{ Automatic = $false; Servers = @('2001:db8::53') } }
        $global:qaDnsFailure = ''; $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        $saved = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
        $verification = Get-Content -LiteralPath (Join-Path $directory 'dns-restored.json') -Raw | ConvertFrom-Json
        Assert-Test ([bool]$saved.RestoredAt -and $global:qaDnsState.IPv4.Automatic -eq $state.Dns.IPv4.Automatic -and $global:qaDnsState.IPv6.Automatic -eq $state.Dns.IPv6.Automatic -and @($verification.Families | Where-Object { -not $_.ModeMatched -or -not $_.ServersMatched }).Count -eq 0) ('DNS rollback verifies mixed modes and ordered manual server lists: ' + $dnsCase)
        $addCalls = @($global:qaCalls | Where-Object { $_.Kind -eq 'dns-netsh' -and $_.Arguments[2] -eq 'add' })
        Assert-Test (@($addCalls | Where-Object { $_.Arguments -notcontains 'index=2' -or $_.Arguments -notcontains 'validate=no' -or $_.Arguments -notcontains 'name=9' }).Count -eq 0) ('Additional manual DNS servers use their original order and selected interface: ' + $dnsCase)
    }
    foreach ($failure in @('Mode', 'Servers', 'Command')) {
        $path = Join-Path $directory ('dns-failure-' + $failure + '.json')
        $state.Dns = @{ IPv4 = @{ Automatic = $false; Servers = @('192.168.1.2') }; IPv6 = @{ Automatic = $true; Servers = @() } }
        $state.RestoredAt = $null; $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
        $global:qaDnsState = @{ IPv4 = @{ Automatic = $false; Servers = @('192.0.2.53') }; IPv6 = @{ Automatic = $false; Servers = @('2001:db8::53') } }
        $global:qaDnsFailure = $failure; $global:qaCalls = @(); $caught = $false
        try { $null = & $fixtureRestore -StatePath $path } catch { $caught = $true }
        $saved = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
        Assert-Test ($caught -and -not $saved.RestoredAt -and @($global:qaCalls | Where-Object { $_.Kind -eq 'unregister' }).Count -eq 0 -and (Test-Path -LiteralPath (Join-Path $directory 'restore-errors.txt'))) ('DNS rollback retains its task and state after failure: ' + $failure)
        Assert-Test $global:qaDnsState.IPv6.Automatic ('DNS rollback still attempts the other family after failure: ' + $failure)
        $global:qaDnsFailure = ''; $global:qaCalls = @(); $null = & $fixtureRestore -StatePath $path
        $saved = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
        Assert-Test ([bool]$saved.RestoredAt -and @($global:qaCalls | Where-Object { $_.Kind -eq 'unregister' }).Count -eq 1) ('A verified DNS rollback retry can finish and remove its task: ' + $failure)
    }
    # A missing target must retain rollback evidence and must never enable another.
    $state.Fault = 'AdapterDisabled'; $state.RestoredAt = $null; $state.AdapterGuid = 'another-guid'
    $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $path
    $global:qaCalls = @(); $caught = $false
    try { $null = & $fixtureRestore -StatePath $path } catch { $caught = $true }
    Assert-Test ($caught -and @($global:qaCalls | Where-Object { $_.Kind -in @('enable', 'unregister') }).Count -eq 0 -and -not (Get-Content -LiteralPath $path -Raw | ConvertFrom-Json).RestoredAt) 'An absent saved adapter retains the task/state and never enables another adapter.'
} finally { Remove-Item -LiteralPath $directory -Recurse -Force }
Write-Output ('Completed ' + $script:passed + ' synthetic fault runner checks. Windows DHCP, Task Scheduler and real firewall timing still need laptop testing.')
