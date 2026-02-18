<?php
require_once 'includes/db_connect.php';

echo "<h1>🔍 DIAGNÓSTICO DA TABELA rotinas_fixas</h1>";
echo "<hr>";

try {
    // Verificar estrutura
    echo "<h2>Estrutura da Tabela:</h2>";
    $stmt = $pdo->query("DESCRIBE rotinas_fixas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Exemplo de rotina
    echo "<h2>Exemplo de Dados:</h2>";
    $stmt = $pdo->prepare("SELECT * FROM rotinas_fixas LIMIT 1");
    $stmt->execute();
    $rotina = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rotina) {
        echo "<pre>";
        print_r($rotina);
        echo "</pre>";
    } else {
        echo "<p>Nenhuma rotina encontrada</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
