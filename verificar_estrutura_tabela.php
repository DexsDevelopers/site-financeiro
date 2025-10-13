<?php
// verificar_estrutura_tabela.php - Verificar estrutura da tabela tarefas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 VERIFICAÇÃO DA ESTRUTURA DA TABELA TAREFAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

try {
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ Tabela 'tarefas' existe<br><br>";
        
        // Mostrar estrutura da tabela
        echo "<h3>📋 ESTRUTURA DA TABELA TAREFAS:</h3>";
        $stmt = $pdo->query("DESCRIBE tarefas");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
        
        foreach ($colunas as $coluna) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($coluna['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($coluna['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($coluna['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($coluna['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($coluna['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($coluna['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Verificar algumas tarefas de exemplo
        echo "<h3>📊 EXEMPLO DE DADOS:</h3>";
        $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ? LIMIT 3");
        $stmt->execute([$_SESSION['user_id']]);
        $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tarefas) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr>";
            foreach (array_keys($tarefas[0]) as $campo) {
                echo "<th>" . htmlspecialchars($campo) . "</th>";
            }
            echo "</tr>";
            
            foreach ($tarefas as $tarefa) {
                echo "<tr>";
                foreach ($tarefa as $valor) {
                    echo "<td>" . htmlspecialchars($valor ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table><br>";
        } else {
            echo "⚠️ Nenhuma tarefa encontrada para este usuário<br>";
        }
        
        // Verificar tarefas de hoje
        echo "<h3>📅 TAREFAS DE HOJE:</h3>";
        $dataHoje = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND (
                DATE(data_limite) = ? 
                OR DATE(data_criacao) = ?
            )
        ");
        $stmt->execute([$_SESSION['user_id'], $dataHoje, $dataHoje]);
        $hoje = $stmt->fetch()['total'];
        echo "📊 Total de tarefas de hoje: $hoje<br>";
        
        if ($hoje > 0) {
            $stmt = $pdo->prepare("
                SELECT id, descricao, prioridade, status, data_limite, data_criacao
                FROM tarefas 
                WHERE id_usuario = ? 
                AND (
                    DATE(data_limite) = ? 
                    OR DATE(data_criacao) = ?
                )
                LIMIT 5
            ");
            $stmt->execute([$_SESSION['user_id'], $dataHoje, $dataHoje]);
            $tarefasHoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Descrição</th><th>Prioridade</th><th>Status</th><th>Data Limite</th><th>Data Criação</th></tr>";
            
            foreach ($tarefasHoje as $tarefa) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($tarefa['id']) . "</td>";
                echo "<td>" . htmlspecialchars($tarefa['descricao']) . "</td>";
                echo "<td>" . htmlspecialchars($tarefa['prioridade']) . "</td>";
                echo "<td>" . htmlspecialchars($tarefa['status']) . "</td>";
                echo "<td>" . htmlspecialchars($tarefa['data_limite'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($tarefa['data_criacao']) . "</td>";
                echo "</tr>";
            }
            echo "</table><br>";
        }
        
    } else {
        echo "❌ Tabela 'tarefas' não existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>✅ Verificação concluída!</strong> Use essas informações para corrigir as APIs.</p>";
?>
