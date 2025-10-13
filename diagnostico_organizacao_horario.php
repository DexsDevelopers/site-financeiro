<?php
// diagnostico_organizacao_horario.php - Diagnóstico completo da página Organização por Horário

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Diagnóstico - Organização por Horário</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: #28a745; }";
echo ".error { color: #dc3545; }";
echo ".warning { color: #ffc107; }";
echo ".info { color: #17a2b8; }";
echo "h1, h2 { color: #333; }";
echo ".test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; background: #f9f9f9; }";
echo ".test-result.success { border-left-color: #28a745; background: #d4edda; }";
echo ".test-result.error { border-left-color: #dc3545; background: #f8d7da; }";
echo ".test-result.warning { border-left-color: #ffc107; background: #fff3cd; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>🔍 DIAGNÓSTICO - ORGANIZAÇÃO POR HORÁRIO</h1>";
echo "<hr>";

// 1. Verificar arquivo principal
echo "<h2>1. Verificação do Arquivo Principal</h2>";

if (file_exists('automatizacao_horario.php')) {
    echo "<div class='test-result success'>";
    echo "✅ <strong>automatizacao_horario.php</strong> - Arquivo existe<br>";
    echo "📊 <strong>Tamanho:</strong> " . number_format(filesize('automatizacao_horario.php')) . " bytes<br>";
    echo "</div>";
    
    // Verificar conteúdo
    $content = file_get_contents('automatizacao_horario.php');
    $checks = [
        '<?php' => 'Código PHP',
        'require_once \'templates/header.php\'' => 'Header template',
        'require_once \'templates/footer.php\'' => 'Footer template',
        'session_start()' => 'Sessão iniciada',
        '$_SESSION[\'user_id\']' => 'Verificação de usuário',
        'pdo->prepare' => 'Conexão com banco',
        'function concluirTarefa' => 'Função JavaScript'
    ];
    
    foreach ($checks as $pattern => $description) {
        if (strpos($content, $pattern) !== false) {
            echo "<div class='test-result success'>✅ <strong>$description:</strong> Encontrado</div>";
        } else {
            echo "<div class='test-result error'>❌ <strong>$description:</strong> NÃO encontrado</div>";
        }
    }
    
} else {
    echo "<div class='test-result error'>❌ <strong>automatizacao_horario.php</strong> - Arquivo NÃO existe</div>";
}

// 2. Verificar dependências
echo "<h2>2. Verificação de Dependências</h2>";

$dependencias = [
    'templates/header.php' => 'Header template',
    'templates/footer.php' => 'Footer template', 
    'includes/db_connect.php' => 'Conexão com banco',
    'concluir_tarefa_ajax.php' => 'Script AJAX de conclusão'
];

foreach ($dependencias as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "<div class='test-result success'>✅ <strong>$descricao</strong> - Existe</div>";
    } else {
        echo "<div class='test-result error'>❌ <strong>$descricao</strong> - NÃO existe</div>";
    }
}

// 3. Verificar configuração do menu
echo "<h2>3. Verificação da Configuração do Menu</h2>";

if (file_exists('includes/load_menu_config.php')) {
    $menu_content = file_get_contents('includes/load_menu_config.php');
    
    $menu_checks = [
        'automatizacao_horario.php' => 'Arquivo no menu',
        'Organização por Horário' => 'Nome da página',
        'bi-clock-history' => 'Ícone da página',
        'produtividade' => 'Seção de produtividade'
    ];
    
    foreach ($menu_checks as $pattern => $description) {
        if (strpos($menu_content, $pattern) !== false) {
            echo "<div class='test-result success'>✅ <strong>$description:</strong> Configurado</div>";
        } else {
            echo "<div class='test-result error'>❌ <strong>$description:</strong> NÃO configurado</div>";
        }
    }
    
} else {
    echo "<div class='test-result error'>❌ <strong>includes/load_menu_config.php</strong> - Arquivo não encontrado</div>";
}

// 4. Teste de acesso
echo "<h2>4. Teste de Acesso</h2>";

$base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$url_teste = $base_url . "/automatizacao_horario.php";

echo "<div class='test-result info'>";
echo "🔗 <strong>URL de teste:</strong> <a href='$url_teste' target='_blank'>$url_teste</a><br>";
echo "</div>";

// Verificar resposta HTTP
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($url_teste, false, $context);
if ($response !== false) {
    echo "<div class='test-result success'>✅ <strong>Resposta HTTP:</strong> Página acessível</div>";
    
    // Verificar se retorna HTML válido
    if (strpos($response, '<html') !== false || strpos($response, '<!DOCTYPE') !== false) {
        echo "<div class='test-result success'>✅ <strong>HTML:</strong> Página retorna HTML válido</div>";
    } else {
        echo "<div class='test-result warning'>⚠️ <strong>HTML:</strong> Página não retorna HTML válido</div>";
    }
    
} else {
    echo "<div class='test-result error'>❌ <strong>Resposta HTTP:</strong> Página não acessível</div>";
    
    // Verificar headers
    $headers = @get_headers($url_teste);
    if ($headers) {
        echo "<div class='test-result warning'>📋 <strong>Headers:</strong> " . $headers[0] . "</div>";
    }
}

// 5. Verificar banco de dados
echo "<h2>5. Verificação do Banco de Dados</h2>";

try {
    require_once 'includes/db_connect.php';
    
    // Verificar tabela tarefas
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "<div class='test-result success'>✅ <strong>Tabela tarefas:</strong> Existe</div>";
        
        // Verificar estrutura
        $stmt = $pdo->query("DESCRIBE tarefas");
        $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $colunas_necessarias = [
            'id' => 'ID da tarefa',
            'descricao' => 'Descrição',
            'prioridade' => 'Prioridade', 
            'status' => 'Status',
            'hora_inicio' => 'Hora de início',
            'data_criacao' => 'Data de criação',
            'id_usuario' => 'ID do usuário'
        ];
        
        foreach ($colunas_necessarias as $coluna => $descricao) {
            if (in_array($coluna, $colunas)) {
                echo "<div class='test-result success'>✅ <strong>$descricao ($coluna):</strong> Existe</div>";
            } else {
                echo "<div class='test-result error'>❌ <strong>$descricao ($coluna):</strong> NÃO existe</div>";
            }
        }
        
    } else {
        echo "<div class='test-result error'>❌ <strong>Tabela tarefas:</strong> NÃO existe</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='test-result error'>❌ <strong>Erro de conexão:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// 6. Instruções de correção
echo "<h2>6. Instruções para Correção</h2>";

echo "<div class='test-result info'>";
echo "<h3>🔧 Se a página não estiver funcionando:</h3>";
echo "<ol>";
echo "<li><strong>Verifique se está logado:</strong> A página requer autenticação</li>";
echo "<li><strong>Teste o acesso direto:</strong> <a href='$url_teste' target='_blank'>Clique aqui</a></li>";
echo "<li><strong>Verifique o console do navegador:</strong> Pressione F12 e veja se há erros JavaScript</li>";
echo "<li><strong>Verifique os logs do servidor:</strong> Procure por erros PHP nos logs</li>";
echo "<li><strong>Teste em modo incógnito:</strong> Para descartar problemas de cache</li>";
echo "<li><strong>Verifique permissões:</strong> Certifique-se de que o arquivo tem permissão de leitura</li>";
echo "</ol>";
echo "</div>";

echo "<div class='test-result warning'>";
echo "<h3>⚠️ Possíveis Causas do Problema:</h3>";
echo "<ul>";
echo "<li><strong>Cache do navegador:</strong> Limpe o cache e tente novamente</li>";
echo "<li><strong>Erro de sintaxe PHP:</strong> Verifique se há erros no arquivo</li>";
echo "<li><strong>Problema de sessão:</strong> Verifique se a sessão está funcionando</li>";
echo "<li><strong>Problema de banco:</strong> Verifique se a conexão está funcionando</li>";
echo "<li><strong>Problema de permissões:</strong> Verifique as permissões dos arquivos</li>";
echo "</ul>";
echo "</div>";

echo "<div class='test-result success'>";
echo "<h3>✅ Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Execute este diagnóstico: <a href='teste_organizacao_horario.php'>teste_organizacao_horario.php</a></li>";
echo "<li>Teste o acesso direto: <a href='$url_teste' target='_blank'>automatizacao_horario.php</a></li>";
echo "<li>Verifique o menu: <a href='dashboard.php'>Dashboard</a> → Produtividade → Organização por Horário</li>";
echo "<li>Se ainda não funcionar, verifique os logs do servidor</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
