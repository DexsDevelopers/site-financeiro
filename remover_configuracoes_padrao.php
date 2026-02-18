<?php
// remover_configuracoes_padrao.php - Remover configurações padrão de rotinas

require_once 'includes/db_connect.php';

echo "<h1>🗑️ REMOVENDO CONFIGURAÇÕES PADRÃO DE ROTINAS</h1>";
echo "<hr>";

try {
    // 1. Verificar configurações existentes
    echo "<h3>📊 Verificando configurações padrão</h3>";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_configs,
            COUNT(DISTINCT id_usuario) as usuarios_com_configs
        FROM config_rotina_padrao
    ");
    $stats_configs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📋 <strong>Configurações encontradas:</strong><br>";
    echo "• Total de configurações: {$stats_configs['total_configs']}<br>";
    echo "• Usuários com configurações: {$stats_configs['usuarios_com_configs']}<br><br>";
    
    // 2. Listar configurações por usuário
    echo "<h3>👥 Configurações por usuário</h3>";
    $stmt = $pdo->query("
        SELECT u.id, u.nome_completo, crp.nome, crp.horario_sugerido, crp.ordem
        FROM usuarios u
        JOIN config_rotina_padrao crp ON u.id = crp.id_usuario
        ORDER BY u.id, crp.ordem
    ");
    $configuracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($configuracoes)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Usuário</th><th>Rotina</th><th>Horário</th><th>Ordem</th></tr>";
        foreach ($configuracoes as $config) {
            echo "<tr>";
            echo "<td>{$config['nome_completo']} (ID: {$config['id']})</td>";
            echo "<td>{$config['nome']}</td>";
            echo "<td>{$config['horario_sugerido']}</td>";
            echo "<td>{$config['ordem']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "✅ <strong>Nenhuma configuração padrão encontrada</strong><br><br>";
    }
    
    // 3. Confirmar remoção
    echo "<h3>⚠️ Confirmação de Remoção</h3>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #dc3545;'>";
    echo "<h4>⚠️ ATENÇÃO!</h4>";
    echo "<p><strong>Esta ação irá:</strong></p>";
    echo "<ul>";
    echo "<li>🗑️ <strong>Remover TODAS as configurações padrão</strong> de todos os usuários</li>";
    echo "<li>🔄 <strong>Usuários precisarão criar suas rotinas manualmente</strong></li>";
    echo "<li>📅 <strong>Não haverá rotinas automáticas</strong> para novos dias</li>";
    echo "<li>✅ <strong>Não afetar rotinas já criadas</strong> (apenas as configurações)</li>";
    echo "</ul>";
    echo "<p><strong>Esta ação é IRREVERSÍVEL!</strong></p>";
    echo "</div>";
    
    // 4. Executar remoção
    echo "<h3>🗑️ Executando remoção...</h3>";
    
    $stmt = $pdo->prepare("DELETE FROM config_rotina_padrao");
    $stmt->execute();
    $configs_removidas = $stmt->rowCount();
    
    echo "🗑️ <strong>$configs_removidas configurações removidas</strong><br>";
    
    // 5. Verificar resultado
    echo "<h3>✅ Verificando resultado</h3>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM config_rotina_padrao");
    $total_apos = $stmt->fetchColumn();
    
    if ($total_apos == 0) {
        echo "✅ <strong>Remoção concluída com sucesso!</strong><br>";
        echo "📊 <strong>Total de configurações após remoção:</strong> $total_apos<br>";
    } else {
        echo "⚠️ <strong>Ainda restam $total_apos configurações</strong><br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 REMOÇÃO CONCLUÍDA!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Resumo da operação:</h4>";
    echo "<ul>";
    echo "<li><strong>Configurações removidas:</strong> $configs_removidas</li>";
    echo "<li><strong>Rotinas existentes:</strong> Mantidas intactas</li>";
    echo "<li><strong>Sistema:</strong> Funcionará normalmente</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔄 Como funciona agora:</h4>";
    echo "<ol>";
    echo "<li><strong>Usuários acessam tarefas.php</strong></li>";
    echo "<li><strong>Não haverá rotinas automáticas</strong></li>";
    echo "<li><strong>Usuários podem adicionar rotinas manualmente</strong></li>";
    echo "<li><strong>Cada rotina será salva individualmente</strong></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>💡 Para restaurar:</h4>";
    echo "<p>Se quiser <strong>restaurar as configurações padrão</strong>, execute: <a href='adicionar_rotinas_usuarios.php'>adicionar_rotinas_usuarios.php</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se o banco de dados está funcionando e se o usuário tem permissões para deletar registros.</p>";
    echo "</div>";
}
?>
