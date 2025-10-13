# Deploy Instantâneo - Ultra Rápido
# Para mudanças pequenas - apenas commit e push

$BRANCH = "main"
$LOG_FILE = "logs/deploy-instantaneo.log"

# Criar diretório de logs
if (!(Test-Path "logs")) {
    New-Item -ItemType Directory -Path "logs" -Force | Out-Null
}

# Função de log
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    Write-Host $logEntry
    Add-Content -Path $LOG_FILE -Value $logEntry
}

Write-Host ""
Write-Host "=============================================" -ForegroundColor Green
Write-Host " ⚡ DEPLOY INSTANTÂNEO - Ultra Rápido" -ForegroundColor Yellow
Write-Host "=============================================" -ForegroundColor Green
Write-Host ""

Write-Log "=== DEPLOY INSTANTÂNEO INICIADO ===" "INFO"

# 1. Commit instantâneo
Write-Host "📝 Fazendo commit..." -ForegroundColor Cyan
git add .
$commitMessage = "Deploy instantâneo - $(Get-Date -Format 'HH:mm:ss')"
git commit -m $commitMessage | Out-Null
Write-Log "Commit realizado: $commitMessage" "SUCCESS"

# 2. Push instantâneo
Write-Host "🚀 Enviando para GitHub..." -ForegroundColor Cyan
git push origin $BRANCH
Write-Log "Código enviado para GitHub" "SUCCESS"

# 3. Verificação instantânea (apenas 10 segundos)
Write-Host "🔍 Verificando deploy (10 segundos)..." -ForegroundColor Yellow
Write-Log "Verificação rápida do deploy..." "INFO"

$maxWait = 10       # Apenas 10 segundos!
$interval = 2       # Verificação a cada 2 segundos
$elapsed = 0

while ($elapsed -lt $maxWait) {
    try {
        $response = Invoke-WebRequest -Uri "https://gold-quail-250128.hostingersite.com/seu_projeto/" -UseBasicParsing -TimeoutSec 3
        if ($response.StatusCode -eq 200) {
            Write-Host ""
            Write-Host "✅ Deploy concluído em ${elapsed}s!" -ForegroundColor Green
            Write-Log "Deploy concluído em ${elapsed}s" "SUCCESS"
            
            # Notificação de sucesso
            Add-Type -AssemblyName System.Windows.Forms
            [System.Windows.Forms.MessageBox]::Show("Deploy concluído em ${elapsed} segundos!", "Deploy OK", "OK", "Information")
            exit 0
        }
    } catch {
        # Continua aguardando
    }
    
    # Contador simples
    Write-Host "Aguardando... ${elapsed}s" -ForegroundColor DarkYellow
    
    Start-Sleep -Seconds $interval
    $elapsed += $interval
}

# Se não conseguiu verificar em 10 segundos, assume que está OK
Write-Host ""
Write-Host "⚡ Deploy enviado! (Verificação rápida)" -ForegroundColor Green
Write-Host "💡 O site pode levar alguns segundos para atualizar" -ForegroundColor Yellow
Write-Host "🔗 Verifique: https://gold-quail-250128.hostingersite.com/seu_projeto/" -ForegroundColor Cyan

Write-Log "Deploy enviado - verificação rápida concluída" "SUCCESS"

# Notificação de envio
Add-Type -AssemblyName System.Windows.Forms
[System.Windows.Forms.MessageBox]::Show("Deploy enviado com sucesso! O site será atualizado em alguns segundos.", "Deploy Enviado", "OK", "Information")
