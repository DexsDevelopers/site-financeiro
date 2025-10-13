<?php
// criar_tabelas_rotina_pomodoro.php - Script para criar tabelas do sistema de rotina e pomodoro

require_once 'includes/db_connect.php';

echo "<h1>🏗️ CRIANDO TABELAS DO SISTEMA DE ROTINA E POMODORO</h1>";
echo "<hr>";

try {
    // 1. Tabela para rotina diária
    $sql_rotina = "
    CREATE TABLE IF NOT EXISTS rotina_diaria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        status ENUM('pendente', 'concluido') DEFAULT 'pendente',
        data_execucao DATE NOT NULL,
        horario TIME DEFAULT NULL,
        ordem INT DEFAULT 0,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rotina_dia (id_usuario, nome, data_execucao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_rotina);
    echo "✅ <strong>Tabela rotina_diaria</strong> criada com sucesso!<br>";
    
    // 2. Tabela para sessões de pomodoro
    $sql_pomodoro = "
    CREATE TABLE IF NOT EXISTS pomodoro_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_tarefa INT NULL,
        tipo ENUM('trabalho', 'pausa_curta', 'pausa_longa') DEFAULT 'trabalho',
        inicio TIMESTAMP NOT NULL,
        fim TIMESTAMP NULL,
        duracao_minutos INT DEFAULT 25,
        status ENUM('ativo', 'pausado', 'concluido', 'cancelado') DEFAULT 'ativo',
        tempo_pausado INT DEFAULT 0,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_tarefa) REFERENCES tarefas(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_pomodoro);
    echo "✅ <strong>Tabela pomodoro_sessions</strong> criada com sucesso!<br>";
    
    // 3. Tabela para configurações de rotina padrão
    $sql_config_rotina = "
    CREATE TABLE IF NOT EXISTS config_rotina_padrao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        horario_sugerido TIME DEFAULT NULL,
        ordem INT DEFAULT 0,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_config_rotina);
    echo "✅ <strong>Tabela config_rotina_padrao</strong> criada com sucesso!<br>";
    
    // 4. Adicionar coluna hora_inicio na tabela tarefas se não existir
    $sql_check_hora = "SHOW COLUMNS FROM tarefas LIKE 'hora_inicio'";
    $stmt = $pdo->query($sql_check_hora);
    
    if ($stmt->rowCount() == 0) {
        $sql_add_hora = "ALTER TABLE tarefas ADD COLUMN hora_inicio TIME DEFAULT NULL";
        $pdo->exec($sql_add_hora);
        echo "✅ <strong>Coluna hora_inicio</strong> adicionada à tabela tarefas!<br>";
    } else {
        echo "✅ <strong>Coluna hora_inicio</strong> já existe na tabela tarefas!<br>";
    }
    
    // 5. Adicionar coluna tempo_gasto na tabela tarefas se não existir
    $sql_check_tempo = "SHOW COLUMNS FROM tarefas LIKE 'tempo_gasto'";
    $stmt = $pdo->query($sql_check_tempo);
    
    if ($stmt->rowCount() == 0) {
        $sql_add_tempo = "ALTER TABLE tarefas ADD COLUMN tempo_gasto INT DEFAULT 0 COMMENT 'Tempo gasto em minutos'";
        $pdo->exec($sql_add_tempo);
        echo "✅ <strong>Coluna tempo_gasto</strong> adicionada à tabela tarefas!<br>";
    } else {
        echo "✅ <strong>Coluna tempo_gasto</strong> já existe na tabela tarefas!<br>";
    }
    
    // 6. Inserir rotinas padrão para usuários existentes
    $stmt = $pdo->query("SELECT id FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $rotinas_padrao = [
        ['Treinar', '06:00:00'],
        ['Estudar', '08:00:00'],
        ['Ler', '20:00:00'],
        ['Organizar o dia', '07:00:00'],
        ['Meditar', '19:00:00'],
        ['Revisar metas', '21:00:00']
    ];
    
    foreach ($usuarios as $usuario_id) {
        foreach ($rotinas_padrao as $index => $rotina) {
            $sql_insert = "INSERT IGNORE INTO config_rotina_padrao (id_usuario, nome, horario_sugerido, ordem) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute([$usuario_id, $rotina[0], $rotina[1], $index + 1]);
        }
    }
    
    echo "✅ <strong>Rotinas padrão</strong> inseridas para todos os usuários!<br>";
    
    echo "<hr>";
    echo "<h2>🎉 ESTRUTURA CRIADA COM SUCESSO!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>📋 Tabelas criadas:</h4>";
    echo "<ul>";
    echo "<li><strong>rotina_diaria</strong> - Controle de hábitos diários</li>";
    echo "<li><strong>pomodoro_sessions</strong> - Sessões de pomodoro</li>";
    echo "<li><strong>config_rotina_padrao</strong> - Configurações de rotina</li>";
    echo "<li><strong>Colunas adicionadas em tarefas:</strong> hora_inicio, tempo_gasto</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🚀 Próximos passos:</h4>";
    echo "<ol>";
    echo "<li>Criar página de rotina diária</li>";
    echo "<li>Implementar sistema de pomodoro</li>";
    echo "<li>Adicionar automatização por horário</li>";
    echo "<li>Integrar tudo no dashboard</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro ao criar tabelas:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se o usuário do banco tem permissões para criar tabelas.</p>";
    echo "</div>";
}
?>
