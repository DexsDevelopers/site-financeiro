<?php
// teste_automacao.php - Teste do sistema de automação
echo "<h1>🧪 Teste do Sistema de Automação</h1>";
echo "<hr>";

// 1. Verificar arquivos de configuração
echo "<h2>1. Verificação dos Arquivos de Configuração</h2>";

$configFiles = [
    '.cursor/automation.json' => 'Configuração de automação',
    '.cursor/settings.json' => 'Configurações do Cursor',
    '.cursor/deploy.ps1' => 'Script de deploy principal',
    '.cursor/auto-deploy.ps1' => 'Script de deploy automático'
];

foreach ($configFiles as $file => $description) {
    if (file_exists($file)) {
        echo "✅ <strong>$description</strong> ($file) - <span style='color: green;'>EXISTE</span><br>";
        
        // Verificar se o arquivo não está vazio
        $content = file_get_contents($file);
        if (strlen($content) > 0) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;📄 Arquivo não está vazio (" . strlen($content) . " bytes)<br>";
        } else {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;⚠️ Arquivo está vazio<br>";
        }
    } else {
        echo "❌ <strong>$description</strong> ($file) - <span style='color: red;'>NÃO EXISTE</span><br>";
    }
}

// 2. Verificar diretório de logs
echo "<br><h2>2. Verificação do Sistema de Logs</h2>";
if (!file_exists('logs')) {
    echo "📁 Criando diretório de logs...<br>";
    mkdir('logs', 0755, true);
    echo "✅ Diretório 'logs' criado<br>";
} else {
    echo "✅ Diretório 'logs' existe<br>";
}

// 3. Testar permissões do PowerShell
echo "<br><h2>3. Teste de Permissões do PowerShell</h2>";
$psCommand = 'Get-ExecutionPolicy';
$output = shell_exec("powershell -Command \"$psCommand\" 2>&1");
echo "Política de execução atual: <strong>" . trim($output) . "</strong><br>";

if (strpos($output, 'Restricted') !== false) {
    echo "⚠️ <span style='color: orange;'>Política restritiva detectada. Execute: Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser</span><br>";
} else {
    echo "✅ <span style='color: green;'>Política de execução adequada</span><br>";
}

// 4. Verificar configuração do Git
echo "<br><h2>4. Verificação da Configuração do Git</h2>";
$gitUser = shell_exec("git config user.name 2>&1");
$gitEmail = shell_exec("git config user.email 2>&1");
$gitRemote = shell_exec("git remote -v 2>&1");

echo "Usuário Git: <strong>" . trim($gitUser) . "</strong><br>";
echo "Email Git: <strong>" . trim($gitEmail) . "</strong><br>";
echo "Repositório remoto:<br>";
echo "<pre>" . htmlspecialchars($gitRemote) . "</pre>";

// 5. Simular teste de automação
echo "<br><h2>5. Simulação de Teste de Automação</h2>";
echo "Para testar a automação manualmente, execute:<br>";
echo "<code>powershell -ExecutionPolicy Bypass -File \".\\.cursor\\deploy.ps1\"</code><br><br>";

echo "Ou para o script de automação:<br>";
echo "<code>powershell -ExecutionPolicy Bypass -File \".\\.cursor\\auto-deploy.ps1\"</code><br><br>";

// 6. Verificar se o Cursor AI está configurado
echo "<br><h2>6. Instruções para Configurar no Cursor AI</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
echo "<h4>📋 Passos para Ativar a Automação:</h4>";
echo "<ol>";
echo "<li><strong>Abra o Command Palette:</strong> Ctrl + Shift + P</li>";
echo "<li><strong>Digite:</strong> <code>Cursor: Create Automation</code></li>";
echo "<li><strong>Configure:</strong>";
echo "<ul>";
echo "<li>Trigger: <code>On Git Commit</code></li>";
echo "<li>Command: <code>powershell -ExecutionPolicy Bypass -File \".\\.cursor\\deploy.ps1\"</code></li>";
echo "<li>Branch: <code>main</code></li>";
echo "</ul>";
echo "</li>";
echo "<li><strong>Salve a configuração</strong></li>";
echo "</ol>";
echo "</div>";

// 7. Status atual do sistema
echo "<br><h2>7. Status do Sistema</h2>";
$currentTime = date('Y-m-d H:i:s');
$lastCommit = shell_exec("git log -1 --format=%cd 2>&1");
$branch = shell_exec("git branch --show-current 2>&1");

echo "⏰ Hora atual: <strong>$currentTime</strong><br>";
echo "📝 Último commit: <strong>" . trim($lastCommit) . "</strong><br>";
echo "🌿 Branch atual: <strong>" . trim($branch) . "</strong><br>";

echo "<br><h3>🎯 Conclusão:</h3>";
echo "Se todos os arquivos estiverem ✅ e as permissões estiverem corretas, a automação deve funcionar automaticamente quando você fizer commits no Git.<br>";
echo "A automação irá:";
echo "<ul>";
echo "<li>Detectar mudanças automaticamente</li>";
echo "<li>Fazer commit das mudanças</li>";
echo "<li>Enviar para o GitHub</li>";
echo "<li>Aguardar o deploy na Hostinger</li>";
echo "<li>Notificar sobre o sucesso/erro</li>";
echo "</ul>";
?>
