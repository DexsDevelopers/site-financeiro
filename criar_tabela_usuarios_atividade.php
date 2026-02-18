<?php
/**
 * Script de migração: Adiciona rastreamento de atividade de usuários
 * - Adiciona coluna ultimo_acesso na tabela usuarios
 * - Cria tabela usuarios_atividade para logs detalhados
 */

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/db_connect.php';

echo "<h1>🔄 Migração: Rastreamento de Atividade de Usuários</h1>";
echo "<pre>";

try {
    $pdo->beginTransaction();

    // 1. Adicionar coluna ultimo_acesso na tabela usuarios (se não existir)
    $colExists = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acesso'")->fetch(PDO::FETCH_ASSOC);
    if (!$colExists) {
        echo "✅ Adicionando coluna 'ultimo_acesso' na tabela usuarios...\n";
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultimo_acesso DATETIME NULL AFTER data_criacao");
        echo "✅ Coluna 'ultimo_acesso' adicionada com sucesso.\n";
    } else {
        echo "ℹ️ Coluna 'ultimo_acesso' já existe.\n";
    }

    // 2. Criar tabela usuarios_atividade para logs detalhados
    echo "\n✅ Criando tabela 'usuarios_atividade'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios_atividade (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            tipo_acao VARCHAR(50) NOT NULL COMMENT 'login, logout, pagina_acessada, etc',
            pagina VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            dados_extras JSON NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (id_usuario),
            INDEX idx_criado_em (criado_em),
            INDEX idx_tipo_acao (tipo_acao),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela 'usuarios_atividade' criada com sucesso.\n";

    // 3. Backfill: Atualizar ultimo_acesso com data_criacao para usuários existentes
    echo "\n✅ Atualizando 'ultimo_acesso' para usuários existentes...\n";
    $pdo->exec("
        UPDATE usuarios 
        SET ultimo_acesso = data_criacao 
        WHERE ultimo_acesso IS NULL
    ");
    $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "✅ {$affected} usuários atualizados.\n";

    $pdo->commit();
    echo "\n✅ Migração concluída com sucesso!\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Erro na migração: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "</pre>";
echo "<p><a href='admin/index.php'>← Voltar para Admin</a></p>";
?>

