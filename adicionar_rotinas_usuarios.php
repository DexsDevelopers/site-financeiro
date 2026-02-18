<?php
// adicionar_rotinas_usuarios.php - Adicionar rotinas padrão para todos os usuários

require_once 'includes/db_connect.php';

echo "<h1>🔧 ADICIONANDO ROTINAS PADRÃO PARA TODOS OS USUÁRIOS</h1>";
echo "<hr>";

try {
    // 1. Buscar todos os usuários
    $stmt = $pdo->query("SELECT id, nome_completo FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>👥 Usuários encontrados:</h3>";
    foreach ($usuarios as $usuario) {
        echo "• ID {$usuario['id']}: {$usuario['nome_completo']}<br>";
    }
    echo "<br>";
    
    // 2. Rotinas padrão
    $rotinas_padrao = [
        ['Treinar', '06:00:00', 1],
        ['Estudar', '08:00:00', 2],
        ['Ler', '20:00:00', 3],
        ['Organizar o dia', '07:00:00', 4],
        ['Meditar', '19:00:00', 5],
        ['Revisar metas', '21:00:00', 6]
    ];
    
    $usuarios_processados = 0;
    $rotinas_adicionadas = 0;
    
    foreach ($usuarios as $usuario) {
        $userId = $usuario['id'];
        echo "<h4>🔄 Processando usuário: {$usuario['nome_completo']} (ID: $userId)</h4>";
        
        // Verificar se já tem rotinas padrão
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM config_rotina_padrao WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $total_rotinas = $stmt->fetch()['total'];
        
        if ($total_rotinas == 0) {
            echo "• Adicionando rotinas padrão...<br>";
            
            foreach ($rotinas_padrao as $rotina) {
                $stmt = $pdo->prepare("
                    INSERT INTO config_rotina_padrao (id_usuario, nome, horario_sugerido, ordem, ativo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$userId, $rotina[0], $rotina[1], $rotina[2]]);
                $rotinas_adicionadas++;
            }
            echo "✅ <strong>Rotinas padrão adicionadas</strong><br>";
        } else {
            echo "✅ <strong>Usuário já possui $total_rotinas rotinas padrão</strong><br>";
        }
        
        $usuarios_processados++;
        echo "<br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 PROCESSO CONCLUÍDO!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>📊 Resumo:</h4>";
    echo "<ul>";
    echo "<li><strong>Usuários processados:</strong> $usuarios_processados</li>";
    echo "<li><strong>Rotinas adicionadas:</strong> $rotinas_adicionadas</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Como testar:</h4>";
    echo "<ol>";
    echo "<li>Faça login com diferentes usuários</li>";
    echo "<li>Acesse <a href='tarefas.php'>tarefas.php</a></li>";
    echo "<li>Verifique se a seção 'Rotina Diária' aparece para todos</li>";
    echo "<li>Teste adicionar, editar e marcar hábitos como concluídos</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se as tabelas existem. Execute primeiro: <a href='criar_tabelas_rotina_pomodoro.php'>criar_tabelas_rotina_pomodoro.php</a></p>";
    echo "</div>";
}
?>
