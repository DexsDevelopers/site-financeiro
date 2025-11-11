<?php
/**
 * Script para adicionar colunas opcionais à tabela rotina_dias
 * Execute este script uma vez se quiser adicionar descricao, duracao e nivel
 */

require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Adicionar Colunas à Tabela rotina_dias</h1>";
echo "<hr>";

try {
    // Verificar se as colunas já existem
    $stmt = $pdo->query("SHOW COLUMNS FROM rotina_dias");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $colunasParaAdicionar = [];
    
    if (!in_array('descricao', $colunas)) {
        $colunasParaAdicionar[] = "ALTER TABLE rotina_dias ADD COLUMN descricao TEXT NULL AFTER nome_treino";
    }
    
    if (!in_array('duracao', $colunas)) {
        $colunasParaAdicionar[] = "ALTER TABLE rotina_dias ADD COLUMN duracao INT DEFAULT 60 AFTER descricao";
    }
    
    if (!in_array('nivel', $colunas)) {
        $colunasParaAdicionar[] = "ALTER TABLE rotina_dias ADD COLUMN nivel VARCHAR(20) DEFAULT 'iniciante' AFTER duracao";
    }
    
    if (empty($colunasParaAdicionar)) {
        echo "<p style='color: green;'>✅ Todas as colunas já existem na tabela rotina_dias.</p>";
    } else {
        echo "<p>Colunas que serão adicionadas:</p><ul>";
        foreach ($colunasParaAdicionar as $sql) {
            echo "<li>" . htmlspecialchars($sql) . "</li>";
        }
        echo "</ul>";
        
        foreach ($colunasParaAdicionar as $sql) {
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ " . htmlspecialchars($sql) . "</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
        echo "<hr>";
        echo "<p style='color: green;'><strong>✅ Processo concluído!</strong></p>";
        echo "<p>Agora você pode usar as colunas descricao, duracao e nivel na tabela rotina_dias.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Colunas atuais da tabela rotina_dias:</h3>";
    echo "<ul>";
    foreach ($colunas as $coluna) {
        echo "<li>" . htmlspecialchars($coluna) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

