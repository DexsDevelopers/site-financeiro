<?php
// verificar_remember_me.php - Verificar e corrigir sistema de "Lembrar-me"

require_once 'includes/db_connect.php';

echo "<h1>🔍 VERIFICANDO SISTEMA DE LEMBRAR-ME</h1>";
echo "<hr>";

try {
    // 1. Verificar se a tabela remember_tokens existe
    echo "<h3>1. Verificando tabela remember_tokens</h3>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() == 0) {
        echo "❌ <strong>Tabela remember_tokens não existe!</strong><br>";
        echo "🔧 <strong>Criando tabela...</strong><br>";
        
        $sql_create_table = "CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    }
    
    // 2. Verificar estrutura da tabela
    echo "<h3>2. Estrutura da tabela remember_tokens</h3>";
    $stmt = $pdo->query("DESCRIBE remember_tokens");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($colunas as $coluna) {
        echo "<tr>";
        echo "<td><strong>{$coluna['Field']}</strong></td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Verificar se há tokens ativos
    echo "<h3>3. Tokens ativos no sistema</h3>";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_tokens,
               COUNT(CASE WHEN is_active = 1 THEN 1 END) as tokens_ativos,
               COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as tokens_validos
        FROM remember_tokens
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 <strong>Estatísticas:</strong><br>";
    echo "• Total de tokens: {$stats['total_tokens']}<br>";
    echo "• Tokens ativos: {$stats['tokens_ativos']}<br>";
    echo "• Tokens válidos: {$stats['tokens_validos']}<br>";
    
    // 4. Limpar tokens expirados
    echo "<h3>4. Limpeza de tokens expirados</h3>";
    $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW() OR is_active = 0");
    $stmt->execute();
    $tokensRemovidos = $stmt->rowCount();
    
    if ($tokensRemovidos > 0) {
        echo "🧹 <strong>$tokensRemovidos tokens expirados removidos</strong><br>";
    } else {
        echo "✅ <strong>Nenhum token expirado encontrado</strong><br>";
    }
    
    // 5. Testar sistema de remember me
    echo "<h3>5. Teste do sistema</h3>";
    
    // Verificar se o RememberMeManager está funcionando
    require_once 'includes/remember_me_manager.php';
    $rememberManager = new RememberMeManager($pdo);
    
    echo "✅ <strong>RememberMeManager carregado com sucesso</strong><br>";
    
    // Verificar se há cookies de remember me
    if (isset($_COOKIE['remember_token'])) {
        echo "🍪 <strong>Cookie remember_token encontrado</strong><br>";
        
        $token = $_COOKIE['remember_token'];
        $userData = $rememberManager->verifyRememberToken($token);
        
        if ($userData) {
            echo "✅ <strong>Token válido para usuário: {$userData['nome']}</strong><br>";
        } else {
            echo "❌ <strong>Token inválido ou expirado</strong><br>";
        }
    } else {
        echo "ℹ️ <strong>Nenhum cookie remember_token encontrado</strong><br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 VERIFICAÇÃO CONCLUÍDA!</h2>";
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Próximos passos:</h4>";
    echo "<ol>";
    echo "<li><strong>Faça logout</strong> se estiver logado</li>";
    echo "<li><strong>Faça login novamente</strong> marcando 'Lembrar-me'</li>";
    echo "<li><strong>Feche o navegador</strong> e abra novamente</li>";
    echo "<li><strong>Verifique se o login automático funciona</strong></li>";
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
