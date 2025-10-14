<?php
// teste_rotina_usuarios.php - Testar se rotina diária funciona para todos os usuários

require_once 'includes/db_connect.php';

echo "<h1>🧪 TESTE - ROTINA DIÁRIA PARA TODOS OS USUÁRIOS</h1>";
echo "<hr>";

try {
    // 1. Listar todos os usuários
    $stmt = $pdo->query("SELECT id, nome_completo FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>👥 Usuários no sistema:</h3>";
    foreach ($usuarios as $usuario) {
        echo "• ID {$usuario['id']}: {$usuario['nome_completo']}<br>";
    }
    echo "<br>";
    
    // 2. Verificar rotinas padrão para cada usuário
    echo "<h3>📋 Verificando rotinas padrão:</h3>";
    foreach ($usuarios as $usuario) {
        $userId = $usuario['id'];
        
        $stmt = $pdo->prepare("
            SELECT nome, horario_sugerido, ordem, ativo 
            FROM config_rotina_padrao 
            WHERE id_usuario = ? 
            ORDER BY ordem
        ");
        $stmt->execute([$userId]);
        $rotinas = $stmt->fetchAll();
        
        echo "<h4>👤 {$usuario['nome_completo']} (ID: $userId):</h4>";
        if (empty($rotinas)) {
            echo "❌ <strong>Nenhuma rotina padrão encontrada!</strong><br>";
            echo "🔧 <a href='adicionar_rotinas_usuarios.php'>Executar correção</a><br>";
        } else {
            echo "✅ <strong>" . count($rotinas) . " rotinas padrão encontradas:</strong><br>";
            foreach ($rotinas as $rotina) {
                $status = $rotina['ativo'] ? '✅' : '❌';
                echo "&nbsp;&nbsp;• $status {$rotina['nome']} - {$rotina['horario_sugerido']}<br>";
            }
        }
        echo "<br>";
    }
    
    // 3. Verificar rotinas para hoje
    $dataHoje = date('Y-m-d');
    echo "<h3>📅 Verificando rotinas para hoje ($dataHoje):</h3>";
    foreach ($usuarios as $usuario) {
        $userId = $usuario['id'];
        
        $stmt = $pdo->prepare("
            SELECT rd.nome, rd.status, rd.horario, crp.horario_sugerido
            FROM rotina_diaria rd
            LEFT JOIN config_rotina_padrao crp ON rd.nome = crp.nome AND rd.id_usuario = crp.id_usuario
            WHERE rd.id_usuario = ? AND rd.data_execucao = ?
            ORDER BY rd.ordem
        ");
        $stmt->execute([$userId, $dataHoje]);
        $rotinas_hoje = $stmt->fetchAll();
        
        echo "<h4>👤 {$usuario['nome_completo']} (ID: $userId):</h4>";
        if (empty($rotinas_hoje)) {
            echo "❌ <strong>Nenhuma rotina para hoje!</strong><br>";
            echo "💡 <strong>Dica:</strong> Acesse <a href='tarefas.php'>tarefas.php</a> para criar automaticamente<br>";
        } else {
            echo "✅ <strong>" . count($rotinas_hoje) . " rotinas para hoje:</strong><br>";
            foreach ($rotinas_hoje as $rotina) {
                $status_icon = $rotina['status'] === 'concluido' ? '✅' : '⏳';
                $horario = $rotina['horario'] ?: $rotina['horario_sugerido'];
                echo "&nbsp;&nbsp;• $status_icon {$rotina['nome']} - $horario<br>";
            }
        }
        echo "<br>";
    }
    
    echo "<hr>";
    echo "<h2>🎯 PRÓXIMOS PASSOS</h2>";
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Para testar completamente:</h4>";
    echo "<ol>";
    echo "<li><strong>Faça login com cada usuário</strong> e acesse <a href='tarefas.php'>tarefas.php</a></li>";
    echo "<li><strong>Verifique se a seção 'Rotina Diária' aparece</strong> para todos</li>";
    echo "<li><strong>Teste as funcionalidades:</strong> adicionar, editar, marcar como concluído</li>";
    echo "<li><strong>Se algum usuário não tiver rotinas:</strong> execute <a href='adicionar_rotinas_usuarios.php'>adicionar_rotinas_usuarios.php</a></li>";
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
