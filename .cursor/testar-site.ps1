# Testar Site Simples
Write-Host ""
Write-Host '=============================================' -ForegroundColor Cyan
Write-Host " 🔍 Testando Site com /seu_projeto/" -ForegroundColor Yellow
Write-Host '=============================================' -ForegroundColor Cyan
Write-Host ""

Write-Host "Testando: https://gold-quail-250128.hostingersite.com/seu_projeto/" -ForegroundColor Yellow

try {
    $response = Invoke-WebRequest -Uri "https://gold-quail-250128.hostingersite.com/seu_projeto/" -UseBasicParsing -TimeoutSec 10
    Write-Host "✅ SITE ONLINE - Status: $($response.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host "❌ SITE OFFLINE - Erro: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host '=============================================' -ForegroundColor Cyan
Write-Host ' ✅ Teste concluído!' -ForegroundColor Green
Write-Host '=============================================' -ForegroundColor Cyan
