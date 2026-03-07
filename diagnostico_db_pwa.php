<?php
require_once 'includes/db_connect.php';

echo "<h1>Diagnóstico de Banco de Dados</h1>";

try {
    $stmt = $pdo->query("DESCRIBE rotinas_fixas");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Colunas em 'rotinas_fixas': " . implode(", ", $colunas) . "</p>";
    
    if (in_array('dias_semana', $colunas)) {
        echo "<p style='color: green;'>✅ A coluna 'dias_semana' EXISTE.</p>";
    } else {
        echo "<p style='color: red;'>❌ A coluna 'dias_semana' NÃO EXISTE.</p>";
        
        echo "<h2>Tentando criar coluna agora...</h2>";
        try {
            $pdo->exec("ALTER TABLE rotinas_fixas ADD COLUMN dias_semana VARCHAR(20) DEFAULT NULL");
            echo "<p style='color: green;'>✅ Comando ALTER TABLE executado com sucesso!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ FALHA ao executar ALTER TABLE: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERRO ao descrever tabela: " . $e->getMessage() . "</p>";
}

echo "<h1>Diagnóstico PWA</h1>";
$files = ['manifest.json', 'sw.js', 'sw-minimal.js'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ Arquivo '$file' existe. Permissões: " . substr(sprintf('%o', fileperms($file)), -4) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Arquivo '$file' NÃO existe!</p>";
    }
}
?>
