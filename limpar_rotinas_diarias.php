<?php
// limpar_rotinas_diarias.php - Limpar todas as rotinas diárias de todos os usuários

require_once 'includes/db_connect.php';

echo "<h1>🧹 LIMPANDO ROTINAS DIÁRIAS DE TODOS OS USUÁRIOS</h1>";
echo "<hr>";

try {
    // 1. Verificar quantas rotinas existem antes da limpeza
    echo "<h3>📊 Verificando rotinas existentes</h3>";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_rotinas,
            COUNT(DISTINCT id_usuario) as usuarios_com_rotinas,
            MIN(data_execucao) as data_mais_antiga,
            MAX(data_execucao) as data_mais_recente
        FROM rotina_diaria
    ");
    $stats_antes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📋 <strong>Antes da limpeza:</strong><br>";
    echo "• Total de rotinas: {$stats_antes['total_rotinas']}<br>";
    echo "• Usuários com rotinas: {$stats_antes['usuarios_com_rotinas']}<br>";
    echo "• Data mais antiga: {$stats_antes['data_mais_antiga']}<br>";
    echo "• Data mais recente: {$stats_antes['data_mais_recente']}<br><br>";
    
    // 2. Listar usuários que têm rotinas
    echo "<h3>👥 Usuários com rotinas diárias</h3>";
    $stmt = $pdo->query("
        SELECT u.id, u.nome_completo, COUNT(rd.id) as total_rotinas
        FROM usuarios u
        LEFT JOIN rotina_diaria rd ON u.id = rd.id_usuario
        WHERE rd.id IS NOT NULL
        GROUP BY u.id, u.nome_completo
        ORDER BY total_rotinas DESC
    ");
    $usuarios_com_rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($usuarios_com_rotinas)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Total de Rotinas</th></tr>";
        foreach ($usuarios_com_rotinas as $usuario) {
            echo "<tr>";
            echo "<td>{$usuario['id']}</td>";
            echo "<td>{$usuario['nome_completo']}</td>";
            echo "<td>{$usuario['total_rotinas']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "✅ <strong>Nenhum usuário possui rotinas diárias</strong><br><br>";
    }
    
    // 3. Confirmar limpeza
    echo "<h3>⚠️ Confirmação de Limpeza</h3>";
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #ffc107;'>";
    echo "<h4>⚠️ ATENÇÃO!</h4>";
    echo "<p><strong>Esta ação irá:</strong></p>";
    echo "<ul>";
    echo "<li>🗑️ <strong>Remover TODAS as rotinas diárias</strong> de todos os usuários</li>";
    echo "<li>📅 <strong>Limpar rotinas de todas as datas</strong> (passado, presente e futuro)</li>";
    echo "<li>🔄 <strong>Manter apenas as configurações padrão</strong> (config_rotina_padrao)</li>";
    echo "<li>✅ <strong>Não afetar outras funcionalidades</strong> do sistema</li>";
    echo "</ul>";
    echo "<p><strong>As rotinas serão recriadas automaticamente</strong> quando os usuários acessarem a página de tarefas.</p>";
    echo "</div>";
    
    // 4. Executar limpeza
    echo "<h3>🧹 Executando limpeza...</h3>";
    
    // Limpar todas as rotinas diárias
    $stmt = $pdo->prepare("DELETE FROM rotina_diaria");
    $stmt->execute();
    $rotinas_removidas = $stmt->rowCount();
    
    echo "🗑️ <strong>$rotinas_removidas rotinas removidas</strong><br>";
    
    // 5. Verificar resultado
    echo "<h3>✅ Verificando resultado</h3>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rotina_diaria");
    $total_apos = $stmt->fetchColumn();
    
    if ($total_apos == 0) {
        echo "✅ <strong>Limpeza concluída com sucesso!</strong><br>";
        echo "📊 <strong>Total de rotinas após limpeza:</strong> $total_apos<br>";
    } else {
        echo "⚠️ <strong>Ainda restam $total_apos rotinas</strong><br>";
    }
    
    // 6. Verificar configurações padrão
    echo "<h3>📋 Verificando configurações padrão</h3>";
    
    $stmt = $pdo->query("
        SELECT u.id, u.nome_completo, COUNT(crp.id) as rotinas_padrao
        FROM usuarios u
        LEFT JOIN config_rotina_padrao crp ON u.id = crp.id_usuario
        GROUP BY u.id, u.nome_completo
        ORDER BY rotinas_padrao DESC
    ");
    $configuracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Rotinas Padrão</th></tr>";
    foreach ($configuracoes as $config) {
        echo "<tr>";
        echo "<td>{$config['id']}</td>";
        echo "<td>{$config['nome_completo']}</td>";
        echo "<td>{$config['rotinas_padrao']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<hr>";
    echo "<h2>🎉 LIMPEZA CONCLUÍDA!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Resumo da operação:</h4>";
    echo "<ul>";
    echo "<li><strong>Rotinas removidas:</strong> $rotinas_removidas</li>";
    echo "<li><strong>Configurações padrão:</strong> Mantidas intactas</li>";
    echo "<li><strong>Outras funcionalidades:</strong> Não afetadas</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔄 Como funciona agora:</h4>";
    echo "<ol>";
    echo "<li><strong>Usuários acessam tarefas.php</strong></li>";
    echo "<li><strong>Sistema verifica se há rotinas para hoje</strong></li>";
    echo "<li><strong>Se não houver, cria automaticamente</strong> baseado nas configurações padrão</li>";
    echo "<li><strong>Usuários podem adicionar/editar</strong> suas rotinas normalmente</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>💡 Dica:</h4>";
    echo "<p>Se quiser <strong>remover também as configurações padrão</strong>, execute: <a href='remover_configuracoes_padrao.php'>remover_configuracoes_padrao.php</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se o banco de dados está funcionando e se o usuário tem permissões para deletar registros.</p>";
    echo "</div>";
}
?>
