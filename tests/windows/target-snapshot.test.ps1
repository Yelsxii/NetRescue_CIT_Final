# Read-only tests of the production selection function, using synthetic interfaces.
# No modules, administrator privileges, network changes or Pester are required.
$ErrorActionPreference = 'Stop'
$readerPath = Join-Path (Join-Path $PSScriptRoot '..\..') 'resources\powershell\read-configuration.ps1'
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile($readerPath, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
$definition = $ast.Find({ param($node)
    $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and
    $node.Name -eq 'Select-TargetSnapshot'
}, $true)
if (-not $definition) { throw 'Production Select-TargetSnapshot function is missing.' }
# Load only that production function. Do not execute the reader's Windows queries.
. ([scriptblock]::Create($definition.Extent.Text))

function New-TestInterface([string]$Name, [string]$Status = 'Up', [string[]]$Gateways = @('192.168.1.1'), [bool]$IPv4 = $true) {
    $info = [pscustomobject]@{
        Name = $Name
        Id = '00000000-0000-0000-0000-000000000001'
        OperationalStatus = [System.Net.NetworkInformation.OperationalStatus]::$Status
        NetworkInterfaceType = [System.Net.NetworkInformation.NetworkInterfaceType]::Wireless80211
        TestIPv4 = $IPv4
        TestProperties = [pscustomobject]@{ GatewayAddresses = @($Gateways | ForEach-Object {
            [pscustomobject]@{ Address = [System.Net.IPAddress]::Parse($_) }
        })
            UnicastAddresses = @([pscustomobject]@{
                Address = [System.Net.IPAddress]::Parse('192.168.1.5')
                DuplicateAddressDetectionState = [System.Net.NetworkInformation.DuplicateAddressDetectionState]::Preferred
            })
            DnsAddresses = @([System.Net.IPAddress]::Parse('192.168.1.1'), [System.Net.IPAddress]::Parse('2001:db8::53'))
        }
    }
    $info.TestProperties | Add-Member -MemberType ScriptMethod -Name GetIPv4Properties -Value {
        return [pscustomobject]@{ Index = 9; IsDhcpEnabled = $true }
    }
    $info | Add-Member -MemberType ScriptMethod -Name Supports -Value { param($component) return $this.TestIPv4 }
    $info | Add-Member -MemberType ScriptMethod -Name GetIPProperties -Value { return $this.TestProperties }
    return $info
}

$passed = 0
function Assert-Test([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
    $script:passed++
    Write-Output ('PASS: ' + $Message)
}

$wifi = New-TestInterface 'Wi-Fi'
$ethernet = New-TestInterface 'Ethernet'
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($ethernet, $wifi) }) 'Wi-Fi'
Assert-Test ($null -ne $choice -and $choice.Interface.Name -eq 'Wi-Fi' -and $choice.Gateway -eq '192.168.1.1') 'Exact Up Wi-Fi is selected even when Ethernet is also Up.'

$down = New-TestInterface 'Wi-Fi' 'Down'
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($ethernet, $down) }) 'Wi-Fi'
Assert-Test ($null -eq $choice) 'Down Wi-Fi requires administrative-state fallback; Ethernet cannot replace it.'

$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($ethernet) }) 'Wi-Fi'
Assert-Test ($null -eq $choice) 'An absent target requires fallback and is never guessed to be disabled.'

$multiple = New-TestInterface 'Wi-Fi' 'Up' @('192.168.1.1', '192.168.1.2')
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($multiple) }) 'Wi-Fi'
Assert-Test ($null -eq $choice) 'Multiple gateways require route-metric fallback.'

$noGateway = New-TestInterface 'Wi-Fi' 'Up' @()
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($noGateway) }) 'Wi-Fi'
Assert-Test ($null -ne $choice -and $null -eq $choice.Gateway) 'Up with no gateway remains a measurable incomplete configuration.'

$ipv6Only = New-TestInterface 'Wi-Fi' 'Up' @('2001:db8::1') $false
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($ipv6Only) }) 'Wi-Fi'
Assert-Test ($null -eq $choice) 'An IPv6-only target does not become an IPv4 configuration.'

$duplicates = New-TestInterface 'Wi-Fi' 'Up' @('192.168.1.1', '192.168.1.1', '2001:db8::1')
$choice = Select-TargetSnapshot ([pscustomobject]@{ Interfaces = @($duplicates) }) 'Wi-Fi'
Assert-Test ($null -ne $choice -and $choice.Gateway -eq '192.168.1.1') 'Duplicate or IPv6 gateway entries do not create a false IPv4 ambiguity.'

# Execute the actual production main block too. Replace OS access with fixtures;
# detailed networking queries must not be called on these unambiguous Up paths.
$writeDefinition = $ast.Find({ param($node)
    $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Write-Reading'
}, $true)
. ([scriptblock]::Create($writeDefinition.Extent.Text))
$mainDefinition = @($ast.EndBlock.Statements | Where-Object {
    $_ -is [System.Management.Automation.Language.TryStatementAst]
})[0]
if (-not $mainDefinition) { throw 'Production reader main block is missing.' }
$main = [scriptblock]::Create($mainDefinition.Extent.Text)
function Get-InterfaceSnapshot { return $script:testSnapshot }
function Get-NetAdapter { throw 'Detailed adapter queries must not run for this fixture.' }
function Get-NetRoute { throw 'Detailed route queries must not run for this fixture.' }
$InterfaceName = 'Wi-Fi'
$reader = 'route_and_adapter'
$script:testSnapshot = [pscustomobject]@{ Interfaces = @($ethernet, $wifi); ByIndex = @{} }
$reading = (& $main | Out-String) | ConvertFrom-Json
Assert-Test ($reading.reader -eq 'target_snapshot' -and -not $reading.error -and
    $reading.adapterAvailable -and $reading.adapterState -eq 'up' -and
    $reading.interfaceName -eq 'Wi-Fi' -and $reading.localIp -eq '192.168.1.5' -and
    $reading.gatewayIp -eq '192.168.1.1' -and $reading.addressSource -eq 'dhcp' -and
    @($reading.dnsServers).Count -eq 2 -and $reading.dnsServers -contains '2001:db8::53') 'Complete production reading returns the target IP, gateway, both DNS families and reader without detailed queries.'

$noGateway.TestProperties.UnicastAddresses = @()
$script:testSnapshot = [pscustomobject]@{ Interfaces = @($noGateway); ByIndex = @{} }
$reading = (& $main | Out-String) | ConvertFrom-Json
Assert-Test ($reading.reader -eq 'target_snapshot' -and -not $reading.error -and
    $reading.adapterState -eq 'up' -and $null -eq $reading.localIp -and $null -eq $reading.gatewayIp) 'Complete production reading preserves missing IP and gateway during acquisition.'

Write-Output ('Completed ' + $passed + ' read-only snapshot tests. These do not test Windows adapter repair.')
