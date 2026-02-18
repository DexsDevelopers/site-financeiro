# Testar URLs Corretas do Site
# Testa diferentes páginas com /seu_projeto/

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " 🔍 Testando URLs Corretas do Site" -ForegroundColor Yellow
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

$urls = @(
    'https://gold-quail-250128.hostingersite.com/seu_projeto/',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/index.php',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/dashboard.php',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/login.php',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/financeiro.php',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/tarefas.php'
)

$urlsFuncionando = @()

foreach ($url in $urls) {
    try {
        Write-Host "Testando: $url" -ForegroundColor Yellow
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
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
    Write-Host "💡 O site pode estar offline ou com problemas temporários" -ForegroundColor Yellow
    Write-Host "🔧 Possíveis causas:" -ForegroundColor Yellow
    Write-Host "  - Site em manutenção" -ForegroundColor Yellow
    Write-Host "  - Problemas de DNS" -ForegroundColor Yellow
    Write-Host "  - Servidor offline" -ForegroundColor Yellow
    Write-Host "  - Deploy ainda em andamento" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Pressione qualquer tecla para continuar..."
$null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
