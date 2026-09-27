# Dot-source this file to select the isolated RHU PHP runtime for this terminal.
# Usage: . .\scripts\use-local-php.ps1
$rhuPhpDirectory = Join-Path $env:LOCALAPPDATA 'RHU-Dev\php83'
if (-not (Test-Path -LiteralPath (Join-Path $rhuPhpDirectory 'php.exe'))) {
    throw 'RHU PHP runtime not found. Install PHP 8.3+ and use it on PATH, or follow docs/local-development.md.'
}
$env:Path = $rhuPhpDirectory + [IO.Path]::PathSeparator + $env:Path
php --version
