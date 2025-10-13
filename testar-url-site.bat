@echo off
title Testar URL do Site
color 0a
echo ==============================================
echo  🔍 Testando URLs do Site
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

echo Testando diferentes URLs do site...
echo.

powershell -Command "
$urls = @(
    'https://gold-quail-250128.hostingersite.com/',
    'https://gold-quail-250128.hostingersite.com/index.php',
    'https://gold-quail-250128.hostingersite.com/dashboard.php',
    'https://gold-quail-250128.hostingersite.com/login.php'
)

foreach ($url in $urls) {
    try {
        Write-Host 'Testando: ' $url -ForegroundColor Yellow
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host '✅ FUNCIONA: ' $url -ForegroundColor Green
            Write-Host 'Status: ' $response.StatusCode -ForegroundColor Green
        }
    } catch {
        Write-Host '❌ ERRO: ' $url -ForegroundColor Red
        Write-Host 'Erro: ' $_.Exception.Message -ForegroundColor Red
    }
    Write-Host ''
}
"

echo.
echo ==============================================
echo ✅ Teste de URLs concluído!
echo ==============================================
pause
