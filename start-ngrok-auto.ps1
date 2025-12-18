# Script avançado para iniciar ngrok e atualizar .htaccess automaticamente
# Expõe a porta 3001 e atualiza a configuração

Write-Host "🚀 Iniciando ngrok com atualização automática..." -ForegroundColor Green
Write-Host ""

# Verificar se o ngrok está instalado
$ngrokPath = Get-Command ngrok -ErrorAction SilentlyContinue
if (-not $ngrokPath) {
    Write-Host "❌ Erro: ngrok não encontrado!" -ForegroundColor Red
    Write-Host ""
    Write-Host "📥 Para instalar:" -ForegroundColor Yellow
    Write-Host "   Baixe em: https://ngrok.com/download" -ForegroundColor Cyan
    Write-Host "   Ou: choco install ngrok" -ForegroundColor Cyan
    exit 1
}

# Verificar se o bot está rodando
Write-Host "🔍 Verificando bot na porta 3001..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:3001/status" -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
    Write-Host "✅ Bot está rodando" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Bot não está respondendo. Inicie o bot primeiro:" -ForegroundColor Yellow
    Write-Host "   cd whatsapp-bot-site-financeiro && .\start-bot.ps1" -ForegroundColor Cyan
    exit 1
}

Write-Host ""
Write-Host "🌐 Iniciando ngrok..." -ForegroundColor Green
Write-Host ""

# Iniciar ngrok em background e capturar a URL
$ngrokProcess = Start-Process -FilePath "ngrok" -ArgumentList "http", "3001", "--log=stdout" -NoNewWindow -PassThru -RedirectStandardOutput "ngrok-output.txt"

Start-Sleep -Seconds 5

# Tentar obter a URL da API do ngrok
try {
    $ngrokApi = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -TimeoutSec 2 -ErrorAction Stop
    $publicUrl = ($ngrokApi.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1).public_url
    
    if ($publicUrl) {
        Write-Host "✅ Ngrok iniciado com sucesso!" -ForegroundColor Green
        Write-Host ""
        Write-Host "🌐 URL pública: $publicUrl" -ForegroundColor Cyan
        Write-Host ""
        
        # Atualizar .htaccess
        $htaccessPath = ".\.htaccess"
        if (Test-Path $htaccessPath) {
            Write-Host "📝 Atualizando .htaccess..." -ForegroundColor Yellow
            $htaccessContent = Get-Content $htaccessPath -Raw
            $newContent = $htaccessContent -replace 'SetEnv WHATSAPP_API_URL https://[^\s]+', "SetEnv WHATSAPP_API_URL $publicUrl"
            
            if ($newContent -ne $htaccessContent) {
                Set-Content -Path $htaccessPath -Value $newContent -NoNewline
                Write-Host "✅ .htaccess atualizado com a nova URL" -ForegroundColor Green
            } else {
                Write-Host "⚠️  Não foi possível atualizar .htaccess automaticamente" -ForegroundColor Yellow
                Write-Host "   Atualize manualmente com: $publicUrl" -ForegroundColor Cyan
            }
        } else {
            Write-Host "⚠️  Arquivo .htaccess não encontrado" -ForegroundColor Yellow
        }
        
        Write-Host ""
        Write-Host "=" * 60 -ForegroundColor Gray
        Write-Host "✅ Configuração concluída!" -ForegroundColor Green
        Write-Host ""
        Write-Host "📋 URL do ngrok: $publicUrl" -ForegroundColor Cyan
        Write-Host "⚠️  Mantenha este terminal aberto para manter o túnel ativo" -ForegroundColor Yellow
        Write-Host "   Pressione Ctrl+C para parar" -ForegroundColor Yellow
        Write-Host ""
        
        # Manter o processo rodando
        $ngrokProcess.WaitForExit()
    } else {
        Write-Host "⚠️  Não foi possível obter a URL do ngrok automaticamente" -ForegroundColor Yellow
        Write-Host "   Verifique manualmente em: http://localhost:4040" -ForegroundColor Cyan
        $ngrokProcess.WaitForExit()
    }
} catch {
    Write-Host "⚠️  Erro ao obter URL do ngrok: $_" -ForegroundColor Yellow
    Write-Host "   O ngrok está rodando, mas não foi possível obter a URL automaticamente" -ForegroundColor Yellow
    Write-Host "   Acesse: http://localhost:4040 para ver a URL" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "   Pressione Ctrl+C para parar" -ForegroundColor Yellow
    $ngrokProcess.WaitForExit()
}





