<?php
// teste_rotinas_simples.php - Teste simples das rotinas fixas

require_once 'includes/db_connect.php';

echo "<h1>🧪 TESTE SIMPLES - ROTINAS FIXAS</h1>";
echo "<hr>";

try {
    // 1. Verificar se as tabelas existem
    echo "<h3>1. Verificando tabelas</h3>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'rotinas_fixas'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>Tabela rotinas_fixas existe</strong><br>";
    } else {
        echo "❌ <strong>Tabela rotinas_fixas NÃO existe</strong><br>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'rotina_controle_diario'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>Tabela rotina_controle_diario existe</strong><br>";
    } else {
        echo "❌ <strong>Tabela rotina_controle_diario NÃO existe</strong><br>";
    }
    
    // 2. Criar tabelas se não existirem
    echo "<h3>2. Criando tabelas se necessário</h3>";
    
    // Tabela rotinas_fixas
    $sql_rotinas_fixas = "
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
    
    $pdo->exec($sql_rotinas_fixas);
    echo "✅ <strong>Tabela rotinas_fixas criada/verificada</strong><br>";
    
    // Tabela rotina_controle_diario
    $sql_controle = "
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
    
    $pdo->exec($sql_controle);
    echo "✅ <strong>Tabela rotina_controle_diario criada/verificada</strong><br>";
    
    // 3. Verificar usuários
    echo "<h3>3. Verificando usuários</h3>";
    $stmt = $pdo->query("SELECT id, nome_completo FROM usuarios LIMIT 5");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👥 <strong>Usuários encontrados:</strong><br>";
    foreach ($usuarios as $usuario) {
        echo "• ID {$usuario['id']}: {$usuario['nome_completo']}<br>";
    }
    
    // 4. Criar rotinas fixas para o primeiro usuário
    if (!empty($usuarios)) {
        $userId = $usuarios[0]['id'];
        echo "<h3>4. Criando rotinas fixas para usuário ID $userId</h3>";
        
        // Verificar se já tem rotinas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rotinas_fixas WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $total_rotinas = $stmt->fetchColumn();
        
        if ($total_rotinas == 0) {
            $rotinas_exemplo = [
                ['Treinar', '06:00:00'],
                ['Estudar', '08:00:00'],
                ['Ler', '20:00:00'],
                ['Organizar o dia', '07:00:00'],
                ['Meditar', '19:00:00']
            ];
            
            foreach ($rotinas_exemplo as $index => $rotina) {
                $stmt = $pdo->prepare("
                    INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, ordem, ativo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$userId, $rotina[0], $rotina[1], $index + 1]);
                echo "✅ <strong>{$rotina[0]}</strong> criada<br>";
            }
        } else {
            echo "ℹ️ <strong>Usuário já possui $total_rotinas rotinas fixas</strong><br>";
        }
    }
    
    // 5. Testar consulta
    echo "<h3>5. Testando consulta das rotinas fixas</h3>";
    $dataHoje = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.horario_execucao,
               rcd.observacoes
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinas_fixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rotinas_fixas)) {
        echo "✅ <strong>Consulta funcionando! Encontradas " . count($rotinas_fixas) . " rotinas fixas</strong><br>";
        foreach ($rotinas_fixas as $rotina) {
            echo "• <strong>{$rotina['nome']}</strong> - Status: " . ($rotina['status_hoje'] ?? 'pendente') . "<br>";
        }
    } else {
        echo "❌ <strong>Consulta não retornou resultados</strong><br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 TESTE CONCLUÍDO!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Próximos passos:</h4>";
    echo "<ol>";
    echo "<li><strong>Faça login</strong> no sistema</li>";
    echo "<li><strong>Acesse tarefas.php</strong> - As rotinas fixas devem aparecer</li>";
    echo "<li><strong>Se não aparecer</strong>, verifique se há erros no console do navegador</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>🔧 Solução:</h4>";
    echo "<p>Verifique se o banco de dados está funcionando e se o usuário tem permissões.</p>";
    echo "</div>";
}
?>
