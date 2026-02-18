# Script de Deploy Automático - Cursor AI
# Executa automaticamente quando há mudanças no Git

param(
    [string]$CommitMessage = "Deploy automático",
    [switch]$Force = $false
)

# Configurações
$BRANCH = "main"
$REPO = "https://github.com/DexsDevelopers/site-financeiro.git"
$CHECK_URL = "https://gold-quail-250128.hostingersite.com/seu_projeto/"
$LOG_FILE = "logs/auto-deploy.log"

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

Write-Log "=== INICIANDO DEPLOY AUTOMÁTICO ===" "INFO"

try {
    # 1. Verificar se há mudanças para commit
    $gitStatus = git status --porcelain
    if ($gitStatus) {
        Write-Log "Mudanças detectadas, fazendo commit automático..." "INFO"
        
        # Adicionar todas as mudanças
        git add .
        
        # Commit com mensagem automática
        $autoMessage = "Auto-commit: $CommitMessage - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        git commit -m $autoMessage
        
        Write-Log "Commit realizado: $autoMessage" "SUCCESS"
    } else {
        Write-Log "Nenhuma mudança detectada" "INFO"
    }

    # 2. Push para o repositório
    Write-Log "Enviando mudanças para o GitHub..." "INFO"
    git push origin $BRANCH
    
    if ($LASTEXITCODE -eq 0) {
        Write-Log "Código enviado para o GitHub com sucesso" "SUCCESS"
    } else {
        Write-Log "Erro ao enviar para o GitHub" "ERROR"
        exit 1
    }

    # 3. Aguardar deploy da Hostinger
    Write-Log "Aguardando Hostinger processar o deploy..." "INFO"
    
    $maxWait = 300  # 5 minutos
    $interval = 10  # 10 segundos
    $elapsed = 0
    $deploySuccess = $false

    while ($elapsed -lt $maxWait -and !$deploySuccess) {
        try {
            $response = Invoke-WebRequest -Uri "$CHECK_URL?v=$elapsed" -UseBasicParsing -TimeoutSec 10
            
            if ($response.StatusCode -eq 200) {
                $deploySuccess = $true
                Write-Log "Deploy concluído e site online!" "SUCCESS"
                
                # Notificação de sucesso
                if (Get-Command "msg" -ErrorAction SilentlyContinue) {
                    msg $env:USERNAME "Deploy automático concluído com sucesso!"
                }
                
                break
            }
        } catch {
            $percent = [math]::Round(($elapsed / $maxWait) * 100, 0)
            $barLength = [math]::Round(($percent / 4), 0)
            $progressBar = ("█" * $barLength).PadRight(25, "░")
            
            Write-Host "`rDeploy em andamento: [$progressBar] $percent% ($elapsed s)" -NoNewline -ForegroundColor Yellow
        }

        Start-Sleep -Seconds $interval
        $elapsed += $interval
    }

    if (!$deploySuccess) {
        Write-Log "Tempo limite atingido - deploy pode estar em andamento" "WARNING"
    }

    Write-Log "=== DEPLOY AUTOMÁTICO FINALIZADO ===" "INFO"

} catch {
    Write-Log "ERRO no deploy automático: $($_.Exception.Message)" "ERROR"
    exit 1
}
