# Script para iniciar o bot WhatsApp
Write-Host "🚀 Iniciando bot WhatsApp..." -ForegroundColor Green
Write-Host ""

# Verificar se está no diretório correto
if (-not (Test-Path "index.js")) {
    Write-Host "❌ Erro: index.js não encontrado. Execute este script na pasta whatsapp-bot-site-financeiro" -ForegroundColor Red
    exit 1
}

# Matar processos na porta 3001
Write-Host "🔍 Verificando porta 3001..." -ForegroundColor Yellow
node kill-port.js

Start-Sleep -Seconds 2

# Iniciar o bot
Write-Host ""
Write-Host "✅ Iniciando bot..." -ForegroundColor Green
Write-Host "📱 Acesse http://localhost:3001/qr para ver o QR code" -ForegroundColor Cyan
Write-Host ""

node index.js


