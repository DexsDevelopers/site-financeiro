<?php
/**
 * Script para corrigir a estrutura da tabela rotina_exercicios
 * Remove a coluna id_usuario e foreign key incorreta se existirem
 */

require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Corrigir Estrutura da Tabela rotina_exercicios</h1>";
echo "<hr>";

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'rotina_exercicios'");
    if ($stmt_check->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ Tabela 'rotina_exercicios' não existe. Ela será criada automaticamente quando necessário.</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ Tabela 'rotina_exercicios' encontrada.</p>";
    
    // Verificar estrutura atual
    echo "<h3>Estrutura Atual:</h3>";
    $stmt_cols = $pdo->query("SHOW COLUMNS FROM rotina_exercicios");
    $colunas = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($colunas as $coluna) {
        echo "<li><strong>" . htmlspecialchars($coluna['Field']) . "</strong> - " . htmlspecialchars($coluna['Type']) . "</li>";
    }
    echo "</ul>";
    
    // Verificar foreign keys
    echo "<h3>Foreign Keys Atuais:</h3>";
    $stmt_fk = $pdo->query("
        SELECT 
            CONSTRAINT_NAME, 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'rotina_exercicios' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $foreign_keys = $stmt_fk->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($foreign_keys)) {
        echo "<p>Nenhuma foreign key encontrada.</p>";
    } else {
        echo "<ul>";
        foreach ($foreign_keys as $fk) {
            echo "<li><strong>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</strong>: " . 
                 htmlspecialchars($fk['COLUMN_NAME']) . " → " . 
                 htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "." . 
                 htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</li>";
        }
        echo "</ul>";
    }
    
    // Verificar se existe coluna id_usuario
    $tem_id_usuario = false;
    $fk_id_usuario = null;
    foreach ($colunas as $coluna) {
        if ($coluna['Field'] === 'id_usuario') {
            $tem_id_usuario = true;
            break;
        }
    }
    
    // Encontrar foreign key relacionada a id_usuario
    foreach ($foreign_keys as $fk) {
        if ($fk['COLUMN_NAME'] === 'id_usuario') {
            $fk_id_usuario = $fk['CONSTRAINT_NAME'];
            break;
        }
    }
    
    if ($tem_id_usuario || $fk_id_usuario) {
        echo "<hr>";
        echo "<h3>🔧 Corrigindo estrutura...</h3>";
        
        // Nota: Não usamos transação porque ALTER TABLE faz commit automático em MySQL
        $erros_ocorridos = [];
        
        try {
            // 1. Remover foreign key de id_usuario se existir
            if ($fk_id_usuario) {
                echo "<p>Removendo foreign key '{$fk_id_usuario}'...</p>";
                try {
                    $pdo->exec("ALTER TABLE rotina_exercicios DROP FOREIGN KEY `{$fk_id_usuario}`");
                    echo "<p style='color: green;'>✅ Foreign key removida.</p>";
                } catch (PDOException $e) {
                    $erros_ocorridos[] = "Erro ao remover foreign key: " . $e->getMessage();
                    echo "<p style='color: orange;'>⚠️ Erro ao remover foreign key (pode já ter sido removida): " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
            
            // 2. Remover índice de id_usuario se existir
            try {
                $pdo->exec("ALTER TABLE rotina_exercicios DROP INDEX `id_usuario`");
                echo "<p style='color: green;'>✅ Índice 'id_usuario' removido.</p>";
            } catch (PDOException $e) {
                // Índice pode não existir, ignorar
                echo "<p style='color: orange;'>⚠️ Índice 'id_usuario' não encontrado (pode não existir).</p>";
            }
            
            // 3. Remover coluna id_usuario se existir
            if ($tem_id_usuario) {
                echo "<p>Removendo coluna 'id_usuario'...</p>";
                try {
                    $pdo->exec("ALTER TABLE rotina_exercicios DROP COLUMN `id_usuario`");
                    echo "<p style='color: green;'>✅ Coluna 'id_usuario' removida.</p>";
                } catch (PDOException $e) {
                    $erros_ocorridos[] = "Erro ao remover coluna id_usuario: " . $e->getMessage();
                    echo "<p style='color: red;'>❌ Erro ao remover coluna: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
            
            // 4. Garantir que as colunas necessárias existam
            $colunas_necessarias = [
                'id_rotina_dia' => 'INT NOT NULL',
                'id_exercicio' => 'INT NOT NULL',
                'series_sugeridas' => 'INT NULL',
                'repeticoes_sugeridas' => 'VARCHAR(50) NULL',
                'ordem' => 'INT DEFAULT 0'
            ];
            
            $colunas_existentes = array_column($colunas, 'Field');
            
            foreach ($colunas_necessarias as $coluna => $tipo) {
                if (!in_array($coluna, $colunas_existentes)) {
                    echo "<p>Adicionando coluna '{$coluna}'...</p>";
                    try {
                        $pdo->exec("ALTER TABLE rotina_exercicios ADD COLUMN `{$coluna}` {$tipo}");
                        echo "<p style='color: green;'>✅ Coluna '{$coluna}' adicionada.</p>";
                    } catch (PDOException $e) {
                        $erros_ocorridos[] = "Erro ao adicionar coluna {$coluna}: " . $e->getMessage();
                        echo "<p style='color: orange;'>⚠️ Não foi possível adicionar coluna '{$coluna}': " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                }
            }
            
            // 5. Garantir foreign keys corretas
            $fk_necessarias = [
                'id_rotina_dia' => ['rotina_dias', 'id'],
                'id_exercicio' => ['exercicios', 'id']
            ];
            
            $fk_existentes = [];
            foreach ($foreign_keys as $fk) {
                $fk_existentes[$fk['COLUMN_NAME']] = $fk;
            }
            
            foreach ($fk_necessarias as $coluna => $ref) {
                if (!isset($fk_existentes[$coluna])) {
                    echo "<p>Adicionando foreign key para '{$coluna}'...</p>";
                    $fk_name = "fk_rotina_exercicios_{$coluna}";
                    try {
                        $pdo->exec("
                            ALTER TABLE rotina_exercicios 
                            ADD CONSTRAINT `{$fk_name}` 
                            FOREIGN KEY (`{$coluna}`) 
                            REFERENCES `{$ref[0]}`(`{$ref[1]}`) 
                            ON DELETE CASCADE
                        ");
                        echo "<p style='color: green;'>✅ Foreign key para '{$coluna}' adicionada.</p>";
                    } catch (PDOException $e) {
                        $erros_ocorridos[] = "Erro ao adicionar foreign key para {$coluna}: " . $e->getMessage();
                        echo "<p style='color: orange;'>⚠️ Não foi possível adicionar foreign key para '{$coluna}': " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                } else {
                    echo "<p style='color: green;'>✅ Foreign key para '{$coluna}' já existe.</p>";
                }
            }
            
            echo "<hr>";
            if (empty($erros_ocorridos)) {
                echo "<h3 style='color: green;'>✅ Estrutura corrigida com sucesso!</h3>";
            } else {
                echo "<h3 style='color: orange;'>⚠️ Estrutura corrigida com alguns avisos:</h3>";
                echo "<ul>";
                foreach ($erros_ocorridos as $erro) {
                    echo "<li>" . htmlspecialchars($erro) . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Erro crítico: " . htmlspecialchars($e->getMessage()) . "</p>";
            throw $e;
        }
        
    } else {
        echo "<hr>";
        echo "<p style='color: green;'>✅ A tabela já está com a estrutura correta (sem coluna id_usuario).</p>";
    }
    
    // Mostrar estrutura final
    echo "<hr>";
    echo "<h3>Estrutura Final:</h3>";
    $stmt_final = $pdo->query("SHOW COLUMNS FROM rotina_exercicios");
    $colunas_finais = $stmt_final->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($colunas_finais as $coluna) {
        echo "<li><strong>" . htmlspecialchars($coluna['Field']) . "</strong> - " . htmlspecialchars($coluna['Type']) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

