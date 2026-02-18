<?php
// corrigir_rotina_diaria_usuarios.php - Corrigir rotina diária para todos os usuários

require_once 'includes/db_connect.php';

echo "<h1>🔧 CORRIGINDO ROTINA DIÁRIA PARA TODOS OS USUÁRIOS</h1>";
echo "<hr>";

try {
    // 1. Buscar todos os usuários
    $stmt = $pdo->query("SELECT id, nome FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>👥 Usuários encontrados:</h3>";
    foreach ($usuarios as $usuario) {
        echo "• ID {$usuario['id']}: {$usuario['nome']}<br>";
    }
    echo "<br>";
    
    // 2. Verificar se as tabelas existem
    $tabelas_necessarias = ['rotina_diaria', 'config_rotina_padrao'];
    foreach ($tabelas_necessarias as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() == 0) {
            echo "❌ <strong>Tabela $tabela não existe!</strong><br>";
            echo "Execute primeiro: <a href='criar_tabelas_rotina_pomodoro.php'>criar_tabelas_rotina_pomodoro.php</a><br><br>";
            exit();
        }
    }
    echo "✅ <strong>Tabelas necessárias existem</strong><br><br>";
    
    // 3. Rotinas padrão para todos os usuários
    $rotinas_padrao = [
        ['Treinar', '06:00:00', 1],
        ['Estudar', '08:00:00', 2],
        ['Ler', '20:00:00', 3],
        ['Organizar o dia', '07:00:00', 4],
        ['Meditar', '19:00:00', 5],
        ['Revisar metas', '21:00:00', 6]
    ];
    
    $usuarios_processados = 0;
    $rotinas_criadas = 0;
    
    foreach ($usuarios as $usuario) {
        $userId = $usuario['id'];
        echo "<h4>🔄 Processando usuário: {$usuario['nome']} (ID: $userId)</h4>";
        
        // Verificar se já tem rotinas padrão
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM config_rotina_padrao WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $total_rotinas = $stmt->fetch()['total'];
        
        if ($total_rotinas == 0) {
            echo "• Criando rotinas padrão...<br>";
            
            foreach ($rotinas_padrao as $rotina) {
                $stmt = $pdo->prepare("
                    INSERT INTO config_rotina_padrao (id_usuario, nome, horario_sugerido, ordem, ativo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$userId, $rotina[0], $rotina[1], $rotina[2]]);
                $rotinas_criadas++;
            }
            echo "✅ <strong>Rotinas padrão criadas</strong><br>";
        } else {
            echo "✅ <strong>Usuário já possui $total_rotinas rotinas padrão</strong><br>";
        }
        
        // Criar rotinas para hoje se não existirem
        $dataHoje = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM rotina_diaria 
            WHERE id_usuario = ? AND data_execucao = ?
        ");
        $stmt->execute([$userId, $dataHoje]);
        $rotinas_hoje = $stmt->fetch()['total'];
        
        if ($rotinas_hoje == 0) {
            echo "• Criando rotinas para hoje ($dataHoje)...<br>";
            
            // Buscar rotinas padrão do usuário
            $stmt = $pdo->prepare("
                SELECT nome, horario_sugerido, ordem 
                FROM config_rotina_padrao 
                WHERE id_usuario = ? AND ativo = TRUE 
                ORDER BY ordem
            ");
            $stmt->execute([$userId]);
            $rotinas_padrao_usuario = $stmt->fetchAll();
            
            foreach ($rotinas_padrao_usuario as $rotina) {
                $stmt = $pdo->prepare("
                    INSERT INTO rotina_diaria (id_usuario, nome, data_execucao, horario, ordem, status) 
                    VALUES (?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $userId, 
                    $rotina['nome'], 
                    $dataHoje, 
                    $rotina['horario_sugerido'], 
                    $rotina['ordem']
                ]);
            }
            echo "✅ <strong>Rotinas para hoje criadas</strong><br>";
        } else {
            echo "✅ <strong>Usuário já possui $rotinas_hoje rotinas para hoje</strong><br>";
        }
        
        $usuarios_processados++;
        echo "<br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 CORREÇÃO CONCLUÍDA!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>📊 Resumo:</h4>";
    echo "<ul>";
    echo "<li><strong>Usuários processados:</strong> $usuarios_processados</li>";
    echo "<li><strong>Rotinas criadas:</strong> $rotinas_criadas</li>";
    echo "<li><strong>Data de hoje:</strong> " . date('d/m/Y') . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Próximos passos:</h4>";
    echo "<ol>";
    echo "<li>Acesse <a href='tarefas.php'>tarefas.php</a> para ver a rotina diária</li>";
    echo "<li>Teste com diferentes usuários logados</li>";
    echo "<li>Verifique se as rotinas aparecem para todos</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se as tabelas existem e se o usuário do banco tem permissões.</p>";
    echo "</div>";
}
?>
