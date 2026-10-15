# Synthetic local adapter states; no real adapter cmdlets or snapshots are used.
$ErrorActionPreference = 'Stop'
$path = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../resources/powershell/read-configuration.ps1'))
$tokens = $null
$errors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile($path, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
$snapshot = $ast.Find({ param($node)
    $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Get-InterfaceSnapshot'
}, $true)
if (-not $snapshot) { throw 'Production snapshot function was not found.' }
$body = [IO.File]::ReadAllText($path).Replace($snapshot.Extent.Text, 'function Get-InterfaceSnapshot { return [pscustomobject]@{ Interfaces = @(); ByIndex = @{} } }')
$directory = Join-Path ([IO.Path]::GetTempPath()) ('nr-reader-fixture-' + [guid]::NewGuid().ToString())
$engineName = if ($PSVersionTable.PSEdition -eq 'Desktop') { 'powershell.exe' } elseif ($env:OS -eq 'Windows_NT') { 'pwsh.exe' } else { 'pwsh' }
$engine = Join-Path $PSHOME $engineName
$wrapper = @'
param([string]$Reader, [string]$Status)
$ErrorActionPreference = 'Stop'
function Get-NetAdapter { [CmdletBinding()] param([string]$Name)
    [pscustomobject]@{ Name='Wi-Fi'; Status=$Status; InterfaceIndex=11 }
}
function Get-NetRoute { [CmdletBinding()] param($AddressFamily, $InterfaceIndex) return @() }
& $Reader -InterfaceName 'Wi-Fi'
exit $LASTEXITCODE
'@
try {
    $null = New-Item -ItemType Directory -Path $directory
    $reader = Join-Path $directory 'reader.ps1'
    $runner = Join-Path $directory 'runner.ps1'
    Set-Content -LiteralPath $reader -Value $body -Encoding UTF8
    Set-Content -LiteralPath $runner -Value $wrapper -Encoding UTF8
    foreach ($status in @('Unknown', 'Disabled', 'Disconnected', 'Up')) {
        $output = & $engine -NoLogo -NoProfile -NonInteractive -File $runner -Reader $reader -Status $status
        if ($LASTEXITCODE -ne 0) { throw ('Fixture failed: ' + $status) }
        $reading = ($output | Out-String) | ConvertFrom-Json
        if ($reading.interfaceName -ne 'Wi-Fi') { throw 'Target adapter was lost.' }
        if ($status -eq 'Unknown') {
            if ($reading.errorCode -ne 'adapter_transitioning' -or $reading.windowsAdapterStatus -ne 'Unknown' -or $null -ne $reading.adapterState) { throw 'Unknown was misclassified.' }
        } elseif ($status -eq 'Up') {
            if ($reading.errorCode -ne 'adapter_snapshot_changed') { throw 'Snapshot change was not retained.' }
        } elseif ($reading.adapterState -ne $status.ToLowerInvariant()) {
            throw ('Measured state changed: ' + $status)
        }
        Write-Output ('PASS: synthetic ' + $status)
    }
} finally {
    if (Test-Path -LiteralPath $directory) { Remove-Item -LiteralPath $directory -Recurse -Force }
}
