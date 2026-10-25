$ErrorActionPreference = 'Stop'
$rhuBackupScript = Join-Path $PSScriptRoot 'backup-database.ps1'
$rhuProjectDirectory = Split-Path -Parent $PSScriptRoot
$rhuTaskName = 'RHU SQLite Backup'
$rhuAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $rhuBackupScript + '"') -WorkingDirectory $rhuProjectDirectory
$rhuTrigger = New-ScheduledTaskTrigger -Daily -At '18:00'
$rhuPrincipal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$rhuSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew
if (Get-ScheduledTask -TaskName $rhuTaskName -ErrorAction SilentlyContinue) {
    throw 'RHU SQLite Backup already exists. Review its action before changing it.'
}
Register-ScheduledTask -TaskName $rhuTaskName -Action $rhuAction -Trigger $rhuTrigger -Principal $rhuPrincipal -Settings $rhuSettings -Description 'Create and verify a private SQLite backup daily while the current Windows user is signed in.' | Out-Null
Write-Output 'Daily backup registered at 18:00 local time. The Windows user must be signed in; the application need not be running.'
