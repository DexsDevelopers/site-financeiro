<?php
// teste_organizacao_horario_final.php - Teste final da página Organização por Horário
echo "<h1>🧪 Teste Final - Página Organização por Horário</h1>";
echo "<hr>";

// 1. Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ <strong>Usuário não logado!</strong><br>";
    echo "Para testar a página, você precisa estar logado. <a href='index.php'>Clique aqui para fazer login</a>";
    echo "</div>";
    exit();
}

echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "✅ <strong>Usuário logado:</strong> ID " . $_SESSION['user_id'];
echo "</div>";

// 2. Verificar dependências
echo "<h2>2. Verificação de Dependências</h2>";

$dependencies = [
    'templates/header.php' => 'Header template',
    'templates/footer.php' => 'Footer template',
    'includes/db_connect.php' => 'Conexão com banco',
    'concluir_tarefa_ajax.php' => 'Script de conclusão de tarefas'
];

foreach ($dependencies as $file => $description) {
    if (file_exists($file)) {
        echo "✅ <strong>$description</strong> ($file) - <span style='color: green;'>EXISTE</span><br>";
    } else {
        echo "❌ <strong>$description</strong> ($file) - <span style='color: red;'>NÃO EXISTE</span><br>";
    }
}

// 3. Testar conexão com banco
echo "<br><h2>3. Teste de Conexão com Banco de Dados</h2>";
try {
    require_once 'includes/db_connect.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✅ <span style='color: green;'>Conexão com banco estabelecida</span><br>";
        
        // Testar consulta simples
        $stmt = $pdo->query("SELECT 1 as test");
        if ($stmt) {
            echo "✅ <span style='color: green;'>Consulta de teste bem-sucedida</span><br>";
        }
    } else {
        echo "❌ <span style='color: red;'>Conexão com banco falhou</span><br>";
    }
} catch (Exception $e) {
    echo "❌ <span style='color: red;'>Erro de conexão: " . $e->getMessage() . "</span><br>";
}

// 4. Testar a página diretamente
echo "<br><h2>4. Teste de Acesso à Página</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>🔗 Links de Teste:</strong><br>";
echo "• <a href='automatizacao_horario.php' target='_blank'>Abrir página em nova aba</a><br>";
echo "• <a href='automatizacao_horario.php' target='_self'>Abrir página na mesma aba</a><br>";
echo "</div>";

// 5. Verificar estrutura da tabela tarefas
echo "<br><h2>5. Verificação da Estrutura da Tabela</h2>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("DESCRIBE tarefas");
        $columns = $stmt->fetchAll();
        
        $requiredColumns = ['id', 'descricao', 'prioridade', 'status', 'hora_inicio', 'data_criacao', 'id_usuario'];
        
        foreach ($requiredColumns as $col) {
            $found = false;
            foreach ($columns as $column) {
                if ($column['Field'] === $col) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                echo "✅ Coluna <strong>$col</strong> - <span style='color: green;'>EXISTE</span><br>";
            } else {
                echo "❌ Coluna <strong>$col</strong> - <span style='color: red;'>NÃO EXISTE</span><br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ <span style='color: red;'>Erro ao verificar estrutura: " . $e->getMessage() . "</span><br>";
}

// 6. Testar JavaScript
echo "<br><h2>6. Teste de JavaScript</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>📋 Para testar o JavaScript:</strong><br>";
echo "1. Abra a página <a href='automatizacao_horario.php' target='_blank'>automatizacao_horario.php</a><br>";
echo "2. Pressione F12 para abrir o console do navegador<br>";
echo "3. Verifique se há erros JavaScript<br>";
echo "4. Teste clicar em uma tarefa para concluí-la<br>";
echo "</div>";

// 7. Instruções de debug
echo "<br><h2>7. Instruções de Debug</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
echo "<h4>🔧 Se a página não estiver funcionando:</h4>";
echo "<ol>";
echo "<li><strong>Verifique o console do navegador (F12):</strong> Procure por erros JavaScript</li>";
echo "<li><strong>Verifique os logs do servidor:</strong> Procure por erros PHP</li>";
echo "<li><strong>Teste em modo incógnito:</strong> Para descartar problemas de cache</li>";
echo "<li><strong>Verifique as permissões:</strong> Certifique-se de que o arquivo tem permissão de leitura</li>";
echo "<li><strong>Verifique a configuração do servidor:</strong> Certifique-se de que o PHP está funcionando</li>";
echo "</ol>";
echo "</div>";

// 8. Status final
echo "<br><h2>8. Status Final</h2>";
$currentTime = date('Y-m-d H:i:s');
echo "⏰ <strong>Hora do teste:</strong> $currentTime<br>";

if (file_exists('automatizacao_horario.php')) {
    $fileSize = filesize('automatizacao_horario.php');
    echo "📄 <strong>Tamanho do arquivo:</strong> " . number_format($fileSize) . " bytes<br>";
}

echo "<br><div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>🎯 Próximo passo:</strong> Se todos os itens acima estiverem ✅, a página deve estar funcionando. ";
echo "Se ainda houver problemas, verifique os logs do servidor para mais detalhes.";
echo "</div>";
?>
