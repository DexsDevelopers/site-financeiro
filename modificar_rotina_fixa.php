<?php
// modificar_rotina_fixa.php - Modificar sistema de rotina diária para ser fixa

require_once 'includes/db_connect.php';

echo "<h1>🔧 MODIFICANDO SISTEMA DE ROTINA DIÁRIA PARA SER FIXA</h1>";
echo "<hr>";

try {
    // 1. Criar nova tabela para rotinas fixas
    echo "<h3>1. Criando tabela para rotinas fixas</h3>";
    
    $sql_create_table = "
    CREATE TABLE IF NOT EXISTS rotinas_fixas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        horario_sugerido TIME DEFAULT NULL,
        ordem INT DEFAULT 0,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rotina_usuario (id_usuario, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_create_table);
    echo "✅ <strong>Tabela rotinas_fixas criada com sucesso!</strong><br>";
    
    // 2. Criar tabela para controle diário
    echo "<h3>2. Criando tabela para controle diário</h3>";
    
    $sql_create_controle = "
    CREATE TABLE IF NOT EXISTS rotina_controle_diario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_rotina_fixa INT NOT NULL,
        data_execucao DATE NOT NULL,
        status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
        horario_execucao TIME DEFAULT NULL,
        observacoes TEXT DEFAULT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_rotina_fixa) REFERENCES rotinas_fixas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_controle_dia (id_usuario, id_rotina_fixa, data_execucao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_create_controle);
    echo "✅ <strong>Tabela rotina_controle_diario criada com sucesso!</strong><br>";
    
    // 3. Migrar dados existentes
    echo "<h3>3. Migrando dados existentes</h3>";
    
    // Buscar usuários que têm configurações padrão
    $stmt = $pdo->query("SELECT DISTINCT id_usuario FROM config_rotina_padrao");
    $usuarios_com_config = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $rotinas_migradas = 0;
    foreach ($usuarios_com_config as $usuario_id) {
        // Buscar configurações do usuário
        $stmt = $pdo->prepare("
            SELECT nome, horario_sugerido, ordem 
            FROM config_rotina_padrao 
            WHERE id_usuario = ? AND ativo = TRUE 
            ORDER BY ordem
        ");
        $stmt->execute([$usuario_id]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($configs as $config) {
            // Inserir na tabela de rotinas fixas
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO rotinas_fixas (id_usuario, nome, horario_sugerido, ordem, ativo) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $usuario_id, 
                $config['nome'], 
                $config['horario_sugerido'], 
                $config['ordem']
            ]);
            $rotinas_migradas++;
        }
    }
    
    echo "✅ <strong>$rotinas_migradas rotinas migradas para o sistema fixo</strong><br>";
    
    // 4. Criar controle para hoje
    echo "<h3>4. Criando controle para hoje</h3>";
    
    $dataHoje = date('Y-m-d');
    $stmt = $pdo->query("
        SELECT rf.id, rf.id_usuario, rf.nome, rf.horario_sugerido, rf.ordem
        FROM rotinas_fixas rf
        WHERE rf.ativo = TRUE
        ORDER BY rf.id_usuario, rf.ordem
    ");
    $rotinas_fixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $controles_criados = 0;
    foreach ($rotinas_fixas as $rotina) {
        // Verificar se já existe controle para hoje
        $stmt = $pdo->prepare("
            SELECT id FROM rotina_controle_diario 
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        $stmt->execute([$rotina['id_usuario'], $rotina['id'], $dataHoje]);
        
        if (!$stmt->fetch()) {
            // Criar controle para hoje
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmt->execute([$rotina['id_usuario'], $rotina['id'], $dataHoje]);
            $controles_criados++;
        }
    }
    
    echo "✅ <strong>$controles_criados controles criados para hoje</strong><br>";
    
    // 5. Mostrar estatísticas
    echo "<h3>5. Estatísticas do novo sistema</h3>";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT rf.id_usuario) as usuarios_com_rotinas,
            COUNT(rf.id) as total_rotinas_fixas,
            COUNT(rcd.id) as controles_hoje
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa AND rcd.data_execucao = CURDATE()
        WHERE rf.ativo = TRUE
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 <strong>Estatísticas:</strong><br>";
    echo "• Usuários com rotinas fixas: {$stats['usuarios_com_rotinas']}<br>";
    echo "• Total de rotinas fixas: {$stats['total_rotinas_fixas']}<br>";
    echo "• Controles para hoje: {$stats['controles_hoje']}<br>";
    
    echo "<hr>";
    echo "<h2>🎉 SISTEMA MODIFICADO COM SUCESSO!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ O que mudou:</h4>";
    echo "<ul>";
    echo "<li><strong>Rotinas fixas:</strong> Agora são permanentes para cada usuário</li>";
    echo "<li><strong>Controle diário:</strong> Sistema controla execução por dia</li>";
    echo "<li><strong>Flexibilidade:</strong> Usuários podem ativar/desativar rotinas</li>";
    echo "<li><strong>Histórico:</strong> Mantém registro de execução diária</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔄 Como funciona agora:</h4>";
    echo "<ol>";
    echo "<li><strong>Rotinas fixas:</strong> Lista permanente de hábitos do usuário</li>";
    echo "<li><strong>Controle diário:</strong> Para cada dia, marca se fez ou não</li>";
    echo "<li><strong>Progresso:</strong> Mostra quantos hábitos foram cumpridos hoje</li>";
    echo "<li><strong>Flexibilidade:</strong> Pode pular, adiar ou marcar como concluído</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>📝 Próximo passo:</h4>";
    echo "<p>Agora você precisa <strong>atualizar o arquivo tarefas.php</strong> para usar o novo sistema de rotinas fixas.</p>";
    echo "<p>Execute: <a href='atualizar_tarefas_rotina_fixa.php'>atualizar_tarefas_rotina_fixa.php</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se o banco de dados está funcionando e se o usuário tem permissões.</p>";
    echo "</div>";
}
?>
