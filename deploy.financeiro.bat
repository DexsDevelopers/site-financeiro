@echo off
title CleanOps EXTREME+ - Remove UWP, Disable Tasks, Hard Reset Search
setlocal enabledelayedexpansion

:: Check admin
openfiles >nul 2>&1
if %errorlevel% neq 0 (
  echo Execute este script como Administrador (Run as Administrator).
  pause
  exit /b 1
)

echo === CLEANOPS EXTREME+ ===
echo Rodando em: %DATE% %TIME%

:: Create log folder
set LOGDIR=C:\CleanOps_ExtremePlus
md "%LOGDIR%" 2>nul

:: Run the heavy lifting in PowerShell
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"$ErrorActionPreference='Stop'; ^
$log='%LOGDIR%\cleanops_extreme_plus_log_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.txt'; ^
Start-Transcript -Path $log -Force; ^
Write-Output '=== CleanOps EXTREME+ PowerShell Routine ==='; ^

# 1) Tentar criar ponto de restauração (se habilitado)
try { if (Get-Service -Name 'srservice' -ErrorAction SilentlyContinue) { Checkpoint-Computer -Description 'CleanOps_EXTREME_PLUS' -RestorePointType 'MODIFY_SETTINGS' -ErrorAction SilentlyContinue; Write-Output 'Restore point attempted.' } else { Write-Output 'System Restore service absent or disabled.' } } catch { Write-Output 'Restore point failed: ' + $_.Exception.Message } ; ^

# 2) Exportar lista de Appx instalados (backup inventory)
$backupFolder = '%LOGDIR%\appx_backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss'); New-Item -Path $backupFolder -ItemType Directory -Force | Out-Null; ^
Get-AppxPackage -AllUsers | Select-Object Name, PackageFullName | Sort-Object Name | Out-File -FilePath (Join-Path $backupFolder 'appx_allusers_list.txt'); ^
Get-AppxProvisionedPackage -Online | Select-Object PackageName | Sort-Object PackageName | Out-File -FilePath (Join-Path $backupFolder 'provisioned_appx_list.txt'); ^
Write-Output 'Appx inventories exported to ' + $backupFolder ; ^

# 3) Lista de apps alvo (remover recorrentemente bloatware UWP - NÃO remove Edge/Store)
$targets = @(
 'Microsoft.XboxApp',
 'Microsoft.XboxGameOverlay',
 'Microsoft.XboxGamingOverlay',
 'Microsoft.XboxGameCallableUI',
 'Microsoft.ZuneMusic',
 'Microsoft.ZuneVideo',
 'Microsoft.WindowsFeedbackHub',
 'Microsoft.GetHelp',
 'Microsoft.Getstarted',
 'Microsoft.Messaging',
 'Microsoft.People',
 'Microsoft.Microsoft3DViewer',
 'Microsoft.MixedReality.Portal',
 'Microsoft.MicrosoftOfficeHub',
 'Microsoft.SkypeApp',
 'Microsoft.Print3D',
 'Microsoft.BingNews',
 'Microsoft.BingWeather',
 'Microsoft.WindowsAlarms',
 'Microsoft.WindowsMaps',
 'Microsoft.3DBuilder'
) ; ^

Write-Output '-> Removendo pacotes AppX (CurrentUser & AllUsers) listados...'; ^
foreach ($t in $targets) { ^
  try { ^
    Get-AppxPackage -AllUsers | Where-Object { $_.Name -like "$t*" } | ForEach-Object { Write-Output 'Removing (user): ' + $_.PackageFullName; Remove-AppxPackage -Package $_.PackageFullName -ErrorAction SilentlyContinue } ; ^
    Get-AppxProvisionedPackage -Online | Where-Object { $_.PackageName -like "$t*" } | ForEach-Object { Write-Output 'Removing provisioned: ' + $_.PackageName; Remove-AppxProvisionedPackage -Online -PackageName $_.PackageName -ErrorAction SilentlyContinue } ; ^
  } catch { Write-Output 'Erro ao remover '+$t+': '+$_.Exception.Message } ^
} ; ^

# 4) Uninstall OneDrive (optional aggressive) - leave commented; user can uncomment if wants full removal
# try { Write-Output 'Attempting OneDrive uninstall (x86/x64)'; Start-Process -FilePath 'C:\Windows\SysWOW64\OneDriveSetup.exe' -ArgumentList '/uninstall' -NoNewWindow -Wait -ErrorAction SilentlyContinue } catch { Write-Output 'OneDrive uninstall failed or OneDrive not present.' }

# 5) Disable a set of non-critical scheduled tasks (feedback/telemetry/etc)
Write-Output '-> Disabling selected scheduled tasks (Customer Experience / Feedback / Tips)'; ^
$taskPaths = @('\Microsoft\Windows\Customer Experience Improvement Program\*', '\Microsoft\Windows\WindowsFeedback\*', '\Microsoft\Windows\Application Experience\ProgramDataUpdater', '\Microsoft\Windows\Application Experience\AitAgent', '\Microsoft\Windows\Shell\FamilySafetyMonitorTask') ; ^
foreach ($p in $taskPaths) { try { Get-ScheduledTask -TaskPath (Split-Path $p) -ErrorAction SilentlyContinue | Where-Object { $_.TaskName -ne '' } | ForEach-Object { Disable-ScheduledTask -TaskName $_.TaskName -TaskPath $_.TaskPath -ErrorAction SilentlyContinue; Write-Output 'Disabled: ' + $_.TaskPath + $_.TaskName } } catch { Write-Output 'Task disable error for '+$p+': '+$_.Exception.Message } } ; ^

# 6) Cleanup orphan tasks under \Microsoft\Windows\WindowsAzure or other telemetry-ish paths (disable if found)
try { Get-ScheduledTask | Where-Object { $_.TaskPath -like '\Microsoft\Windows\*Feedback*' -or $_.TaskPath -like '\Microsoft\Windows\Customer*' } | ForEach-Object { Disable-ScheduledTask -TaskName $_.TaskName -TaskPath $_.TaskPath -ErrorAction SilentlyContinue; Write-Output 'Disabled (pattern): ' + $_.TaskPath + $_.TaskName } } catch { }

# 7) Hard reset Windows Search (index database purge -> rebuild)
Write-Output '-> Hard resetting Windows Search index (will force rebuild).'; ^
try { ^
  Write-Output 'Stopping WSearch service...'; Stop-Service -Name 'WSearch' -Force -ErrorAction SilentlyContinue; ^
  $searchData = 'C:\ProgramData\Microsoft\Search\Data\Applications\Windows'; ^
  if (Test-Path $searchData) { Write-Output 'Removing search DB files at ' + $searchData; Remove-Item -Path $searchData\* -Recurse -Force -ErrorAction SilentlyContinue } else { Write-Output 'Search data path not found: ' + $searchData } ; ^
  Write-Output 'Clearing Search registry profile list (best-effort)'; ^
  try { Remove-Item -Path 'HKLM:\SOFTWARE\Microsoft\Windows Search\Gather\Windows\SystemIndex' -Recurse -Force -ErrorAction SilentlyContinue } catch { } ; ^
  Start-Service -Name 'WSearch' -ErrorAction SilentlyContinue; ^
  Write-Output 'Windows Search service restarted; index will rebuild automatically (could take time).' ^
} catch { Write-Output 'Windows Search reset error: ' + $_.Exception.Message } ; ^

# 8) Aggressive housekeeping (temp, prefetch, thumbnail, winsock reset etc)
Write-Output '-> Housekeeping: temp/prefetch/thumbcache and network reset'; ^
try { ^
  Remove-Item -Path $env:TEMP\* -Recurse -Force -ErrorAction SilentlyContinue; ^
  Remove-Item -Path 'C:\Windows\Temp\*' -Recurse -Force -ErrorAction SilentlyContinue; ^
  Remove-Item -Path 'C:\Windows\Prefetch\*' -Recurse -Force -ErrorAction SilentlyContinue; ^
  Remove-Item -Path (Join-Path $env:LOCALAPPDATA 'Microsoft\Windows\Explorer\thumbcache_*') -Force -ErrorAction SilentlyContinue; ^
  ipconfig /flushdns | Out-Null; ^
  netsh int ip reset | Out-Null; ^
  netsh winsock reset | Out-Null; ^
  Write-Output 'Housekeeping done.' ^
} catch { Write-Output 'Housekeeping error: ' + $_.Exception.Message } ; ^

# 9) Optional: remove UWP provisioned leftovers with known wildcard patterns (final sweep)
Write-Output '-> Sweep provisioned packages with broad patterns (careful)'; ^
try { Get-AppxProvisionedPackage -Online | Where-Object { $_.PackageName -match 'Xbox|Zune|3D|Messaging|MixedReality|Microsoft.MicrosoftOfficeHub|People|Skype' } | ForEach-Object { Write-Output 'Removing provisioned (sweep): ' + $_.PackageName; Remove-AppxProvisionedPackage -Online -PackageName $_.PackageName -ErrorAction SilentlyContinue } } catch { Write-Output 'Provisioned sweep error: ' + $_.Exception.Message } ; ^

# 10) Rebuild Search Index (trigger)
try { Write-Output 'Triggering SearchIndexer restart to encourage rebuild'; Stop-Service -Name 'WSearch' -Force -ErrorAction SilentlyContinue; Start-Service -Name 'WSearch' -ErrorAction SilentlyContinue; } catch { }

Write-Output '=== END: CleanOps EXTREME+ ==='; ^
Stop-Transcript; ^
" 

echo.
echo Finalizado. Logs e inventários salvos em %LOGDIR%.
echo Atenção: Windows pode reconstruir índices em background — pode demorar e usar CPU/disk.
echo Se notar apps importantes faltando, restaure via backup do inventário (tem listagens em %LOGDIR%).
pause
endlocal
exit /b 0
