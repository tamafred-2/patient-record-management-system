$ErrorActionPreference = 'Stop'
$rhuProjectDirectory = Split-Path -Parent $PSScriptRoot
$rhuLocalPhp = Join-Path $env:LOCALAPPDATA 'RHU-Dev/php83/php.exe'
$rhuPhp = if (Test-Path -LiteralPath $rhuLocalPhp) { $rhuLocalPhp } else { (Get-Command php -ErrorAction Stop).Source }
Push-Location -LiteralPath $rhuProjectDirectory
try {
    & $rhuPhp artisan database:backup --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'SQLite backup or restore verification failed.' }
} finally {
    Pop-Location
}
