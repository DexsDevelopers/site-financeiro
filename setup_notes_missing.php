<?php
// setup_notes_missing.php - Garante que as tabelas de notas e mapas mentais existam
require_once 'includes/db_connect.php';

$queries = [
    // Tabela de cursos (se não existir)
    "CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome_curso VARCHAR(255) NOT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cursos_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Tabela de notas_cursos
    "CREATE TABLE IF NOT EXISTS notas_cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_curso INT NULL,
        titulo VARCHAR(255) NOT NULL,
        conteudo TEXT NOT NULL,
        categoria VARCHAR(50) DEFAULT 'outros',
        prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'baixa',
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_curso) REFERENCES cursos(id) ON DELETE SET NULL,
        INDEX idx_notas_usuario (id_usuario),
        INDEX idx_notas_curso (id_curso),
        INDEX idx_notas_categoria (categoria)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Tabela de mapas_mentais
    "CREATE TABLE IF NOT EXISTS mapas_mentais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_nota INT NULL,
        titulo VARCHAR(255) NOT NULL,
        dados LONGTEXT NOT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_nota) REFERENCES notas_cursos(id) ON DELETE SET NULL,
        INDEX idx_mapas_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "<h1>Configurando Tabelas de Notas...</h1>";

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Sucesso na query: " . substr($sql, 0, 50) . "...</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Erro na query: " . $e->getMessage() . "</p>";
    }
}

echo "<hr><p><a href='notas_cursos.php'>Voltar para Notas</a></p>";
?>
