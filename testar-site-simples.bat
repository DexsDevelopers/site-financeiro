@echo off
title Testar Site - Helmer
color 0a
echo ==============================================
echo  🔍 Testando Site com /seu_projeto/
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

echo Testando URLs do site...
echo.

powershell -Command "
Write-Host 'Testando: https://gold-quail-250128.hostingersite.com/seu_projeto/' -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri 'https://gold-quail-250128.hostingersite.com/seu_projeto/' -UseBasicParsing -TimeoutSec 10
    Write-Host '✅ SITE ONLINE - Status:' $response.StatusCode -ForegroundColor Green
} catch {
    Write-Host '❌ SITE OFFLINE - Erro:' $_.Exception.Message -ForegroundColor Red
}
"

echo.
echo ==============================================
echo ✅ Teste concluído!
echo ==============================================
pause
