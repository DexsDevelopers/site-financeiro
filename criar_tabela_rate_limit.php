<?php
/**
 * Script para criar a tabela de rate limiting para IA
 * Execute este arquivo uma vez para criar a tabela necessária
 */

require_once 'includes/db_connect.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limit_ia (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            tipo_requisicao VARCHAR(50) NOT NULL DEFAULT 'gemini',
            timestamp_request DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            INDEX idx_usuario_timestamp (id_usuario, timestamp_request),
            INDEX idx_timestamp (timestamp_request),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela 'rate_limit_ia' criada com sucesso!<br>";
    echo "O sistema de rate limiting está pronto para uso.<br>";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela: " . $e->getMessage() . "<br>";
    echo "A tabela pode já existir. Verifique o banco de dados.";
}
?>

