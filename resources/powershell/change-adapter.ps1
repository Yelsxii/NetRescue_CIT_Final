param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Enable', 'Restart')]
    [string]$Action,
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9 ._()\-]{1,64}$')]
    [string]$InterfaceName
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$watch = [System.Diagnostics.Stopwatch]::StartNew()
function Write-Stage([string]$Stage) {
    [Console]::Out.WriteLine('NetRescue command stage: ' + $Stage + '; elapsed_ms=' + $watch.ElapsedMilliseconds)
    [Console]::Out.Flush()
}

Write-Stage 'powershell_started'
try {
    Write-Stage 'module_import_start'
    Import-Module NetAdapter -ErrorAction Stop
    Write-Stage 'module_import_finished'
    Write-Stage 'command_start'
    if ($Action -eq 'Enable') {
        Enable-NetAdapter -Name $InterfaceName -Confirm:$false -ErrorAction Stop
    } else {
        Restart-NetAdapter -Name $InterfaceName -Confirm:$false -ErrorAction Stop
    }
    Write-Stage 'command_returned'
    exit 0
} catch {
    Write-Stage 'command_error'
    [Console]::Error.WriteLine($_.Exception.Message)
    exit 1
}
