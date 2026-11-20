# Installs (or updates) the Windows scheduled task "Payslip DB Backup".
# Runs `php artisan backup:database --if-due` every day at 23:00 and at Windows sign-in;
# a run missed because the PC was off starts as soon as the PC is on again.
#
# Usage (PowerShell):  powershell -ExecutionPolicy Bypass -File scripts\install-backup-task.ps1 [-Time 23:00] [-Php C:\xampp\php\php.exe]
param(
    [string]$Time = '23:00',
    [string]$Php = 'C:\xampp\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$artisan = Join-Path $project 'artisan'
if (-not (Test-Path $Php)) { throw "PHP not found at $Php. Pass -Php <path to php.exe>." }

$action = New-ScheduledTaskAction -Execute $Php -Argument "`"$artisan`" backup:database --if-due" -WorkingDirectory $project
$triggers = @(
    (New-ScheduledTaskTrigger -Daily -At $Time),
    (New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME")
)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew
# S4U = runs in the background (no console window) even when nobody is signed in, without storing a password
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType S4U -RunLevel Limited

try {
    Register-ScheduledTask -TaskName 'Payslip DB Backup' -Description 'Daily MySQL backup of the Payslip Management database' `
        -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Force | Out-Null
    Write-Output "Installed 'Payslip DB Backup' (background, daily at $Time and at sign-in)."
} catch {
    # Fallback when S4U is not allowed for this account: run while the user is signed in
    $principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName 'Payslip DB Backup' -Description 'Daily MySQL backup of the Payslip Management database' `
        -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Force | Out-Null
    Write-Output "Installed 'Payslip DB Backup' (runs while you are signed in, daily at $Time and at sign-in)."
}
