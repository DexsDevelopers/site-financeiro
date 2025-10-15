<?php
/**
 * TESTE DE DEPLOY COMPLETO
 * Verificação sistemática do sistema após deploy
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Teste de Deploy - Sistema Financeiro</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }";
echo ".success { background: #d4edda; border-color: #c3e6cb; color: #155724; }";
echo ".error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }";
echo ".warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }";
echo ".info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }";
echo "h1, h2 { color: #333; }";
echo ".status { font-weight: bold; }";
echo ".details { margin-top: 10px; font-size: 0.9em; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>🚀 TESTE DE DEPLOY COMPLETO</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";

// 1. TESTE DE CONECTIVIDADE COM BANCO
echo "<div class='test-section'>";
echo "<h2>1. 🔗 Teste de Conectividade com Banco de Dados</h2>";

try {
    require_once 'includes/db_connect.php';
    echo "<div class='success'>";
    echo "<span class='status'>✅ SUCESSO:</span> Conexão com banco de dados estabelecida<br>";
    echo "<span class='details'>Host: " . DB_HOST . " | Database: " . DB_NAME . "</span>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<span class='status'>❌ ERRO:</span> Falha na conexão com banco de dados<br>";
    echo "<span class='details'>Erro: " . $e->getMessage() . "</span>";
    echo "</div>";
}
echo "</div>";

// 2. TESTE DE ARQUIVOS CRÍTICOS
echo "<div class='test-section'>";
echo "<h2>2. 📁 Teste de Arquivos Críticos</h2>";

$arquivos_criticos = [
    'tarefas.php' => 'Página principal de tarefas',
    'includes/db_connect.php' => 'Conexão com banco de dados',
    'includes/remember_me_manager.php' => 'Gerenciador de "Lembre-se de mim"',
    'templates/header.php' => 'Cabeçalho do sistema',
    'templates/footer.php' => 'Rodapé do sistema',
    'processar_rotina_fixa.php' => 'Processador de rotinas fixas',
    'atualizar_ordem_habitos.php' => 'Atualizador de ordem de hábitos'
];

foreach ($arquivos_criticos as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "<div class='success'>";
        echo "<span class='status'>✅</span> $descricao: <strong>$arquivo</strong><br>";
        echo "<span class='details'>Tamanho: " . number_format(filesize($arquivo)) . " bytes | Modificado: " . date('d/m/Y H:i:s', filemtime($arquivo)) . "</span>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<span class='status'>❌</span> $descricao: <strong>$arquivo</strong> - ARQUIVO NÃO ENCONTRADO<br>";
        echo "</div>";
    }
}
echo "</div>";

// 3. TESTE DE SINTAXE PHP
echo "<div class='test-section'>";
echo "<h2>3. 🔍 Teste de Sintaxe PHP</h2>";

$arquivos_php = ['tarefas.php', 'index.php', 'login.php', 'dashboard.php'];
foreach ($arquivos_php as $arquivo) {
    if (file_exists($arquivo)) {
        $output = shell_exec("php -l $arquivo 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "<div class='success'>";
            echo "<span class='status'>✅</span> $arquivo: Sintaxe OK<br>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<span class='status'>❌</span> $arquivo: Erro de sintaxe<br>";
            echo "<span class='details'>$output</span>";
            echo "</div>";
        }
    }
}
echo "</div>";

// 4. TESTE DE TABELAS DO BANCO
echo "<div class='test-section'>";
echo "<h2>4. 🗄️ Teste de Estrutura do Banco de Dados</h2>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tabelas_criticas = [
        'usuarios' => 'Tabela de usuários',
        'tarefas' => 'Tabela de tarefas',
        'rotinas_fixas' => 'Tabela de rotinas fixas',
        'rotina_controle_diario' => 'Tabela de controle diário',
        'transacoes' => 'Tabela de transações',
        'remember_tokens' => 'Tabela de tokens de "Lembre-se de mim"'
    ];
    
    foreach ($tabelas_criticas as $tabela => $descricao) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tabela");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "<div class='success'>";
            echo "<span class='status'>✅</span> $descricao: <strong>$tabela</strong> ($count registros)<br>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<span class='status'>❌</span> $descricao: <strong>$tabela</strong> - TABELA NÃO ENCONTRADA<br>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<span class='status'>❌</span> Erro ao verificar banco de dados: " . $e->getMessage() . "<br>";
    echo "</div>";
}
echo "</div>";

// 5. TESTE DE PERMISSÕES
echo "<div class='test-section'>";
echo "<h2>5. 🔐 Teste de Permissões de Arquivos</h2>";

$diretorios = ['uploads/', 'logs/', 'cache/'];
foreach ($diretorios as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<div class='success'>";
            echo "<span class='status'>✅</span> $dir: Gravável<br>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<span class='status'>❌</span> $dir: Não gravável<br>";
            echo "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<span class='status'>⚠️</span> $dir: Diretório não existe<br>";
        echo "</div>";
    }
}
echo "</div>";

// 6. TESTE DE CONFIGURAÇÕES
echo "<div class='test-section'>";
echo "<h2>6. ⚙️ Teste de Configurações do Sistema</h2>";

echo "<div class='info'>";
echo "<span class='status'>ℹ️</span> <strong>Informações do Servidor:</strong><br>";
echo "<span class='details'>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Request Time: " . date('d/m/Y H:i:s', $_SERVER['REQUEST_TIME']) . "<br>";
echo "</span>";
echo "</div>";
echo "</div>";

// 7. RESUMO DO TESTE
echo "<div class='test-section'>";
echo "<h2>7. 📊 Resumo do Teste de Deploy</h2>";

$timestamp = date('d/m/Y H:i:s');
echo "<div class='info'>";
echo "<span class='status'>📋</span> <strong>Teste Concluído em:</strong> $timestamp<br>";
echo "<span class='details'>";
echo "✅ Arquivos críticos verificados<br>";
echo "✅ Sintaxe PHP validada<br>";
echo "✅ Estrutura do banco verificada<br>";
echo "✅ Permissões de arquivos testadas<br>";
echo "✅ Configurações do sistema analisadas<br>";
echo "</span>";
echo "</div>";
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>8. 🎯 Próximos Passos</h2>";
echo "<div class='info'>";
echo "<span class='status'>📝</span> <strong>Recomendações:</strong><br>";
echo "<span class='details'>";
echo "1. Acesse a página de tarefas para testar funcionalidades<br>";
echo "2. Verifique se os botões de concluir tarefas funcionam<br>";
echo "3. Teste a funcionalidade de 'Lembre-se de mim'<br>";
echo "4. Verifique se as rotinas fixas estão funcionando<br>";
echo "5. Monitore o console do navegador para erros JavaScript<br>";
echo "</span>";
echo "</div>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
