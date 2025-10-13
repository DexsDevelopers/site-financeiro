# Deploy Simples e Rápido
# Versão sem problemas de sintaxe

$BRANCH = "main"
$LOG_FILE = "logs/deploy-simples.log"

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
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " 🚀 DEPLOY SIMPLES E RÁPIDO" -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

Write-Log "=== DEPLOY SIMPLES INICIADO ===" "INFO"

# 1. Commit das mudanças
Write-Log "Fazendo commit das mudanças..." "INFO"
git add .
$commitMessage = "Deploy simples - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $commitMessage | Out-Null
Write-Log "Commit realizado: $commitMessage" "SUCCESS"

# 2. Push para GitHub
Write-Log "Enviando para GitHub..." "INFO"
git push origin $BRANCH
Write-Log "Código enviado para GitHub" "SUCCESS"

# 3. Verificação rápida (1 minuto apenas)
Write-Host "Aguardando deploy da Hostinger (1 minuto)..." -ForegroundColor Yellow
Write-Log "Aguardando deploy da Hostinger..." "INFO"

$maxWait = 60       # Apenas 1 minuto
$interval = 5       # Verificação a cada 5 segundos
$elapsed = 0

while ($elapsed -lt $maxWait) {
    try {
        $response = Invoke-WebRequest -Uri "https://gold-quail-250128.hostingersite.com/" -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host ""
            Write-Host "✅ Site online! Deploy concluído!" -ForegroundColor Green
            Write-Log "Deploy concluído com sucesso" "SUCCESS"
            exit 0
        }
    } catch {
        # Continua aguardando
    }
    
    # Barra de progresso simples
    $percent = [math]::Round(($elapsed / $maxWait) * 100, 0)
    Write-Host "Deploy: $percent% (${elapsed}s)" -ForegroundColor DarkYellow
    
    Start-Sleep -Seconds $interval
    $elapsed += $interval
}

# Tempo limite atingido
Write-Host ""
Write-Host "⏰ Tempo limite atingido (1 minuto)" -ForegroundColor Red
Write-Host "💡 O deploy pode estar em andamento na Hostinger" -ForegroundColor Yellow
Write-Host '🔍 Verifique: https://gold-quail-250128.hostingersite.com/' -ForegroundColor Cyan

Write-Log 'Tempo limite atingido - deploy pode estar em andamento' 'WARNING'
