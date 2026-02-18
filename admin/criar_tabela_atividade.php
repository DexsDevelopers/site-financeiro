<?php
/**
 * Script de migração: Adiciona rastreamento de atividade de usuários
 * - Adiciona coluna ultimo_acesso na tabela usuarios
 * - Cria tabela usuarios_atividade para logs detalhados
 */

require_once 'header_admin.php';

date_default_timezone_set('America/Sao_Paulo');

echo "<div class='container-fluid py-4'>";
echo "<div class='admin-card'>";
echo "<div class='card-header'>";
echo "<h3 class='mb-0'><i class='bi bi-database-fill-add me-2'></i>Migração: Rastreamento de Atividade de Usuários</h3>";
echo "</div>";
echo "<div class='card-body'>";
echo "<pre style='background: #1a1a1a; color: #0f0; padding: 1rem; border-radius: 8px; overflow-x: auto;'>";

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
    $stmt = $pdo->exec("
        UPDATE usuarios 
        SET ultimo_acesso = data_criacao 
        WHERE ultimo_acesso IS NULL
    ");
    $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "✅ {$affected} usuários atualizados.\n";

    $pdo->commit();
    echo "\n✅ Migração concluída com sucesso!\n";
    echo "\n🔄 <a href='index.php' style='color: #0f0;'>← Voltar para Gerenciamento de Usuários</a>\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Erro na migração: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Stack trace: " . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre>";
echo "</div>";
echo "</div>";
echo "</div>";

require_once 'footer_admin.php';
?>

