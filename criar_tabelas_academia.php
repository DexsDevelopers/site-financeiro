<?php
/**
 * Script para criar tabelas necessárias para o sistema de rotina de academia
 * Execute este script uma vez para criar as tabelas: rotinas, rotina_dias, exercicios, rotina_exercicios
 */

require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Criar Tabelas do Sistema de Academia</h1>";
echo "<hr>";

try {
    // 1. Tabela rotinas
    echo "<h3>1. Criando/Verificando tabela 'rotinas'</h3>";
    $sql_rotinas = "
    CREATE TABLE IF NOT EXISTS rotinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome_rotina VARCHAR(100) DEFAULT 'Rotina Principal',
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_rotinas);
    echo "<p style='color: green;'>✅ Tabela 'rotinas' criada/verificada com sucesso!</p>";
    
    // 2. Tabela rotina_dias
    echo "<h3>2. Criando/Verificando tabela 'rotina_dias'</h3>";
    $sql_rotina_dias = "
    CREATE TABLE IF NOT EXISTS rotina_dias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_rotina INT NOT NULL,
        dia_semana TINYINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),
        nome_treino VARCHAR(100) NOT NULL,
        FOREIGN KEY (id_rotina) REFERENCES rotinas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rotina_dia (id_rotina, dia_semana),
        INDEX idx_rotina (id_rotina),
        INDEX idx_dia (dia_semana)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_rotina_dias);
    echo "<p style='color: green;'>✅ Tabela 'rotina_dias' criada/verificada com sucesso!</p>";
    
    // 3. Tabela exercicios
    echo "<h3>3. Criando/Verificando tabela 'exercicios'</h3>";
    $sql_exercicios = "
    CREATE TABLE IF NOT EXISTS exercicios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome_exercicio VARCHAR(100) NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_exercicio_usuario (id_usuario, nome_exercicio),
        INDEX idx_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_exercicios);
    echo "<p style='color: green;'>✅ Tabela 'exercicios' criada/verificada com sucesso!</p>";
    
    // 4. Tabela rotina_exercicios
    echo "<h3>4. Criando/Verificando tabela 'rotina_exercicios'</h3>";
    $sql_rotina_exercicios = "
    CREATE TABLE IF NOT EXISTS rotina_exercicios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_rotina_dia INT NOT NULL,
        id_exercicio INT NOT NULL,
        series_sugeridas INT NULL,
        repeticoes_sugeridas VARCHAR(50) NULL,
        ordem INT DEFAULT 0,
        FOREIGN KEY (id_rotina_dia) REFERENCES rotina_dias(id) ON DELETE CASCADE,
        FOREIGN KEY (id_exercicio) REFERENCES exercicios(id) ON DELETE CASCADE,
        INDEX idx_rotina_dia (id_rotina_dia),
        INDEX idx_exercicio (id_exercicio),
        INDEX idx_ordem (ordem)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_rotina_exercicios);
    echo "<p style='color: green;'>✅ Tabela 'rotina_exercicios' criada/verificada com sucesso!</p>";
    
    echo "<hr>";
    echo "<h3>✅ Todas as tabelas foram criadas/verificadas com sucesso!</h3>";
    echo "<p>Você pode agora usar o sistema de rotina de academia normalmente.</p>";
    
    // Verificar estrutura das tabelas
    echo "<hr>";
    echo "<h3>Estrutura das Tabelas:</h3>";
    
    $tabelas = ['rotinas', 'rotina_dias', 'exercicios', 'rotina_exercicios'];
    foreach ($tabelas as $tabela) {
        echo "<h4>Tabela: {$tabela}</h4>";
        $stmt = $pdo->query("SHOW COLUMNS FROM {$tabela}");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>";
        foreach ($colunas as $coluna) {
            echo "<li><strong>" . htmlspecialchars($coluna['Field']) . "</strong> - " . htmlspecialchars($coluna['Type']) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

