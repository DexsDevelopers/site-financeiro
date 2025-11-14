<?php
// criar_tabelas_google_integration.php - Script para criar tabelas de integração Google

require_once 'includes/db_connect.php';

echo "<h2>🔗 Criando Tabelas de Integração Google</h2>";

try {
    // Tabela de tokens OAuth
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS google_oauth_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT,
            token_type VARCHAR(50) DEFAULT 'Bearer',
            expires_in INT DEFAULT 3600,
            expires_at DATETIME,
            scope TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (id_usuario),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabela 'google_oauth_tokens' criada/verificada com sucesso!</p>";
    
    // Tabela de configurações de integração
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS google_integrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            service_name VARCHAR(50) NOT NULL,
            enabled TINYINT(1) DEFAULT 1,
            settings JSON,
            last_sync DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_service (id_usuario, service_name),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabela 'google_integrations' criada/verificada com sucesso!</p>";
    
    echo "<h3>✅ Todas as tabelas foram criadas com sucesso!</h3>";
    echo "<p><a href='integracoes_google.php' class='btn btn-primary'>Ir para Integrações Google</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>

