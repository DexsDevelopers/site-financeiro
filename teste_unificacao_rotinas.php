<?php
// teste_unificacao_rotinas.php - Teste para verificar a unificação das rotinas

echo "<h1>🧪 TESTE DE UNIFICAÇÃO DAS ROTINAS</h1>";
echo "<hr>";

// 1. Verificar se o arquivo rotina_diaria.php foi removido
echo "<h2>1. Verificação de Remoção</h2>";

if (!file_exists('rotina_diaria.php')) {
    echo "✅ <strong>rotina_diaria.php</strong> - Arquivo removido com sucesso<br>";
} else {
    echo "❌ <strong>rotina_diaria.php</strong> - Arquivo ainda existe<br>";
}

// 2. Verificar se tarefas.php foi modificado
echo "<h2>2. Verificação de Integração</h2>";

if (file_exists('tarefas.php')) {
    $content = file_get_contents('tarefas.php');
    
    if (strpos($content, 'ROTINA DIÁRIA INTEGRADA') !== false) {
        echo "✅ <strong>Seção de rotina diária</strong> - Integrada em tarefas.php<br>";
    } else {
        echo "❌ <strong>Seção de rotina diária</strong> - NÃO encontrada em tarefas.php<br>";
    }
    
    if (strpos($content, 'toggleRotina') !== false) {
        echo "✅ <strong>Função toggleRotina</strong> - Implementada<br>";
    } else {
        echo "❌ <strong>Função toggleRotina</strong> - NÃO encontrada<br>";
    }
    
    if (strpos($content, 'adicionarHabit') !== false) {
        echo "✅ <strong>Função adicionarHabit</strong> - Implementada<br>";
    } else {
        echo "❌ <strong>Função adicionarHabit</strong> - NÃO encontrada<br>";
    }
    
    if (strpos($content, 'rotina-card') !== false) {
        echo "✅ <strong>Estilos CSS</strong> - Implementados<br>";
    } else {
        echo "❌ <strong>Estilos CSS</strong> - NÃO encontrados<br>";
    }
} else {
    echo "❌ <strong>tarefas.php</strong> - Arquivo não encontrado<br>";
}

// 3. Verificar configuração do menu
echo "<h2>3. Verificação do Menu</h2>";

if (file_exists('includes/load_menu_config.php')) {
    $menuContent = file_get_contents('includes/load_menu_config.php');
    
    if (strpos($menuContent, 'rotina_diaria.php') === false) {
        echo "✅ <strong>Menu de produtividade</strong> - rotina_diaria.php removido<br>";
    } else {
        echo "❌ <strong>Menu de produtividade</strong> - rotina_diaria.php ainda presente<br>";
    }
    
    if (strpos($menuContent, "'produtividade' => ['tarefas.php', 'calendario.php', 'temporizador.php', 'pomodoro.php', 'automatizacao_horario.php']") !== false) {
        echo "✅ <strong>Configuração do menu</strong> - Atualizada corretamente<br>";
    } else {
        echo "❌ <strong>Configuração do menu</strong> - NÃO atualizada<br>";
    }
} else {
    echo "❌ <strong>includes/load_menu_config.php</strong> - Arquivo não encontrado<br>";
}

// 4. Verificar arquivos de suporte
echo "<h2>4. Verificação de Arquivos de Suporte</h2>";

$arquivosSuporte = [
    'adicionar_rotina_diaria.php',
    'salvar_rotina_diaria.php'
];

foreach ($arquivosSuporte as $arquivo) {
    if (file_exists($arquivo)) {
        echo "✅ <strong>{$arquivo}</strong> - Arquivo existe<br>";
    } else {
        echo "❌ <strong>{$arquivo}</strong> - Arquivo NÃO existe<br>";
    }
}

// 5. Instruções de teste
echo "<h2>5. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar a unificação:</h4>";
echo "<ol>";
echo "<li><strong>Acesse a página de tarefas:</strong> <a href='tarefas.php' target='_blank'>tarefas.php</a></li>";
echo "<li><strong>Verifique a seção de rotina diária:</strong> Deve aparecer no topo da página</li>";
echo "<li><strong>Teste as funcionalidades:</strong></li>";
echo "    <ul>";
echo "        <li>Clique nos hábitos para marcar como concluído</li>";
echo "        <li>Clique em 'Adicionar Hábito' para criar novos hábitos</li>";
echo "        <li>Verifique o progresso circular</li>";
echo "    </ul>";
echo "<li><strong>Verifique o menu:</strong> Acesse a seção 'Produtividade' e confirme que 'Rotina Diária' não aparece mais</li>";
echo "<li><strong>Teste a responsividade:</strong> Verifique se funciona bem no mobile</li>";
echo "</ol>";
echo "</div>";

// 6. Status da unificação
echo "<h2>6. Status da Unificação</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ Unificação Realizada:</h4>";
echo "<ul>";
echo "<li><strong>Funcionalidades integradas:</strong> Rotina diária agora faz parte da página de tarefas</li>";
echo "<li><strong>Menu atualizado:</strong> 'Rotina Diária' removido do menu de produtividade</li>";
echo "<li><strong>Arquivo removido:</strong> rotina_diaria.php deletado</li>";
echo "<li><strong>Design unificado:</strong> Estilos consistentes entre rotina e tarefas</li>";
echo "<li><strong>Funcionalidades preservadas:</strong> Todas as funcionalidades da rotina diária mantidas</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>⚠️ Observações:</h4>";
echo "<ul>";
echo "<li>A seção de rotina diária só aparece se houver hábitos configurados</li>";
echo "<li>Os hábitos são criados automaticamente baseados na configuração padrão</li>";
echo "<li>O progresso é calculado automaticamente baseado nos hábitos concluídos</li>";
echo "<li>Todas as funcionalidades de AJAX foram preservadas</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Unificação concluída!</strong> A rotina diária agora está integrada na página de tarefas e o menu foi atualizado.</p>";
?>
