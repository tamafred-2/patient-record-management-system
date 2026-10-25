param([switch]$Production, [switch]$SkipDependencies)
$ErrorActionPreference = 'Stop'
$rhuProjectDirectory = Split-Path -Parent $PSScriptRoot
$rhuLocalPhp = Join-Path $env:LOCALAPPDATA 'RHU-Dev/php83/php.exe'
if (Test-Path -LiteralPath $rhuLocalPhp) { $env:Path = (Split-Path -Parent $rhuLocalPhp) + [IO.Path]::PathSeparator + $env:Path }
function Invoke-RhuStep {
    param([string]$Program, [string[]]$Arguments)
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Update stopped: $Program failed (exit $LASTEXITCODE)." }
}
Push-Location -LiteralPath $rhuProjectDirectory
try {
    foreach ($rhuTool in @('php', 'npm.cmd')) { Get-Command $rhuTool -ErrorAction Stop | Out-Null }
    if (-not (Test-Path -LiteralPath '.env')) { throw 'This is an update script. Complete first-time setup before running it.' }
    if (Test-Path -LiteralPath 'storage/framework/down') { throw 'Application is already in maintenance mode. Resolve the previous update before retrying.' }
    if (-not $SkipDependencies) {
        Get-Command composer -ErrorAction Stop | Out-Null
        $rhuComposerArgs = @('install', '--prefer-dist', '--no-interaction', '--optimize-autoloader')
        if ($Production) { $rhuComposerArgs += '--no-dev' }
        Invoke-RhuStep 'composer' $rhuComposerArgs
        Invoke-RhuStep 'npm.cmd' @('ci')
    }
    Invoke-RhuStep 'php' @('artisan', 'optimize:clear')
    $rhuCheckArgs = @('artisan', 'deployment:check', '--before-update')
    if ($Production) { $rhuCheckArgs += '--production' }
    Invoke-RhuStep 'php' $rhuCheckArgs
    Invoke-RhuStep 'npm.cmd' @('run', 'build')
    Invoke-RhuStep 'php' @('artisan', 'database:backup', '--no-interaction')
    Invoke-RhuStep 'php' @('artisan', 'down', '--retry=60')
    Invoke-RhuStep 'php' @('artisan', 'migrate', '--force', '--no-interaction')
    Invoke-RhuStep 'php' @('artisan', 'db:seed', '--class=RolePermissionSeeder', '--force', '--no-interaction')
    Invoke-RhuStep 'php' @('artisan', 'optimize')
    $rhuCheckArgs = @('artisan', 'deployment:check')
    if ($Production) { $rhuCheckArgs += '--production' }
    Invoke-RhuStep 'php' $rhuCheckArgs
    Invoke-RhuStep 'php' @('artisan', 'queue:restart')
    Invoke-RhuStep 'php' @('artisan', 'up')
    Write-Output 'Update completed. Restart your supervised workers and run the staff workflow smoke checks.'
} catch {
    Write-Warning 'Do not reset the database or regenerate APP_KEY. If maintenance mode was enabled, it remains enabled until the failure is resolved.'
    throw
} finally {
    Pop-Location
}
