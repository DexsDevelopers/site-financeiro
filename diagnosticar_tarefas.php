<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    die('Não autenticado');
}

echo "<h2>Diagnóstico da Tabela TAREFAS</h2>";

// 1. Estrutura da tabela
echo "<h3>1. Estrutura da Tabela:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE tarefas");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erro ao listar colunas: " . $e->getMessage();
}

// 2. Todas as tarefas do usuário
echo "<h3>2. Todas as Tarefas do Usuário (ID: $userId):</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($todas)) {
        echo "Nenhuma tarefa encontrada para este usuário.";
    } else {
        echo "<pre>" . json_encode($todas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} catch (Exception $e) {
    echo "Erro ao buscar tarefas: " . $e->getMessage();
}

// 3. Tarefas com status = 'pendente'
echo "<h3>3. Tarefas com status='pendente':</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt->execute([$userId]);
    $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total: " . count($pendentes);
    if ($pendentes) {
        echo "<pre>" . json_encode($pendentes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

// 4. Filtro da página atual
echo "<h3>4. Resultado com filtro da página (como aparece na listagem):</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT id, titulo, prioridade, data_limite, descricao, status 
        FROM tarefas 
        WHERE id_usuario = ? AND (
            status = 'pendente' OR status IS NULL OR status = '' OR status IN ('Em andamento','em_andamento','em andamento')
        )
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), COALESCE(ordem, 9999), data_limite
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $filtradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total: " . count($filtradas);
    if ($filtradas) {
        echo "<pre>" . json_encode($filtradas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
