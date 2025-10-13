<?php
// teste_sistemas_completos.php - Teste completo dos novos sistemas

echo "<h1>🧪 TESTE COMPLETO DOS NOVOS SISTEMAS</h1>";
echo "<hr>";

// 1. Verificar se as tabelas foram criadas
echo "<h2>1. Verificação de Tabelas do Banco</h2>";

try {
    require_once 'includes/db_connect.php';
    
    $tabelas = ['rotina_diaria', 'pomodoro_sessions', 'config_rotina_padrao'];
    
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "✅ <strong>$tabela</strong> - Tabela existe<br>";
        } else {
            echo "❌ <strong>$tabela</strong> - Tabela NÃO existe<br>";
        }
    }
    
    // Verificar colunas adicionadas em tarefas
    $stmt = $pdo->query("SHOW COLUMNS FROM tarefas LIKE 'hora_inicio'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>tarefas.hora_inicio</strong> - Coluna existe<br>";
    } else {
        echo "❌ <strong>tarefas.hora_inicio</strong> - Coluna NÃO existe<br>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM tarefas LIKE 'tempo_gasto'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>tarefas.tempo_gasto</strong> - Coluna existe<br>";
    } else {
        echo "❌ <strong>tarefas.tempo_gasto</strong> - Coluna NÃO existe<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro de conexão:</strong> " . $e->getMessage() . "<br>";
}

echo "<br>";

// 2. Verificar arquivos criados
echo "<h2>2. Verificação de Arquivos</h2>";

$arquivos = [
    'rotina_diaria.php' => 'Página principal da rotina diária',
    'pomodoro.php' => 'Sistema de pomodoro',
    'automatizacao_horario.php' => 'Automatização por horário',
    'salvar_rotina_diaria.php' => 'Backend para salvar rotina',
    'adicionar_rotina_diaria.php' => 'Backend para adicionar rotina',
    'criar_sessao_pomodoro.php' => 'Backend para criar sessão pomodoro',
    'finalizar_sessao_pomodoro.php' => 'Backend para finalizar pomodoro',
    'buscar_historico_pomodoro.php' => 'Backend para buscar histórico',
    'cancelar_sessao_pomodoro.php' => 'Backend para cancelar pomodoro'
];

foreach ($arquivos as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "✅ <strong>$arquivo</strong> - $descricao<br>";
    } else {
        echo "❌ <strong>$arquivo</strong> - $descricao (NÃO encontrado)<br>";
    }
}

echo "<br>";

// 3. Verificar integração no dashboard
echo "<h2>3. Verificação de Integração</h2>";

if (file_exists('dashboard.php')) {
    $content = file_get_contents('dashboard.php');
    
    $checks = [
        'rotina_hoje' => 'Variável de rotina no dashboard',
        'stats_rotina' => 'Estatísticas de rotina',
        'stats_pomodoro' => 'Estatísticas de pomodoro',
        'toggleRotina' => 'Função JavaScript para rotina',
        'startPomodoroQuick' => 'Função JavaScript para pomodoro',
        'Rotina Diária' => 'Card de rotina no dashboard',
        'Pomodoro Timer' => 'Card de pomodoro no dashboard',
        'Organização por Horário' => 'Card de organização por horário'
    ];
    
    foreach ($checks as $termo => $descricao) {
        if (strpos($content, $termo) !== false) {
            echo "✅ <strong>$descricao</strong> - Integrado<br>";
        } else {
            echo "❌ <strong>$descricao</strong> - NÃO integrado<br>";
        }
    }
} else {
    echo "❌ <strong>dashboard.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 4. Verificar menu
echo "<h2>4. Verificação do Menu</h2>";

if (file_exists('templates/header.php')) {
    $content = file_get_contents('templates/header.php');
    
    $menu_checks = [
        'rotina_diaria.php' => 'Link para rotina diária',
        'pomodoro.php' => 'Link para pomodoro',
        'automatizacao_horario.php' => 'Link para organização por horário',
        'Rotina Diária' => 'Nome do menu rotina',
        'Pomodoro Timer' => 'Nome do menu pomodoro',
        'Organização por Horário' => 'Nome do menu organização'
    ];
    
    foreach ($menu_checks as $termo => $descricao) {
        if (strpos($content, $termo) !== false) {
            echo "✅ <strong>$descricao</strong> - No menu<br>";
        } else {
            echo "❌ <strong>$descricao</strong> - NÃO no menu<br>";
        }
    }
} else {
    echo "❌ <strong>templates/header.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 5. Instruções de teste
echo "<h2>5. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar os novos sistemas:</h4>";
echo "<ol>";
echo "<li><strong>Primeiro:</strong> Execute <a href='criar_tabelas_rotina_pomodoro.php' target='_blank'>criar_tabelas_rotina_pomodoro.php</a> para criar as tabelas</li>";
echo "<li><strong>Dashboard:</strong> Acesse <a href='dashboard.php' target='_blank'>dashboard.php</a> e verifique os novos cards</li>";
echo "<li><strong>Rotina Diária:</strong> Acesse <a href='rotina_diaria.php' target='_blank'>rotina_diaria.php</a> e teste os hábitos</li>";
echo "<li><strong>Pomodoro:</strong> Acesse <a href='pomodoro.php' target='_blank'>pomodoro.php</a> e teste o timer</li>";
echo "<li><strong>Organização:</strong> Acesse <a href='automatizacao_horario.php' target='_blank'>automatizacao_horario.php</a> e veja a organização por horário</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✨ Funcionalidades implementadas:</h4>";
echo "<ul>";
echo "<li><strong>1️⃣ Rotina Diária Inteligente:</strong> Hábitos diários com progresso visual</li>";
echo "<li><strong>2️⃣ Pomodoro Evoluído:</strong> Timer com integração às tarefas</li>";
echo "<li><strong>3️⃣ Automatização por Horário:</strong> Organização automática por período do dia</li>";
echo "<li><strong>🎨 Design Responsivo:</strong> Funciona perfeitamente no mobile e desktop</li>";
echo "<li><strong>🔗 Integração Completa:</strong> Tudo integrado no dashboard principal</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>⚠️ Próximos passos:</h4>";
echo "<ol>";
echo "<li>Teste todas as funcionalidades em diferentes dispositivos</li>";
echo "<li>Verifique se as notificações estão funcionando</li>";
echo "<li>Teste a integração com tarefas existentes</li>";
echo "<li>Configure as rotinas padrão para seus usuários</li>";
echo "</ol>";
echo "</div>";
?>
