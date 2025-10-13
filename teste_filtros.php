<?php
// Teste dos filtros de prioridade
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🧪 Teste dos Filtros de Prioridade</h2>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>";

try {
    // Buscar todas as tarefas pendentes
    $stmt = $pdo->prepare("SELECT id, descricao, prioridade FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY prioridade");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tarefas)) {
        echo "<p class='error'>❌ Nenhuma tarefa pendente encontrada</p>";
        echo "<p>Vamos criar tarefas de teste:</p>";
        
        // Criar tarefas de teste
        $tarefas_teste = [
            ['desc' => 'Tarefa Alta Prioridade', 'prio' => 'Alta'],
            ['desc' => 'Tarefa Média Prioridade', 'prio' => 'Média'],
            ['desc' => 'Tarefa Baixa Prioridade', 'prio' => 'Baixa']
        ];
        
        foreach ($tarefas_teste as $tarefa) {
            $stmt_insert = $pdo->prepare("INSERT INTO tarefas (id_usuario, descricao, prioridade, status, data_criacao) VALUES (?, ?, ?, 'pendente', NOW())");
            $stmt_insert->execute([$userId, $tarefa['desc'], $tarefa['prio']]);
        }
        
        echo "<p class='success'>✅ Tarefas de teste criadas!</p>";
        
        // Buscar novamente
        $stmt->execute([$userId]);
        $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "<h3>Tarefas Encontradas:</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Descrição</th><th>Prioridade</th><th>Data Attribute</th></tr>";
    
    foreach ($tarefas as $tarefa) {
        $dataAttribute = strtolower(str_replace('é', 'e', $tarefa['prioridade']));
        echo "<tr>";
        echo "<td>{$tarefa['id']}</td>";
        echo "<td>{$tarefa['descricao']}</td>";
        echo "<td><strong>{$tarefa['prioridade']}</strong></td>";
        echo "<td><code>data-priority=\"{$dataAttribute}\"</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Mapeamento de Filtros:</h3>";
    echo "<ul>";
    echo "<li><strong>alta</strong> → filtra tarefas com data-priority=\"alta\"</li>";
    echo "<li><strong>media</strong> → filtra tarefas com data-priority=\"media\"</li>";
    echo "<li><strong>baixa</strong> → filtra tarefas com data-priority=\"baixa\"</li>";
    echo "</ul>";
    
    echo "<h3>Teste JavaScript:</h3>";
    echo "<p>Abra o console do navegador (F12) e vá para a página de tarefas. Quando clicar nos filtros, você verá logs mostrando:</p>";
    echo "<ul>";
    echo "<li>Card priority: [prioridade da tarefa]</li>";
    echo "<li>Filter: [filtro selecionado]</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro no banco: " . $e->getMessage() . "</p>";
}

echo "<hr><p style='color:#666;font-size:12px;'><strong>Lembre-se:</strong> Delete este arquivo após o teste!</p>";
?>

<div style="margin-top: 30px; padding: 15px; background: #e8f4fd; border-radius: 5px;">
    <h4>🔧 Como Testar:</h4>
    <ol>
        <li><strong>Vá para a página de tarefas:</strong> <a href="tarefas.php" target="_blank">tarefas.php</a></li>
        <li><strong>Abra o console:</strong> Pressione F12 → Console</li>
        <li><strong>Clique nos filtros:</strong> Alta, Média, Baixa Prioridade</li>
        <li><strong>Veja os logs:</strong> Deve mostrar "Card priority" e "Filter"</li>
        <li><strong>Verifique se filtra:</strong> Só devem aparecer tarefas da prioridade selecionada</li>
    </ol>
</div>
