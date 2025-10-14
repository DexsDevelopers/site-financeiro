<?php
// verificar_remember_me_completo.php - Verificação completa do sistema "Lembre-se de mim"

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 Verificação Completa do Sistema 'Lembre-se de mim'</h2>";

// 1. Verificar conexão
echo "<h3>1. Conexão com Banco</h3>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Conexão OK<br>";
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Verificar se tabela remember_tokens existe
echo "<h3>2. Verificação da Tabela remember_tokens</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ Tabela remember_tokens existe<br>";
        
        // Verificar estrutura
        $stmt = $pdo->query("DESCRIBE remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Estrutura da tabela:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ Tabela remember_tokens NÃO existe<br>";
        echo "<h4>Criando tabela...</h4>";
        
        $createTable = "
        CREATE TABLE remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            user_agent TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token),
            INDEX idx_expires_at (expires_at),
            INDEX idx_is_active (is_active)
        )";
        
        $pdo->exec($createTable);
        echo "✅ Tabela criada com sucesso!<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar tabela: " . $e->getMessage() . "<br>";
}

// 3. Verificar se usuário está logado
echo "<h3>3. Status da Sessão</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br>";
    echo "Nome: " . ($_SESSION['user_name'] ?? 'N/A') . "<br>";
    echo "Email: " . ($_SESSION['user_email'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Usuário NÃO está logado<br>";
}

// 4. Verificar cookies
echo "<h3>4. Verificação de Cookies</h3>";
if (isset($_COOKIE['remember_token'])) {
    echo "✅ Cookie remember_token encontrado: " . substr($_COOKIE['remember_token'], 0, 20) . "...<br>";
} else {
    echo "❌ Cookie remember_token NÃO encontrado<br>";
}

// 5. Testar sistema de remember me
echo "<h3>5. Teste do Sistema Remember Me</h3>";
require_once 'includes/remember_me_manager.php';

$rememberManager = new RememberMeManager($pdo);

// Verificar token do cookie
$token = $rememberManager->getTokenFromCookie();
if ($token) {
    echo "Token encontrado: " . substr($token, 0, 20) . "...<br>";
    
    // Verificar se token é válido
    $userData = $rememberManager->verifyRememberToken($token);
    if ($userData) {
        echo "✅ Token VÁLIDO<br>";
        echo "Usuário: " . $userData['nome'] . " (ID: " . $userData['id'] . ")<br>";
        echo "Expira em: " . $userData['expires_at'] . "<br>";
        echo "Último uso: " . ($userData['last_used_at'] ?? 'Nunca') . "<br>";
    } else {
        echo "❌ Token INVÁLIDO ou EXPIRADO<br>";
    }
} else {
    echo "❌ Nenhum token encontrado no cookie<br>";
}

// 6. Estatísticas dos tokens
if (isset($_SESSION['user_id'])) {
    echo "<h3>6. Estatísticas dos Tokens (Usuário Atual)</h3>";
    $stats = $rememberManager->getTokenStats($_SESSION['user_id']);
    if ($stats) {
        echo "Total de tokens: " . $stats['total_tokens'] . "<br>";
        echo "Tokens ativos: " . $stats['active_tokens'] . "<br>";
        echo "Tokens revogados: " . $stats['revoked_tokens'] . "<br>";
        echo "Tokens expirados: " . $stats['expired_tokens'] . "<br>";
    }
}

// 7. Teste de criação de token (se usuário estiver logado)
if (isset($_SESSION['user_id'])) {
    echo "<h3>7. Teste de Criação de Token</h3>";
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Test Agent';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $newToken = $rememberManager->createRememberToken($_SESSION['user_id'], $userAgent, $ipAddress);
    
    if ($newToken) {
        echo "✅ Token criado com sucesso: " . substr($newToken, 0, 20) . "...<br>";
        
        // Verificar se token foi salvo no banco
        $userData = $rememberManager->verifyRememberToken($newToken);
        if ($userData) {
            echo "✅ Token salvo e verificado com sucesso!<br>";
        } else {
            echo "❌ Token criado mas não foi salvo corretamente<br>";
        }
    } else {
        echo "❌ Falha ao criar token<br>";
    }
}

// 8. Limpeza de tokens expirados
echo "<h3>8. Limpeza de Tokens Expirados</h3>";
$cleaned = $rememberManager->cleanExpiredTokens();
if ($cleaned !== false) {
    echo "✅ Tokens expirados removidos: " . $cleaned . "<br>";
} else {
    echo "❌ Erro ao limpar tokens expirados<br>";
}

echo "<hr>";
echo "<h3>🎯 Resumo</h3>";
echo "<p><strong>Para testar o sistema:</strong></p>";
echo "<ol>";
echo "<li>Faça logout</li>";
echo "<li>Faça login marcando 'Lembre-se de mim'</li>";
echo "<li>Feche o navegador</li>";
echo "<li>Abra novamente e acesse o site</li>";
echo "<li>Deve fazer login automático</li>";
echo "</ol>";

echo "<p><a href='index.php'>← Voltar para Login</a></p>";
?>
