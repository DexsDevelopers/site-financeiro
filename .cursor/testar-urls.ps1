# Testar URLs do Site
# Encontra a URL correta do site

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " 🔍 Testando URLs do Site" -ForegroundColor Yellow
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

$urls = @(
    'https://gold-quail-250128.hostingersite.com/',
    'https://gold-quail-250128.hostingersite.com/index.php',
    'https://gold-quail-250128.hostingersite.com/dashboard.php',
    'https://gold-quail-250128.hostingersite.com/login.php',
    'https://gold-quail-250128.hostingersite.com/financeiro.php'
)

$urlsFuncionando = @()

foreach ($url in $urls) {
    try {
        Write-Host "Testando: $url" -ForegroundColor Yellow
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host "✅ FUNCIONA: $url" -ForegroundColor Green
            Write-Host "Status: $($response.StatusCode)" -ForegroundColor Green
            $urlsFuncionando += $url
        }
    } catch {
        Write-Host "❌ ERRO: $url" -ForegroundColor Red
        Write-Host "Erro: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " 📊 RESULTADO:" -ForegroundColor Yellow
Write-Host "=============================================" -ForegroundColor Cyan

if ($urlsFuncionando.Count -gt 0) {
    Write-Host "✅ URLs que funcionam:" -ForegroundColor Green
    foreach ($url in $urlsFuncionando) {
        Write-Host "  - $url" -ForegroundColor Green
    }
} else {
    Write-Host "❌ Nenhuma URL funcionou" -ForegroundColor Red
    Write-Host "💡 O site pode estar offline ou a URL pode estar incorreta" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Pressione qualquer tecla para continuar..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
