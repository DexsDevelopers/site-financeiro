# Script para iniciar o ngrok para o bot WhatsApp
# Expõe a porta 3001 (bot WhatsApp) através do ngrok

Write-Host "🚀 Iniciando ngrok para bot WhatsApp..." -ForegroundColor Green
Write-Host ""

# Verificar se o ngrok está instalado
$ngrokPath = Get-Command ngrok -ErrorAction SilentlyContinue
if (-not $ngrokPath) {
    Write-Host "❌ Erro: ngrok não encontrado!" -ForegroundColor Red
    Write-Host ""
    Write-Host "📥 Para instalar o ngrok:" -ForegroundColor Yellow
    Write-Host "   1. Baixe em: https://ngrok.com/download" -ForegroundColor Cyan
    Write-Host "   2. Extraia o ngrok.exe" -ForegroundColor Cyan
    Write-Host "   3. Adicione ao PATH ou coloque na pasta do projeto" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "   Ou use: choco install ngrok (se tiver Chocolatey)" -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

# Verificar se o bot está rodando na porta 3001
Write-Host "🔍 Verificando se o bot está rodando na porta 3001..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:3001/status" -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
    Write-Host "✅ Bot está rodando na porta 3001" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Aviso: Bot não está respondendo na porta 3001" -ForegroundColor Yellow
    Write-Host "   Certifique-se de que o bot está rodando antes de iniciar o ngrok" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "   Para iniciar o bot, execute:" -ForegroundColor Cyan
    Write-Host "   cd whatsapp-bot-site-financeiro" -ForegroundColor Cyan
    Write-Host "   .\start-bot.ps1" -ForegroundColor Cyan
    Write-Host ""
    $continue = Read-Host "Deseja continuar mesmo assim? (s/N)"
    if ($continue -ne "s" -and $continue -ne "S") {
        exit 0
    }
}

Write-Host ""
Write-Host "🌐 Iniciando túnel ngrok na porta 3001..." -ForegroundColor Green
Write-Host ""
Write-Host "📋 Instruções:" -ForegroundColor Cyan
Write-Host "   - A URL do ngrok será exibida abaixo" -ForegroundColor White
Write-Host "   - Copie a URL HTTPS (ex: https://xxxxx.ngrok-free.app)" -ForegroundColor White
Write-Host "   - Atualize o arquivo .htaccess com a nova URL se necessário" -ForegroundColor White
Write-Host ""
Write-Host "⚠️  Pressione Ctrl+C para parar o ngrok" -ForegroundColor Yellow
Write-Host ""
Write-Host "=" * 60 -ForegroundColor Gray
Write-Host ""

# Iniciar ngrok
ngrok http 3001






