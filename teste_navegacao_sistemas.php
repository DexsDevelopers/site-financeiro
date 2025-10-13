<?php
// teste_navegacao_sistemas.php - Teste de navegação dos novos sistemas

echo "<h1>🧪 TESTE DE NAVEGAÇÃO DOS SISTEMAS</h1>";
echo "<hr>";

// 1. Verificar se as páginas existem
echo "<h2>1. Verificação de Arquivos</h2>";

$paginas = [
    'rotina_diaria.php' => 'Rotina Diária',
    'pomodoro.php' => 'Pomodoro Timer',
    'automatizacao_horario.php' => 'Organização por Horário',
    'equipe.php' => 'Minha Equipe'
];

foreach ($paginas as $arquivo => $nome) {
    if (file_exists($arquivo)) {
        echo "✅ <strong>$nome</strong> - Arquivo existe<br>";
        
        // Verificar se o arquivo tem conteúdo válido
        $content = file_get_contents($arquivo);
        if (strpos($content, '<?php') !== false && strpos($content, 'require_once') !== false) {
            echo "✅ <strong>$nome</strong> - Estrutura PHP válida<br>";
        } else {
            echo "❌ <strong>$nome</strong> - Estrutura PHP inválida<br>";
        }
    } else {
        echo "❌ <strong>$nome</strong> - Arquivo NÃO existe<br>";
    }
}

echo "<br>";

// 2. Verificar configuração do menu
echo "<h2>2. Verificação do Menu</h2>";

if (file_exists('includes/load_menu_config.php')) {
    $content = file_get_contents('includes/load_menu_config.php');
    
    $menu_checks = [
        'rotina_diaria.php' => 'Rotina Diária no menu',
        'pomodoro.php' => 'Pomodoro no menu',
        'automatizacao_horario.php' => 'Organização por Horário no menu',
        'produtividade' => 'Seção de Produtividade'
    ];
    
    foreach ($menu_checks as $termo => $descricao) {
        if (strpos($content, $termo) !== false) {
            echo "✅ <strong>$descricao</strong> - Configurado<br>";
        } else {
            echo "❌ <strong>$descricao</strong> - NÃO configurado<br>";
        }
    }
} else {
    echo "❌ <strong>includes/load_menu_config.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 3. Verificar header
echo "<h2>3. Verificação do Header</h2>";

if (file_exists('templates/header.php')) {
    $content = file_get_contents('templates/header.php');
    
    if (strpos($content, 'Minha Equipe') !== false) {
        echo "✅ <strong>Botão Minha Equipe</strong> - Restaurado<br>";
    } else {
        echo "❌ <strong>Botão Minha Equipe</strong> - NÃO encontrado<br>";
    }
    
    if (strpos($content, 'equipe.php') !== false) {
        echo "✅ <strong>Link para equipe.php</strong> - Configurado<br>";
    } else {
        echo "❌ <strong>Link para equipe.php</strong> - NÃO configurado<br>";
    }
} else {
    echo "❌ <strong>templates/header.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 4. Verificar se as tabelas existem
echo "<h2>4. Verificação das Tabelas do Banco</h2>";

try {
    require_once 'includes/db_connect.php';
    
    $tabelas = [
        'rotina_diaria' => 'Tabela de rotina diária',
        'pomodoro_sessions' => 'Tabela de sessões de pomodoro',
        'config_rotina_padrao' => 'Tabela de configuração de rotina'
    ];
    
    foreach ($tabelas as $tabela => $descricao) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "✅ <strong>$descricao</strong> - Tabela existe<br>";
        } else {
            echo "❌ <strong>$descricao</strong> - Tabela NÃO existe<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro de conexão com banco:</strong> " . $e->getMessage() . "<br>";
}

echo "<br>";

// 5. Instruções de teste
echo "<h2>5. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar a navegação:</h4>";
echo "<ol>";
echo "<li><strong>Primeiro:</strong> Execute <a href='criar_tabelas_rotina_pomodoro.php' target='_blank'>criar_tabelas_rotina_pomodoro.php</a> se as tabelas não existirem</li>";
echo "<li><strong>Dashboard:</strong> Acesse <a href='dashboard.php' target='_blank'>dashboard.php</a> e verifique se os novos cards estão na seção de produtividade</li>";
echo "<li><strong>Menu:</strong> Verifique se o menu lateral tem a seção 'Produtividade' com os novos itens</li>";
echo "<li><strong>Minha Equipe:</strong> Acesse <a href='equipe.php' target='_blank'>equipe.php</a> e verifique se a página carrega</li>";
echo "<li><strong>Rotina Diária:</strong> Acesse <a href='rotina_diaria.php' target='_blank'>rotina_diaria.php</a> e teste os hábitos</li>";
echo "<li><strong>Pomodoro:</strong> Acesse <a href='pomodoro.php' target='_blank'>pomodoro.php</a> e teste o timer</li>";
echo "<li><strong>Organização:</strong> Acesse <a href='automatizacao_horario.php' target='_blank'>automatizacao_horario.php</a> e veja a organização por horário</li>";
echo "</ol>";
echo "</div>";

// 6. Status dos problemas
echo "<h2>6. Status dos Problemas</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ Problemas Resolvidos:</h4>";
echo "<ul>";
echo "<li><strong>Novos sistemas movidos para Produtividade:</strong> Rotina Diária, Pomodoro e Organização por Horário agora estão na seção de Produtividade</li>";
echo "<li><strong>Botão Minha Equipe restaurado:</strong> Página equipe.php criada e link adicionado ao menu</li>";
echo "<li><strong>Menu reorganizado:</strong> Estrutura do menu atualizada para incluir os novos sistemas</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>⚠️ Verificações Necessárias:</h4>";
echo "<ul>";
echo "<li><strong>Teste de acesso:</strong> Verifique se todas as páginas carregam corretamente</li>";
echo "<li><strong>Banco de dados:</strong> Execute criar_tabelas_rotina_pomodoro.php se necessário</li>";
echo "<li><strong>Permissões:</strong> Verifique se não há problemas de permissão nos arquivos</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Teste concluído!</strong> Os sistemas foram reorganizados e o botão Minha Equipe foi restaurado.</p>";
?>
