<?php
// verificar_rotinas_fixas.php - Verificar se o sistema de rotinas fixas está funcionando

require_once 'includes/db_connect.php';

echo "<h1>🔍 VERIFICANDO SISTEMA DE ROTINAS FIXAS</h1>";
echo "<hr>";

try {
    // 1. Verificar se as tabelas existem
    echo "<h3>1. Verificando tabelas</h3>";
    
    $tabelas = ['rotinas_fixas', 'rotina_controle_diario'];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "✅ <strong>Tabela $tabela existe</strong><br>";
        } else {
            echo "❌ <strong>Tabela $tabela NÃO existe</strong><br>";
        }
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
    $stmt = $pdo->query("SELECT id, nome_completo FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👥 <strong>Usuários encontrados:</strong><br>";
    foreach ($usuarios as $usuario) {
        echo "• ID {$usuario['id']}: {$usuario['nome_completo']}<br>";
    }
    
    // 4. Verificar rotinas fixas existentes
    echo "<h3>4. Verificando rotinas fixas</h3>";
    $stmt = $pdo->query("
        SELECT rf.*, u.nome_completo 
        FROM rotinas_fixas rf 
        JOIN usuarios u ON rf.id_usuario = u.id 
        ORDER BY rf.id_usuario, rf.ordem
    ");
    $rotinas_fixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rotinas_fixas)) {
        echo "📋 <strong>Rotinas fixas encontradas:</strong><br>";
        foreach ($rotinas_fixas as $rotina) {
            echo "• {$rotina['nome_completo']}: {$rotina['nome']} (Ativo: " . ($rotina['ativo'] ? 'Sim' : 'Não') . ")<br>";
        }
    } else {
        echo "⚠️ <strong>Nenhuma rotina fixa encontrada</strong><br>";
    }
    
    // 5. Criar rotinas fixas de exemplo se não existirem
    if (empty($rotinas_fixas)) {
        echo "<h3>5. Criando rotinas fixas de exemplo</h3>";
        
        $rotinas_exemplo = [
            ['Treinar', '06:00:00'],
            ['Estudar', '08:00:00'],
            ['Ler', '20:00:00'],
            ['Organizar o dia', '07:00:00'],
            ['Meditar', '19:00:00']
        ];
        
        foreach ($usuarios as $usuario) {
            echo "👤 <strong>Criando rotinas para {$usuario['nome_completo']}:</strong><br>";
            
            foreach ($rotinas_exemplo as $index => $rotina) {
                $stmt = $pdo->prepare("
                    INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, ordem, ativo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$usuario['id'], $rotina[0], $rotina[1], $index + 1]);
                echo "  ✅ {$rotina[0]}<br>";
            }
        }
    }
    
    // 6. Criar controles para hoje
    echo "<h3>6. Criando controles para hoje</h3>";
    $dataHoje = date('Y-m-d');
    
    $stmt = $pdo->query("
        SELECT rf.id, rf.id_usuario, rf.nome
        FROM rotinas_fixas rf
        WHERE rf.ativo = TRUE
        ORDER BY rf.id_usuario, rf.ordem
    ");
    $rotinas_ativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $controles_criados = 0;
    foreach ($rotinas_ativas as $rotina) {
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
    
    // 7. Testar consulta que o tarefas.php usa
    echo "<h3>7. Testando consulta do tarefas.php</h3>";
    
    $userId = 1; // Testar com usuário ID 1
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
    $resultado_teste = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($resultado_teste)) {
        echo "✅ <strong>Consulta funcionando! Encontradas " . count($resultado_teste) . " rotinas fixas</strong><br>";
        foreach ($resultado_teste as $rotina) {
            echo "• {$rotina['nome']} - Status: " . ($rotina['status_hoje'] ?? 'pendente') . "<br>";
        }
    } else {
        echo "❌ <strong>Consulta não retornou resultados</strong><br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 VERIFICAÇÃO CONCLUÍDA!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Próximos passos:</h4>";
    echo "<ol>";
    echo "<li><strong>Acesse tarefas.php</strong> para ver as rotinas fixas</li>";
    echo "<li><strong>Se ainda não aparecer</strong>, verifique se há erros no console do navegador</li>";
    echo "<li><strong>Teste adicionar uma rotina fixa</strong> usando o botão na página</li>";
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
