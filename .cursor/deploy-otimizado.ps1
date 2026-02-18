# Deploy Otimizado - Versão Rápida
# Resolve problemas de lentidão e URL incorreta

$BRANCH = "main"
$REPO = "https://github.com/DexsDevelopers/site-financeiro.git"
$LOG_FILE = "logs/deploy-otimizado.log"

# URLs de teste (adicione a URL correta do seu site)
$TEST_URLS = @(
    "https://gold-quail-250128.hostingersite.com/",
    "https://gold-quail-250128.hostingersite.com/index.php",
    "https://gold-quail-250128.hostingersite.com/dashboard.php"
)

# Criar diretório de logs se não existir
if (!(Test-Path "logs")) {
    New-Item -ItemType Directory -Path "logs" -Force | Out-Null
}

# Função de log otimizada
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    Write-Host $logEntry
    Add-Content -Path $LOG_FILE -Value $logEntry
}

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " 🚀 DEPLOY OTIMIZADO - Versão Rápida" -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

Write-Log "=== DEPLOY OTIMIZADO INICIADO ===" "INFO"

# 1. Commit rápido (sem verificação desnecessária)
Write-Log "Fazendo commit das mudanças..." "INFO"
git add .
$commitMessage = "Deploy otimizado - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
git commit -m $commitMessage | Out-Null
Write-Log "Commit realizado: $commitMessage" "SUCCESS"

# 2. Push para GitHub
Write-Log "Enviando para GitHub..." "INFO"
git push origin $BRANCH
Write-Log "Código enviado para GitHub" "SUCCESS"

# 3. Verificação rápida do deploy (tempo reduzido)
Write-Host "Aguardando deploy da Hostinger..." -ForegroundColor Yellow
Write-Log "Aguardando deploy da Hostinger..." "INFO"

$maxWait = 120      # Reduzido para 2 minutos
$interval = 3       # Verificação a cada 3 segundos
$elapsed = 0

while ($elapsed -lt $maxWait) {
    $siteOnline = $false
    
    # Testa múltiplas URLs
    foreach ($url in $TEST_URLS) {
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) {
                $siteOnline = $true
                Write-Host ""
                Write-Host "✅ Site online! URL: $url" -ForegroundColor Green
                Write-Log "Site verificado online: $url" "SUCCESS"
                break
            }
        } catch {
            # Continua testando outras URLs
        }
    }
    
    if ($siteOnline) {
        # Notificação de sucesso
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.MessageBox]::Show("Deploy concluído com sucesso! Site online.", "Deploy OK", "OK", "Information")
        Write-Log "Deploy concluído com sucesso" "SUCCESS"
        exit 0
    }
    
    # Barra de progresso otimizada
    $percent = [math]::Round(($elapsed / $maxWait) * 100, 0)
    $barLength = [math]::Round(($percent / 4), 0)
    $progressBar = ("█" * $barLength).PadRight(25, "░")
    Write-Host "Deploy: [$progressBar] $percent%  (${elapsed}s)" -ForegroundColor DarkYellow -NoNewline
    Write-Host "`r" -NoNewline
    
    Start-Sleep -Seconds $interval
    $elapsed += $interval
}

# Se chegou aqui, o tempo limite foi atingido
Write-Host ""
Write-Host "⏰ Tempo limite atingido (2 minutos)" -ForegroundColor Red
Write-Host "💡 O deploy pode estar em andamento na Hostinger" -ForegroundColor Yellow
Write-Host "🔍 Verifique manualmente: https://gold-quail-250128.hostingersite.com/" -ForegroundColor Cyan

Write-Log 'Tempo limite atingido - deploy pode estar em andamento' 'WARNING'

# Notificação de tempo limite
Add-Type -AssemblyName System.Windows.Forms
[System.Windows.Forms.MessageBox]::Show('Deploy pode estar em andamento. Verifique o painel Hostinger.', 'Deploy em andamento', 'OK', 'Warning')
