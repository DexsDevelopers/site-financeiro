<?php
/**
 * Webhook para deploy automático na Hostinger
 * 
 * Como usar:
 * 1. Configure este arquivo como webhook no GitHub:
 *    Settings > Webhooks > Add webhook
 *    Payload URL: https://seu-site.com/deploy_webhook.php
 *    Content type: application/json
 *    Secret: (opcional, mas recomendado)
 *    Events: Just the push event
 * 
 * 2. OU execute manualmente acessando:
 *    https://seu-site.com/deploy_webhook.php?key=SUA_CHAVE_SECRETA
 * 
 * 3. OU configure um cron job que execute este script periodicamente
 */

// Segurança: chave secreta para autenticação
$SECRET_KEY = 'troque-esta-chave-por-uma-chave-secreta-forte';
$WEBHOOK_SECRET = getenv('WEBHOOK_SECRET') ?: $SECRET_KEY;

// Verificar se é uma requisição do GitHub
$headers = getallheaders();
$githubSignature = $headers['X-Hub-Signature-256'] ?? '';
$githubEvent = $headers['X-GitHub-Event'] ?? '';

// Se for do GitHub, validar assinatura
if (!empty($githubSignature) && !empty($githubEvent)) {
    $payload = file_get_contents('php://input');
    $calculatedSignature = 'sha256=' . hash_hmac('sha256', $payload, $WEBHOOK_SECRET);
    
    if (!hash_equals($calculatedSignature, $githubSignature)) {
        http_response_code(403);
        die('Invalid signature');
    }
    
    // Processar apenas eventos de push
    if ($githubEvent !== 'push') {
        http_response_code(200);
        die('Event ignored: ' . $githubEvent);
    }
    
    $data = json_decode($payload, true);
    $branch = explode('/', $data['ref'])[2] ?? 'main';
    
    // Processar apenas branch main
    if ($branch !== 'main') {
        http_response_code(200);
        die('Branch ignored: ' . $branch);
    }
} else {
    // Requisição manual: verificar chave
    $key = $_GET['key'] ?? '';
    if ($key !== $SECRET_KEY) {
        http_response_code(403);
        die('Invalid key');
    }
}

// Configurações
$BRANCH = 'main';
$REPO_PATH = __DIR__;
$LOG_FILE = $REPO_PATH . '/logs/deploy_webhook.log';

// Criar diretório de logs se não existir
if (!is_dir($REPO_PATH . '/logs')) {
    mkdir($REPO_PATH . '/logs', 0755, true);
}

// Função de log
function logMessage($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($LOG_FILE, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("=== INICIANDO DEPLOY ===");

// Verificar se git está disponível
$gitVersion = shell_exec('git --version 2>&1');
if (empty($gitVersion)) {
    logMessage("ERRO: Git não está disponível no servidor");
    http_response_code(500);
    die('Git not available');
}

// Mudar para o diretório do repositório
chdir($REPO_PATH);

// 1. Verificar status do git
logMessage("Verificando status do repositório...");
$gitStatus = shell_exec('git status 2>&1');
logMessage("Status: " . substr($gitStatus, 0, 100));

// 2. Buscar mudanças do GitHub
logMessage("Buscando mudanças do GitHub...");
$fetchOutput = shell_exec('git fetch origin ' . $BRANCH . ' 2>&1');
logMessage("Fetch: " . $fetchOutput);

// 3. Verificar se há mudanças
$localCommit = shell_exec('git rev-parse HEAD 2>&1');
$remoteCommit = shell_exec('git rev-parse origin/' . $BRANCH . ' 2>&1');

logMessage("Local commit: " . trim($localCommit));
logMessage("Remote commit: " . trim($remoteCommit));

if (trim($localCommit) === trim($remoteCommit)) {
    logMessage("Nenhuma mudança detectada. Já está atualizado.");
    http_response_code(200);
    die('Already up to date');
}

// 4. Fazer pull
logMessage("Fazendo pull do branch " . $BRANCH . "...");
$pullOutput = shell_exec('git pull origin ' . $BRANCH . ' 2>&1');
logMessage("Pull output: " . $pullOutput);

// 5. Verificar se o pull foi bem-sucedido
if (strpos($pullOutput, 'error') !== false || strpos($pullOutput, 'fatal') !== false) {
    logMessage("ERRO no pull: " . $pullOutput);
    http_response_code(500);
    die('Pull failed');
}

// 6. Limpar cache se necessário (opcional)
if (function_exists('opcache_reset')) {
    opcache_reset();
    logMessage("OPcache limpo");
}

logMessage("=== DEPLOY CONCLUÍDO COM SUCESSO ===");
http_response_code(200);
echo "Deploy successful";
?>

