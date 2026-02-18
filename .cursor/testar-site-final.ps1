# Testar Site Final
Write-Host ""
Write-Host "Testando Site com /seu_projeto/" -ForegroundColor Yellow
Write-Host ""

try {
    $response = Invoke-WebRequest -Uri "https://gold-quail-250128.hostingersite.com/seu_projeto/" -UseBasicParsing -TimeoutSec 10
    Write-Host "SITE ONLINE - Status: $($response.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host "SITE OFFLINE - Erro: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Teste concluido!" -ForegroundColor Green
