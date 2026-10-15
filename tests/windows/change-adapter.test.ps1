# Executes only mocked cmdlets around the actual production script. No adapter changes.
$ErrorActionPreference = 'Stop'
$production = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../resources/powershell/change-adapter.ps1'))
$engineName = if ($PSVersionTable.PSEdition -eq 'Desktop') { 'powershell.exe' } elseif ($env:OS -eq 'Windows_NT') { 'pwsh.exe' } else { 'pwsh' }
$engine = Join-Path $PSHOME $engineName
$temporary = Join-Path ([IO.Path]::GetTempPath()) ('nr-command-fixture-' + [guid]::NewGuid().ToString() + '.ps1')
$wrapper = @'
param([string]$Production, [string]$Case, [string]$Action, [string]$Target)
$ErrorActionPreference = 'Stop'
function Import-Module { [CmdletBinding()] param([string]$Name)
    if ($Case -eq 'import_failure') { throw 'Fixture module failure' }
}
function Enable-NetAdapter { [CmdletBinding(SupportsShouldProcess=$true)] param([string]$Name)
    [Console]::Out.WriteLine('fixture_enable:' + $Name)
    if ($Case -eq 'command_failure') { throw 'Fixture access denied' }
}
function Restart-NetAdapter { [CmdletBinding(SupportsShouldProcess=$true)] param([string]$Name)
    [Console]::Out.WriteLine('fixture_restart:' + $Name)
}
& $Production -Action $Action -InterfaceName $Target
exit $LASTEXITCODE
'@
$cases = @(
    @{ Case='enable'; Action='Enable'; Target='Wi-Fi'; Exit=0; Marker='fixture_enable:Wi-Fi' },
    @{ Case='restart'; Action='Restart'; Target='Wi-Fi 2'; Exit=0; Marker='fixture_restart:Wi-Fi 2' },
    @{ Case='import_failure'; Action='Enable'; Target='Wi-Fi'; Exit=1; Marker='Fixture module failure' },
    @{ Case='command_failure'; Action='Enable'; Target='Wi-Fi'; Exit=1; Marker='Fixture access denied' },
    @{ Case='invalid_action'; Action='Other'; Target='Wi-Fi'; Exit=1; Marker=$null },
    @{ Case='invalid_target'; Action='Enable'; Target='Wi-Fi*'; Exit=1; Marker=$null }
)
try {
    Set-Content -LiteralPath $temporary -Value $wrapper -Encoding UTF8
    foreach ($case in $cases) {
        $ErrorActionPreference = 'Continue'
        $output = & $engine -NoLogo -NoProfile -NonInteractive -File $temporary -Production $production -Case $case.Case -Action $case.Action -Target $case.Target 2>&1
        $code = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        $text = $output | Out-String
        if (($case.Exit -eq 0 -and $code -ne 0) -or ($case.Exit -ne 0 -and $code -eq 0)) { throw ('Wrong exit status for ' + $case.Case + ': ' + $text) }
        if ($case.Marker -and -not $text.Contains($case.Marker)) { throw ('Missing marker for ' + $case.Case + ': ' + $text) }
        $calls = @($output | Where-Object { [string]$_ -match '^fixture_(enable|restart):' }).Count
        $expected = if ($case.Case -in @('enable', 'restart', 'command_failure')) { 1 } else { 0 }
        if ($calls -ne $expected) { throw ('Wrong command count for ' + $case.Case) }
        if ($case.Exit -eq 0 -and -not $text.Contains('command_returned')) { throw 'Missing successful completion marker.' }
        if ($case.Exit -ne 0 -and $text.Contains('command_returned')) { throw 'Failure reported successful completion.' }
        Write-Output ('PASS: ' + $case.Case)
    }
} finally {
    Remove-Item -LiteralPath $temporary -Force -ErrorAction SilentlyContinue
}
