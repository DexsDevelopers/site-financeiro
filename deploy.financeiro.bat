@echo off
title Otimizacao Avancada 24-7 - Lucas

:: ===========================
:: CONFIGURAÇÕES
:: ===========================
set LOGPATH=%~dp0logs
if not exist "%LOGPATH%" mkdir "%LOGPATH%"
set LOGFILE=%LOGPATH%\log_%date:~-4%-%date:~3,2%-%date:~0,2%.txt"

echo ===== INICIO %date% %time% ===== >> "%LOGFILE%"


:: ===========================
:: LIMPEZA PROFISSIONAL
:: ===========================
echo Limpando temporarios... | tee >> "%LOGFILE%"
del /f /s /q "%TEMP%\*" >nul 2>>"%LOGFILE%"
del /f /s /q "C:\Windows\Temp\*" >nul 2>>"%LOGFILE%"

echo Limpando caches do Explorer... | tee >> "%LOGFILE%"
del /f /s /q "%LOCALAPPDATA%\Microsoft\Windows\Explorer\thumbcache*" >nul 2>>"%LOGFILE%"

echo Limpando cache de updates... | tee >> "%LOGFILE%"
net stop wuauserv >nul 2>>"%LOGFILE%"
net stop bits >nul 2>>"%LOGFILE%"
del /f /s /q "C:\Windows\SoftwareDistribution\Download\*" >nul 2>>"%LOGFILE%"
net start wuauserv >nul 2>>"%LOGFILE%"
net start bits >nul 2>>"%LOGFILE%"

echo Limpando Prefetch... | tee >> "%LOGFILE%"
del /f /s /q "C:\Windows\Prefetch\*" >nul 2>>"%LOGFILE%"


:: ===========================
:: MONITORAMENTO DE CPU E MEMÓRIA
:: ===========================
echo Coletando metrica de CPU e RAM... | tee >> "%LOGFILE%"

for /f "tokens=2 delims==" %%a in ('wmic cpu get LoadPercentage /value') do set CPU=%%a
for /f "tokens=2 delims==" %%a in ('wmic OS get FreePhysicalMemory /value') do set FREEMEM=%%a

echo CPU: %CPU%%% | tee >> "%LOGFILE%"
echo MEMORIA LIVRE: %FREEMEM% KB | tee >> "%LOGFILE%"

:: Auto-ação se CPU > 80%
if %CPU% GTR 80 (
    echo CPU ALTA - otimizando processos... | tee >> "%LOGFILE%"
    wmic process where name="svchost.exe" CALL setpriority "below normal" >nul 2>>"%LOGFILE%"
)

:: Auto-ação se memória baixa (< 800.000 KB)
if %FREEMEM% LSS 800000 (
    echo MEMORIA BAIXA - limpando standby list... | tee >> "%LOGFILE%"
    powershell -command "Clear-Content -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management\PrefetchParameters' -Force" 2>>"%LOGFILE%"
)


:: ===========================
:: NETWORK STACK RESET
:: ===========================
echo Otimizando rede... | tee >> "%LOGFILE%"
ipconfig /flushdns >nul 2>>"%LOGFILE%"


:: ===========================
:: VERIFICACAO DE DISCO
:: ===========================
echo Verificando integridade do disco... | tee >> "%LOGFILE%"
chkdsk C: /scan >nul 2>>"%LOGFILE%"


:: ===========================
:: WATCHDOG DE SERVICOS CRÍTICOS
:: ===========================
echo Rodando watchdog de servicos... | tee >> "%LOGFILE%"

call :watchdog "wuauserv"
call :watchdog "bits"
call :watchdog "Winmgmt"
call :watchdog "EventLog"


:: ===========================
:: FINALIZAÇÃO
:: ===========================
echo ===== FIM %date% %time% ===== >> "%LOGFILE%"
echo Pipeline finalizado. Logs armazenados em: %LOGPATH%
pause
exit /b


:: ===========================
:: FUNÇÃO WATCHDOG
:: ===========================
:watchdog
sc query %1 | find "RUNNING" >nul
if %errorlevel%==1 (
    echo Servico %1 parado. Reiniciando... | tee >> "%LOGFILE%"
    net start %1 >nul 2>>"%LOGFILE%"
)
exit /b
