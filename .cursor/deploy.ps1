# Deploy automatico com monitoramento e barra de progresso
# Compatibilidade total com PowerShell Windows
# Sistema de automação integrado com Cursor AI

$BRANCH = "main"
$REPO = "https://github.com/DexsDevelopers/site-financeiro.git"
$CHECK_URL = "https://gold-quail-250128.hostingersite.com/seu_projeto/"  # ajuste se necessario
$LOG_FILE = "logs/deploy.log"

# Criar diretório de logs se não existir
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
Write-Host "=============================================" -ForegroundColor DarkGray
Write-Host " Iniciando deploy automatico - Cursor -> Hostinger" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor DarkGray
Write-Host ""

Write-Log "=== INICIANDO DEPLOY AUTOMÁTICO ===" "INFO"

# 1. Commit e push automaticos
Write-Log "Verificando mudanças..." "INFO"
$gitStatus = git status --porcelain
if ($gitStatus) {
    Write-Log "Mudanças detectadas, fazendo commit..." "INFO"
    git add .
    $commitMessage = "Deploy automático - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    git commit -m $commitMessage | Out-Null
    Write-Log "Commit realizado: $commitMessage" "SUCCESS"
} else {
    Write-Log "Nenhuma mudança detectada" "INFO"
}

git push origin $BRANCH

Write-Host "Codigo enviado para o GitHub ($REPO)" -ForegroundColor Green
Write-Log "Código enviado para o GitHub" "SUCCESS"
Write-Host "Aguardando Hostinger iniciar o deploy..." -ForegroundColor Yellow
Write-Log "Aguardando Hostinger processar o deploy..." "INFO"

# 2. Parametros de monitoramento
$maxWait = 300     # tempo maximo de espera (segundos)
$interval = 5      # intervalo entre verificacoes
$elapsed = 0

# 3. Barra de progresso
while ($elapsed -lt $maxWait) {
    try {
        $response = Invoke-WebRequest -Uri "$CHECK_URL?v=$elapsed" -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-Host ""
            Write-Host "Deploy concluido e site online!" -ForegroundColor Green
            Add-Type -AssemblyName System.Windows.Forms
            [System.Windows.Forms.MessageBox]::Show("Deploy concluido com sucesso na Hostinger!", "Deploy OK", "OK", "Information")
            exit
        }
    } catch {
        $percent = [math]::Round(($elapsed / $maxWait) * 100, 0)
        $barLength = [math]::Round(($percent / 4), 0)
        $progressBar = ("#" * $barLength).PadRight(25, "-")
        Write-Host ("Deploy em andamento: [$progressBar] {0}%  ({1}s)" -f $percent, $elapsed) -ForegroundColor DarkYellow
    }

    Start-Sleep -Seconds $interval
    $elapsed += $interval
}

# 4. Caso o tempo estoure
Write-Host ""
Write-Host "Tempo limite atingido - o deploy pode ainda estar sendo processado ou cacheado." -ForegroundColor Red
Add-Type -AssemblyName System.Windows.Forms
[System.Windows.Forms.MessageBox]::Show("Deploy pode estar em andamento. Verifique o painel Hostinger.", "Deploy pendente", "OK", "Warning")
