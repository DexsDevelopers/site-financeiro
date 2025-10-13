<?php
// teste_organizacao_horario.php - Teste da página de Organização por Horário

echo "<h1>🧪 TESTE DA PÁGINA ORGANIZAÇÃO POR HORÁRIO</h1>";
echo "<hr>";

// 1. Verificar se o arquivo existe
echo "<h2>1. Verificação do Arquivo</h2>";
if (file_exists('automatizacao_horario.php')) {
    echo "✅ <strong>automatizacao_horario.php</strong> - Arquivo existe<br>";
    
    // Verificar tamanho do arquivo
    $size = filesize('automatizacao_horario.php');
    echo "📊 <strong>Tamanho:</strong> " . number_format($size) . " bytes<br>";
    
    // Verificar se tem conteúdo PHP válido
    $content = file_get_contents('automatizacao_horario.php');
    if (strpos($content, '<?php') !== false) {
        echo "✅ <strong>Estrutura PHP:</strong> Válida<br>";
    } else {
        echo "❌ <strong>Estrutura PHP:</strong> Inválida<br>";
    }
    
    // Verificar se tem require_once templates/header.php
    if (strpos($content, "require_once 'templates/header.php'") !== false) {
        echo "✅ <strong>Header:</strong> Incluído corretamente<br>";
    } else {
        echo "❌ <strong>Header:</strong> NÃO incluído<br>";
    }
    
    // Verificar se tem footer
    if (strpos($content, "require_once 'templates/footer.php'") !== false) {
        echo "✅ <strong>Footer:</strong> Incluído corretamente<br>";
    } else {
        echo "❌ <strong>Footer:</strong> NÃO incluído<br>";
    }
    
} else {
    echo "❌ <strong>automatizacao_horario.php</strong> - Arquivo NÃO existe<br>";
}

echo "<br>";

// 2. Verificar dependências
echo "<h2>2. Verificação de Dependências</h2>";

$dependencias = [
    'templates/header.php' => 'Header template',
    'templates/footer.php' => 'Footer template',
    'includes/db_connect.php' => 'Conexão com banco',
    'concluir_tarefa.php' => 'Script de conclusão de tarefas'
];

foreach ($dependencias as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "✅ <strong>$descricao</strong> - Existe<br>";
    } else {
        echo "❌ <strong>$descricao</strong> - NÃO existe<br>";
    }
}

echo "<br>";

// 3. Verificar configuração do menu
echo "<h2>3. Verificação do Menu</h2>";

if (file_exists('includes/load_menu_config.php')) {
    $menu_content = file_get_contents('includes/load_menu_config.php');
    
    if (strpos($menu_content, 'automatizacao_horario.php') !== false) {
        echo "✅ <strong>Menu configurado:</strong> automatizacao_horario.php está no menu<br>";
    } else {
        echo "❌ <strong>Menu configurado:</strong> automatizacao_horario.php NÃO está no menu<br>";
    }
    
    if (strpos($menu_content, 'Organização por Horário') !== false) {
        echo "✅ <strong>Nome da página:</strong> 'Organização por Horário' configurado<br>";
    } else {
        echo "❌ <strong>Nome da página:</strong> 'Organização por Horário' NÃO configurado<br>";
    }
    
} else {
    echo "❌ <strong>includes/load_menu_config.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 4. Teste de acesso direto
echo "<h2>4. Teste de Acesso Direto</h2>";

$url_teste = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/automatizacao_horario.php";
echo "🔗 <strong>URL de teste:</strong> <a href='$url_teste' target='_blank'>$url_teste</a><br>";

// Verificar se a URL responde
$headers = @get_headers($url_teste);
if ($headers && strpos($headers[0], '200') !== false) {
    echo "✅ <strong>Resposta HTTP:</strong> 200 OK<br>";
} else {
    echo "❌ <strong>Resposta HTTP:</strong> Erro ou não acessível<br>";
    if ($headers) {
        echo "📋 <strong>Headers:</strong> " . $headers[0] . "<br>";
    }
}

echo "<br>";

// 5. Verificar estrutura da tabela tarefas
echo "<h2>5. Verificação da Estrutura do Banco</h2>";

try {
    require_once 'includes/db_connect.php';
    
    // Verificar se a tabela tarefas existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ <strong>Tabela tarefas:</strong> Existe<br>";
        
        // Verificar colunas necessárias
        $stmt = $pdo->query("DESCRIBE tarefas");
        $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $colunas_necessarias = ['id', 'descricao', 'prioridade', 'status', 'hora_inicio', 'data_criacao', 'id_usuario'];
        foreach ($colunas_necessarias as $coluna) {
            if (in_array($coluna, $colunas)) {
                echo "✅ <strong>Coluna $coluna:</strong> Existe<br>";
            } else {
                echo "❌ <strong>Coluna $coluna:</strong> NÃO existe<br>";
            }
        }
        
    } else {
        echo "❌ <strong>Tabela tarefas:</strong> NÃO existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro de conexão:</strong> " . $e->getMessage() . "<br>";
}

echo "<br>";

// 6. Instruções para correção
echo "<h2>6. Instruções para Correção</h2>";
echo "<p>Se a página não estiver funcionando, siga estes passos:</p>";
echo "<ol>";
echo "<li><strong>Verifique se está logado:</strong> A página requer autenticação</li>";
echo "<li><strong>Teste o acesso direto:</strong> <a href='$url_teste' target='_blank'>Clique aqui</a></li>";
echo "<li><strong>Verifique o console do navegador:</strong> Pressione F12 e veja se há erros JavaScript</li>";
echo "<li><strong>Verifique os logs do servidor:</strong> Procure por erros PHP</li>";
echo "<li><strong>Teste em modo incógnito:</strong> Para descartar problemas de cache</li>";
echo "</ol>";

echo "<br>";
echo "<h3>🎯 Próximos Passos</h3>";
echo "<p>Se tudo estiver correto acima, o problema pode ser:</p>";
echo "<ul>";
echo "<li><strong>Cache do navegador:</strong> Limpe o cache e tente novamente</li>";
echo "<li><strong>Permissões de arquivo:</strong> Verifique se o arquivo tem permissão de leitura</li>";
echo "<li><strong>Configuração do servidor:</strong> Verifique se o PHP está funcionando</li>";
echo "<li><strong>Erro de sintaxe:</strong> Verifique se há erros PHP no arquivo</li>";
echo "</ul>";

?>
