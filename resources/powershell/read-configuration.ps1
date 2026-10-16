param([string]$InterfaceName = '')

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$reader = 'route_and_adapter'
$errorCode = 'configuration_read_failed'
$windowsAdapterStatus = $null

function Write-Reading($available, $state, $name, $ip, $gateway, $dns, $addressSource, $dnsSource) {
    [pscustomobject]@{
        adapterAvailable = [bool]$available
        adapterState = $state
        interfaceName = $name
        localIp = $ip
        gatewayIp = $gateway
        dnsServers = @($dns)
        addressSource = $addressSource
        dnsSource = $dnsSource
        reader = $reader
    } | ConvertTo-Json -Compress -Depth 4
}

function Select-NamedAdapter { param($Adapters)
    $wifi = @($Adapters | Where-Object { $_.Name -match 'Wi-?Fi|Wireless|WLAN' } | Select-Object -First 1)
    if ($wifi.Count) { return $wifi[0] }
    $ethernet = @($Adapters | Where-Object { $_.Name -match 'Ethernet' } | Select-Object -First 1)
    if ($ethernet.Count) { return $ethernet[0] }
    return @($Adapters | Select-Object -First 1)[0]
}

function Get-InterfaceSnapshot {
    # Capture this AFTER any slow CIM call; an earlier snapshot can describe
    # the state before Wi-Fi finished reconnecting.
    $interfaces = @([System.Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces())
    $up = @($interfaces | Where-Object {
        $_.OperationalStatus -eq [System.Net.NetworkInformation.OperationalStatus]::Up -and
        $_.NetworkInterfaceType -ne [System.Net.NetworkInformation.NetworkInterfaceType]::Loopback -and
        $_.Supports([System.Net.NetworkInformation.NetworkInterfaceComponent]::IPv4)
    })
    $byIndex = @{}
    foreach ($item in $up) { $byIndex[[int]$item.GetIPProperties().GetIPv4Properties().Index] = $item }
    return [pscustomobject]@{ Interfaces = $interfaces; ByIndex = $byIndex }
}

function Select-TargetSnapshot($Snapshot, [string]$Name) {
    if (-not $Name) { return $null }
    $matching = @($Snapshot.Interfaces | Where-Object {
        $_.Name -eq $Name -and
        $_.OperationalStatus -eq [System.Net.NetworkInformation.OperationalStatus]::Up -and
        $_.NetworkInterfaceType -ne [System.Net.NetworkInformation.NetworkInterfaceType]::Loopback -and
        $_.Supports([System.Net.NetworkInformation.NetworkInterfaceComponent]::IPv4)
    })
    if ($matching.Count -ne 1) { return $null }
    $gateways = @($matching[0].GetIPProperties().GatewayAddresses | Where-Object {
        $_.Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and
        $_.Address.ToString() -ne '0.0.0.0'
    } | ForEach-Object { $_.Address.ToString() } | Select-Object -Unique)
    if ($gateways.Count -gt 1) { return $null }
    return [pscustomobject]@{
        Interface = $matching[0]
        Gateway = $(if ($gateways.Count) { $gateways[0] } else { $null })
    }
}

function Select-SoleSnapshot($Snapshot) {
    # A single Up IPv4 interface cannot lose route precedence to another Up
    # interface. Multiple interfaces/gateways still require Windows route metrics.
    $up = @($Snapshot.Interfaces | Where-Object {
        $_.OperationalStatus -eq [System.Net.NetworkInformation.OperationalStatus]::Up -and
        $_.NetworkInterfaceType -ne [System.Net.NetworkInformation.NetworkInterfaceType]::Loopback -and
        $_.Supports([System.Net.NetworkInformation.NetworkInterfaceComponent]::IPv4)
    })
    if ($up.Count -ne 1) { return $null }
    return Select-TargetSnapshot $Snapshot $up[0].Name
}

try {
    $selected = $null
    $gateway = $null
    if ($InterfaceName) {
        # For an exact target already Up, read the current .NET snapshot first.
        # This does not load NetAdapter/NetTCPIP just to rediscover that target.
        # Multiple gateways still need route metrics; Down never implies Disabled.
        $snapshot = Get-InterfaceSnapshot
        $quickTarget = Select-TargetSnapshot $snapshot $InterfaceName
        if ($quickTarget) {
            $selected = $quickTarget.Interface
            $gateway = $quickTarget.Gateway
            $reader = 'target_snapshot'
        }

        if (-not $selected) {
            # A down/absent .NET interface needs its administrative state read.
            # An unused disconnected Ethernet adapter is never a substitute.
            $target = Get-NetAdapter -Name $InterfaceName -ErrorAction Stop | Where-Object { $_.Name -eq $InterfaceName } | Select-Object -First 1
            if (-not $target) { throw 'The requested adapter was not returned by Windows.' }
            $windowsAdapterStatus = [string]$target.Status
            if ($target.Status -eq 'Disabled') {
                Write-Reading $false 'disabled' $target.Name $null $null @() 'unknown' 'unknown'
                exit 0
            }
            if ($target.Status -in @('Disconnected', 'Lower Layer Down')) {
                Write-Reading $false 'disconnected' $target.Name $null $null @() 'unknown' 'unknown'
                exit 0
            }
            if ($target.Status -ne 'Up') {
                $errorCode = 'adapter_transitioning'
                throw ('The requested adapter has no stable link state. Windows status: ' + $windowsAdapterStatus + '.')
            }
            $routes = @(Get-NetRoute -AddressFamily IPv4 -InterfaceIndex $target.InterfaceIndex -ErrorAction Stop | Where-Object {
                $_.DestinationPrefix -eq '0.0.0.0/0' -and $_.NextHop -and $_.NextHop -ne '0.0.0.0'
            } | Sort-Object @{ Expression = { [long]$_.RouteMetric + [long]$_.InterfaceMetric } })
            $snapshot = Get-InterfaceSnapshot
            if (-not $snapshot.ByIndex.ContainsKey([int]$target.InterfaceIndex)) {
                $errorCode = 'adapter_snapshot_changed'
                throw 'The requested adapter changed state during the reading. Automatic verification must read it again.'
            }
            $selected = $snapshot.ByIndex[[int]$target.InterfaceIndex]
            if ($routes.Count) { $gateway = [string]$routes[0].NextHop }
        }
    } else {

        $snapshot = Get-InterfaceSnapshot
        $sole = Select-SoleSnapshot $snapshot
        if ($sole) {
            $selected = $sole.Interface
            $gateway = $sole.Gateway
            $reader = 'single_interface_snapshot'
        } else {

            # Keep route-based selection for Ethernet/Wi-Fi/VPN and multiple gateways.
            # Get all IPv4 routes so an absent default route is a normal empty filter.
            $routes = @(Get-NetRoute -AddressFamily IPv4 -ErrorAction Stop | Where-Object {
                $_.DestinationPrefix -eq '0.0.0.0/0' -and $_.NextHop -and $_.NextHop -ne '0.0.0.0'
            } | Sort-Object @{ Expression = { [long]$_.RouteMetric + [long]$_.InterfaceMetric } }, InterfaceIndex)
            $snapshot = Get-InterfaceSnapshot
            $routes = @($routes | Where-Object { $snapshot.ByIndex.ContainsKey([int]$_.InterfaceIndex) })
            if ($routes.Count) {
                $selected = $snapshot.ByIndex[[int]$routes[0].InterfaceIndex]
                $gateway = [string]$routes[0].NextHop
            } else {
                # Disabled adapters can be absent from the .NET snapshot. Query adapter
                # administrative state only on this path, never infer Disabled from Down.
                $physical = @(Get-NetAdapter -Physical -ErrorAction Stop)
                $snapshot = Get-InterfaceSnapshot
                $activePhysical = @($physical | Where-Object { $_.Status -eq 'Up' })
                $active = Select-NamedAdapter -Adapters $activePhysical
                if ($active) {
                    if (-not $snapshot.ByIndex.ContainsKey([int]$active.InterfaceIndex)) {
                        $errorCode = 'adapter_snapshot_changed'
                        throw 'The active adapter changed state during the reading. Windows must return a fresh interface snapshot.'
                    }
                    $selected = $snapshot.ByIndex[[int]$active.InterfaceIndex]
                } else {
                    $disabled = Select-NamedAdapter -Adapters @($physical | Where-Object { $_.Status -eq 'Disabled' })
                    if ($disabled) {
                        Write-Reading $false 'disabled' $disabled.Name $null $null @() 'unknown' 'unknown'
                        exit 0
                    }
                    $disconnected = Select-NamedAdapter -Adapters @($physical | Where-Object { $_.Status -in @('Disconnected', 'Lower Layer Down') })
                    if ($disconnected) {
                        Write-Reading $false 'disconnected' $disconnected.Name $null $null @() 'unknown' 'unknown'
                        exit 0
                    }
                }
            }
        }
    }

    if (-not $selected) {
        Write-Reading $false 'missing' $null $null $null @() 'unknown' 'unknown'
        exit 0
    }
    $properties = $selected.GetIPProperties()
    $ipv4 = @($properties.UnicastAddresses | Where-Object {
        $_.Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and
        $_.Address.ToString() -notlike '127.*' -and $_.Address.ToString() -ne '0.0.0.0' -and
        $_.DuplicateAddressDetectionState -notin @(
            [System.Net.NetworkInformation.DuplicateAddressDetectionState]::Invalid,
            [System.Net.NetworkInformation.DuplicateAddressDetectionState]::Tentative,
            [System.Net.NetworkInformation.DuplicateAddressDetectionState]::Duplicate
        )
    } | Sort-Object @{ Expression = { $_.Address.ToString() -like '169.254.*' } } |
        ForEach-Object { $_.Address.ToString() } | Select-Object -First 1)
    $address = if ($ipv4.Count) { $ipv4[0] } else { $null }
    $dns = @($properties.DnsAddresses | ForEach-Object { $_.ToString() } | Select-Object -Unique)
    $addressSource = if ($properties.GetIPv4Properties().IsDhcpEnabled) { 'dhcp' } else { 'static' }
    $dnsSource = 'unknown'
    $key = $null
    try {
        $guid = '{' + $selected.Id.Trim('{}') + '}'
        $key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Services\Tcpip\Parameters\Interfaces\' + $guid)
        if ($key) {
            $manualDns = [string]$key.GetValue('NameServer', '')
            $dnsSource = if ($manualDns.Trim()) { 'static' } else { 'dhcp' }
        }
    } catch { $dnsSource = 'unknown' } finally { if ($key) { $key.Dispose() } }
    Write-Reading $true 'up' $selected.Name $address $gateway $dns $addressSource $dnsSource
} catch {
    [pscustomobject]@{
        adapterAvailable = $false; adapterState = $null; interfaceName = $(if ($InterfaceName) { $InterfaceName } else { $null })
        localIp = $null; gatewayIp = $null; dnsServers = @()
        addressSource = 'unknown'; dnsSource = 'unknown'; error = $_.Exception.Message
        reader = $reader
        errorCode = $errorCode
        windowsAdapterStatus = $windowsAdapterStatus
    } | ConvertTo-Json -Compress -Depth 4
}
