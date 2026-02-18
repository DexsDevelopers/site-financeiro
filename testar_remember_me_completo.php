
<?php
/**
 * TESTE COMPLETO - SISTEMA "LEMBRAR-ME POR 30 DIAS"
 * Este arquivo testa todas as etapas do funcionamento
 */

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Teste Sistema Remember-Me</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<style>";
echo "body { padding: 20px; background: #f5f5f5; }";
echo ".test-box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #007bff; }";
echo ".test-box.success { border-left-color: #28a745; }";
echo ".test-box.error { border-left-color: #dc3545; }";
echo ".test-box.warning { border-left-color: #ffc107; }";
echo "code { background: #f4f4f4; padding: 10px; display: block; margin: 10px 0; border-radius: 4px; overflow-x: auto; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>🔍 TESTE COMPLETO - SISTEMA 'LEMBRAR-ME'</h1>";
echo "<hr>";

// ===== TESTE 1: Verificar conexão com banco =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 1: Conexão com Banco de Dados</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p class='text-success'><strong>✓ Conexão OK</strong></p>";
} catch (Exception $e) {
    echo "<p class='text-danger'><strong>✗ Erro de Conexão:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";

// ===== TESTE 2: Verificar tabela remember_tokens =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 2: Tabela remember_tokens</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='text-success'><strong>✓ Tabela existe</strong></p>";
        
        // Verificar estrutura
        $stmt = $pdo->query("DESCRIBE remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table class='table table-sm'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='text-warning'><strong>⚠ Tabela não existe, criando...</strong></p>";
        $sql = "CREATE TABLE remember_tokens (
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
        $pdo->exec($sql);
        echo "<p class='text-success'><strong>✓ Tabela criada com sucesso!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'><strong>✗ Erro:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";

// ===== TESTE 3: Verificar cookies =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 3: Cookies</h3>";
if (isset($_COOKIE['remember_token'])) {
    echo "<p class='text-success'><strong>✓ Cookie remember_token encontrado</strong></p>";
    echo "<p>Token (primeiros 20 caracteres): <code>" . substr($_COOKIE['remember_token'], 0, 20) . "...</code></p>";
    
    // Verificar se o token é válido
    $rememberManager = new RememberMeManager($pdo);
    $userData = $rememberManager->verifyRememberToken($_COOKIE['remember_token']);
    if ($userData) {
        echo "<p class='text-success'><strong>✓ Token válido!</strong></p>";
        echo "<p>Usuário: {$userData['nome']} (ID: {$userData['id']})</p>";
        echo "<p>Expira em: {$userData['expires_at']}</p>";
    } else {
        echo "<p class='text-danger'><strong>✗ Token inválido ou expirado</strong></p>";
    }
} else {
    echo "<p class='text-warning'><strong>ℹ️ Nenhum cookie remember_token encontrado</strong></p>";
}
echo "</div>";

// ===== TESTE 4: Verificar sessão =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 4: Sessão</h3>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='text-success'><strong>✓ Usuário logado</strong></p>";
    echo "<p>ID da Sessão: {$_SESSION['user_id']}</p>";
    echo "<p>Nome: {$_SESSION['user_name'] ?? 'N/A'}</p>";
    echo "<p>Email: {$_SESSION['user_email'] ?? 'N/A'}</p>";
} else {
    echo "<p class='text-warning'><strong>ℹ️ Nenhum usuário na sessão</strong></p>";
}
echo "</div>";

// ===== TESTE 5: Listar tokens ativos =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 5: Tokens Ativos no Sistema</h3>";
try {
    $stmt = $pdo->query("
        SELECT rt.id, rt.user_id, u.usuario, u.nome_completo, 
               SUBSTR(rt.token, 1, 20) as token_preview, 
               rt.expires_at, rt.created_at, rt.is_active
        FROM remember_tokens rt
        JOIN usuarios u ON rt.user_id = u.id
        ORDER BY rt.created_at DESC
        LIMIT 10
    ");
    
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tokens) {
        echo "<table class='table table-sm'>";
        echo "<tr><th>ID</th><th>Usuário</th><th>Token</th><th>Ativo</th><th>Expira</th></tr>";
        foreach ($tokens as $token) {
            $expiresIn = strtotime($token['expires_at']) - time();
            $daysLeft = floor($expiresIn / (24 * 60 * 60));
            $status = $token['is_active'] ? '✓ Ativo' : '✗ Inativo';
            echo "<tr>";
            echo "<td>{$token['id']}</td>";
            echo "<td>{$token['usuario']}</td>";
            echo "<td><code>{$token['token_preview']}...</code></td>";
            echo "<td>$status</td>";
            echo "<td>{$daysLeft}d</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='text-warning'><strong>ℹ️ Nenhum token encontrado</strong></p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'><strong>✗ Erro:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";

// ===== TESTE 6: Diagnóstico de RememberMeManager =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 6: RememberMeManager</h3>";
try {
    $rememberManager = new RememberMeManager($pdo);
    echo "<p class='text-success'><strong>✓ RememberMeManager carregado</strong></p>";
    
    // Testar método getTokenFromCookie
    $cookieToken = $rememberManager->getTokenFromCookie();
    if ($cookieToken) {
        echo "<p><strong>Token do Cookie:</strong> <code>" . substr($cookieToken, 0, 20) . "...</code></p>";
    } else {
        echo "<p class='text-warning'><strong>ℹ️ Nenhum token no cookie</strong></p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'><strong>✗ Erro:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";

// ===== TESTE 7: Informações do Servidor =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 7: Informações do Servidor</h3>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) ? 'Sim' : 'Não') . "</p>";
echo "<p><strong>User Agent:</strong> " . $_SERVER['HTTP_USER_AGENT'] . "</p>";
echo "<p><strong>Remote Addr:</strong> " . $_SERVER['REMOTE_ADDR'] . "</p>";
echo "<p><strong>Host:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "</div>";

// ===== TESTE 8: Teste prático - Criar token =====
echo "<div class='test-box'>";
echo "<h3>✅ Teste 8: Criar Token de Teste</h3>";
if (isset($_SESSION['user_id'])) {
    try {
        $rememberManager = new RememberMeManager($pdo);
        $newToken = $rememberManager->createRememberToken(
            $_SESSION['user_id'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        if ($newToken) {
            echo "<p class='text-success'><strong>✓ Token criado com sucesso!</strong></p>";
            echo "<p>Token (primeiros 20 caracteres): <code>" . substr($newToken, 0, 20) . "...</code></p>";
            echo "<p>Expira em: " . date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)) . "</p>";
            
            // Verificar se está no cookie
            if (isset($_COOKIE['remember_token'])) {
                echo "<p class='text-success'><strong>✓ Cookie foi definido!</strong></p>";
            } else {
                echo "<p class='text-warning'><strong>⚠️ Cookie pode não ter sido definido imediatamente</strong></p>";
                echo "<p><em>Dica: Verifique no DevTools do navegador após recarregar a página</em></p>";
            }
        } else {
            echo "<p class='text-danger'><strong>✗ Falha ao criar token</strong></p>";
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'><strong>✗ Erro:</strong> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='text-warning'><strong>ℹ️ Faça login para testar</strong></p>";
}
echo "</div>";

// ===== PRÓXIMOS PASSOS =====
echo "<div class='test-box success'>";
echo "<h3>📋 Próximos Passos</h3>";
echo "<ol>";
echo "<li>Verifique se todos os testes passaram acima</li>";
echo "<li>Faça logout completamente (feche o navegador)</li>";
echo "<li>Faça login novamente e <strong>marque 'Lembrar-me'</strong></li>";
echo "<li>Feche o navegador completamente</li>";
echo "<li>Abra novamente e verifique se fez login automático</li>";
echo "<li>Se não funcionou, volte a esta página para verificar cookies/tokens</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
