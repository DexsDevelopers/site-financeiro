<?php
// includes/create_remember_tokens_table.php - Criar tabela para tokens de lembrança

require_once 'db_connect.php';

try {
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
    echo "Tabela remember_tokens criada com sucesso!";
    
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage();
}
?>
