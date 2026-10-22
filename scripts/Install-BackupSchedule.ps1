param([string]$Php = (Get-Command php -ErrorAction Stop).Source)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$action = New-ScheduledTaskAction -Execute $Php -Argument ('"' + (Join-Path $projectRoot 'artisan') + '" backup:run') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Daily -At '02:00'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName 'IS314 Fiji Employment Daily Backup' -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Encrypted local database and private document backup for the IS314 working copy.' -Force | Select-Object TaskName,State
