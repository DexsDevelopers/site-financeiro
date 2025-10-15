<?php
// diagnosticar_remember_me.php - Diagnosticar e corrigir problemas do "Lembre-se de mim"

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

echo "<h2>🔍 DIAGNÓSTICO COMPLETO DO SISTEMA 'LEMBRE-SE DE MIM'</h2>";
echo "<hr>";

// 1. Verificar se a tabela existe e tem a estrutura correta
echo "<h3>1. Verificação da Tabela remember_tokens</h3>";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() == 0) {
        echo "❌ <strong>Tabela remember_tokens não existe!</strong><br>";
        echo "🔧 <strong>Criando tabela...</strong><br>";
        
        $sql_create_table = "CREATE TABLE remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            user_agent TEXT,
            ip_address VARCHAR(45),
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token),
            INDEX idx_expires_at (expires_at),
            INDEX idx_is_active (is_active)
        )";
        
        $pdo->exec($sql_create_table);
        echo "✅ <strong>Tabela remember_tokens criada com sucesso!</strong><br>";
    } else {
        echo "✅ <strong>Tabela remember_tokens existe</strong><br>";
        
        // Verificar estrutura
        $stmt = $pdo->query("DESCRIBE remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Estrutura atual:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Erro ao verificar tabela:</strong> " . $e->getMessage() . "<br>";
}

// 2. Verificar configuração atual
echo "<h3>2. Configuração Atual</h3>";
$rememberManager = new RememberMeManager($pdo);

// Verificar se usuário está logado
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    echo "✅ <strong>Usuário logado:</strong> ID $userId<br>";
    
    // Verificar tokens existentes
    $stats = $rememberManager->getTokenStats($userId);
    if ($stats) {
        echo "<h4>Estatísticas dos Tokens:</h4>";
        echo "• Total de tokens: " . $stats['total_tokens'] . "<br>";
        echo "• Tokens ativos: " . $stats['active_tokens'] . "<br>";
        echo "• Tokens revogados: " . $stats['revoked_tokens'] . "<br>";
        echo "• Tokens expirados: " . $stats['expired_tokens'] . "<br>";
    }
    
    // Verificar tokens ativos
    $stmt = $pdo->prepare("
        SELECT token, expires_at, created_at, last_used_at 
                          FROM remember_tokens 
                          WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
                          ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tokens) {
        echo "<h4>Tokens Ativos:</h4>";
        foreach ($tokens as $token) {
            $expiresIn = strtotime($token['expires_at']) - time();
            $daysLeft = floor($expiresIn / (24 * 60 * 60));
            echo "• Token: " . substr($token['token'], 0, 20) . "...<br>";
            echo "&nbsp;&nbsp;Expira em: " . $token['expires_at'] . " ($daysLeft dias restantes)<br>";
            echo "&nbsp;&nbsp;Criado em: " . $token['created_at'] . "<br>";
            echo "&nbsp;&nbsp;Último uso: " . ($token['last_used_at'] ?: 'Nunca') . "<br><br>";
        }
    } else {
        echo "❌ <strong>Nenhum token ativo encontrado</strong><br>";
    }
    
} else {
    echo "❌ <strong>Usuário não está logado</strong><br>";
}

// 3. Verificar cookies
echo "<h3>3. Verificação de Cookies</h3>";
if (isset($_COOKIE['remember_token'])) {
    $cookieToken = $_COOKIE['remember_token'];
    echo "✅ <strong>Cookie remember_token encontrado:</strong> " . substr($cookieToken, 0, 20) . "...<br>";
    
    // Verificar se o token do cookie é válido
    $userData = $rememberManager->verifyRememberToken($cookieToken);
    if ($userData) {
        echo "✅ <strong>Token do cookie é válido</strong><br>";
        echo "• Usuário: " . $userData['nome'] . " (ID: " . $userData['id'] . ")<br>";
        echo "• Expira em: " . $userData['expires_at'] . "<br>";
    } else {
        echo "❌ <strong>Token do cookie é inválido ou expirado</strong><br>";
    }
} else {
    echo "❌ <strong>Cookie remember_token não encontrado</strong><br>";
}

// 4. Teste de criação de token
echo "<h3>4. Teste de Criação de Token</h3>";
if (isset($_SESSION['user_id'])) {
    echo "🔧 <strong>Criando novo token de teste...</strong><br>";
    
    $newToken = $rememberManager->createRememberToken(
        $_SESSION['user_id'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    );
    
    if ($newToken) {
        echo "✅ <strong>Token criado com sucesso!</strong><br>";
        echo "• Token: " . substr($newToken, 0, 20) . "...<br>";
        
        // Verificar se o cookie foi definido
        if (isset($_COOKIE['remember_token'])) {
            echo "✅ <strong>Cookie definido corretamente</strong><br>";
        } else {
            echo "❌ <strong>Cookie não foi definido</strong><br>";
        }
    } else {
        echo "❌ <strong>Falha ao criar token</strong><br>";
    }
}

// 5. Limpeza de tokens expirados
echo "<h3>5. Limpeza de Tokens Expirados</h3>";
$cleaned = $rememberManager->cleanExpiredTokens();
if ($cleaned !== false) {
    echo "✅ <strong>Limpeza concluída:</strong> $cleaned tokens expirados removidos<br>";
} else {
    echo "❌ <strong>Erro na limpeza de tokens</strong><br>";
}

// 6. Verificar configuração do cookie
echo "<h3>6. Configuração do Cookie</h3>";
echo "• Nome do cookie: remember_token<br>";
echo "• Expiração: 30 dias<br>";
echo "• HTTPS only: " . (isset($_SERVER['HTTPS']) ? 'Sim' : 'Não') . "<br>";
echo "• HttpOnly: Sim<br>";

// 7. Recomendações
echo "<h3>7. Recomendações</h3>";
echo "<ul>";
echo "<li>✅ Certifique-se de que o site está usando HTTPS</li>";
echo "<li>✅ Verifique se os cookies estão habilitados no navegador</li>";
echo "<li>✅ Teste em modo incógnito para verificar se funciona</li>";
echo "<li>✅ Limpe tokens expirados regularmente</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Diagnóstico concluído!</strong> Se ainda houver problemas, verifique os logs do servidor.</p>";
?>
