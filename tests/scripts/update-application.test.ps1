$ErrorActionPreference = 'Stop'
$rhuSandbox = Join-Path ([IO.Path]::GetTempPath()) ('rhu-update-check-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path (Join-Path $rhuSandbox 'scripts') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot '../../scripts/update-application.ps1') -Destination (Join-Path $rhuSandbox 'scripts/update-application.ps1')
Set-Content -LiteralPath (Join-Path $rhuSandbox '.env') -Value 'TEST_FIXTURE_ONLY=true'
$global:rhuCalls = [Collections.Generic.List[string]]::new()
$global:rhuFailMigration = $false
function global:php {
    $rhuInvocation = $args -join ' '
    $global:rhuCalls.Add($rhuInvocation)
    $global:LASTEXITCODE = if ($global:rhuFailMigration -and $rhuInvocation -like 'artisan migrate*') { 1 } else { 0 }
}
function global:npm.cmd { $global:rhuCalls.Add('npm ' + ($args -join ' ')); $global:LASTEXITCODE = 0 }
try {
    & (Join-Path $rhuSandbox 'scripts/update-application.ps1') -SkipDependencies
    if ($global:rhuCalls[-1] -ne 'artisan up') { throw 'Successful update did not reopen the application.' }
    $rhuBackupIndex = $global:rhuCalls.IndexOf('artisan database:backup --no-interaction')
    $rhuMigrateIndex = $global:rhuCalls.IndexOf('artisan migrate --force --no-interaction')
    if ($rhuBackupIndex -lt 0 -or $rhuBackupIndex -ge $rhuMigrateIndex) { throw 'Migration was not preceded by a backup.' }
    $global:rhuCalls.Clear()
    $global:rhuFailMigration = $true
    $rhuFailed = $false
    try { & (Join-Path $rhuSandbox 'scripts/update-application.ps1') -SkipDependencies } catch { $rhuFailed = $true }
    if (-not $rhuFailed -or $global:rhuCalls.Contains('artisan up')) { throw 'Failed migration did not stop the update safely.' }
    if ($global:rhuCalls.Exists([Predicate[string]]{ param($step) $step -like 'artisan db:seed*' })) { throw 'Update continued after migration failure.' }
    Write-Output 'PASS: successful completion, backup ordering and fail-stop maintenance behavior (simulated commands).'
} finally {
    Remove-Item Function:\php -ErrorAction SilentlyContinue
    Remove-Item Function:\npm.cmd -ErrorAction SilentlyContinue
    $rhuResolvedSandbox = [IO.Path]::GetFullPath($rhuSandbox)
    $rhuTempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\') + '\'
    if ($rhuResolvedSandbox.StartsWith($rhuTempRoot, [StringComparison]::OrdinalIgnoreCase) -and (Split-Path -Leaf $rhuResolvedSandbox) -like 'rhu-update-check-*') {
        Remove-Item -LiteralPath $rhuResolvedSandbox -Recurse -Force
    }
}
