<?php
// verificar_coluna_usuario.php - Verificar e criar coluna 'usuario' se necessário

// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db_connect.php';

echo "<h2>🔍 VERIFICANDO COLUNA 'usuario' NA TABELA 'usuarios'</h2>";

try {
    // Verificar estrutura da tabela usuarios
    $stmt = $pdo->query("DESCRIBE usuarios");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Colunas atuais da tabela 'usuarios':</h3>";
    echo "<ul>";
    foreach ($colunas as $coluna) {
        echo "<li><strong>{$coluna['Field']}</strong> ({$coluna['Type']}) - {$coluna['Null']} - {$coluna['Key']}</li>";
    }
    echo "</ul>";
    
    // Verificar se a coluna 'usuario' existe
    $colunasExistentes = array_column($colunas, 'Field');
    
    if (in_array('usuario', $colunasExistentes)) {
        echo "<p style='color: green;'><strong>✅ Coluna 'usuario' já existe na tabela!</strong></p>";
        
        // Verificar se há dados na coluna usuario
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE usuario IS NOT NULL AND usuario != ''");
        $totalComUsuario = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<p><strong>📊 Usuários com campo 'usuario' preenchido:</strong> $totalComUsuario</p>";
        
        if ($totalComUsuario == 0) {
            echo "<p style='color: orange;'><strong>⚠️ Nenhum usuário tem o campo 'usuario' preenchido.</strong></p>";
            echo "<p>Você pode:</p>";
            echo "<ol>";
            echo "<li>Preencher manualmente o campo 'usuario' para cada usuário</li>";
            echo "<li>Usar o script de migração abaixo para copiar email para usuario</li>";
            echo "</ol>";
            
            // Script de migração
            echo "<h3>🔄 Script de Migração (Copiar email para usuario):</h3>";
            echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
            echo "UPDATE usuarios SET usuario = email WHERE usuario IS NULL OR usuario = '';";
            echo "</pre>";
            
            echo "<p><strong>Executar migração?</strong> <a href='?migrar=1' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>SIM, EXECUTAR</a></p>";
            
            if (isset($_GET['migrar']) && $_GET['migrar'] == '1') {
                try {
                    $stmt = $pdo->prepare("UPDATE usuarios SET usuario = email WHERE usuario IS NULL OR usuario = ''");
                    $stmt->execute();
                    $linhasAfetadas = $stmt->rowCount();
                    
                    echo "<p style='color: green;'><strong>✅ Migração executada! $linhasAfetadas usuários atualizados.</strong></p>";
                    echo "<p><a href='index.php'>🔐 Ir para o Login</a></p>";
                } catch (PDOException $e) {
                    echo "<p style='color: red;'><strong>❌ Erro na migração:</strong> " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p style='color: green;'><strong>✅ Usuários já têm campo 'usuario' preenchido!</strong></p>";
            echo "<p><a href='index.php'>🔐 Ir para o Login</a></p>";
        }
        
    } else {
        echo "<p style='color: red;'><strong>❌ Coluna 'usuario' NÃO existe na tabela!</strong></p>";
        echo "<p><strong>🔧 Criando coluna 'usuario'...</strong></p>";
        
        try {
            // Criar coluna usuario
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN usuario VARCHAR(50) UNIQUE AFTER email");
            echo "<p style='color: green;'><strong>✅ Coluna 'usuario' criada com sucesso!</strong></p>";
            
            // Migrar dados (copiar email para usuario)
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario = email WHERE usuario IS NULL OR usuario = ''");
            $stmt->execute();
            $linhasAfetadas = $stmt->rowCount();
            
            echo "<p style='color: green;'><strong>✅ Migração executada! $linhasAfetadas usuários atualizados.</strong></p>";
            echo "<p><a href='index.php'>🔐 Ir para o Login</a></p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'><strong>❌ Erro ao criar coluna:</strong> " . $e->getMessage() . "</p>";
        }
    }
    
    // Mostrar alguns usuários de exemplo
    echo "<h3>📋 Exemplos de usuários:</h3>";
    $stmt = $pdo->query("SELECT id, nome_completo, email, usuario, tipo, role FROM usuarios LIMIT 5");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($usuarios)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Usuário</th><th>Tipo</th><th>Role</th></tr>";
        foreach ($usuarios as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['nome_completo']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>" . ($user['usuario'] ?: '<em>Vazio</em>') . "</td>";
            echo "<td>{$user['tipo']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Erro de banco de dados:</strong> " . $e->getMessage() . "</p>";
}

echo "<br>";
echo "<p><strong>✅ Verificação concluída!</strong></p>";
?>
